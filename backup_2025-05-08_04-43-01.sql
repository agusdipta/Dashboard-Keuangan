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
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO transaksi VALUES('10','2025-05-01','uang transferan','500000.00','pemasukan');
INSERT INTO transaksi VALUES('11','2025-05-08','beli kertas foto','30000.00','pengeluaran');
INSERT INTO transaksi VALUES('12','2025-05-08','beli saldo kartu tol','50000.00','pengeluaran');
INSERT INTO transaksi VALUES('13','2025-05-08','paket skincare happy','65000.00','pengeluaran');
INSERT INTO transaksi VALUES('14','2025-05-08','tiket festika','100000.00','pengeluaran');
INSERT INTO transaksi VALUES('15','2025-05-08','paket tas happy','50000.00','pengeluaran');


