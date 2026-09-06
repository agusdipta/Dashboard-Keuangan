<?php
/**
 * Navigasi bawah untuk layar HP (<= 768px): tab bar Dashboard / Laporan / + / Atur
 * plus bottom sheet "Tambah Transaksi".
 *
 * Sertakan tepat sebelum </body> pada halaman terkunci.
 * Butuh   : $koneksi, $UID (dari auth.php)
 * Opsional: $AKTIF = 'dashboard' | 'laporan' | 'pengaturan'
 */
$AKTIF = $AKTIF ?? '';
$kat_sheet = ambil_kategori($koneksi, $UID);
?>
<div class="mnav-scrim" id="mnavScrim"></div>

<div class="sheet-tambah" id="sheetTambah" role="dialog" aria-modal="true" aria-label="Tambah transaksi">
    <div class="sheet-grip"></div>
    <div class="sheet-head">
        <h3><i class="fas fa-plus-circle"></i> Tambah Transaksi</h3>
        <button type="button" class="sheet-close" id="sheetClose" aria-label="Tutup">&times;</button>
    </div>
    <form action="tambah.php" method="post" class="sheet-form">
        <?= csrf_field() ?>
        <?php require __DIR__ . '/partial_scan_struk.php'; ?>
        <?php $transaction_prefix = 's_'; $transaction_categories = $kat_sheet; require __DIR__ . '/partial_transaction_fields.php'; ?>
    </form>
</div>

<nav class="mnav" aria-label="Navigasi utama">
    <a href="index.php" class="mnav-item<?= $AKTIF === 'dashboard' ? ' active' : '' ?>">
        <i class="fas fa-house"></i><span>Beranda</span>
    </a>
    <a href="transaksi.php" class="mnav-item<?= $AKTIF === 'transaksi' ? ' active' : '' ?>">
        <i class="fas fa-receipt"></i><span>Transaksi</span>
    </a>
    <button type="button" class="mnav-fab" id="mnavFab" aria-label="Tambah transaksi">
        <i class="fas fa-plus"></i>
    </button>
    <a href="laporan.php" class="mnav-item<?= $AKTIF === 'laporan' ? ' active' : '' ?>">
        <i class="fas fa-chart-line"></i><span>Laporan</span>
    </a>
    <a href="pengaturan.php" class="mnav-item<?= $AKTIF === 'pengaturan' ? ' active' : '' ?>">
        <i class="fas fa-gear"></i><span>Atur</span>
    </a>
</nav>

<script src="receipt-parser.js?v=<?= filemtime(__DIR__ . '/receipt-parser.js') ?>" defer></script>
<script src="scan-struk.js?v=<?= filemtime(__DIR__ . '/scan-struk.js') ?>" defer></script>

<script>
    (function () {
        var sheet = document.getElementById('sheetTambah');
        var scrim = document.getElementById('mnavScrim');
        var fab = document.getElementById('mnavFab');
        var closeBtn = document.getElementById('sheetClose');
        if (!sheet || !scrim || !fab) return;

        var scrollYSebelum = 0;

        function bukaSheet() {
            // Kunci scroll latar: bekukan <body> di posisi sekarang
            scrollYSebelum = window.scrollY || document.documentElement.scrollTop || 0;
            document.body.style.top = '-' + scrollYSebelum + 'px';
            document.body.classList.add('sheet-lock');
            document.documentElement.classList.add('sheet-lock');
            sheet.classList.add('show');
            scrim.classList.add('show');
            sheet.querySelector('form').dispatchEvent(new Event('transaction:open'));
            setTimeout(function () {
                if (!sheet.classList.contains('show')) return;
                var camera = sheet.querySelector('[data-scan-camera]');
                var f = camera && camera.getClientRects().length ? camera : document.getElementById('s_keterangan');
                if (f && !f.matches(':disabled')) f.focus({ preventScroll: true });
            }, 280);
        }
        function tutupSheet() {
            document.body.classList.remove('sheet-lock');
            document.documentElement.classList.remove('sheet-lock');
            document.body.style.top = '';
            sheet.classList.remove('show');
            scrim.classList.remove('show');
            window.scrollTo(0, scrollYSebelum);
        }

        fab.addEventListener('click', bukaSheet);
        scrim.addEventListener('click', tutupSheet);
        closeBtn.addEventListener('click', tutupSheet);
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && sheet.classList.contains('show')) tutupSheet();
        });

        // Saring pilihan kategori sesuai tipe transaksi
        var tipe = document.getElementById('s_tipe');
        var kat = document.getElementById('s_kategori');
        if (tipe && kat) {
            var semua = Array.prototype.slice.call(kat.options)
                .filter(function (o) { return o.value !== ''; })
                .map(function (o) { return { value: o.value, text: o.textContent.trim(), tipe: o.dataset.tipe }; });
            function sync() {
                var t = tipe.value, prev = kat.value;
                kat.innerHTML = '<option value="">— Tanpa kategori —</option>';
                semua.filter(function (o) { return o.tipe === t; }).forEach(function (o) {
                    var opt = document.createElement('option');
                    opt.value = o.value;
                    opt.textContent = o.text;
                    if (o.value === prev) opt.selected = true;
                    kat.appendChild(opt);
                });
            }
            tipe.addEventListener('change', sync);
            sync();
        }
    })();
</script>
