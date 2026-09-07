# FinTrack — Aplikasi Pengelolaan Keuangan Pribadi

Aplikasi web untuk mencatat pemasukan & pengeluaran harian, memantau saldo,
melihat laporan per kategori, dan mencetak laporan keuangan ke PDF.
Dibangun dengan **PHP + PostgreSQL (Neon)** tanpa framework maupun proses build —
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

**Scan struk**

- Tambah Transaksi membuka **Foto Struk**. Pilih foto baru, gambar dari galeri,
  atau **Input Manual**. Form menonjolkan jumlah, diikuti keterangan, jenis
  transaksi, tanggal, dan kategori opsional. Draf tetap ada saat berganti mode.
- Foto JPG, PNG, atau WebP hingga 10 MB diproses di perangkat dengan Tesseract.js
  6.0.1 (Indonesia + Inggris). Mesin dan data bahasa memerlukan internet/CDN.
  Foto tidak dikirim atau disimpan ke server aplikasi.
- OCR otomatis mencoba hingga empat pembacaan (gambar asli, tata letak,
  kontras, dan area ringkasan bawah), dengan batas waktu dua menit. Tidak ada
  tombol baca ulang atau editor potong. Gunakan foto baru atau input manual jika gagal.
- Total atau saran langsung mengisi draf pengeluaran. Nominal yang didukung
  hitungan independen didahulukan, lalu skor OCR. Jika ada beberapa nominal,
  alternatif tersedia di form. Hasil ragu diberi label **Saran total**.
- Saran hitungan memakai angka utuh dari tunai dikurangi kembalian atau subtotal
  beserta biaya/pajak/diskon yang terbaca lengkap. Contoh Ichiban:
  Rp300.000 dikurangi Rp51.675 menghasilkan Rp248.325, langsung mengisi jumlah.
  Angka rusak tidak direkonstruksi dengan menebak digit.
- Label seperti GRAND TOTAL, TOTAL BAYAR, AMOUNT DUE, dan variasi OCR diatur
  dalam TOTAL_LABEL_GROUPS di receipt-parser.js. Spasi nominal seperti
  42900. 00 diterima sebagai Rp42.900. Fixture MINISO/Ichiban menguji teks OCR,
  bukan menjamin ketepatan seluruh karakter pada foto asli.
- Hasil awal tetap dapat menjadi saran bila pembacaan lanjutan terhenti.
  Batal Scan membatalkan penerapan hasil yang sedang diproses. Semua jumlah bisa
  diedit; transaksi hanya tersimpan setelah **Simpan Transaksi** ditekan.
  Saldo dashboard mengikuti saldo awal + pemasukan - pengeluaran bulan berjalan.

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
| Bahasa | PHP 8.2+ (`pdo_pgsql`, `mbstring`) |
| Basis data | PostgreSQL 16+ / Neon |
| Antarmuka | Bootstrap 5.3, Font Awesome 6.4, Chart.js *(via CDN)* + CSS/JS sendiri |
| Build tool | — (tidak ada; tidak butuh Composer/Node) |

> Aset Bootstrap/Font Awesome/Chart.js dimuat dari CDN, jadi butuh koneksi internet
> saat pertama kali membuka aplikasi.

---

## 📁 Struktur berkas

| Berkas | Fungsi |
|---|---|
| `koneksi.php` | Bootstrap koneksi/sesi + kumpulan fungsi bantu (CSRF, auth, kategori, tabel transaksi, dll) |
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
| `partial_scan_struk.php` | Kontrol foto/pilih gambar dan hasil scan di form tambah |
| `receipt-parser.js` · `scan-struk.js` | Ekstraksi total rupiah dan alur OCR di browser |
| partial_transaction_fields.php | Form manual dan hasil scan bersama untuk desktop/HP |
| `styles.css` · `script.js` | Gaya & skrip antarmuka |
| `db_keuangan.sql` | Dump skema + sedikit data contoh (untuk impor manual) |
| `migrasi_kategori.sql` · `migrasi_multiuser.sql` | Skrip migrasi manual (opsional) |

---

## ✅ Kebutuhan dan deployment

Branch `deployvercel` memakai **PHP 8.2+**, ekstensi `pdo_pgsql` dan `mbstring`,
serta **PostgreSQL**. Lihat [panduan Vercel + Neon](DEPLOY_VERCEL.md).

Untuk lokal, jalankan `database/schema.sql` pada database PostgreSQL kosong,
set environment `DATABASE_URL`, lalu:

```bash
php -S localhost:8000 api/index.php
```

File `.env` tidak dimuat otomatis. Gunakan environment shell atau pengaturan
Vercel. Akun pertama yang didaftarkan menjadi admin aktif; akun berikutnya
menunggu persetujuan admin.

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
| `app_session` | Sesi login bersama antar-instance Vercel |
| `auth_token` | Token *cookie* "Ingat saya" |
| `saldo` | Saldo awal per pengguna (`user_id`, `saldo_awal`) |
| `kategori` | Kategori per pengguna (`nama`, `tipe`, `ikon`, `warna`, `user_id`) |
| `transaksi` | `tanggal`, `keterangan`, `jumlah`, `tipe`, `kategori_id`, `user_id` |

> `db_keuangan.sql` dan file migrasi/backup lama menggunakan MySQL; jangan
> jalankan pada Neon. Skema PostgreSQL berada di `database/schema.sql`.

---

## ⚙️ Catatan

- **Backup database** (Pengaturan → admin) mengunduh data semua user sebagai
  SQL PostgreSQL. Pulihkan ke database kosong yang telah diberi skema; sesi dan
  token login tidak disertakan.
- **Tema** disimpan di `localStorage` peramban (per perangkat), bukan di server.
- Aset CDN (Bootstrap/Font Awesome/Chart.js) butuh internet pada pemuatan pertama.
- Uji aturan total struk: `node --test tests/receipt-parser.test.js` (Node hanya
  diperlukan untuk pengujian, bukan untuk menjalankan aplikasi).
- Uji alur scan di browser: `node tests/scan-struk.browser.cjs`; tambahkan `--ocr`
  untuk membaca gambar struk sintetis dengan OCR/CDN asli. Memerlukan Node 22+,
  PHP, dan Chrome/Edge (atur `BROWSER_PATH` bila tidak terdeteksi). Pengujian
  memakai server lokal dan form sementara, tanpa membaca/menulis database aplikasi.
- Kredensial database dibaca dari environment `DATABASE_URL`. Jangan commit `.env`.
