-- ================================================================
--  Migrasi: multi-user (login, buat akun, isolasi data per user)
--  Jalankan sekali di database `db_keuangan`.
--  Aman diabaikan bila aplikasi sudah pernah dibuka — koneksi.php
--  menerapkan perubahan yang sama secara otomatis & idempoten.
-- ================================================================

-- 1. Tabel users (login). Bila tabel lama sudah ada, lengkapi kolomnya:
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `nama_lengkap` varchar(100) NOT NULL,
  `peran` enum('admin','user') NOT NULL DEFAULT 'user',
  `status` enum('aktif','pending','nonaktif') NOT NULL DEFAULT 'pending',
  `gagal_login` int(11) NOT NULL DEFAULT 0,
  `kunci_sampai` datetime NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Kalau `users` versi lama (tanpa kolom baru), jalankan baris berikut
-- (abaikan error "Duplicate column"):
ALTER TABLE `users` ADD `peran` enum('admin','user') NOT NULL DEFAULT 'user';
ALTER TABLE `users` ADD `status` enum('aktif','pending','nonaktif') NOT NULL DEFAULT 'pending';
ALTER TABLE `users` ADD `gagal_login` int(11) NOT NULL DEFAULT 0;
ALTER TABLE `users` ADD `kunci_sampai` datetime NULL DEFAULT NULL;

-- Akun lama berpassword plaintext tidak kompatibel dengan hash -> nonaktifkan
UPDATE `users` SET status = 'nonaktif'
WHERE password NOT LIKE '$2y$%' AND password NOT LIKE '$2a$%' AND password NOT LIKE '$2b$%';

-- 2. Token "ingat saya"
CREATE TABLE IF NOT EXISTS `auth_token` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `selector` char(32) NOT NULL,
  `validator_hash` char(64) NOT NULL,
  `kadaluarsa` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_selector` (`selector`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Kolom pemilik pada data (abaikan error "Duplicate column")
ALTER TABLE `transaksi` ADD `user_id` int(11) NULL DEFAULT NULL, ADD KEY `idx_user` (`user_id`);
ALTER TABLE `kategori`  ADD `user_id` int(11) NULL DEFAULT NULL, ADD KEY `idx_user` (`user_id`);
ALTER TABLE `saldo`     ADD `user_id` int(11) NULL DEFAULT NULL, ADD UNIQUE KEY `uq_user` (`user_id`);

-- 4. Unik kategori: dari (nama,tipe) global menjadi per user
ALTER TABLE `kategori` DROP INDEX `uq_nama_tipe`;
ALTER TABLE `kategori` ADD UNIQUE KEY `uq_user_nama_tipe` (`user_id`,`nama`,`tipe`);

-- Setelah ini: buka aplikasi -> halaman "Buat Akun".
-- Akun pertama otomatis jadi admin dan mewarisi seluruh data lama
-- (transaksi/kategori/saldo yang user_id-nya masih NULL).
