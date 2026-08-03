<?php
$koneksi = new mysqli("127.0.0.1", "root", "", "db_keuangan");

// Ambil saldo awal
$res_saldo = $koneksi->query("SELECT saldo_awal FROM saldo WHERE id = 1");
if($res_saldo->num_rows > 0) {
    $row_saldo = $res_saldo->fetch_assoc();
    $saldo_awal = $row_saldo['saldo_awal'];
} else {
    // Jika tidak ada data, gunakan nilai default
    $saldo_awal = 0;
}

// Filter tanggal
$tanggal_mulai = isset($_GET['tanggal_mulai']) ? $_GET['tanggal_mulai'] : date('Y-m-01'); // Default: awal bulan ini
$tanggal_akhir = isset($_GET['tanggal_akhir']) ? $_GET['tanggal_akhir'] : date('Y-m-t'); // Default: akhir bulan ini

// Query transaksi berdasarkan filter
$sql_transaksi = "SELECT * FROM transaksi WHERE tanggal BETWEEN '$tanggal_mulai' AND '$tanggal_akhir' ORDER BY tanggal DESC";
$result_transaksi = $koneksi->query($sql_transaksi);

// Hitung total pemasukan dan pengeluaran
$total_pemasukan = 0;
$total_pengeluaran = 0;
$data_transaksi = [];

if ($result_transaksi->num_rows > 0) {
    while ($row = $result_transaksi->fetch_assoc()) {
        $data_transaksi[] = $row;
        if ($row['tipe'] === 'pemasukan') {
            $total_pemasukan += $row['jumlah'];
        } else {
            $total_pengeluaran += $row['jumlah'];
        }
    }
}

// Hitung saldo akhir
$saldo_akhir = $saldo_awal + $total_pemasukan - $total_pengeluaran;

// Hitung statistik per kategori (contoh sederhana)
$sql_kategori = "SELECT keterangan, SUM(jumlah) as total FROM transaksi 
                WHERE tanggal BETWEEN '$tanggal_mulai' AND '$tanggal_akhir' 
                GROUP BY keterangan ORDER BY total DESC LIMIT 5";
$result_kategori = $koneksi->query($sql_kategori);
$data_kategori = [];

if ($result_kategori->num_rows > 0) {
    while ($row = $result_kategori->fetch_assoc()) {
        $data_kategori[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Keuangan</title>
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
                <li><a href="index.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <li class="active"><a href="laporan.php"><i class="fas fa-chart-line"></i> Laporan</a></li>
                <li><a href="pengaturan.php"><i class="fas fa-cog"></i> Pengaturan</a></li>
            </ul>
        </nav>

        <main class="content">
            <header class="content-header">
                <h1><i class="fas fa-chart-line"></i> Laporan Keuangan</h1>
                <div class="user-info">
                    <span><?= date('d F Y') ?></span>
                </div>
            </header>

            <div class="card mb-4">
                <div class="card-header">
                    <h2>Filter Laporan</h2>
                </div>
                <div class="card-body">
                    <form action="laporan.php" method="get" class="row g-3">
                        <div class="col-md-4">
                            <label for="tanggal_mulai" class="form-label">Tanggal Mulai</label>
                            <input type="date" class="form-control" id="tanggal_mulai" name="tanggal_mulai" value="<?= $tanggal_mulai ?>">
                        </div>
                        <div class="col-md-4">
                            <label for="tanggal_akhir" class="form-label">Tanggal Akhir</label>
                            <input type="date" class="form-control" id="tanggal_akhir" name="tanggal_akhir" value="<?= $tanggal_akhir ?>">
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary me-2">
                                <i class="fas fa-filter"></i> Filter
                            </button>
                            <button type="button" id="exportPDF" class="btn btn-success">
                                <i class="fas fa-file-pdf"></i> Ekspor PDF
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="row">
                <div class="col-md-8">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h2>Ringkasan Keuangan</h2>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="summary-box">
                                        <h4>Saldo Awal</h4>
                                        <p class="amount">Rp <?= number_format($saldo_awal, 0, ',', '.') ?></p>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="summary-box income">
                                        <h4>Total Pemasukan</h4>
                                        <p class="amount income-text">Rp <?= number_format($total_pemasukan, 0, ',', '.') ?></p>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="summary-box expense">
                                        <h4>Total Pengeluaran</h4>
                                        <p class="amount expense-text">Rp <?= number_format($total_pengeluaran, 0, ',', '.') ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-4 p-3 bg-light rounded">
                                <h4>Saldo Akhir: <span class="text-primary">Rp <?= number_format($saldo_akhir, 0, ',', '.') ?></span></h4>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h2>Grafik Perbandingan</h2>
                        </div>
                        <div class="card-body">
                            <canvas id="pieChart" width="100%" height="200"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header">
                    <h2>Daftar Transaksi</h2>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="transaksiTable">
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
                                if (count($data_transaksi) > 0) {
                                    foreach ($data_transaksi as $row) {
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
                                    echo "<tr><td colspan='4' class='text-center'>Tidak ada transaksi untuk periode ini</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header">
                    <h2>Top 5 Kategori Transaksi</h2>
                </div>
                <div class="card-body">
                    <canvas id="barChart" width="100%" height="300"></canvas>
                </div>
            </div>
        </main>
    </div>

    <!-- Form untuk ekspor PDF (tersembunyi) -->
    <form id="pdfForm" action="generate_pdf_html.php" method="post" target="_blank" style="display: none;">
        <input type="hidden" name="tanggal_mulai" value="<?= $tanggal_mulai ?>">
        <input type="hidden" name="tanggal_akhir" value="<?= $tanggal_akhir ?>">
    </form>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Pie Chart untuk perbandingan pemasukan dan pengeluaran
        const pieCtx = document.getElementById('pieChart').getContext('2d');
        const pieChart = new Chart(pieCtx, {
            type: 'pie',
            data: {
                labels: ['Pemasukan', 'Pengeluaran'],
                datasets: [{
                    data: [<?= $total_pemasukan ?>, <?= $total_pengeluaran ?>],
                    backgroundColor: [
                        'rgba(76, 175, 80, 0.7)',
                        'rgba(244, 67, 54, 0.7)'
                    ],
                    borderColor: [
                        'rgba(76, 175, 80, 1)',
                        'rgba(244, 67, 54, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                    }
                }
            }
        });

        // Bar Chart untuk top 5 kategori
        const barCtx = document.getElementById('barChart').getContext('2d');
        const barChart = new Chart(barCtx, {
            type: 'bar',
            data: {
                labels: [
                    <?php 
                    foreach ($data_kategori as $kategori) {
                        echo "'" . $kategori['keterangan'] . "', ";
                    }
                    ?>
                ],
                datasets: [{
                    label: 'Total (Rp)',
                    data: [
                        <?php 
                        foreach ($data_kategori as $kategori) {
                            echo $kategori['total'] . ", ";
                        }
                        ?>
                    ],
                    backgroundColor: 'rgba(67, 97, 238, 0.7)',
                    borderColor: 'rgba(67, 97, 238, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Ekspor PDF
        document.getElementById('exportPDF').addEventListener('click', function() {
            document.getElementById('pdfForm').submit();
        });
    </script>
    <script src="script.js"></script>
</body>
</html>
