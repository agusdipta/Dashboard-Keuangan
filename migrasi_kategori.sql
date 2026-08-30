-- ================================================================
--  Migrasi: fitur Kategori Transaksi
--  Jalankan sekali di database `db_keuangan` (mis. lewat phpMyAdmin
--  atau: mysql -u root db_keuangan < migrasi_kategori.sql).
--  Aman diabaikan jika aplikasi sudah pernah dibuka -- koneksi.php
--  membuat perubahan yang sama secara otomatis.
-- ================================================================

CREATE TABLE IF NOT EXISTS `kategori` (
  `id`    int(11)     NOT NULL AUTO_INCREMENT,
  `nama`  varchar(60) NOT NULL,
  `tipe`  enum('pemasukan','pengeluaran') NOT NULL,
  `ikon`  varchar(40) NOT NULL DEFAULT 'fa-tag',
  `warna` varchar(9)  NOT NULL DEFAULT '#6c757d',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_nama_tipe` (`nama`,`tipe`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tambah kolom kategori_id ke tabel transaksi (abaikan error "Duplicate column")
ALTER TABLE `transaksi`
  ADD `kategori_id` int(11) NULL DEFAULT NULL AFTER `tipe`,
  ADD KEY `idx_kategori` (`kategori_id`);

-- Kategori bawaan
INSERT IGNORE INTO `kategori` (`nama`, `tipe`, `ikon`, `warna`) VALUES
('Gaji','pemasukan','fa-money-check-dollar','#2e7d32'),
('Bonus','pemasukan','fa-gift','#43a047'),
('Penjualan','pemasukan','fa-store','#66bb6a'),
('Transfer Masuk','pemasukan','fa-arrow-down','#1b5e20'),
('Lainnya (Masuk)','pemasukan','fa-circle-plus','#81c784'),
('Makanan & Minuman','pengeluaran','fa-utensils','#ef5350'),
('Transportasi','pengeluaran','fa-car','#ff7043'),
('Belanja','pengeluaran','fa-bag-shopping','#ec407a'),
('Tagihan & Utilitas','pengeluaran','fa-file-invoice-dollar','#ab47bc'),
('Kesehatan','pengeluaran','fa-heart-pulse','#26a69a'),
('Hiburan','pengeluaran','fa-clapperboard','#5c6bc0'),
('Pendidikan','pengeluaran','fa-book','#42a5f5'),
('Lainnya (Keluar)','pengeluaran','fa-circle-minus','#9e9e9e');
