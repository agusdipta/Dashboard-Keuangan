<?php
require __DIR__ . '/auth.php';
csrf_check();

/* ---- Parameter periode ---- */
$tanggal_mulai = $_POST['tanggal_mulai'] ?? '';
$tanggal_akhir = $_POST['tanggal_akhir'] ?? '';
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal_mulai)) {
    $tanggal_mulai = date('Y-m-01');
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal_akhir)) {
    $tanggal_akhir = date('Y-m-t');
}
if ($tanggal_mulai > $tanggal_akhir) {
    [$tanggal_mulai, $tanggal_akhir] = [$tanggal_akhir, $tanggal_mulai];
}

$nama_user  = $USER['nama_lengkap'];
$saldo_awal = saldo_awal_user($koneksi, $UID);

/* ---- Ambil transaksi kronologis (untuk saldo berjalan) ---- */
$stmt = $koneksi->prepare(
    "SELECT t.*, k.nama AS kategori_nama
     FROM transaksi t
     LEFT JOIN kategori k ON k.id = t.kategori_id
     WHERE t.user_id = ? AND t.tanggal BETWEEN ? AND ?
     ORDER BY t.tanggal ASC, t.id ASC"
);
$stmt->bind_param('iss', $UID, $tanggal_mulai, $tanggal_akhir);
$stmt->execute();
$res = $stmt->get_result();

$rows         = [];
$total_masuk  = 0.0;
$total_keluar = 0.0;
$saldo_jalan  = (float) $saldo_awal;
$kat          = [];

while ($r = $res->fetch_assoc()) {
    $j = (float) $r['jumlah'];
    if ($r['tipe'] === 'pemasukan') {
        $total_masuk += $j;
        $saldo_jalan += $j;
    } else {
        $total_keluar += $j;
        $saldo_jalan  -= $j;
        $nk = $r['kategori_nama'] ?: 'Tanpa Kategori';
        $kat[$nk] = ($kat[$nk] ?? 0) + $j;
    }
    $r['saldo_jalan'] = $saldo_jalan;
    $rows[] = $r;
}
$stmt->close();
arsort($kat);

$net         = $total_masuk - $total_keluar;
$saldo_akhir = (float) $saldo_awal + $net;
$jml         = count($rows);

/* ---- Helper tampilan ---- */
function rp(float $n): string
{
    return ($n < 0 ? '-' : '') . 'Rp ' . number_format(abs($n), 0, ',', '.');
}
function tgl_id(string $ymd): string
{
    $b = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    $t = strtotime($ymd);
    return (int) date('j', $t) . ' ' . $b[(int) date('n', $t)] . ' ' . date('Y', $t);
}
$periode = tgl_id($tanggal_mulai) . ' – ' . tgl_id($tanggal_akhir);
$dicetak = date('d/m/Y H:i');
$judul   = "Laporan Keuangan {$periode} — {$nama_user}";
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($judul) ?></title>
    <style id="pageRule">@page { size: A4 landscape; margin: 12mm 13mm; }</style>
    <style>
        :root { --ink: #1b1b1f; --soft: #6b7280; --line: #d6d8de; --pos: #15803d; --neg: #b91c1c; }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            background: #eef0f4;
            color: var(--ink);
            font-family: "Segoe UI", -apple-system, Roboto, "Helvetica Neue", Arial, sans-serif;
            font-size: 11pt;
            line-height: 1.4;
        }

        /* ---- Toolbar (hanya di layar) ---- */
        .toolbar {
            position: sticky;
            top: 0;
            z-index: 10;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px 14px;
            padding: 10px 16px;
            background: #fff;
            border-bottom: 1px solid var(--line);
            font-size: 10pt;
        }
        .btn {
            font: inherit;
            font-size: 10pt;
            font-weight: 600;
            padding: 8px 16px;
            border: 1px solid var(--line);
            border-radius: 6px;
            background: #fff;
            color: var(--ink);
            cursor: pointer;
        }
        .btn-primary { background: #1b1b1f; color: #fff; border-color: #1b1b1f; }
        .opt { display: inline-flex; align-items: center; gap: 6px; }
        .opt-label { color: var(--soft); }
        .seg {
            font: inherit;
            font-size: 9.5pt;
            padding: 5px 12px;
            border: 1px solid var(--line);
            background: #fff;
            color: var(--soft);
            cursor: pointer;
        }
        .seg:first-of-type { border-radius: 6px 0 0 6px; }
        .seg:last-of-type { border-radius: 0 6px 6px 0; border-left: none; }
        .seg.active { background: #1b1b1f; color: #fff; border-color: #1b1b1f; }
        .opt.chk { gap: 5px; color: var(--soft); cursor: pointer; }

        /* ---- Dokumen ---- */
        .sheet {
            margin: 22px auto 44px;
            background: #fff;
            padding: 30px 38px 38px;
            box-shadow: 0 2px 22px rgba(20, 30, 60, 0.10);
        }
        body.potret .sheet  { max-width: 800px; }
        body.lanskap .sheet { max-width: 1160px; }

        .doc-head { border-bottom: 2px solid var(--ink); padding-bottom: 11px; }
        .brand { font-weight: 800; letter-spacing: 0.5px; font-size: 12pt; }
        .doc-head h1 { font-size: 18pt; font-weight: 700; margin: 2px 0 11px; }
        .meta { border-collapse: collapse; }
        .meta td { padding: 1px 0; font-size: 9.5pt; border: none; }
        .meta td:first-child { color: var(--soft); width: 86px; }

        h2 {
            font-size: 10.5pt;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            border-bottom: 1px solid var(--line);
            padding-bottom: 4px;
            margin: 20px 0 7px;
        }
        h2 span { color: var(--soft); font-weight: 400; text-transform: none; letter-spacing: 0; }

        table.data { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
        table.data th {
            text-align: left;
            font-size: 8pt;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            color: var(--soft);
            border-bottom: 1.5px solid var(--ink);
            padding: 6px 7px;
        }
        table.data td {
            padding: 5px 7px;
            border-bottom: 1px solid var(--line);
            font-size: 9pt;
            vertical-align: top;
        }
        table.data tr.total-row td {
            border-top: 1.5px solid var(--ink);
            border-bottom: none;
            font-weight: 700;
            padding-top: 7px;
        }
        table.data tr.total-row { break-inside: avoid; }
        .num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
        .pos { color: var(--pos); }
        .neg { color: var(--neg); }

        table.sum td { border: none; padding: 4px 0; font-size: 10pt; }
        table.sum td.num { min-width: 150px; }
        table.sum tr.grand td {
            border-top: 1.5px solid var(--ink);
            font-weight: 700;
            font-size: 11pt;
            padding-top: 8px;
        }

        /* Tabel transaksi: lebar terkunci supaya tidak kepotong di kertas */
        .tx { table-layout: fixed; }
        .tx col.c-no  { width: 5%; }
        .tx col.c-tgl { width: 9%; }
        .tx col.c-ket { width: 33%; }
        .tx col.c-kat { width: 17%; }
        .tx col.c-jml { width: 17%; }
        .tx col.c-sld { width: 19%; }
        .tx th, .tx td { font-size: 8.6pt; padding: 4px 6px; }
        .tx td { word-break: break-word; overflow-wrap: anywhere; }
        .tx tbody tr { break-inside: avoid; }
        body.potret .tx th, body.potret .tx td { font-size: 8pt; padding: 3.5px 5px; }

        .kosong { color: var(--soft); font-style: italic; padding: 8px 0; }

        body.isi-ringkas .sec-transaksi { display: none; }

        /* Penutup: catatan + kolom tanda tangan (satu blok, tidak terpecah halaman) */
        .doc-end {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 24px;
            margin-top: 26px;
            padding-top: 8px;
            border-top: 1px solid var(--line);
            break-inside: avoid;
        }
        .doc-note { font-size: 8.5pt; color: var(--soft); }
        .ttd { text-align: center; min-width: 220px; }
        .ttd p { margin: 0; font-size: 10pt; }
        .ttd-space { height: 52px; border-bottom: 1px solid var(--ink); margin: 6px 14px 6px; }
        .ttd-nama { font-weight: 600; }
        body.ttd-off .ttd { display: none; }
        body.ttd-off .doc-end { justify-content: flex-start; }

        .avoid { break-inside: avoid; }

        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .sheet {
                max-width: none !important;
                margin: 0;
                padding: 0;
                box-shadow: none;
            }
            table.data thead { display: table-header-group; }
            .pos, .neg { color: var(--ink); }
        }
    </style>
</head>
<body class="lanskap isi-lengkap ttd-on">
    <div class="toolbar">
        <button class="btn btn-primary" onclick="window.print()">Cetak / Simpan PDF</button>

        <span class="opt">
            <span class="opt-label">Orientasi</span>
            <button type="button" class="seg" data-grp="orient" data-val="lanskap">Lanskap</button>
            <button type="button" class="seg" data-grp="orient" data-val="potret">Potret</button>
        </span>

        <span class="opt">
            <span class="opt-label">Isi</span>
            <button type="button" class="seg" data-grp="isi" data-val="lengkap">Lengkap</button>
            <button type="button" class="seg" data-grp="isi" data-val="ringkas">Ringkas</button>
        </span>

        <label class="opt chk">
            <input type="checkbox" id="ttdChk" checked> Kolom tanda tangan
        </label>

        <button class="btn" onclick="window.close()">Tutup</button>
        <span class="opt-label">Di dialog cetak pilih <b>Simpan sebagai PDF</b>.</span>
    </div>

    <article class="sheet">
        <header class="doc-head">
            <div class="brand">FinTrack</div>
            <h1>Laporan Keuangan</h1>
            <table class="meta">
                <tr><td>Nama</td><td><?= e($nama_user) ?></td></tr>
                <tr><td>Periode</td><td><?= e($periode) ?></td></tr>
                <tr><td>Dicetak</td><td><?= e($dicetak) ?> WIB</td></tr>
            </table>
        </header>

        <section class="avoid">
            <h2>Ringkasan</h2>
            <table class="sum">
                <tr><td>Saldo Awal</td><td class="num"><?= rp($saldo_awal) ?></td></tr>
                <tr><td>Total Pemasukan</td><td class="num pos">+<?= rp($total_masuk) ?></td></tr>
                <tr><td>Total Pengeluaran</td><td class="num neg">-<?= rp($total_keluar) ?></td></tr>
                <tr><td>Arus Kas Bersih</td><td class="num <?= $net >= 0 ? 'pos' : 'neg' ?>"><?= ($net >= 0 ? '+' : '-') . rp(abs($net)) ?></td></tr>
                <tr class="grand"><td>Saldo Akhir</td><td class="num"><?= rp($saldo_akhir) ?></td></tr>
            </table>
        </section>

        <section class="avoid">
            <h2>Rincian Pengeluaran per Kategori</h2>
            <?php if ($kat): ?>
                <table class="data">
                    <thead>
                        <tr><th>Kategori</th><th class="num">Jumlah</th><th class="num">Porsi</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($kat as $nama => $jml_k):
                            $p = $total_keluar > 0 ? $jml_k / $total_keluar * 100 : 0; ?>
                            <tr>
                                <td><?= e($nama) ?></td>
                                <td class="num"><?= rp((float) $jml_k) ?></td>
                                <td class="num"><?= number_format($p, 1, ',', '.') ?>%</td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="total-row">
                            <td>Total Pengeluaran</td>
                            <td class="num"><?= rp($total_keluar) ?></td>
                            <td class="num">100%</td>
                        </tr>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="kosong">Tidak ada pengeluaran pada periode ini.</p>
            <?php endif; ?>
        </section>

        <section class="sec-transaksi">
            <h2>Daftar Transaksi <span>(<?= $jml ?> transaksi)</span></h2>
            <?php if ($rows): ?>
                <table class="data tx">
                    <colgroup>
                        <col class="c-no"><col class="c-tgl"><col class="c-ket">
                        <col class="c-kat"><col class="c-jml"><col class="c-sld">
                    </colgroup>
                    <thead>
                        <tr>
                            <th class="num">No</th>
                            <th>Tanggal</th>
                            <th>Keterangan</th>
                            <th>Kategori</th>
                            <th class="num">Jumlah</th>
                            <th class="num">Saldo Berjalan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $i => $r): ?>
                            <tr>
                                <td class="num"><?= $i + 1 ?></td>
                                <td><?= date('d/m/y', strtotime($r['tanggal'])) ?></td>
                                <td><?= e($r['keterangan']) ?></td>
                                <td><?= e($r['kategori_nama'] ?: '—') ?></td>
                                <td class="num <?= $r['tipe'] === 'pemasukan' ? 'pos' : 'neg' ?>">
                                    <?= ($r['tipe'] === 'pemasukan' ? '+' : '-') . rp((float) $r['jumlah']) ?>
                                </td>
                                <td class="num"><?= rp((float) $r['saldo_jalan']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="total-row">
                            <td colspan="4">TOTAL</td>
                            <td class="num <?= $net >= 0 ? 'pos' : 'neg' ?>"><?= ($net >= 0 ? '+' : '-') . rp(abs($net)) ?></td>
                            <td class="num"><?= rp($saldo_akhir) ?></td>
                        </tr>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="kosong">Tidak ada transaksi pada periode ini.</p>
            <?php endif; ?>
        </section>

        <div class="doc-end">
            <span class="doc-note">Dokumen dibuat otomatis oleh FinTrack &middot; <?= e($dicetak) ?> WIB</span>
            <div class="ttd">
                <p>Dibuat oleh,</p>
                <div class="ttd-space"></div>
                <p class="ttd-nama"><?= e($nama_user) ?></p>
            </div>
        </div>
    </article>

    <script>
        (function () {
            var body = document.body;
            var pageRule = document.getElementById('pageRule');
            var ttdChk = document.getElementById('ttdChk');

            var opsi = { orient: 'lanskap', isi: 'lengkap', ttd: true };
            try {
                var s = JSON.parse(localStorage.getItem('pdfOpsi') || '{}');
                if (s.orient) opsi.orient = s.orient;
                if (s.isi) opsi.isi = s.isi;
                if (typeof s.ttd === 'boolean') opsi.ttd = s.ttd;
            } catch (e) {}

            function terapkan() {
                body.classList.toggle('lanskap', opsi.orient === 'lanskap');
                body.classList.toggle('potret', opsi.orient === 'potret');
                body.classList.toggle('isi-lengkap', opsi.isi === 'lengkap');
                body.classList.toggle('isi-ringkas', opsi.isi === 'ringkas');
                body.classList.toggle('ttd-on', opsi.ttd);
                body.classList.toggle('ttd-off', !opsi.ttd);
                pageRule.textContent = '@page { size: A4 ' +
                    (opsi.orient === 'potret' ? 'portrait' : 'landscape') + '; margin: 12mm 13mm; }';
                ttdChk.checked = opsi.ttd;
                document.querySelectorAll('.seg').forEach(function (b) {
                    b.classList.toggle('active', opsi[b.dataset.grp] === b.dataset.val);
                });
                try { localStorage.setItem('pdfOpsi', JSON.stringify(opsi)); } catch (e) {}
            }

            document.querySelectorAll('.seg').forEach(function (b) {
                b.addEventListener('click', function () {
                    opsi[b.dataset.grp] = b.dataset.val;
                    // Ringkas cocoknya potret, Lengkap cocoknya lanskap
                    if (b.dataset.grp === 'isi') opsi.orient = (b.dataset.val === 'ringkas') ? 'potret' : 'lanskap';
                    terapkan();
                });
            });
            ttdChk.addEventListener('change', function () { opsi.ttd = ttdChk.checked; terapkan(); });

            terapkan();
        })();
    </script>
</body>
</html>
