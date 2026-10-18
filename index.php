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
function transaksi_bulan(PDO $koneksi, int $user_id, int $bulan, int $tahun): array
{
    $stmt = $koneksi->prepare(
        "SELECT t.*, k.nama AS kategori_nama, k.ikon AS kategori_ikon, k.warna AS kategori_warna
         FROM transaksi t
         LEFT JOIN kategori k ON k.id = t.kategori_id
         WHERE t.user_id = ? AND EXTRACT(MONTH FROM t.tanggal) = ? AND EXTRACT(YEAR FROM t.tanggal) = ?
         ORDER BY t.tanggal DESC, t.id DESC"
    );
    $stmt->bindValue(1, $user_id, PDO::PARAM_INT);
    $stmt->bindValue(2, $bulan, PDO::PARAM_INT);
    $stmt->bindValue(3, $tahun, PDO::PARAM_INT);
    $stmt->execute();
    $res = $stmt;
    $rows = [];
    while ($row = $res->fetch()) {
        $rows[] = $row;
    }
    $stmt->closeCursor();
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

// --- Data dashboard (dihitung dari transaksi bulan ini) ---
$per_kategori = [];
foreach ($tx_ini as $r) {
    if ($r['tipe'] !== 'pengeluaran') {
        continue;
    }
    $nama = $r['kategori_nama'] ?: 'Tanpa Kategori';
    if (!isset($per_kategori[$nama])) {
        $per_kategori[$nama] = ['total' => 0.0, 'warna' => $r['kategori_warna'] ?: '#9e9e9e', 'ikon' => $r['kategori_ikon'] ?: 'fa-tag'];
    }
    $per_kategori[$nama]['total'] += (float) $r['jumlah'];
}
uasort($per_kategori, fn($a, $b) => $b['total'] <=> $a['total']);

// Porsi pemasukan yang sudah terpakai (bar di kartu saldo)
if ($total_pemasukan_ini > 0) {
    $terpakai = min(100, $total_pengeluaran_ini / $total_pemasukan_ini * 100);
    $terpakai_teks = number_format($terpakai, 0) . '% pemasukan bulan ini sudah terpakai';
} else {
    $terpakai = $total_pengeluaran_ini > 0 ? 100 : 0;
    $terpakai_teks = 'Belum ada pemasukan bulan ini';
}

$NAMA_BULAN = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
$label_bulan_ini = $NAMA_BULAN[$bulan_ini] . ' ' . $tahun_ini;

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

// Riwayat di Dashboard hanya pratinjau, dikelompokkan per hari — sisanya di halaman Transaksi
const RIWAYAT_MAKS = 6;
$rentang_ini = 'tanggal_mulai=' . date('Y-m-01') . '&tanggal_akhir=' . date('Y-m-t');
$riwayat_hari = [];
foreach (array_slice($tx_ini, 0, RIWAYAT_MAKS) as $r) {
    $tgl = date('Y-m-d', strtotime($r['tanggal']));
    $riwayat_hari[$tgl][] = $r;
}
function label_hari(string $tgl): string
{
    $label = date('j', strtotime($tgl)) . ' ' . $GLOBALS['NAMA_BULAN'][(int) date('n', strtotime($tgl))];
    if ($tgl === date('Y-m-d')) {
        return "Hari ini · $label";
    }
    return $tgl === date('Y-m-d', strtotime('-1 day')) ? "Kemarin · $label" : $label;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Dashboard — FinTrack</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="styles.css?v=<?= filemtime(__DIR__ . '/styles.css') ?>">
    <script>(function(){try{var t=localStorage.getItem("appTheme");if(!t)t=window.matchMedia("(prefers-color-scheme: dark)").matches?"dark":"light";document.documentElement.setAttribute("data-theme",t);}catch(e){}})();</script>
</head>
<body>
    <div class="app-container">
        <nav class="sidebar">
            <div class="sidebar-header">
                <h3><i class="fas fa-wallet"></i> FinTrack</h3>
            </div>
            <ul class="sidebar-menu">
                <li class="active"><a href="index.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="transaksi.php"><i class="fas fa-receipt"></i> Transaksi</a></li>
                <li><a href="laporan.php"><i class="fas fa-chart-line"></i> Laporan</a></li>
                <li><a href="pengaturan.php"><i class="fas fa-cog"></i> Pengaturan</a></li>
            </ul>
        </nav>

        <main class="content">
            <header class="content-header">
                <?= topbar_user($USER) ?>
                <h1>Ringkasan <?= e($label_bulan_ini) ?></h1>
            </header>

            <?php if ($flash): ?>
                <div class="alert alert-<?= e($flash['tipe']) ?> app-flash">
                    <i class="fas fa-<?= $flash['tipe'] === 'success' ? 'circle-check' : 'circle-exclamation' ?>"></i>
                    <?= e($flash['pesan']) ?>
                </div>
            <?php endif; ?>

            <div class="dash-row dash-row-hero">
                <div class="dash-stack">
                    <section class="saldo-card" aria-label="Saldo">
                        <div class="saldo-top">
                            <div>
                                <h2 class="saldo-label">Saldo saat ini</h2>
                                <p class="saldo-amount count-up" data-value="<?= (float) $saldo_akhir_ini ?>">Rp <?= number_format($saldo_akhir_ini, 0, ',', '.') ?></p>
                            </div>
                            <?= trend_pill((float) $saldo_akhir_ini, (float) $saldo_akhir_lalu) ?>
                        </div>
                        <div>
                            <div class="saldo-bar"><span style="width: <?= round($terpakai, 1) ?>%"></span></div>
                            <p class="saldo-note"><?= e($terpakai_teks) ?></p>
                        </div>
                        <div class="saldo-split">
                            <div class="saldo-stat">
                                <span class="saldo-stat-ic in"><i class="fas fa-arrow-down" aria-hidden="true"></i></span>
                                <div>
                                    <span class="saldo-stat-label">Pemasukan</span>
                                    <span class="saldo-stat-value count-up" data-value="<?= (float) $total_pemasukan_ini ?>">Rp <?= number_format($total_pemasukan_ini, 0, ',', '.') ?></span>
                                </div>
                            </div>
                            <div class="saldo-stat">
                                <span class="saldo-stat-ic out"><i class="fas fa-arrow-up" aria-hidden="true"></i></span>
                                <div>
                                    <span class="saldo-stat-label">Pengeluaran</span>
                                    <span class="saldo-stat-value count-up" data-value="<?= (float) $total_pengeluaran_ini ?>">Rp <?= number_format($total_pengeluaran_ini, 0, ',', '.') ?></span>
                                </div>
                            </div>
                        </div>
                    </section>
                    <div class="card">
                        <div class="card-header">
                            <h2>Tren saldo</h2>
                            <span class="card-sub"><?= e($label_bulan_ini) ?></span>
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

                <div class="card add-transaction-card" id="tambah">
                    <div class="card-header">
                        <h2>Catat transaksi</h2>
                    </div>
                    <div class="card-body">
                        <form action="tambah.php" method="post" class="transaction-form">
                            <?= csrf_field() ?>
                            <?php require __DIR__ . '/partial_scan_struk.php'; ?>
                            <?php $transaction_prefix = ''; $transaction_categories = $daftar_kategori; require __DIR__ . '/partial_transaction_fields.php'; ?>
                        </form>
                    </div>
                </div>
            </div>

            <div class="dash-row">
                <div class="card">
                    <div class="card-header">
                        <h2>Pengeluaran per kategori</h2>
                        <a class="card-link" href="laporan.php">Laporan</a>
                    </div>
                    <div class="card-body">
                        <?php if ($per_kategori): ?>
                            <div class="kat-list">
                                <?php foreach ($per_kategori as $nama => $r):
                                    $persen = $total_pengeluaran_ini > 0 ? $r['total'] / $total_pengeluaran_ini * 100 : 0;
                                    $pct_txt = $persen >= 0.5 ? number_format($persen, 0) . '%' : ($persen > 0 ? '<1%' : '0%');
                                ?>
                                    <div class="kat-item">
                                        <div class="kat-item-head">
                                            <span class="kategori-chip" style="--kat:<?= e($r['warna']) ?>"><i class="fas <?= e($r['ikon']) ?>"></i> <?= e($nama) ?></span>
                                            <span class="kat-item-amt">Rp <?= number_format($r['total'], 0, ',', '.') ?></span>
                                        </div>
                                        <div class="kat-item-track">
                                            <div class="kat-item-bar"><span style="width: <?= max(2, round($persen, 1)) ?>%; background: <?= e($r['warna']) ?>;"></span></div>
                                            <span class="kat-item-pct"><?= $pct_txt ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="empty-state"><i class="fas fa-chart-pie"></i> Belum ada pengeluaran bulan ini.</p>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card">
                    <div class="card-header">
                        <h2>Transaksi terbaru</h2>
                        <div class="card-actions">
                            <button class="btn btn-sm btn-outline-primary" id="exportCurrentMonth">
                                <i class="fas fa-download"></i> Ekspor
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (!$riwayat_hari): ?>
                            <p class="empty-state"><i class="fas fa-receipt"></i> Belum ada transaksi bulan ini.</p>
                        <?php endif; ?>
                        <?php foreach ($riwayat_hari as $tgl => $rows): ?>
                            <h3 class="tx-day"><?= e(label_hari($tgl)) ?></h3>
                            <?php foreach ($rows as $r): $masuk = $r['tipe'] === 'pemasukan'; ?>
                                <a class="tx-item" href="edit.php?id=<?= (int) $r['id'] ?>&amp;kembali=index.php" aria-label="Edit <?= e($r['keterangan']) ?>">
                                    <span class="tx-ic" style="--kat: <?= e($r['kategori_warna'] ?: ($masuk ? 'var(--success-color)' : 'var(--text-muted)')) ?>">
                                        <i class="fas <?= e($r['kategori_ikon'] ?: ($masuk ? 'fa-arrow-down' : 'fa-tag')) ?>" aria-hidden="true"></i>
                                    </span>
                                    <span class="tx-main">
                                        <span class="tx-title"><?= e($r['keterangan']) ?></span>
                                        <span class="tx-sub"><?= e($r['kategori_nama'] ?: 'Tanpa kategori') ?></span>
                                    </span>
                                    <span class="tx-amt<?= $masuk ? ' in' : '' ?>"><?= $masuk ? '+' : '−' ?> Rp <?= number_format($r['jumlah'], 0, ',', '.') ?></span>
                                </a>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                        <?php if ($tx_ini): ?>
                            <a class="lihat-semua" href="<?= e('transaksi.php?' . $rentang_ini) ?>">
                                Lihat semua (<?= count($tx_ini) ?> transaksi) <i class="fas fa-arrow-right"></i>
                            </a>
                        <?php endif; ?>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="script.js?v=<?= filemtime(__DIR__ . '/script.js') ?>"></script>
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

        // ---- Grafik Dashboard ----
        (function () {
            const styles = getComputedStyle(document.documentElement);
            const accent = (styles.getPropertyValue('--primary-color') || '#4361ee').trim();
            const rupiah = (v) => 'Rp ' + Number(v).toLocaleString('id-ID');
            const ringkas = new Intl.NumberFormat('id-ID', { notation: 'compact' });
            if (window.Chart) {
                Chart.defaults.color = (styles.getPropertyValue('--text-muted') || '#6b7280').trim();
                Chart.defaults.borderColor = (styles.getPropertyValue('--border') || '#e6e9f2').trim();
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
                            pointRadius: 0,
                            pointHoverRadius: 4,
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
                            x: { grid: { display: false } },
                            y: { ticks: { callback: (v) => ringkas.format(v) } }
                        }
                    }
                });
            }
        })();
    </script>
    <?php require __DIR__ . '/partial_mobilenav.php'; ?>
</body>
</html>
