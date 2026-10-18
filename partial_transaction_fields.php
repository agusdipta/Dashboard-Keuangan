<?php $transaction_category_id = $transaction_prefix === 's_' ? 's_kategori' : 'kategori_id'; ?>
<fieldset class="transaction-fields" data-transaction-fields data-tipe="pengeluaran">
    <legend data-transaction-title>Input Manual</legend>
    <p class="transaction-review-note" data-transaction-summary hidden role="status" aria-live="polite"></p>
    <?php /* <select>/<input> asli tetap sumber nilai (dipakai scan-struk.js); tombol & chip hanya lapisan JS. */ ?>
    <div class="form-group js-native">
        <label for="<?= $transaction_prefix ?>tipe">Jenis transaksi</label>
        <select id="<?= $transaction_prefix ?>tipe" name="tipe" class="form-control" required>
            <option value="pengeluaran">Pengeluaran</option>
            <option value="pemasukan">Pemasukan</option>
        </select>
    </div>
    <div class="tipe-toggle" data-tipe-toggle role="group" aria-label="Jenis transaksi" hidden>
        <button type="button" data-value="pengeluaran" aria-pressed="true">Pengeluaran</button>
        <button type="button" data-value="pemasukan" aria-pressed="false">Pemasukan</button>
    </div>
    <div class="transaction-amount form-group">
        <label for="<?= $transaction_prefix ?>jumlah">Jumlah</label>
        <div class="transaction-amount-input">
            <span aria-hidden="true">Rp</span>
            <input type="text" inputmode="numeric" id="<?= $transaction_prefix ?>jumlah" name="jumlah" class="form-control js-rupiah" placeholder="0" aria-label="Jumlah dalam rupiah" autocomplete="off" required>
        </div>
    </div>
    <div class="form-group">
        <label for="<?= $transaction_prefix ?>keterangan">Keterangan</label>
        <input type="text" id="<?= $transaction_prefix ?>keterangan" name="keterangan" class="form-control" placeholder="Contoh: makan siang" required>
    </div>
    <div class="form-group">
        <label for="<?= $transaction_category_id ?>" id="<?= $transaction_prefix ?>kategori-label">Kategori <span class="transaction-optional">opsional</span></label>
        <select id="<?= $transaction_category_id ?>" name="kategori_id" class="form-control js-native">
            <option value="">Tanpa kategori</option>
            <?php foreach ($transaction_categories as $k): ?>
                <option value="<?= (int) $k['id'] ?>" data-tipe="<?= e($k['tipe']) ?>"><?= e($k['nama']) ?></option>
            <?php endforeach; ?>
        </select>
        <div class="chip-group" data-kategori-chips role="group" aria-labelledby="<?= $transaction_prefix ?>kategori-label" hidden>
            <?php foreach ($transaction_categories as $k): ?>
                <button type="button" class="chip" data-value="<?= (int) $k['id'] ?>" data-tipe="<?= e($k['tipe']) ?>" aria-pressed="false" style="--kat: <?= e($k['warna'] ?: '#6c757d') ?>">
                    <i class="fas <?= e($k['ikon'] ?: 'fa-tag') ?>" aria-hidden="true"></i> <?= e($k['nama']) ?>
                </button>
            <?php endforeach; ?>
            <span class="chip-empty" data-kategori-empty hidden>Belum ada kategori untuk jenis ini. Atur di Pengaturan.</span>
        </div>
    </div>
    <div class="form-group">
        <label for="<?= $transaction_prefix ?>tanggal">Tanggal</label>
        <div class="chip-group chip-group-date" data-tanggal-chips hidden>
            <button type="button" class="chip" data-value="<?= date('Y-m-d') ?>" aria-pressed="false">Hari ini</button>
            <button type="button" class="chip" data-value="<?= date('Y-m-d', strtotime('-1 day')) ?>" aria-pressed="false">Kemarin</button>
        </div>
        <input type="date" id="<?= $transaction_prefix ?>tanggal" name="tanggal" class="form-control" value="<?= date('Y-m-d') ?>" required>
    </div>
    <button type="submit" class="btn btn-primary btn-block transaction-save">Simpan Transaksi</button>
</fieldset>
