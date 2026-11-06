// CORNICREZ - Timbangan IoT: ESP32-S3 + HX711 + LCD 1602 I2C + kirim ke website
//
// Library (Library Manager): HX711 (Bogdan Necula), LiquidCrystal I2C (Frank de Brabander)
// Board: ESP32S3 Dev Module | Serial Monitor: 115200
// Perintah di Serial Monitor: t = tare ulang, k = kalibrasi

#include <WiFi.h>
#include <WiFiClientSecure.h>
#include <HTTPClient.h>
#include <Wire.h>
#include <LiquidCrystal_I2C.h>
#include <HX711.h>

// ================= UBAH BAGIAN INI =================
const char *WIFI_SSID  = "NAMA_WIFI";
const char *WIFI_PASS  = "PASSWORD_WIFI";

// Alamat file api/simpan.php di hosting-mu
const char *URL_SIMPAN = "https://domainmu.com/cornicrez/api/simpan.php";

// Harus SAMA dengan API_KEY_ESP32 di config.php (huruf dan angka saja)
const char *API_KEY    = "ganti-dengan-kata-sandi-panjang";

const unsigned long INTERVAL_KIRIM_MS = 5000;   // kirim ke website tiap 5 detik
float calibrationFactor = 420.0;                // ganti dengan hasil kalibrasi (perintah k)
// ===================================================

const int DT_PIN  = 4;
const int SCK_PIN = 5;
const int SDA_PIN = 8;
const int SCL_PIN = 9;

HX711 scale;
LiquidCrystal_I2C *lcd = nullptr;
unsigned long terakhirKirim = 0;

// Mencari alamat I2C perangkat pertama yang menjawab
byte cariAlamat() {
  for (byte a = 1; a < 127; a++) {
    Wire.beginTransmission(a);
    if (Wire.endTransmission() == 0) return a;
  }
  return 0;
}

// Tampilkan dua baris teks di LCD
void lcdPesan(const char *baris1, const char *baris2) {
  if (lcd == nullptr) return;
  lcd->clear();
  lcd->setCursor(0, 0);
  lcd->print(baris1);
  lcd->setCursor(0, 1);
  lcd->print(baris2);
}

// Tampilkan berat (kg) di baris 2 LCD, lebar tetap 16 karakter
void tampilkan(float beratGram) {
  if (lcd == nullptr) return;
  char buf[20];
  snprintf(buf, sizeof(buf), "Berat:%6.3f kg ", beratGram / 1000.0);
  lcd->setCursor(0, 1);
  lcd->print(buf);
}

void sambungWiFi() {
  Serial.print("Menyambung ke WiFi ");
  Serial.println(WIFI_SSID);
  WiFi.mode(WIFI_STA);
  WiFi.begin(WIFI_SSID, WIFI_PASS);
  unsigned long mulai = millis();
  while (WiFi.status() != WL_CONNECTED && millis() - mulai < 20000) {
    delay(500);
    Serial.print(".");
  }
  if (WiFi.status() == WL_CONNECTED) {
    Serial.println("\nWiFi tersambung.");
  } else {
    Serial.println("\nWiFi belum tersambung, akan dicoba lagi otomatis.");
  }
}

// Kirim berat (gram -> kg) ke api/simpan.php
void kirimKeWebsite(float beratGram) {
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("WiFi putus, mencoba menyambung ulang...");
    WiFi.reconnect();
    return;
  }

  WiFiClientSecure client;
  client.setInsecure();            // lewati verifikasi sertifikat HTTPS (cara sederhana)
  HTTPClient http;
  http.setTimeout(10000);
  if (!http.begin(client, URL_SIMPAN)) {
    Serial.println("URL_SIMPAN tidak valid.");
    return;
  }
  http.addHeader("Content-Type", "application/x-www-form-urlencoded");

  String body = "key=" + String(API_KEY) + "&berat=" + String(beratGram / 1000.0, 3);
  int kode = http.POST(body);

  Serial.print("Kirim ke website: ");
  if (kode > 0) {
    Serial.print("HTTP ");
    Serial.println(kode);
    if (kode != 200) Serial.println(http.getString());   // tampilkan pesan error dari server
  } else {
    Serial.println(HTTPClient::errorToString(kode));
  }
  http.end();
}

void setup() {
  Serial.begin(115200);
  delay(2000);

  Wire.begin(SDA_PIN, SCL_PIN);
  byte alamat = cariAlamat();
  if (alamat == 0) {
    Serial.println("LCD tidak ditemukan, cek kabel SDA/SCL/VCC/GND.");
  } else {
    Serial.print("LCD ditemukan di alamat 0x");
    Serial.println(alamat, HEX);
    lcd = new LiquidCrystal_I2C(alamat, 16, 2);
    lcd->init();
    lcd->backlight();
  }

  lcdPesan("Timbangan IoT", "Tare, kosongkan");
  scale.begin(DT_PIN, SCK_PIN);
  scale.set_scale(calibrationFactor);
  delay(1000);
  scale.tare();

  lcdPesan("Timbangan IoT", "Menyambung WiFi");
  sambungWiFi();

  lcdPesan("Timbangan IoT", "");
  Serial.println("Siap.");
  Serial.println("Perintah: t = tare ulang, k = kalibrasi");
}

void loop() {
  if (Serial.available()) {
    char c = Serial.read();
    if (c == 't') {
      scale.tare();
      Serial.println("Tare selesai.");
    } else if (c == 'k') {
      kalibrasi();
    }
  }

  if (scale.is_ready()) {
    float berat = scale.get_units(5);      // gram, rata-rata 5 pembacaan
    if (fabs(berat) < 0.5) berat = 0;      // abaikan noise kecil
    if (berat < 0) berat = 0;
    Serial.print("Berat: ");
    Serial.print(berat, 1);
    Serial.println(" g");
    tampilkan(berat);

    if (millis() - terakhirKirim >= INTERVAL_KIRIM_MS) {
      terakhirKirim = millis();
      kirimKeWebsite(berat);
    }
  } else {
    Serial.println("HX711 tidak terdeteksi, cek kabel.");
    if (lcd != nullptr) {
      lcd->setCursor(0, 1);
      lcd->print("HX711 tdk ada   ");
    }
  }

  delay(500);
}

// Tunggu pengguna mengetik sesuatu di Serial Monitor
void tungguInput() {
  while (Serial.available()) Serial.read();
  while (!Serial.available()) delay(10);
  delay(100);
}

void kalibrasi() {
  lcdPesan("KALIBRASI", "Cek Serial Mon.");
  Serial.println("== KALIBRASI ==");
  Serial.println("1. Kosongkan timbangan, lalu kirim sembarang karakter.");
  tungguInput();
  scale.set_scale();
  scale.tare();

  Serial.println("2. Letakkan beban yang diketahui beratnya,");
  Serial.println("   lalu ketik beratnya dalam gram (contoh: 500) dan kirim.");
  tungguInput();
  float beratAsli = Serial.parseFloat();

  if (beratAsli <= 0) {
    Serial.println("Berat tidak valid, kalibrasi dibatalkan.");
    scale.set_scale(calibrationFactor);
    lcdPesan("Timbangan IoT", "");
    return;
  }

  float faktor = scale.get_value(20) / beratAsli;
  scale.set_scale(faktor);

  Serial.print("Faktor kalibrasi: ");
  Serial.println(faktor, 2);
  Serial.println("Salin angka ini ke 'calibrationFactor' di kode, lalu upload ulang.");
  lcdPesan("Timbangan IoT", "");
}
