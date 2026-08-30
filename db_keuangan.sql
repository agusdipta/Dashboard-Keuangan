-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Waktu pembuatan: 08 Bulan Mei 2025 pada 04.49
-- Versi server: 10.4.32-MariaDB
-- Versi PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `db_keuangan`
--

-- --------------------------------------------------------

--
-- Struktur dari tabel `pemasukan`
--

CREATE TABLE `pemasukan` (
  `id` int(11) NOT NULL,
  `tanggal` date NOT NULL,
  `keterangan` varchar(255) NOT NULL,
  `jumlah` decimal(12,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `pengeluaran`
--

CREATE TABLE `pengeluaran` (
  `id` int(11) NOT NULL,
  `tanggal` date NOT NULL,
  `keterangan` varchar(255) NOT NULL,
  `jumlah` decimal(12,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `saldo`
--

CREATE TABLE `saldo` (
  `id` int(11) NOT NULL,
  `saldo_awal` decimal(12,2) NOT NULL,
  `user_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `saldo`
--

INSERT INTO `saldo` (`id`, `saldo_awal`) VALUES
(1, 0.00);

-- --------------------------------------------------------

--
-- Struktur dari tabel `users` (multi-user)
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `nama_lengkap` varchar(100) NOT NULL,
  `peran` enum('admin','user') NOT NULL DEFAULT 'user',
  `status` enum('aktif','pending','nonaktif') NOT NULL DEFAULT 'pending',
  `gagal_login` int(11) NOT NULL DEFAULT 0,
  `kunci_sampai` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `auth_token` (cookie "ingat saya")
--

CREATE TABLE `auth_token` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `selector` char(32) NOT NULL,
  `validator_hash` char(64) NOT NULL,
  `kadaluarsa` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `transaksi`
--

CREATE TABLE `transaksi` (
  `id` int(11) NOT NULL,
  `tanggal` date NOT NULL,
  `keterangan` varchar(255) NOT NULL,
  `jumlah` decimal(12,2) NOT NULL,
  `tipe` enum('pemasukan','pengeluaran') NOT NULL,
  `kategori_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `kategori`
--

CREATE TABLE `kategori` (
  `id` int(11) NOT NULL,
  `nama` varchar(60) NOT NULL,
  `tipe` enum('pemasukan','pengeluaran') NOT NULL,
  `ikon` varchar(40) NOT NULL DEFAULT 'fa-tag',
  `warna` varchar(9) NOT NULL DEFAULT '#6c757d',
  `user_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `kategori`
--

INSERT INTO `kategori` (`id`, `nama`, `tipe`, `ikon`, `warna`) VALUES
(1, 'Gaji', 'pemasukan', 'fa-money-check-dollar', '#2e7d32'),
(2, 'Bonus', 'pemasukan', 'fa-gift', '#43a047'),
(3, 'Penjualan', 'pemasukan', 'fa-store', '#66bb6a'),
(4, 'Transfer Masuk', 'pemasukan', 'fa-arrow-down', '#1b5e20'),
(5, 'Lainnya (Masuk)', 'pemasukan', 'fa-circle-plus', '#81c784'),
(6, 'Makanan & Minuman', 'pengeluaran', 'fa-utensils', '#ef5350'),
(7, 'Transportasi', 'pengeluaran', 'fa-car', '#ff7043'),
(8, 'Belanja', 'pengeluaran', 'fa-bag-shopping', '#ec407a'),
(9, 'Tagihan & Utilitas', 'pengeluaran', 'fa-file-invoice-dollar', '#ab47bc'),
(10, 'Kesehatan', 'pengeluaran', 'fa-heart-pulse', '#26a69a'),
(11, 'Hiburan', 'pengeluaran', 'fa-clapperboard', '#5c6bc0'),
(12, 'Pendidikan', 'pengeluaran', 'fa-book', '#42a5f5'),
(13, 'Lainnya (Keluar)', 'pengeluaran', 'fa-circle-minus', '#9e9e9e');

--
-- Dumping data untuk tabel `transaksi`
--

INSERT INTO `transaksi` (`id`, `tanggal`, `keterangan`, `jumlah`, `tipe`) VALUES
(10, '2025-05-01', 'uang transferan', 500000.00, 'pemasukan'),
(11, '2025-05-08', 'beli kertas foto', 30000.00, 'pengeluaran'),
(12, '2025-05-08', 'beli saldo kartu tol', 50000.00, 'pengeluaran'),
(13, '2025-05-08', 'paket skincare happy', 65000.00, 'pengeluaran'),
(14, '2025-05-08', 'tiket festika', 100000.00, 'pengeluaran'),
(15, '2025-05-08', 'paket tas happy', 50000.00, 'pengeluaran');

--
-- Indexes for dumped tables
--

--
-- Indeks untuk tabel `pemasukan`
--
ALTER TABLE `pemasukan`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `pengeluaran`
--
ALTER TABLE `pengeluaran`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `saldo`
--
ALTER TABLE `saldo`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_user` (`user_id`);

--
-- Indeks untuk tabel `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indeks untuk tabel `auth_token`
--
ALTER TABLE `auth_token`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_selector` (`selector`),
  ADD KEY `idx_user` (`user_id`);

--
-- Indeks untuk tabel `transaksi`
--
ALTER TABLE `transaksi`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_kategori` (`kategori_id`),
  ADD KEY `idx_user` (`user_id`);

--
-- Indeks untuk tabel `kategori`
--
ALTER TABLE `kategori`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_user_nama_tipe` (`user_id`,`nama`,`tipe`),
  ADD KEY `idx_user` (`user_id`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `pemasukan`
--
ALTER TABLE `pemasukan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT untuk tabel `pengeluaran`
--
ALTER TABLE `pengeluaran`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT untuk tabel `transaksi`
--
ALTER TABLE `transaksi`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT untuk tabel `kategori`
--
ALTER TABLE `kategori`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT untuk tabel `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `auth_token`
--
ALTER TABLE `auth_token`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
