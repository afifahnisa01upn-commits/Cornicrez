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

-- 2. Keamanan: aktifkan RLS TANPA policy.
--    Kunci publik (anon / publishable) tidak bisa membaca atau menulis tabel ini.
--    Server PHP memakai secret key, jadi tetap bisa mengakses.
alter table public.timbangan enable row level security;

-- 3. Rata-rata berat per jam (tanpa data simulasi).
--    Dipakai dashboard untuk memperkirakan pemakaian harian.
create or replace view public.timbangan_per_jam
with (security_invoker = true) as
select date_trunc('hour', created_at) as jam,
       round(avg(berat_kg), 3)        as berat_kg
from public.timbangan
where sumber <> 'simulasi'
group by 1;

-- ------------------------------------------------------------
-- Opsional: hapus data simulasi setelah selesai uji coba
--   delete from public.timbangan where sumber = 'simulasi';
-- ------------------------------------------------------------
