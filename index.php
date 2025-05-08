<?php
$koneksi = new mysqli("localhost", "root", "", "db_keuangan");

// Cek koneksi
if ($koneksi->connect_error) {
    die("Koneksi gagal: " . $koneksi->connect_error);
}

// Cek apakah tabel saldo sudah ada
$check_table = $koneksi->query("SHOW TABLES LIKE 'saldo'");
if($check_table->num_rows == 0) {
    // Buat tabel saldo jika belum ada
    $koneksi->query("CREATE TABLE IF NOT EXISTS `saldo` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `saldo_awal` decimal(15,2) NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`)
    )");
    
    // Insert data awal
    $koneksi->query("INSERT INTO saldo (id, saldo_awal) VALUES (1, 0)");
}

// Cek apakah tabel transaksi sudah ada
$check_table = $koneksi->query("SHOW TABLES LIKE 'transaksi'");
if($check_table->num_rows == 0) {
    // Buat tabel transaksi jika belum ada
    $koneksi->query("CREATE TABLE IF NOT EXISTS `transaksi` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `tanggal` date NOT NULL,
        `keterangan` varchar(255) NOT NULL,
        `jumlah` decimal(15,2) NOT NULL,
        `tipe` enum('pemasukan','pengeluaran') NOT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_tanggal` (`tanggal`)
    )");
}

// Ambil saldo awal
$res_saldo = $koneksi->query("SELECT saldo_awal FROM saldo WHERE id = 1");
if($res_saldo->num_rows > 0) {
    $row_saldo = $res_saldo->fetch_assoc();
    $saldo_awal = $row_saldo['saldo_awal'];
} else {
    // Jika tidak ada data, buat data default
    $koneksi->query("INSERT INTO saldo (id, saldo_awal) VALUES (1, 0)");
    $saldo_awal = 0;
}

// Ambil bulan dan tahun sekarang dan bulan lalu
$bulan_ini = date('m');
$tahun_ini = date('Y');
$bulan_lalu = date('m', strtotime('-1 month'));
$tahun_lalu = date('Y', strtotime('-1 month'));

// Query transaksi bulan ini
$sql_bulan_ini = "SELECT * FROM transaksi WHERE MONTH(tanggal) = $bulan_ini AND YEAR(tanggal) = $tahun_ini";
$result_ini = $koneksi->query($sql_bulan_ini);
$total_pemasukan_ini = 0;
$total_pengeluaran_ini = 0;
while ($row = $result_ini->fetch_assoc()) {
    if ($row['tipe'] === 'pemasukan') {
        $total_pemasukan_ini += $row['jumlah'];
    } else {
        $total_pengeluaran_ini += $row['jumlah'];
    }
}
$saldo_akhir_ini = $saldo_awal + $total_pemasukan_ini - $total_pengeluaran_ini;

// Query transaksi bulan lalu
$sql_bulan_lalu = "SELECT * FROM transaksi WHERE MONTH(tanggal) = $bulan_lalu AND YEAR(tanggal) = $tahun_lalu";
$result_lalu = $koneksi->query($sql_bulan_lalu);
$total_pemasukan_lalu = 0;
$total_pengeluaran_lalu = 0;
while ($row = $result_lalu->fetch_assoc()) {
    if ($row['tipe'] === 'pemasukan') {
        $total_pemasukan_lalu += $row['jumlah'];
    } else {
        $total_pengeluaran_lalu += $row['jumlah'];
    }
}
$saldo_akhir_lalu = $saldo_awal + $total_pemasukan_lalu - $total_pengeluaran_lalu;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengelolaan Keuangan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="app-container">
        <nav class="sidebar">
            <div class="sidebar-header">
                <h3><i class="fas fa-wallet"></i> FinTrack</h3>
            </div>
            <ul class="sidebar-menu">
                <li class="active"><a href="index.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="laporan.php"><i class="fas fa-chart-line"></i> Laporan</a></li>
                <li><a href="pengaturan.php"><i class="fas fa-cog"></i> Pengaturan</a></li>
            </ul>
        </nav>

        <main class="content">
            <header class="content-header">
                <h1><i class="fas fa-chart-pie"></i> Dashboard Keuangan</h1>
                <div class="user-info">
                    <span><?= date('d F Y') ?></span>
                </div>
            </header>

            <div class="dashboard-summary">
                <div class="summary-card current-balance">
                    <div class="card-icon">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="card-content">
                        <h3>Saldo Saat Ini</h3>
                        <p class="amount">Rp <?= number_format($saldo_akhir_ini, 0, ',', '.') ?></p>
                    </div>
                </div>
                <div class="summary-card income">
                    <div class="card-icon">
                        <i class="fas fa-arrow-down"></i>
                    </div>
                    <div class="card-content">
                        <h3>Total Pemasukan</h3>
                        <p class="amount">Rp <?= number_format($total_pemasukan_ini, 0, ',', '.') ?></p>
                    </div>
                </div>
                <div class="summary-card expense">
                    <div class="card-icon">
                        <i class="fas fa-arrow-up"></i>
                    </div>
                    <div class="card-content">
                        <h3>Total Pengeluaran</h3>
                        <p class="amount">Rp <?= number_format($total_pengeluaran_ini, 0, ',', '.') ?></p>
                    </div>
                </div>
            </div>

            <div class="dashboard-cards">
                <div class="card comparison-card">
                    <div class="card-header">
                        <h2>Perbandingan Bulan</h2>
                    </div>
                    <div class="card-body">
                        <div class="comparison-container">
                            <div class="comparison-item">
                                <h4>Bulan Ini (<?= date('F Y') ?>)</h4>
                                <div class="comparison-details">
                                    <div class="detail-item">
                                        <span class="label">Saldo Awal:</span>
                                        <span class="value">Rp <?= number_format($saldo_awal, 0, ',', '.') ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="label">Pemasukan:</span>
                                        <span class="value income-text">Rp <?= number_format($total_pemasukan_ini, 0, ',', '.') ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="label">Pengeluaran:</span>
                                        <span class="value expense-text">Rp <?= number_format($total_pengeluaran_ini, 0, ',', '.') ?></span>
                                    </div>
                                    <div class="detail-item total">
                                        <span class="label">Saldo Akhir:</span>
                                        <span class="value">Rp <?= number_format($saldo_akhir_ini, 0, ',', '.') ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="comparison-item">
                                <h4>Bulan Lalu (<?= date('F Y', strtotime('-1 month')) ?>)</h4>
                                <div class="comparison-details">
                                    <div class="detail-item">
                                        <span class="label">Saldo Awal:</span>
                                        <span class="value">Rp <?= number_format($saldo_awal, 0, ',', '.') ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="label">Pemasukan:</span>
                                        <span class="value income-text">Rp <?= number_format($total_pemasukan_lalu, 0, ',', '.') ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="label">Pengeluaran:</span>
                                        <span class="value expense-text">Rp <?= number_format($total_pengeluaran_lalu, 0, ',', '.') ?></span>
                                    </div>
                                    <div class="detail-item total">
                                        <span class="label">Saldo Akhir:</span>
                                        <span class="value">Rp <?= number_format($saldo_akhir_lalu, 0, ',', '.') ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card add-transaction-card">
                    <div class="card-header">
                        <h2>Tambah Transaksi</h2>
                    </div>
                    <div class="card-body">
                        <form action="tambah.php" method="post" class="transaction-form">
                            <div class="form-group">
                                <label for="tanggal"><i class="fas fa-calendar"></i> Tanggal</label>
                                <input type="date" id="tanggal" name="tanggal" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label for="keterangan"><i class="fas fa-file-alt"></i> Keterangan</label>
                                <input type="text" id="keterangan" name="keterangan" class="form-control" placeholder="Keterangan transaksi" required>
                            </div>
                            <div class="form-group">
                                <label for="jumlah"><i class="fas fa-money-bill"></i> Jumlah (Rp)</label>
                                <input type="number" id="jumlah" name="jumlah" class="form-control" placeholder="0" required>
                                <div id="formatted-amount" class="form-text"></div>
                            </div>
                            <div class="form-group">
                                <label for="tipe"><i class="fas fa-exchange-alt"></i> Tipe Transaksi</label>
                                <select id="tipe" name="tipe" class="form-control" required>
                                    <option value="pemasukan">Pemasukan</option>
                                    <option value="pengeluaran">Pengeluaran</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary btn-block">
                                <i class="fas fa-plus-circle"></i> Tambah Transaksi
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="transaction-history">
                <div class="card">
                    <div class="card-header">
                        <h2>Riwayat Transaksi Bulan Ini</h2>
                        <div class="card-actions">
                            <button class="btn btn-sm btn-outline-primary" id="exportCurrentMonth">
                                <i class="fas fa-download"></i> Ekspor
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Tanggal</th>
                                        <th>Keterangan</th>
                                        <th>Jumlah</th>
                                        <th>Tipe</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $result_ini = $koneksi->query($sql_bulan_ini);
                                    if ($result_ini->num_rows > 0) {
                                        while ($row = $result_ini->fetch_assoc()) {
                                            $tipe_class = $row['tipe'] === 'pemasukan' ? 'income-row' : 'expense-row';
                                            $tipe_icon = $row['tipe'] === 'pemasukan' ? 'arrow-down' : 'arrow-up';
                                            
                                            echo "<tr class='$tipe_class'>";
                                            echo "<td>" . date('d M Y', strtotime($row['tanggal'])) . "</td>";
                                            echo "<td>" . $row['keterangan'] . "</td>";
                                            echo "<td>Rp " . number_format($row['jumlah'], 0, ',', '.') . "</td>";
                                            echo "<td><span class='badge rounded-pill " . ($row['tipe'] === 'pemasukan' ? 'bg-success' : 'bg-danger') . "'><i class='fas fa-$tipe_icon'></i> " . ucfirst($row['tipe']) . "</span></td>";
                                            echo "</tr>";
                                        }
                                    } else {
                                        echo "<tr><td colspan='4' class='text-center'>Tidak ada transaksi untuk bulan ini</td></tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="card mt-4">
                    <div class="card-header">
                        <h2>Riwayat Transaksi Bulan Lalu</h2>
                        <div class="card-actions">
                            <button class="btn btn-sm btn-outline-primary" id="exportLastMonth">
                                <i class="fas fa-download"></i> Ekspor
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Tanggal</th>
                                        <th>Keterangan</th>
                                        <th>Jumlah</th>
                                        <th>Tipe</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $result_lalu = $koneksi->query($sql_bulan_lalu);
                                    if ($result_lalu->num_rows > 0) {
                                        while ($row = $result_lalu->fetch_assoc()) {
                                            $tipe_class = $row['tipe'] === 'pemasukan' ? 'income-row' : 'expense-row';
                                            $tipe_icon = $row['tipe'] === 'pemasukan' ? 'arrow-down' : 'arrow-up';
                                            
                                            echo "<tr class='$tipe_class'>";
                                            echo "<td>" . date('d M Y', strtotime($row['tanggal'])) . "</td>";
                                            echo "<td>" . $row['keterangan'] . "</td>";
                                            echo "<td>Rp " . number_format($row['jumlah'], 0, ',', '.') . "</td>";
                                            echo "<td><span class='badge rounded-pill " . ($row['tipe'] === 'pemasukan' ? 'bg-success' : 'bg-danger') . "'><i class='fas fa-$tipe_icon'></i> " . ucfirst($row['tipe']) . "</span></td>";
                                            echo "</tr>";
                                        }
                                    } else {
                                        echo "<tr><td colspan='4' class='text-center'>Tidak ada transaksi untuk bulan lalu</td></tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Form untuk ekspor PDF (tersembunyi) -->
    <form id="pdfFormCurrentMonth" action="generate_pdf_html.php" method="post" target="_blank" style="display: none;">
        <input type="hidden" name="tanggal_mulai" value="<?= date('Y-m-01') ?>">
        <input type="hidden" name="tanggal_akhir" value="<?= date('Y-m-t') ?>">
    </form>
    
    <form id="pdfFormLastMonth" action="generate_pdf_html.php" method="post" target="_blank" style="display: none;">
        <input type="hidden" name="tanggal_mulai" value="<?= date('Y-m-01', strtotime('-1 month')) ?>">
        <input type="hidden" name="tanggal_akhir" value="<?= date('Y-m-t', strtotime('-1 month')) ?>">
    </form>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="script.js"></script>
    <script>
        // Ekspor PDF bulan ini
        document.getElementById('exportCurrentMonth').addEventListener('click', function() {
            document.getElementById('pdfFormCurrentMonth').submit();
        });
        
        // Ekspor PDF bulan lalu
        document.getElementById('exportLastMonth').addEventListener('click', function() {
            document.getElementById('pdfFormLastMonth').submit();
        });
    </script>
</body>
</html>
