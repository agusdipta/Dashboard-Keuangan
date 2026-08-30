<?php
require __DIR__ . '/auth.php';
$AKTIF = 'laporan';

// Saldo awal milik user ini
$saldo_awal = saldo_awal_user($koneksi, $UID);

// Filter tanggal (validasi format YYYY-MM-DD)
$tanggal_mulai = $_GET['tanggal_mulai'] ?? date('Y-m-01');
$tanggal_akhir = $_GET['tanggal_akhir'] ?? date('Y-m-t');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal_mulai)) {
    $tanggal_mulai = date('Y-m-01');
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal_akhir)) {
    $tanggal_akhir = date('Y-m-t');
}

// Daftar transaksi pada periode (prepared statement)
$stmt = $koneksi->prepare(
    "SELECT t.*, k.nama AS kategori_nama, k.ikon AS kategori_ikon, k.warna AS kategori_warna
     FROM transaksi t
     LEFT JOIN kategori k ON k.id = t.kategori_id
     WHERE t.user_id = ? AND t.tanggal BETWEEN ? AND ?
     ORDER BY t.tanggal DESC, t.id DESC"
);
$stmt->bind_param('iss', $UID, $tanggal_mulai, $tanggal_akhir);
$stmt->execute();
$res = $stmt->get_result();
$data_transaksi = [];
$total_pemasukan = 0;
$total_pengeluaran = 0;
while ($row = $res->fetch_assoc()) {
    $data_transaksi[] = $row;
    if ($row['tipe'] === 'pemasukan') {
        $total_pemasukan += $row['jumlah'];
    } else {
        $total_pengeluaran += $row['jumlah'];
    }
}
$stmt->close();

$saldo_akhir = $saldo_awal + $total_pemasukan - $total_pengeluaran;

// Rincian pengeluaran per kategori (untuk grafik batang)
$stmt = $koneksi->prepare(
    "SELECT COALESCE(k.nama, 'Tanpa Kategori') AS nama,
            COALESCE(k.warna, '#9e9e9e')      AS warna,
            SUM(t.jumlah)                     AS total
     FROM transaksi t
     LEFT JOIN kategori k ON k.id = t.kategori_id
     WHERE t.user_id = ? AND t.tipe = 'pengeluaran' AND t.tanggal BETWEEN ? AND ?
     GROUP BY k.id, k.nama, k.warna
     ORDER BY total DESC
     LIMIT 8"
);
$stmt->bind_param('iss', $UID, $tanggal_mulai, $tanggal_akhir);
$stmt->execute();
$res = $stmt->get_result();
$data_kategori = [];
while ($row = $res->fetch_assoc()) {
    $data_kategori[] = $row;
}
$stmt->close();

$flash = get_flash();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
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
                <?= topbar_user($USER) ?>
                <h1><i class="fas fa-chart-line"></i> Laporan Keuangan</h1>
            </header>

            <?php if ($flash): ?>
                <div class="alert alert-<?= e($flash['tipe']) ?> app-flash">
                    <i class="fas fa-<?= $flash['tipe'] === 'success' ? 'circle-check' : 'circle-exclamation' ?>"></i>
                    <?= e($flash['pesan']) ?>
                </div>
            <?php endif; ?>

            <div class="card mb-4">
                <div class="card-header">
                    <h2>Filter Laporan</h2>
                </div>
                <div class="card-body">
                    <form action="laporan.php" method="get" class="row g-3">
                        <div class="col-md-4">
                            <label for="tanggal_mulai" class="form-label">Tanggal Mulai</label>
                            <input type="date" class="form-control" id="tanggal_mulai" name="tanggal_mulai" value="<?= e($tanggal_mulai) ?>">
                        </div>
                        <div class="col-md-4">
                            <label for="tanggal_akhir" class="form-label">Tanggal Akhir</label>
                            <input type="date" class="form-control" id="tanggal_akhir" name="tanggal_akhir" value="<?= e($tanggal_akhir) ?>">
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
                    <?php render_tabel_transaksi($data_transaksi, 'laporan.php'); ?>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header">
                    <h2>Pengeluaran per Kategori</h2>
                </div>
                <div class="card-body">
                    <?php if (count($data_kategori) > 0): ?>
                        <canvas id="barChart" width="100%" height="300"></canvas>
                    <?php else: ?>
                        <p class="text-center text-muted mb-0">Belum ada pengeluaran pada periode ini.</p>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Form untuk ekspor PDF (tersembunyi) -->
    <form id="pdfForm" action="generate_pdf_html.php" method="post" target="_blank" style="display: none;">
        <?= csrf_field() ?>
        <input type="hidden" name="tanggal_mulai" value="<?= e($tanggal_mulai) ?>">
        <input type="hidden" name="tanggal_akhir" value="<?= e($tanggal_akhir) ?>">
    </form>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Pie Chart: perbandingan pemasukan vs pengeluaran
        const pieCtx = document.getElementById('pieChart').getContext('2d');
        new Chart(pieCtx, {
            type: 'pie',
            data: {
                labels: ['Pemasukan', 'Pengeluaran'],
                datasets: [{
                    data: [<?= (float) $total_pemasukan ?>, <?= (float) $total_pengeluaran ?>],
                    backgroundColor: ['rgba(76, 175, 80, 0.7)', 'rgba(244, 67, 54, 0.7)'],
                    borderColor: ['rgba(76, 175, 80, 1)', 'rgba(244, 67, 54, 1)'],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { position: 'bottom' } }
            }
        });

        <?php if (count($data_kategori) > 0): ?>
        // Bar Chart: pengeluaran per kategori
        const barCtx = document.getElementById('barChart').getContext('2d');
        new Chart(barCtx, {
            type: 'bar',
            data: {
                labels: <?= json_encode(array_map(fn($k) => $k['nama'], $data_kategori)) ?>,
                datasets: [{
                    label: 'Total Pengeluaran (Rp)',
                    data: <?= json_encode(array_map(fn($k) => (float) $k['total'], $data_kategori)) ?>,
                    backgroundColor: <?= json_encode(array_map(fn($k) => $k['warna'], $data_kategori)) ?>,
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true } }
            }
        });
        <?php endif; ?>

        // Ekspor PDF
        document.getElementById('exportPDF').addEventListener('click', function () {
            document.getElementById('pdfForm').submit();
        });
    </script>
    <script src="script.js"></script>
    <?php require __DIR__ . '/partial_mobilenav.php'; ?>
</body>
</html>
