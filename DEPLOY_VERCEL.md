# Deploy branch deployvercel ke Vercel + Neon

Aplikasi tetap PHP. Vercel memakai runtime komunitas `vercel-php@0.9.0`
(PHP 8.5, termasuk `pdo_pgsql`), bukan runtime PHP resmi Vercel.

## 1. Siapkan Neon

1. Buat project pada paket Free di Neon dan pilih region dekat pengguna.
2. Buka **SQL Editor**, pilih database yang akan dipakai, lalu jalankan seluruh
   isi `database/schema.sql`. Jalankan sekali saat setup, bukan setiap request.
3. Di **Connect**, aktifkan connection pooling dan salin connection string
   PostgreSQL dengan host `-pooler` dan `sslmode=require`.

Panduan ini memulai database kosong. SQL MySQL lama tidak kompatibel langsung;
memindahkan data lama perlu konversi terpisah. Jangan kirim connection string
ke chat atau commit ke Git.

## 2. Siapkan Vercel

1. Push branch `deployvercel` ke GitHub, lalu import repository ke Vercel.
2. Pilih Framework Preset **Other** dan Root Directory repository ini.
   Biarkan Build Command dan Output Directory tanpa override; `vercel.json`
   menentukan function dan routing.
3. Tambahkan environment variable **DATABASE_URL** berisi connection string
   Neon untuk environment yang akan dipakai (Production dan/atau Preview).
4. Untuk menjadikan branch ini situs produksi, atur **Production Branch** ke
   `deployvercel` pada pengaturan Git/environments proyek. Deploy ulang setelah
   mengganti environment variable.
5. Buka deployment dan segera daftarkan akun admin pertama. Akun selanjutnya
   menunggu persetujuan admin di Pengaturan.

Jangan arahkan preview yang dipakai bereksperimen ke database produksi; gunakan
branch/database Neon terpisah jika preview perlu data sendiri.

## 3. Verifikasi deployment

- Daftar/login, tambah/edit/hapus transaksi, filter laporan, dan cetak PDF.
- Refresh beberapa kali untuk memeriksa sesi login.
- Uji scan struk, dark mode, dan unduhan backup di Pengaturan.
- URL `/database.php`, `/.env`, dan `/db_keuangan.sql` harus menghasilkan 404.

Semua request melewati allowlist `api/index.php`; source PHP, SQL, dan file lokal
bukan aset publik. Backup diunduh langsung tanpa penyimpanan disk server.
Sesi disimpan di PostgreSQL (kedaluwarsa setelah dua jam tanpa aktivitas),
sedangkan cookie “ingat saya” mempertahankan perilaku 30 hari.

## Pengujian lokal

Butuh PHP dengan `pdo_pgsql` dan `mbstring`, Python 3, serta PostgreSQL.
Set `DATABASE_URL` pada shell sebelum menjalankan:

```bash
php -S localhost:8000 api/index.php
```

`.env.example` hanya contoh; aplikasi tidak otomatis membaca `.env`.
Untuk tes integrasi, gunakan **database uji kosong**, terapkan `database/schema.sql`,
lalu set `FINTRACK_TEST_DATABASE_URL` ke database tersebut dan jalankan:

```bash
python3 tests/postgres-smoke.py
node --test tests/receipt-parser.test.js
```

Tes integrasi membuat akun dan data uji. Jangan gunakan database produksi.
Backup SQL berisi data akun dan transaksi, tanpa sesi/token; pulihkan hanya
pada database kosong yang sudah memakai `database/schema.sql`.

Referensi: [Vercel runtimes](https://vercel.com/docs/functions/runtimes),
[vercel-php](https://github.com/vercel-community/php),
[Neon pooling](https://neon.com/docs/connect/connection-pooling).
