# Dashboard Keuangan

Aplikasi pencatatan keuangan pribadi berbasis CodeIgniter 4.

## Fitur

- Daftar dan login memakai email + password.
- Data transaksi terpisah untuk setiap akun.
- Kategori pemasukan dan pengeluaran pribadi.
- Target pemasukan, batas pengeluaran, dan target tabungan bulanan.
- Ringkasan saldo, pemasukan, pengeluaran, dan sisa budget.
- Grafik arus kas harian.
- Pencarian dan filter transaksi.

## Cara Run di Mac

Pastikan PHP, Composer, dan MySQL sudah tersedia.

```bash
cd /Users/agusdipta/Documents/Kodingan/Dashboard_keuangan/Dashboard-Keuangan
brew services start mysql
composer install
mysql -h127.0.0.1 -uroot -e "CREATE DATABASE IF NOT EXISTS db_keuangan CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci"
php spark migrate
php spark serve --host 127.0.0.1 --port 8000
```

Buka:

```text
http://127.0.0.1:8000
```

Konfigurasi database default ada di `app/Config/Database.php`.
