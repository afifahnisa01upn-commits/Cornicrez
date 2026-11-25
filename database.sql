-- ============================================================
--  CORNICREZ - struktur database Supabase
--  Cara pakai: Supabase > SQL Editor > New query > tempel semua > Run
-- ============================================================

-- 1. Tabel data timbangan
create table if not exists public.timbangan (
  id          bigint generated always as identity primary key,
  berat_kg    numeric(8,3) not null check (berat_kg >= 0),
  sumber      text not null default 'esp32' check (sumber in ('esp32', 'manual', 'simulasi')),
  created_at  timestamptz not null default now()
);

create index if not exists timbangan_created_at_idx
  on public.timbangan (created_at desc);

-- 2. Keamanan: aktifkan RLS.
--    Kunci publik (anon / publishable) hanya boleh MEMBACA tabel (policy di bawah).
--    Menulis hanya lewat fungsi cornicrez_record_reading() yang memeriksa isian.
alter table public.timbangan enable row level security;

-- 3. State  persisten. Vercel Functions tidak memiliki filesystem persisten.
create table if not exists public.cornicrez_state (
  id boolean primary key default true check (id),
  state jsonb not null default
    '{"live":null,"jendela":null,"simpan":null,"gagal":null,"ulang":0,"pemakaian":null}'::jsonb
);

alter table public.cornicrez_state enable row level security;

-- Policy BACA untuk anon key (dipakai server/config.js). Tidak ada policy tulis,
-- jadi insert / update / delete langsung dari anon key ditolak.
drop policy if exists "anon boleh baca timbangan" on public.timbangan;
create policy "anon boleh baca timbangan" on public.timbangan
  for select to anon, authenticated using (true);

drop policy if exists "anon boleh baca state" on public.cornicrez_state;
create policy "anon boleh baca state" on public.cornicrez_state
  for select to anon, authenticated using (true);

-- 4. Rata-rata berat per jam (tanpa data simulasi).
--    Dipakai dashboard untuk memperkirakan pemakaian harian.
create or replace view public.timbangan_per_jam
with (security_invoker = true) as
select date_trunc('hour', created_at) as jam,
       round(avg(berat_kg), 3)        as berat_kg
from public.timbangan
where sumber <> 'simulasi'
group by 1;

-- Pencatatan dan state dilakukan atomik agar permintaan paralel tidak menimpa data.
create or replace function public.cornicrez_record_reading(
  p_berat_kg numeric,
  p_sumber text,
  p_interval integer
) returns jsonb
language plpgsql
security definer
set search_path = public, pg_temp
as $$
declare
  v_state jsonb;
  v_window jsonb;
  v_now bigint := extract(epoch from clock_timestamp())::bigint;
  v_save_value numeric;
  v_saved boolean := false;
  v_warning text;
  v_start bigint;
  v_end bigint;
  v_count integer;
  v_total numeric;
  v_usage numeric;
  v_drop numeric;
  v_rows integer;
  v_first timestamptz;
  v_last timestamptz;
  v_hours numeric;
  v_previous_kg numeric;
  v_previous_status text;
  v_current_status text;
begin
  if p_sumber not in ('esp32', 'manual', 'simulasi') then
    raise exception 'Sumber data tidak valid';
  end if;
  if p_berat_kg < 0 or p_berat_kg > 100 then
    raise exception 'Berat di luar batas';
  end if;

  insert into public.cornicrez_state (id) values (true)
  on conflict (id) do nothing;

  select state into v_state
  from public.cornicrez_state
  where id = true
  for update;

  v_state := coalesce(v_state, '{"live":null,"jendela":null,"simpan":null,"gagal":null,"ulang":0,"pemakaian":null}'::jsonb);

-- Mengambil berat sebelum data terbaru diperbarui.
v_previous_kg := nullif(
  v_state->'live'->>'berat_kg',
  ''
)::numeric;

-- Menentukan status sebelumnya.
v_previous_status := CASE
  WHEN v_previous_kg IS NULL THEN NULL
  WHEN v_previous_kg <= 1 THEN 'kritis'
  WHEN v_previous_kg <= 2 THEN 'menipis'
  ELSE 'aman'
END;

-- Menentukan status terbaru.
v_current_status := CASE
  WHEN p_berat_kg <= 1 THEN 'kritis'
  WHEN p_berat_kg <= 2 THEN 'menipis'
  ELSE 'aman'
END;

v_state := jsonb_set(v_state, '{live}', jsonb_build_object(
    'berat_kg', round(p_berat_kg, 3),
    'sumber', p_sumber,
    'waktu', v_now
  ), true);

  if p_sumber = 'manual' then
    v_save_value := p_berat_kg;
  else
    v_window := v_state->'jendela';
    if v_window is null
      or v_window = 'null'::jsonb
      or v_window->>'sumber' <> p_sumber
      or v_now - coalesce((v_window->>'akhir')::bigint, 0) > p_interval then
      v_window := jsonb_build_object(
        'sumber', p_sumber, 'mulai', v_now, 'akhir', v_now, 'n', 0, 'jumlah', 0
      );
    end if;

    v_start := (v_window->>'mulai')::bigint;
    v_end := v_now;
    v_count := (v_window->>'n')::integer + 1;
    v_total := (v_window->>'jumlah')::numeric + p_berat_kg;
    v_window := jsonb_build_object(
      'sumber', p_sumber, 'mulai', v_start, 'akhir', v_end, 'n', v_count, 'jumlah', v_total
    );
    v_state := jsonb_set(v_state, '{jendela}', v_window, true);

    if v_now - v_start >= p_interval
      and v_now >= coalesce((v_state->>'ulang')::bigint, 0) then
      v_save_value := case when p_sumber = 'simulasi' then p_berat_kg else v_total / v_count end;
    end if;
  end if;

  if v_save_value is not null then
    begin
      insert into public.timbangan (berat_kg, sumber)
      values (round(v_save_value, 3), p_sumber);
      v_saved := true;
    exception when others then
      v_warning := 'Gagal menyimpan ke Supabase. Data live tetap diperbarui.';
    end;

    if v_saved then
      v_state := jsonb_set(v_state, '{simpan}', jsonb_build_object(
        'waktu', v_now, 'berat_kg', round(v_save_value, 3)
      ), true);
      v_state := jsonb_set(v_state, '{gagal}', 'null'::jsonb, true);
      v_state := jsonb_set(v_state, '{ulang}', '0'::jsonb, true);

      if p_sumber <> 'manual' then
        v_state := jsonb_set(v_state, '{jendela}', jsonb_build_object(
          'sumber', p_sumber, 'mulai', v_now, 'akhir', v_now, 'n', 0, 'jumlah', 0
        ), true);
      end if;

      if v_state->'pemakaian' is null
        or v_state->'pemakaian' = 'null'::jsonb
        or v_now - coalesce((v_state->'pemakaian'->>'waktu')::bigint, 0) >= 600 then
        select coalesce(sum(greatest(previous_kg - berat_kg - 0.002, 0)), 0),
               count(*), min(jam), max(jam)
          into v_drop, v_rows, v_first, v_last
        from (
          select jam, berat_kg, lag(berat_kg) over (order by jam asc) as previous_kg
          from (
            select jam, berat_kg
            from public.timbangan_per_jam
            where jam >= to_timestamp(v_now - 7 * 86400)
            order by jam desc
            limit 200
          ) recent
        ) ordered_rows;

        if v_rows < 2 or v_drop <= 0 then
          v_usage := 0.5;
        else
          v_hours := extract(epoch from (v_last - v_first)) / 3600.0 + 1;
          v_usage := v_drop / greatest(1, v_hours / 24.0);
        end if;
        v_state := jsonb_set(v_state, '{pemakaian}', jsonb_build_object(
          'nilai', v_usage, 'waktu', v_now
        ), true);
      end if;
    else
      v_state := jsonb_set(v_state, '{gagal}', jsonb_build_object(
        'waktu', v_now, 'pesan', v_warning
      ), true);
      v_state := jsonb_set(v_state, '{ulang}', to_jsonb(v_now + 15), true);
    end if;
  end if;

  update public.cornicrez_state set state = v_state where id = true;
  return jsonb_build_object(
  'ok', true,
  'tersimpan', v_saved,
  'peringatan', v_warning,
  'status_sebelumnya', v_previous_status,
  'status_sekarang', v_current_status,
  'state', v_state
  );
end;
$$;

revoke all on function public.cornicrez_record_reading(numeric, text, integer) from public, anon, authenticated;
grant execute on function public.cornicrez_record_reading(numeric, text, integer) to anon, service_role;

-- ------------------------------------------------------------
-- Opsional: hapus data simulasi setelah selesai uji coba
--   delete from public.timbangan where sumber = 'simulasi';
-- ------------------------------------------------------------
