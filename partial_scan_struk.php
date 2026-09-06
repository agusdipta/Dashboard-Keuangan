<div class="scan-struk" data-scan-struk>
    <div class="scan-struk-heading"><i class="fas fa-receipt" aria-hidden="true"></i> Catat dari struk</div>
    <p>Ambil foto, periksa total, lalu simpan.</p>
    <div class="scan-struk-actions">
        <div class="scan-struk-entry-actions">
            <button type="button" class="btn btn-primary scan-struk-entry-button" data-scan-camera>
                <i class="fas fa-camera" aria-hidden="true"></i> Foto Struk
            </button>
            <button type="button" class="btn btn-primary scan-struk-entry-button" data-transaction-manual hidden>
                <i class="fas fa-pen" aria-hidden="true"></i> Input Manual
            </button>
        </div>
        <button type="button" class="btn btn-outline-primary btn-sm" data-scan-upload>
            <i class="fas fa-image" aria-hidden="true"></i> Pilih Gambar
        </button>
        <button type="button" class="btn btn-outline-secondary btn-sm" data-scan-cancel hidden>Batal Scan</button>
    </div>
    <input type="file" accept="image/jpeg,image/png,image/webp" capture="environment" data-scan-camera-input hidden>
    <input type="file" accept="image/jpeg,image/png,image/webp" data-scan-upload-input hidden>
    <details class="scan-struk-help">
        <summary>Tips foto struk</summary>
        <small>Sertakan tulisan total dan angkanya. JPG, PNG, atau WebP, maksimal 10 MB. Foto diproses di perangkat ini. Scan membutuhkan internet.</small>
    </details>
    <img class="scan-struk-preview" data-scan-preview alt="Pratinjau struk yang dipilih" hidden>
    <p class="scan-struk-status" data-scan-status role="status" aria-live="polite" aria-atomic="true"></p>
    <progress data-scan-progress max="100" aria-label="Progres membaca struk" hidden></progress>
    <label class="scan-struk-choice" data-scan-choice hidden>
        Pilih total yang sesuai dengan struk
        <select class="form-control" data-scan-candidates></select>
    </label>
    <div class="scan-struk-checks" data-scan-checks hidden>
        <strong>Pemeriksaan jumlah</strong>
        <ul data-scan-check-list></ul>
    </div>
    <details data-scan-details hidden>
        <summary>Lihat teks hasil scan</summary>
        <pre data-scan-text></pre>
    </details>
    <noscript>Aktifkan JavaScript untuk scan struk. Transaksi tetap bisa diisi manual.</noscript>
</div>
<div class="transaction-mode-actions">
    <button type="button" class="transaction-mode-link" data-transaction-photo hidden><i class="fas fa-camera" aria-hidden="true"></i> Kembali ke Foto Struk</button>
</div>
