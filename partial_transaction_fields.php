<?php $transaction_category_id = $transaction_prefix === 's_' ? 's_kategori' : 'kategori_id'; ?>
<fieldset class="transaction-fields" data-transaction-fields>
    <legend data-transaction-title>Input Manual</legend>
    <p class="transaction-review-note" data-transaction-summary hidden role="status" aria-live="polite"></p>
    <div class="transaction-amount form-group">
        <label for="<?= $transaction_prefix ?>jumlah">Jumlah transaksi</label>
        <div class="transaction-amount-input">
            <span aria-hidden="true">Rp</span>
            <input type="text" inputmode="numeric" id="<?= $transaction_prefix ?>jumlah" name="jumlah" class="form-control js-rupiah" placeholder="0" aria-label="Jumlah dalam rupiah" autocomplete="off" required>
        </div>
    </div>
    <div class="form-group">
        <label for="<?= $transaction_prefix ?>keterangan">Keterangan</label>
        <input type="text" id="<?= $transaction_prefix ?>keterangan" name="keterangan" class="form-control" placeholder="Contoh: makan siang" required>
    </div>
    <div class="transaction-field-row">
        <div class="form-group">
            <label for="<?= $transaction_prefix ?>tipe">Jenis transaksi</label>
            <select id="<?= $transaction_prefix ?>tipe" name="tipe" class="form-control" required>
                <option value="pengeluaran">Pengeluaran</option>
                <option value="pemasukan">Pemasukan</option>
            </select>
        </div>
        <div class="form-group">
            <label for="<?= $transaction_prefix ?>tanggal">Tanggal</label>
            <input type="date" id="<?= $transaction_prefix ?>tanggal" name="tanggal" class="form-control" value="<?= date('Y-m-d') ?>" required>
        </div>
    </div>
    <div class="form-group">
        <label for="<?= $transaction_category_id ?>">Kategori <span class="transaction-optional">opsional</span></label>
        <select id="<?= $transaction_category_id ?>" name="kategori_id" class="form-control">
            <option value="">Tanpa kategori</option>
            <?php foreach ($transaction_categories as $k): ?>
                <option value="<?= (int) $k['id'] ?>" data-tipe="<?= e($k['tipe']) ?>"><?= e($k['nama']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <button type="submit" class="btn btn-primary btn-block transaction-save">Simpan Transaksi</button>
</fieldset>
