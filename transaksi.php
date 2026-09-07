<?php
require __DIR__ . '/auth.php';
$AKTIF = 'transaksi';

/* ---- Filter tanggal ---- */
$tanggal_mulai = $_GET['tanggal_mulai'] ?? date('Y-m-01');
$tanggal_akhir = $_GET['tanggal_akhir'] ?? date('Y-m-t');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal_mulai)) {
    $tanggal_mulai = date('Y-m-01');
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal_akhir)) {
    $tanggal_akhir = date('Y-m-t');
}
if ($tanggal_mulai > $tanggal_akhir) {
    [$tanggal_mulai, $tanggal_akhir] = [$tanggal_akhir, $tanggal_mulai];
}

/* ---- Pagination + ringkasan periode ---- */
$per = 25;
$hal = max(1, (int) ($_GET['hal'] ?? 1));

$stmt = $koneksi->prepare(
    "SELECT COUNT(*) AS n,
            COALESCE(SUM(CASE WHEN tipe = 'pemasukan'   THEN jumlah END), 0) AS masuk,
            COALESCE(SUM(CASE WHEN tipe = 'pengeluaran' THEN jumlah END), 0) AS keluar
     FROM transaksi
     WHERE user_id = ? AND tanggal BETWEEN ? AND ?"
);
$stmt->bindValue(1, $UID, PDO::PARAM_INT);
$stmt->bindValue(2, $tanggal_mulai, PDO::PARAM_STR);
$stmt->bindValue(3, $tanggal_akhir, PDO::PARAM_STR);
$stmt->execute();
$agg = $stmt->fetch();
$stmt->closeCursor();

$total     = (int) $agg['n'];
$total_hal = max(1, (int) ceil($total / $per));
if ($hal > $total_hal) {
    $hal = $total_hal;
}
$offset = ($hal - 1) * $per;

/* ---- Baris halaman ini ---- */
$stmt = $koneksi->prepare(
    "SELECT t.*, k.nama AS kategori_nama, k.ikon AS kategori_ikon, k.warna AS kategori_warna
     FROM transaksi t
     LEFT JOIN kategori k ON k.id = t.kategori_id
     WHERE t.user_id = ? AND t.tanggal BETWEEN ? AND ?
     ORDER BY t.tanggal DESC, t.id DESC
     LIMIT ? OFFSET ?"
);
$stmt->bindValue(1, $UID, PDO::PARAM_INT);
$stmt->bindValue(2, $tanggal_mulai, PDO::PARAM_STR);
$stmt->bindValue(3, $tanggal_akhir, PDO::PARAM_STR);
$stmt->bindValue(4, $per, PDO::PARAM_INT);
$stmt->bindValue(5, $offset, PDO::PARAM_INT);
$stmt->execute();
$res = $stmt;
$rows = [];
while ($r = $res->fetch()) {
    $rows[] = $r;
}
$stmt->closeCursor();

$flash  = get_flash();
$dari   = $total ? $offset + 1 : 0;
$sampai = min($offset + $per, $total);

/** URL halaman lain dengan filter dipertahankan. */
function url_hal(int $h, string $tm, string $ta): string
{
    return e("transaksi.php?tanggal_mulai=$tm&tanggal_akhir=$ta&hal=$h");
}

$preset = [
    'Bulan Ini'  => [date('Y-m-01'), date('Y-m-t')],
    'Bulan Lalu' => [date('Y-m-01', strtotime('first day of -1 month')), date('Y-m-t', strtotime('first day of -1 month'))],
    '3 Bulan'    => [date('Y-m-01', strtotime('first day of -2 month')), date('Y-m-t')],
    'Tahun Ini'  => [date('Y-01-01'), date('Y-12-31')],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Daftar Transaksi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
    <script>(function(){try{var t=localStorage.getItem("appTheme");if(!t)t=window.matchMedia("(prefers-color-scheme: dark)").matches?"dark":"light";document.documentElement.setAttribute("data-theme",t);}catch(e){}})();</script>
</head>
<body>
    <div class="app-container">
        <nav class="sidebar">
            <div class="sidebar-header">
                <h3><i class="fas fa-wallet"></i> FinTrack</h3>
            </div>
            <ul class="sidebar-menu">
                <li><a href="index.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <li class="active"><a href="transaksi.php"><i class="fas fa-receipt"></i> Transaksi</a></li>
                <li><a href="laporan.php"><i class="fas fa-chart-line"></i> Laporan</a></li>
                <li><a href="pengaturan.php"><i class="fas fa-cog"></i> Pengaturan</a></li>
            </ul>
        </nav>

        <main class="content">
            <header class="content-header">
                <?= topbar_user($USER) ?>
                <h1><i class="fas fa-receipt"></i> Daftar Transaksi</h1>
            </header>

            <?php if ($flash): ?>
                <div class="alert alert-<?= e($flash['tipe']) ?> app-flash">
                    <i class="fas fa-<?= $flash['tipe'] === 'success' ? 'circle-check' : 'circle-exclamation' ?>"></i>
                    <?= e($flash['pesan']) ?>
                </div>
            <?php endif; ?>

            <div class="card mb-4">
                <div class="card-header">
                    <h2>Filter Periode</h2>
                </div>
                <div class="card-body">
                    <form action="transaksi.php" method="get" class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label" for="tm">Dari tanggal</label>
                            <input type="date" class="form-control" id="tm" name="tanggal_mulai" value="<?= e($tanggal_mulai) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="ta">Sampai tanggal</label>
                            <input type="date" class="form-control" id="ta" name="tanggal_akhir" value="<?= e($tanggal_akhir) ?>">
                        </div>
                        <div class="col-md-4 d-flex align-items-end gap-2 flex-wrap">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Terapkan</button>
                            <button type="button" id="exportPDF" class="btn btn-success"><i class="fas fa-file-pdf"></i> Ekspor PDF</button>
                        </div>
                        <div class="col-12 chip-row">
                            <?php foreach ($preset as $label => [$a, $b]):
                                $aktif = ($a === $tanggal_mulai && $b === $tanggal_akhir);
                            ?>
                                <a class="chip-filter<?= $aktif ? ' aktif' : '' ?>" href="<?= e("transaksi.php?tanggal_mulai=$a&tanggal_akhir=$b") ?>"><?= $label ?></a>
                            <?php endforeach; ?>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header">
                    <h2>Transaksi</h2>
                    <span class="card-sub">
                        <?= $total ? 'Menampilkan ' . $dari . '–' . $sampai . ' dari ' . $total : 'Tidak ada transaksi' ?>
                    </span>
                </div>
                <div class="card-body">
                    <div class="tx-ringkas">
                        <span><i class="fas fa-arrow-down income-text"></i> Pemasukan <b class="income-text">Rp <?= number_format($agg['masuk'], 0, ',', '.') ?></b></span>
                        <span><i class="fas fa-arrow-up expense-text"></i> Pengeluaran <b class="expense-text">Rp <?= number_format($agg['keluar'], 0, ',', '.') ?></b></span>
                    </div>

                    <?php render_tabel_transaksi($rows, 'transaksi.php', true); ?>

                    <?php if ($total_hal > 1): ?>
                        <div class="pager">
                            <?php if ($hal > 1): ?>
                                <a class="pager-btn" href="<?= url_hal($hal - 1, $tanggal_mulai, $tanggal_akhir) ?>"><i class="fas fa-chevron-left"></i> Sebelumnya</a>
                            <?php else: ?>
                                <span class="pager-btn disabled"><i class="fas fa-chevron-left"></i> Sebelumnya</span>
                            <?php endif; ?>
                            <span class="pager-info">Halaman <?= $hal ?> / <?= $total_hal ?></span>
                            <?php if ($hal < $total_hal): ?>
                                <a class="pager-btn" href="<?= url_hal($hal + 1, $tanggal_mulai, $tanggal_akhir) ?>">Berikutnya <i class="fas fa-chevron-right"></i></a>
                            <?php else: ?>
                                <span class="pager-btn disabled">Berikutnya <i class="fas fa-chevron-right"></i></span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <form id="pdfForm" action="generate_pdf_html.php" method="post" target="_blank" style="display: none;">
        <?= csrf_field() ?>
        <input type="hidden" name="tanggal_mulai" value="<?= e($tanggal_mulai) ?>">
        <input type="hidden" name="tanggal_akhir" value="<?= e($tanggal_akhir) ?>">
    </form>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="script.js"></script>
    <script>
        document.getElementById('exportPDF').addEventListener('click', function () {
            document.getElementById('pdfForm').submit();
        });
    </script>
    <?php require __DIR__ . '/partial_mobilenav.php'; ?>
</body>
</html>
