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
$NAMA_BULAN = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
$NAMA_HARI  = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
$nama_depan = strtok(trim($USER['nama_lengkap']), ' ') ?: 'kamu';
$hari_ini_teks = $NAMA_HARI[(int) date('w')] . ', ' . date('j') . ' ' . $NAMA_BULAN[$bulan_ini];

// Catatan bulan ini: kategori terbesar, porsi terpakai, transaksi tanpa kategori
$per_kategori = [];
$tanpa_kategori = 0;
foreach ($tx_ini as $r) {
    if (!$r['kategori_id']) {
        $tanpa_kategori++;
    }
    if ($r['tipe'] === 'pengeluaran') {
        $nama = $r['kategori_nama'] ?: 'Tanpa kategori';
        $per_kategori[$nama] = ($per_kategori[$nama] ?? 0) + (float) $r['jumlah'];
    }
}
arsort($per_kategori);
$sisa_hari = (int) date('t') - (int) date('j');
$rupiah = fn($n) => 'Rp ' . number_format($n, 0, ',', '.');

if ($per_kategori) {
    $kat_terbesar = array_key_first($per_kategori);
    $catatan_kategori = $kat_terbesar . ' paling banyak: ' . $rupiah($per_kategori[$kat_terbesar])
        . ' (' . number_format($per_kategori[$kat_terbesar] / $total_pengeluaran_ini * 100, 0) . '%)';
} else {
    $catatan_kategori = 'Belum ada pengeluaran bulan ini';
}
$catatan_terpakai = $total_pemasukan_ini > 0
    ? 'Baru ' . number_format(min(100, $total_pengeluaran_ini / $total_pemasukan_ini * 100), 0) . "% pemasukan terpakai. Sisa $sisa_hari hari."
    : "Belum ada pemasukan bulan ini. Sisa $sisa_hari hari.";

$daftar_kategori = ambil_kategori($koneksi, $UID);
$flash = get_flash();

// Riwayat di Dashboard hanya pratinjau, dikelompokkan per hari — sisanya di halaman Transaksi
const RIWAYAT_MAKS = 6;
$rentang_ini = 'tanggal_mulai=' . date('Y-m-01') . '&tanggal_akhir=' . date('Y-m-t');
$riwayat_hari = [];
foreach (array_slice($tx_ini, 0, RIWAYAT_MAKS) as $r) {
    $tgl = date('Y-m-d', strtotime($r['tanggal']));
    $riwayat_hari[$tgl]['rows'][] = $r;
    $riwayat_hari[$tgl]['total'] = ($riwayat_hari[$tgl]['total'] ?? 0)
        + ($r['tipe'] === 'pemasukan' ? 1 : -1) * (float) $r['jumlah'];
}
function label_hari(string $tgl): string
{
    if ($tgl === date('Y-m-d')) {
        return 'Hari ini';
    }
    if ($tgl === date('Y-m-d', strtotime('-1 day'))) {
        return 'Kemarin';
    }
    return date('j', strtotime($tgl)) . ' ' . $GLOBALS['NAMA_BULAN'][(int) date('n', strtotime($tgl))];
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
                <p class="sapa-tgl"><?= e($hari_ini_teks) ?></p>
                <h1>Halo, <?= e($nama_depan) ?></h1>
            </header>

            <?php if ($flash): ?>
                <div class="alert alert-<?= e($flash['tipe']) ?> app-flash">
                    <i class="fas fa-<?= $flash['tipe'] === 'success' ? 'circle-check' : 'circle-exclamation' ?>"></i>
                    <?= e($flash['pesan']) ?>
                </div>
            <?php endif; ?>

            <div class="dash-row dash-row-hero">
                <section class="saldo-card" aria-label="Saldo">
                    <div class="saldo-top">
                        <div>
                            <h2 class="saldo-label">Saldo kamu</h2>
                            <p class="saldo-amount count-up" data-value="<?= (float) $saldo_akhir_ini ?>"><?= $rupiah($saldo_akhir_ini) ?></p>
                        </div>
                        <?= trend_pill((float) $saldo_akhir_ini, (float) $saldo_akhir_lalu) ?>
                    </div>
                    <div class="saldo-split">
                        <div class="saldo-stat">
                            <span class="saldo-stat-label">Masuk bulan ini</span>
                            <span class="saldo-stat-value count-up" data-value="<?= (float) $total_pemasukan_ini ?>"><?= $rupiah($total_pemasukan_ini) ?></span>
                        </div>
                        <div class="saldo-stat">
                            <span class="saldo-stat-label">Keluar bulan ini</span>
                            <span class="saldo-stat-value count-up" data-value="<?= (float) $total_pengeluaran_ini ?>"><?= $rupiah($total_pengeluaran_ini) ?></span>
                        </div>
                    </div>
                </section>

                <div class="aksi-grid">
                    <button type="button" class="aksi aksi-utama" data-buka-tambah="scan">
                        <i class="fas fa-camera" aria-hidden="true"></i>
                        <span><span class="aksi-judul">Scan struk</span><span class="aksi-sub">Total terisi otomatis</span></span>
                    </button>
                    <button type="button" class="aksi" data-buka-tambah="manual">
                        <i class="fas fa-pen" aria-hidden="true"></i>
                        <span><span class="aksi-judul">Input manual</span><span class="aksi-sub">Pemasukan / pengeluaran</span></span>
                    </button>
                    <a class="aksi" href="laporan.php">
                        <i class="fas fa-chart-line" aria-hidden="true"></i>
                        <span><span class="aksi-judul">Laporan</span><span class="aksi-sub">Per kategori &amp; periode</span></span>
                    </a>
                    <button type="button" class="aksi" id="exportCurrentMonth">
                        <i class="fas fa-download" aria-hidden="true"></i>
                        <span><span class="aksi-judul">Ekspor PDF</span><span class="aksi-sub">Laporan bulan ini</span></span>
                    </button>
                </div>
            </div>

            <section class="dash-section" aria-labelledby="catatanJudul">
                <h2 id="catatanJudul" class="dash-section-title">Catatan bulan ini</h2>
                <div class="catatan-grid">
                    <div class="catatan">
                        <span class="catatan-ic warm"><i class="fas fa-bag-shopping" aria-hidden="true"></i></span>
                        <p><?= e($catatan_kategori) ?></p>
                    </div>
                    <div class="catatan">
                        <span class="catatan-ic good"><i class="fas fa-check" aria-hidden="true"></i></span>
                        <p><?= e($catatan_terpakai) ?></p>
                    </div>
                    <div class="catatan">
                        <span class="catatan-ic"><i class="fas fa-tag" aria-hidden="true"></i></span>
                        <?php if ($tanpa_kategori > 0): ?>
                            <p><?= $tanpa_kategori ?> transaksi belum berkategori. <a href="<?= e('transaksi.php?' . $rentang_ini) ?>">Rapikan</a></p>
                        <?php else: ?>
                            <p>Rata-rata pengeluaran <?= $rupiah($total_pengeluaran_ini / (int) date('j')) ?> per hari.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <section class="dash-section" aria-labelledby="riwayatJudul">
                <div class="dash-section-head">
                    <h2 id="riwayatJudul" class="dash-section-title">Riwayat</h2>
                    <?php if ($tx_ini): ?>
                        <a class="card-link" href="<?= e('transaksi.php?' . $rentang_ini) ?>">Lihat semua (<?= count($tx_ini) ?>)</a>
                    <?php endif; ?>
                </div>
                <?php if (!$riwayat_hari): ?>
                    <p class="empty-state"><i class="fas fa-receipt"></i> Belum ada transaksi bulan ini.</p>
                <?php endif; ?>
                <?php foreach ($riwayat_hari as $tgl => $hari): ?>
                    <h3 class="tx-day"><span><?= e(label_hari($tgl)) ?></span><span><?= $hari['total'] >= 0 ? '+' : '−' ?> <?= $rupiah(abs($hari['total'])) ?></span></h3>
                    <?php foreach ($hari['rows'] as $r): $masuk = $r['tipe'] === 'pemasukan'; ?>
                        <a class="tx-item" href="edit.php?id=<?= (int) $r['id'] ?>&amp;kembali=index.php" aria-label="Edit <?= e($r['keterangan']) ?>">
                            <span class="tx-ic" style="--kat: <?= e($r['kategori_warna'] ?: ($masuk ? 'var(--success-color)' : 'var(--text-muted)')) ?>">
                                <i class="fas <?= e($r['kategori_ikon'] ?: ($masuk ? 'fa-arrow-down' : 'fa-tag')) ?>" aria-hidden="true"></i>
                            </span>
                            <span class="tx-main">
                                <span class="tx-title"><?= e($r['keterangan']) ?></span>
                                <span class="tx-sub"><?= e($r['kategori_nama'] ?: 'Tanpa kategori') ?></span>
                            </span>
                            <span class="tx-amt<?= $masuk ? ' in' : '' ?>"><?= $masuk ? '+' : '−' ?> <?= $rupiah($r['jumlah']) ?></span>
                        </a>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </section>
        </main>
    </div>

    <!-- Form tambah untuk layar lebar; di HP aksi cepat membuka bottom sheet -->
    <dialog class="tambah-dialog" id="dlgTambah" aria-labelledby="dlgTambahJudul">
        <div class="add-transaction-card">
            <div class="tambah-dialog-head">
                <h2 id="dlgTambahJudul">Tambah transaksi</h2>
                <button type="button" class="sheet-close" data-tutup aria-label="Tutup">&times;</button>
            </div>
            <form action="tambah.php" method="post" class="transaction-form">
                <?= csrf_field() ?>
                <?php require __DIR__ . '/partial_scan_struk.php'; ?>
                <?php $transaction_prefix = ''; $transaction_categories = $daftar_kategori; require __DIR__ . '/partial_transaction_fields.php'; ?>
            </form>
        </div>
    </dialog>

    <!-- Form untuk ekspor PDF (tersembunyi) -->
    <form id="pdfFormCurrentMonth" action="generate_pdf_html.php" method="post" target="_blank" style="display: none;">
        <?= csrf_field() ?>
        <input type="hidden" name="tanggal_mulai" value="<?= date('Y-m-01') ?>">
        <input type="hidden" name="tanggal_akhir" value="<?= date('Y-m-t') ?>">
    </form>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
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

        // Aksi cepat Scan / Input manual: HP memakai bottom sheet, layar lebar memakai <dialog>
        (function () {
            const dlg = document.getElementById('dlgTambah');
            const hp = window.matchMedia('(max-width: 768px)');
            function buka(mode) {
                let wadah = dlg;
                if (hp.matches) {
                    document.getElementById('mnavFab').click();
                    wadah = document.getElementById('sheetTambah');
                } else {
                    dlg.showModal();
                    dlg.querySelector('form').dispatchEvent(new Event('transaction:open'));
                }
                const tombol = wadah.querySelector(mode === 'scan' ? '[data-scan-camera]' : '[data-transaction-manual]');
                if (tombol && !tombol.hidden) tombol.click();
            }
            document.querySelectorAll('[data-buka-tambah]').forEach(b => b.addEventListener('click', () => buka(b.dataset.bukaTambah)));
            dlg.querySelector('[data-tutup]').addEventListener('click', () => dlg.close());
            dlg.addEventListener('click', (e) => { if (e.target === dlg) dlg.close(); });
        })();
    </script>
    <?php require __DIR__ . '/partial_mobilenav.php'; ?>
</body>
</html>
