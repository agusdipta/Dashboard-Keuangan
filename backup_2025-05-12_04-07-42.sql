DROP TABLE IF EXISTS pemasukan;
CREATE TABLE `pemasukan` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tanggal` date NOT NULL,
  `keterangan` varchar(255) NOT NULL,
  `jumlah` decimal(12,2) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;



DROP TABLE IF EXISTS pengeluaran;
CREATE TABLE `pengeluaran` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tanggal` date NOT NULL,
  `keterangan` varchar(255) NOT NULL,
  `jumlah` decimal(12,2) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;



DROP TABLE IF EXISTS saldo;
CREATE TABLE `saldo` (
  `id` int(11) NOT NULL,
  `saldo_awal` decimal(12,2) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO saldo VALUES('1','0.00');


DROP TABLE IF EXISTS transaksi;
CREATE TABLE `transaksi` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tanggal` date NOT NULL,
  `keterangan` varchar(255) NOT NULL,
  `jumlah` decimal(12,2) NOT NULL,
  `tipe` enum('pemasukan','pengeluaran') NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO transaksi VALUES('10','2025-05-01','uang transferan','500000.00','pemasukan');
INSERT INTO transaksi VALUES('11','2025-05-08','beli kertas foto','30000.00','pengeluaran');
INSERT INTO transaksi VALUES('12','2025-05-08','beli saldo kartu tol','50000.00','pengeluaran');
INSERT INTO transaksi VALUES('13','2025-05-08','paket skincare happy','65000.00','pengeluaran');
INSERT INTO transaksi VALUES('14','2025-05-08','tiket festika','100000.00','pengeluaran');
INSERT INTO transaksi VALUES('16','2025-05-08','grab kfc','40000.00','pengeluaran');
INSERT INTO transaksi VALUES('17','2025-05-09','uang dari mamak','100000.00','pemasukan');
INSERT INTO transaksi VALUES('18','2025-05-09','uang dari mamak otonan','100000.00','pemasukan');
INSERT INTO transaksi VALUES('19','2025-05-09','mamak','100000.00','pengeluaran');
INSERT INTO transaksi VALUES('20','2025-05-09','beli bensin ','60000.00','pengeluaran');
INSERT INTO transaksi VALUES('21','2025-05-09','beli indomie 2','7000.00','pengeluaran');
INSERT INTO transaksi VALUES('22','2025-05-09','beli meja belajar','48000.00','pengeluaran');
INSERT INTO transaksi VALUES('23','2025-05-11','beli lumpia 3','15000.00','pengeluaran');
INSERT INTO transaksi VALUES('24','2025-05-11','beli kertas foto 4r x2','23000.00','pengeluaran');


