<?php
require __DIR__ . '/auth.php';
$AKTIF = 'dashboard';

// Saldo awal milik user ini
$saldo_awal = saldo_awal_user($koneksi, $UID);

// Periode bulan ini & bulan lalu
$bulan_ini  = (int) date('m');
$tahun_ini  = (int) date('Y');
$bulan_lalu = (int) date('m', strtotime('first day of -1 month'));
$tahun_lalu = (int) date('Y', strtotime('first day of -1 month'));

/** Ambil transaksi satu bulan milik satu user, beserta data kategorinya. */
function transaksi_bulan(mysqli $koneksi, int $user_id, int $bulan, int $tahun): array
{
    $stmt = $koneksi->prepare(
        "SELECT t.*, k.nama AS kategori_nama, k.ikon AS kategori_ikon, k.warna AS kategori_warna
         FROM transaksi t
         LEFT JOIN kategori k ON k.id = t.kategori_id
         WHERE t.user_id = ? AND MONTH(t.tanggal) = ? AND YEAR(t.tanggal) = ?
         ORDER BY t.tanggal DESC, t.id DESC"
    );
    $stmt->bind_param('iii', $user_id, $bulan, $tahun);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $rows[] = $row;
    }
    $stmt->close();
    return $rows;
}

/** Jumlahkan pemasukan & pengeluaran dari sekumpulan baris transaksi. */
function ringkas_transaksi(array $rows): array
{
    $masuk = 0;
    $keluar = 0;
    foreach ($rows as $r) {
        if ($r['tipe'] === 'pemasukan') {
            $masuk += $r['jumlah'];
        } else {
            $keluar += $r['jumlah'];
        }
    }
    return [$masuk, $keluar];
}

/**
 * Pill persentase perubahan dibanding bulan lalu.
 * $buruk_jika_naik = true untuk pengeluaran (naik = jelek).
 */
function trend_pill(float $now, float $prev, bool $buruk_jika_naik = false): string
{
    if ($prev == 0.0) {
        if ($now == 0.0) {
            return "<span class='trend flat' title='Dibanding bulan lalu'><i class='fas fa-minus'></i> —</span>";
        }
        $kelas = $buruk_jika_naik ? 'bad' : 'good';
        return "<span class='trend $kelas' title='Dibanding bulan lalu'><i class='fas fa-arrow-up'></i> baru</span>";
    }
    $delta = ($now - $prev) / abs($prev) * 100;
    if (abs($delta) < 0.05) {
        return "<span class='trend flat' title='Dibanding bulan lalu'><i class='fas fa-minus'></i> 0%</span>";
    }
    $naik = $delta > 0;
    $baik = $buruk_jika_naik ? !$naik : $naik;
    $kelas = $baik ? 'good' : 'bad';
    $panah = $naik ? 'fa-arrow-up' : 'fa-arrow-down';
    return "<span class='trend $kelas' title='Dibanding bulan lalu'><i class='fas $panah'></i> "
        . number_format(abs($delta), 1, ',', '.') . "%</span>";
}

$tx_ini  = transaksi_bulan($koneksi, $UID, $bulan_ini, $tahun_ini);
$tx_lalu = transaksi_bulan($koneksi, $UID, $bulan_lalu, $tahun_lalu);

[$total_pemasukan_ini, $total_pengeluaran_ini]   = ringkas_transaksi($tx_ini);
[$total_pemasukan_lalu, $total_pengeluaran_lalu] = ringkas_transaksi($tx_lalu);

$saldo_akhir_ini  = $saldo_awal + $total_pemasukan_ini - $total_pengeluaran_ini;
$saldo_akhir_lalu = $saldo_awal + $total_pemasukan_lalu - $total_pengeluaran_lalu;

// --- Data grafik dashboard (dihitung dari transaksi bulan ini) ---
$donut = [];
foreach ($tx_ini as $r) {
    if ($r['tipe'] !== 'pengeluaran') {
        continue;
    }
    $nama = $r['kategori_nama'] ?: 'Tanpa Kategori';
    if (!isset($donut[$nama])) {
        $donut[$nama] = ['total' => 0.0, 'warna' => $r['kategori_warna'] ?: '#9e9e9e'];
    }
    $donut[$nama]['total'] += (float) $r['jumlah'];
}
uasort($donut, fn($a, $b) => $b['total'] <=> $a['total']);

$tren_urut = $tx_ini;
usort($tren_urut, fn($a, $b) => strcmp($a['tanggal'], $b['tanggal']) ?: ($a['id'] <=> $b['id']));
$tren_label = [];
$tren_nilai = [];
$run = (float) $saldo_awal;
foreach ($tren_urut as $r) {
    $run += $r['tipe'] === 'pemasukan' ? (float) $r['jumlah'] : -(float) $r['jumlah'];
    $key = date('Y-m-d', strtotime($r['tanggal']));
    $tren_label[$key] = date('d M', strtotime($r['tanggal']));
    $tren_nilai[$key] = $run;
}
$tren_label = array_values($tren_label);
$tren_nilai = array_values($tren_nilai);

$daftar_kategori = ambil_kategori($koneksi, $UID);
$flash = get_flash();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
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
                <?= topbar_user($USER) ?>
                <h1><i class="fas fa-chart-pie"></i> Dashboard Keuangan</h1>
            </header>

            <?php if ($flash): ?>
                <div class="alert alert-<?= e($flash['tipe']) ?> app-flash">
                    <i class="fas fa-<?= $flash['tipe'] === 'success' ? 'circle-check' : 'circle-exclamation' ?>"></i>
                    <?= e($flash['pesan']) ?>
                </div>
            <?php endif; ?>

            <div class="dashboard-summary">
                <div class="summary-card current-balance">
                    <div class="card-icon">
                        <i class="fas fa-wallet"></i>
                    </div>
                    <div class="card-content">
                        <h3>Saldo Saat Ini</h3>
                        <p class="amount count-up" data-value="<?= (float) $saldo_akhir_ini ?>">Rp <?= number_format($saldo_akhir_ini, 0, ',', '.') ?></p>
                        <?= trend_pill((float) $saldo_akhir_ini, (float) $saldo_akhir_lalu) ?>
                    </div>
                </div>
                <div class="summary-card income">
                    <div class="card-icon">
                        <i class="fas fa-circle-down"></i>
                    </div>
                    <div class="card-content">
                        <h3>Total Pemasukan</h3>
                        <p class="amount count-up" data-value="<?= (float) $total_pemasukan_ini ?>">Rp <?= number_format($total_pemasukan_ini, 0, ',', '.') ?></p>
                        <?= trend_pill((float) $total_pemasukan_ini, (float) $total_pemasukan_lalu) ?>
                    </div>
                </div>
                <div class="summary-card expense">
                    <div class="card-icon">
                        <i class="fas fa-circle-up"></i>
                    </div>
                    <div class="card-content">
                        <h3>Total Pengeluaran</h3>
                        <p class="amount count-up" data-value="<?= (float) $total_pengeluaran_ini ?>">Rp <?= number_format($total_pengeluaran_ini, 0, ',', '.') ?></p>
                        <?= trend_pill((float) $total_pengeluaran_ini, (float) $total_pengeluaran_lalu, true) ?>
                    </div>
                </div>
            </div>

            <div class="dashboard-charts">
                <div class="card">
                    <div class="card-header">
                        <h2>Pengeluaran per Kategori</h2>
                        <span class="card-sub"><?= date('F Y') ?></span>
                    </div>
                    <div class="card-body">
                        <?php if ($donut): ?>
                            <div class="chart-wrap"><canvas id="donutChart"></canvas></div>
                        <?php else: ?>
                            <p class="empty-state"><i class="fas fa-chart-pie"></i> Belum ada pengeluaran bulan ini.</p>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card">
                    <div class="card-header">
                        <h2>Tren Saldo</h2>
                        <span class="card-sub"><?= date('F Y') ?></span>
                    </div>
                    <div class="card-body">
                        <?php if (count($tren_nilai) > 0): ?>
                            <div class="chart-wrap"><canvas id="trenChart"></canvas></div>
                        <?php else: ?>
                            <p class="empty-state"><i class="fas fa-chart-line"></i> Belum ada transaksi bulan ini.</p>
                        <?php endif; ?>
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
                                <h4>Bulan Lalu (<?= date('F Y', strtotime('first day of -1 month')) ?>)</h4>
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
                            <?= csrf_field() ?>
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
                                <input type="text" inputmode="numeric" id="jumlah" name="jumlah" class="form-control js-rupiah" placeholder="0" autocomplete="off" required>
                            </div>
                            <div class="form-group">
                                <label for="tipe"><i class="fas fa-exchange-alt"></i> Tipe Transaksi</label>
                                <select id="tipe" name="tipe" class="form-control" required>
                                    <option value="pemasukan">Pemasukan</option>
                                    <option value="pengeluaran">Pengeluaran</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="kategori_id"><i class="fas fa-tags"></i> Kategori</label>
                                <select id="kategori_id" name="kategori_id" class="form-control">
                                    <option value="">— Tanpa kategori —</option>
                                    <?php foreach ($daftar_kategori as $k): ?>
                                        <option value="<?= (int) $k['id'] ?>" data-tipe="<?= e($k['tipe']) ?>"><?= e($k['nama']) ?></option>
                                    <?php endforeach; ?>
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
                        <?php render_tabel_transaksi($tx_ini, 'index.php', false); ?>
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
                        <?php render_tabel_transaksi($tx_lalu, 'index.php', false); ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Form untuk ekspor PDF (tersembunyi) -->
    <form id="pdfFormCurrentMonth" action="generate_pdf_html.php" method="post" target="_blank" style="display: none;">
        <?= csrf_field() ?>
        <input type="hidden" name="tanggal_mulai" value="<?= date('Y-m-01') ?>">
        <input type="hidden" name="tanggal_akhir" value="<?= date('Y-m-t') ?>">
    </form>

    <form id="pdfFormLastMonth" action="generate_pdf_html.php" method="post" target="_blank" style="display: none;">
        <?= csrf_field() ?>
        <input type="hidden" name="tanggal_mulai" value="<?= date('Y-m-01', strtotime('first day of -1 month')) ?>">
        <input type="hidden" name="tanggal_akhir" value="<?= date('Y-m-t', strtotime('first day of -1 month')) ?>">
    </form>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="script.js"></script>
    <script>
        // Saring pilihan kategori sesuai tipe transaksi
        (function () {
            const tipe = document.getElementById('tipe');
            const kategori = document.getElementById('kategori_id');
            if (!tipe || !kategori) return;
            const semua = Array.from(kategori.options)
                .filter(o => o.value !== '')
                .map(o => ({ value: o.value, text: o.textContent.trim(), tipe: o.dataset.tipe }));
            function sync() {
                const t = tipe.value;
                const sebelumnya = kategori.value;
                kategori.innerHTML = '<option value="">— Tanpa kategori —</option>';
                semua.filter(o => o.tipe === t).forEach(o => {
                    const opt = document.createElement('option');
                    opt.value = o.value;
                    opt.textContent = o.text;
                    if (o.value === sebelumnya) opt.selected = true;
                    kategori.appendChild(opt);
                });
            }
            tipe.addEventListener('change', sync);
            sync();
        })();

        // Ekspor PDF bulan ini
        document.getElementById('exportCurrentMonth').addEventListener('click', function () {
            document.getElementById('pdfFormCurrentMonth').submit();
        });

        // Ekspor PDF bulan lalu
        document.getElementById('exportLastMonth').addEventListener('click', function () {
            document.getElementById('pdfFormLastMonth').submit();
        });

        // ---- Grafik Dashboard ----
        (function () {
            const styles = getComputedStyle(document.documentElement);
            const accent = (styles.getPropertyValue('--primary-color') || '#4361ee').trim();
            const surface = (styles.getPropertyValue('--surface') || '#ffffff').trim();
            const rupiah = (v) => 'Rp ' + Number(v).toLocaleString('id-ID');

            const donutEl = document.getElementById('donutChart');
            if (donutEl) {
                new Chart(donutEl, {
                    type: 'doughnut',
                    data: {
                        labels: <?= json_encode(array_keys($donut), JSON_UNESCAPED_UNICODE) ?>,
                        datasets: [{
                            data: <?= json_encode(array_map(fn($d) => round($d['total'], 2), array_values($donut))) ?>,
                            backgroundColor: <?= json_encode(array_map(fn($d) => $d['warna'], array_values($donut))) ?>,
                            borderColor: surface,
                            borderWidth: 2
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        cutout: '66%',
                        plugins: {
                            legend: { position: 'bottom', labels: { boxWidth: 12, padding: 12 } },
                            tooltip: { callbacks: { label: (c) => c.label + ': ' + rupiah(c.parsed) } }
                        }
                    }
                });
            }

            const trenEl = document.getElementById('trenChart');
            if (trenEl) {
                new Chart(trenEl, {
                    type: 'line',
                    data: {
                        labels: <?= json_encode($tren_label, JSON_UNESCAPED_UNICODE) ?>,
                        datasets: [{
                            label: 'Saldo',
                            data: <?= json_encode(array_map(fn($v) => round($v, 2), $tren_nilai)) ?>,
                            borderColor: accent,
                            backgroundColor: accent + '22',
                            fill: true,
                            tension: 0.35,
                            borderWidth: 2,
                            pointRadius: 3,
                            pointBackgroundColor: accent
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: { callbacks: { label: (c) => rupiah(c.parsed.y) } }
                        },
                        scales: {
                            y: { ticks: { callback: (v) => rupiah(v) } }
                        }
                    }
                });
            }
        })();
    </script>
    <?php require __DIR__ . '/partial_mobilenav.php'; ?>
</body>
</html>
