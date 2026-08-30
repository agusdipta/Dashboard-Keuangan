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
        <div class="form-group">
            <label for="s_keterangan"><i class="fas fa-file-alt"></i> Keterangan</label>
            <input type="text" id="s_keterangan" name="keterangan" class="form-control" placeholder="Keterangan transaksi" required>
        </div>
        <div class="form-group">
            <label for="s_jumlah"><i class="fas fa-money-bill"></i> Jumlah (Rp)</label>
            <input type="text" inputmode="numeric" id="s_jumlah" name="jumlah" class="form-control js-rupiah" placeholder="0" autocomplete="off" required>
        </div>
        <div class="sheet-2col">
            <div class="form-group">
                <label for="s_tipe"><i class="fas fa-exchange-alt"></i> Tipe</label>
                <select id="s_tipe" name="tipe" class="form-control" required>
                    <option value="pengeluaran">Pengeluaran</option>
                    <option value="pemasukan">Pemasukan</option>
                </select>
            </div>
            <div class="form-group">
                <label for="s_tanggal"><i class="fas fa-calendar"></i> Tanggal</label>
                <input type="date" id="s_tanggal" name="tanggal" class="form-control" value="<?= date('Y-m-d') ?>" required>
            </div>
        </div>
        <div class="form-group">
            <label for="s_kategori"><i class="fas fa-tags"></i> Kategori</label>
            <select id="s_kategori" name="kategori_id" class="form-control">
                <option value="">— Tanpa kategori —</option>
                <?php foreach ($kat_sheet as $k): ?>
                    <option value="<?= (int) $k['id'] ?>" data-tipe="<?= e($k['tipe']) ?>"><?= e($k['nama']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-primary btn-block">
            <i class="fas fa-plus-circle"></i> Simpan
        </button>
    </form>
</div>

<nav class="mnav" aria-label="Navigasi utama">
    <a href="index.php" class="mnav-item<?= $AKTIF === 'dashboard' ? ' active' : '' ?>">
        <i class="fas fa-house"></i><span>Dashboard</span>
    </a>
    <a href="laporan.php" class="mnav-item<?= $AKTIF === 'laporan' ? ' active' : '' ?>">
        <i class="fas fa-chart-line"></i><span>Laporan</span>
    </a>
    <button type="button" class="mnav-fab" id="mnavFab" aria-label="Tambah transaksi">
        <i class="fas fa-plus"></i>
    </button>
    <a href="pengaturan.php" class="mnav-item<?= $AKTIF === 'pengaturan' ? ' active' : '' ?>">
        <i class="fas fa-gear"></i><span>Atur</span>
    </a>
</nav>

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
            var f = document.getElementById('s_keterangan');
            if (f) setTimeout(function () { f.focus(); }, 280);
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
