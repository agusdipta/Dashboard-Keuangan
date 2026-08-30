# FinTrack — Aplikasi Pengelolaan Keuangan Pribadi

Aplikasi web untuk mencatat pemasukan & pengeluaran harian, memantau saldo,
melihat laporan per kategori, dan mencetak laporan keuangan ke PDF.
Dibangun dengan **PHP + MySQL/MariaDB** tanpa framework maupun proses build —
cukup taruh di web server dan jalankan.

Mendukung **banyak pengguna** (multi-user): tiap akun punya transaksi, kategori,
dan saldo sendiri yang terpisah.

---

## ✨ Fitur

**Autentikasi & multi-user**
- Masuk / Buat Akun / Keluar dengan sesi, kata sandi di-*hash* (`password_hash`).
- Akun **pertama** yang mendaftar otomatis menjadi **admin & aktif**.
  Akun berikutnya berstatus **menunggu persetujuan** admin sebelum bisa dipakai.
- "Ingat saya" (tetap masuk ±30 hari lewat *cookie* token yang di-*hash*).
- Batas percobaan: 5× kata sandi salah → akun dikunci 15 menit.
- Tombol lihat/sembunyikan kata sandi + indikator kekuatan saat mendaftar.
- Isolasi data penuh per pengguna (`user_id` pada transaksi, kategori, saldo).
- Panel **Kelola User** untuk admin (setujui / nonaktifkan / aktifkan akun).

**Dashboard**
- Kartu **Saldo Saat Ini**, **Total Pemasukan**, **Total Pengeluaran** bulan ini
  dengan animasi angka *count-up* dan pil tren (naik/turun) dibanding bulan lalu.
- Grafik **donat pengeluaran per kategori** & **garis tren saldo** bulan berjalan.
- Pratinjau 6 transaksi terbaru + tautan **"Lihat semua"** ke halaman Transaksi.

**Transaksi** (`transaksi.php`)
- Daftar lengkap dengan **filter periode** + chip cepat (Bulan Ini / Bulan Lalu / 3 Bulan / Tahun Ini).
- Ringkasan pemasukan & pengeluaran untuk periode terpilih.
- **Pilih banyak → Hapus Terpilih**, tombol **Edit** per baris, **pagination** 25/halaman.

**Tambah / Edit transaksi**
- Pilihan kategori otomatis tersaring sesuai tipe (pemasukan/pengeluaran).
- Kolom **Jumlah** otomatis menambah pemisah ribuan saat diketik (`600000` → `600.000`).
- Di ponsel: form muncul sebagai *bottom sheet* dari tombol **➕** pada bilah bawah.

**Laporan**
- Filter periode, **Ringkasan Keuangan** (Saldo Awal, Pemasukan, Pengeluaran,
  Arus Kas Bersih, Saldo Akhir).
- Grafik lingkaran perbandingan pemasukan vs pengeluaran.
- **Pengeluaran per Kategori** sebagai daftar peringkat (nominal + bar proporsi + persen).

**Ekspor PDF / Cetak** (`generate_pdf_html.php`)
- Memakai **"Simpan sebagai PDF" bawaan browser** (teks tajam, bisa dicari, ukuran kecil,
  nomor halaman otomatis, header tabel berulang tiap halaman).
- Opsi: **Orientasi** (Lanskap/Potret), **Isi** (Lengkap/Ringkas), **Kolom tanda tangan**.
- Isi: kop resmi, ringkasan, rincian per kategori, dan daftar transaksi lengkap
  dengan kolom **Saldo Berjalan**.

**Pengaturan**
- Atur **Saldo Awal** (per pengguna).
- **Kelola Kategori**: tambah/hapus, pilih ikon (Font Awesome) & warna.
- **Tema**: Terang / Gelap / Ikuti Sistem (juga bisa lewat tombol 🌙/☀️ di kanan atas).
- **Backup Database** (khusus admin) — file `.sql` disimpan di luar folder web.
- **Kelola User** (khusus admin).

**Tampilan**
- Responsif; di ponsel tampil bilah navigasi bawah: **Beranda · Transaksi · ➕ · Laporan · Atur**.
- Mode gelap menyeluruh, disimpan di perangkat, tanpa kedip saat halaman dimuat.

**Keamanan**
- Semua kueri memakai *prepared statement*.
- Token **CSRF** pada setiap form POST; keluaran di-*escape* untuk cegah XSS.
- Notifikasi memakai *flash* sesi (tidak menempel di URL).

---

## 🧰 Teknologi

| Bagian | Dipakai |
|---|---|
| Bahasa | PHP 8.0+ (`mysqli`, `mysqlnd`, `mbstring`) |
| Basis data | MySQL 5.7+ / MariaDB 10.4+ |
| Antarmuka | Bootstrap 5.3, Font Awesome 6.4, Chart.js *(via CDN)* + CSS/JS sendiri |
| Build tool | — (tidak ada; tidak butuh Composer/Node) |

> Aset Bootstrap/Font Awesome/Chart.js dimuat dari CDN, jadi butuh koneksi internet
> saat pertama kali membuka aplikasi.

---

## 📁 Struktur berkas

| Berkas | Fungsi |
|---|---|
| `koneksi.php` | Koneksi DB + **migrasi skema otomatis** + kumpulan fungsi bantu (CSRF, auth, kategori, tabel transaksi, dll) |
| `auth.php` | Penjaga halaman: di-`require` oleh halaman terkunci; menyediakan `$USER`, `$UID` |
| `login.php` · `register.php` · `logout.php` | Halaman publik autentikasi |
| `index.php` | Dashboard |
| `transaksi.php` | Daftar transaksi lengkap (filter, pilih-banyak, pagination) |
| `laporan.php` | Laporan analitik + grafik |
| `pengaturan.php` | Saldo awal, kategori, tema, backup, kelola user |
| `edit.php` | Ubah satu transaksi |
| `tambah.php` · `hapus.php` | Pemroses aksi (POST) tambah / hapus transaksi |
| `generate_pdf_html.php` | Halaman laporan untuk dicetak / disimpan PDF |
| `partial_mobilenav.php` | Komponen bilah navigasi bawah + *bottom sheet* "Tambah Transaksi" |
| `styles.css` · `script.js` | Gaya & skrip antarmuka |
| `db_keuangan.sql` | Dump skema + sedikit data contoh (untuk impor manual) |
| `migrasi_kategori.sql` · `migrasi_multiuser.sql` | Skrip migrasi manual (opsional) |

---

## ✅ Kebutuhan

- **PHP 8.0 atau lebih baru** dengan ekstensi `mysqli`, `mysqlnd`, `mbstring` (aktif secara bawaan di XAMPP/Laragon).
- **MySQL / MariaDB** yang berjalan.
- Peramban modern (Chrome/Edge/Firefox) untuk fitur cetak PDF.

---

## 🚀 Cara set up & menjalankan

### Langkah 1 — Ambil kode

```bash
git clone https://github.com/agusdipta/Dashboard-Keuangan.git
```

Letakkan folder proyek di dalam **document root** web server
(misal `C:\xampp\htdocs\catatanuang` untuk XAMPP, atau `laragon\www\...` untuk Laragon).

### Langkah 2 — Buat database kosong

Nama database default: **`db_keuangan`**.

- Lewat **phpMyAdmin**: buka `http://localhost/phpmyadmin` → **New** → nama `db_keuangan` → Create.
- Atau lewat terminal:

  ```bash
  mysql -u root -e "CREATE DATABASE db_keuangan CHARACTER SET utf8mb4"
  ```

> **Tabel dibuat otomatis.** Saat aplikasi pertama kali dibuka, `koneksi.php`
> membuat semua tabel, kolom, indeks, dan kategori bawaan yang diperlukan
> (aman dijalankan berulang). Impor `db_keuangan.sql` **hanya** bila ingin
> memuat data contoh.

### Langkah 3 — Sesuaikan koneksi database (bila perlu)

Buka `koneksi.php`, baris pengaturan koneksi:

```php
$koneksi = new mysqli("localhost", "root", "", "db_keuangan");
//                      host        user   pass  nama_database
```

Ubah `user` / `pass` bila MySQL kamu memakai kata sandi.

### Langkah 4 — Jalankan

**Opsi A — XAMPP / Laragon (Apache + MySQL)**

1. Start **Apache** dan **MySQL** dari panel kontrol.
2. Buka di peramban:

   ```
   http://localhost/<nama-folder-proyek>/
   ```

**Opsi B — Server bawaan PHP (tanpa Apache)**

MySQL/MariaDB tetap harus berjalan (mis. dari XAMPP), lalu di dalam folder proyek:

```bash
php -S localhost:8000
```

Buka `http://localhost:8000`. Hentikan dengan `Ctrl+C`.

### Langkah 5 — Buat akun admin pertama

Aplikasi akan mengarahkan ke halaman **Masuk**. Klik **"Buat akun admin pertama"**,
isi nama, *username*, dan kata sandi (min. 8 karakter).
Akun pertama ini otomatis menjadi **admin** dan langsung aktif.

Selesai — aplikasi siap dipakai. 🎉

---

## 👥 Menambah pengguna lain

1. Pengguna baru membuka `register.php` dan mendaftar → statusnya **menunggu persetujuan**.
2. **Admin** masuk → **Pengaturan → Kelola User** → klik **Setujui**.
3. Pengguna itu kini bisa masuk dan mulai mencatat keuangannya sendiri.

---

## 🗃️ Skema database (ringkas)

| Tabel | Isi |
|---|---|
| `users` | Akun: `username`, `password` (hash), `nama_lengkap`, `peran` (admin/user), `status` (aktif/pending/nonaktif), `gagal_login`, `kunci_sampai` |
| `auth_token` | Token *cookie* "Ingat saya" |
| `saldo` | Saldo awal per pengguna (`user_id`, `saldo_awal`) |
| `kategori` | Kategori per pengguna (`nama`, `tipe`, `ikon`, `warna`, `user_id`) |
| `transaksi` | `tanggal`, `keterangan`, `jumlah`, `tipe`, `kategori_id`, `user_id` |

> Bila kamu mengimpor `db_keuangan.sql`, akan ada juga tabel lama `pemasukan` &
> `pengeluaran` bawaan versi terdahulu — **tidak dipakai** dan boleh diabaikan/hapus.

---

## ⚙️ Catatan

- **Backup database** (Pengaturan → admin) menulis file `.sql` ke folder
  `backup_keuangan/` **satu tingkat di atas** folder web root, agar tidak bisa
  diunduh lewat peramban.
- **Tema** disimpan di `localStorage` peramban (per perangkat), bukan di server.
- Aset CDN (Bootstrap/Font Awesome/Chart.js) butuh internet pada pemuatan pertama.
- Untuk produksi, disarankan memakai user MySQL khusus (bukan `root`) dan
  memindahkan kredensial ke variabel lingkungan / file konfigurasi terpisah.
