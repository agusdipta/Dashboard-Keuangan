<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Dashboard Keuangan<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php
    $rupiah = static fn ($value) => 'Rp ' . number_format((float) $value, 0, ',', '.');
    $moneyInput = static function ($value): string {
        if ($value === null || $value === '') {
            return '';
        }

        $raw = (string) $value;

        if (is_numeric($raw)) {
            return number_format((float) $raw, 0, ',', '.');
        }

        $digits = preg_replace('/\D/', '', $raw);

        return $digits === '' ? '' : number_format((float) $digits, 0, ',', '.');
    };
    $maxCategory = 0;

    foreach ($categoryBreakdown as $row) {
        $maxCategory = max($maxCategory, (float) $row['total']);
    }
?>

<section class="period-strip">
    <div>
        <p class="eyebrow"><?= esc($periodLabel) ?></p>
        <h2>Halo, <?= esc(session()->get('user_name')) ?></h2>
    </div>
    <form class="period-form" method="get" action="<?= site_url('/') ?>">
        <label>
            Bulan
            <input type="month" name="month" value="<?= esc($period) ?>">
        </label>
        <?php if ($search !== ''): ?>
            <input type="hidden" name="q" value="<?= esc($search) ?>">
        <?php endif; ?>
        <?php if ($type !== ''): ?>
            <input type="hidden" name="type" value="<?= esc($type) ?>">
        <?php endif; ?>
        <button class="secondary-button" type="submit">
            <i data-lucide="calendar-days"></i>
            <span>Terapkan</span>
        </button>
    </form>
</section>

<section class="summary-grid">
    <article class="metric-panel">
        <div class="metric-icon balance"><i data-lucide="wallet"></i></div>
        <div>
            <span>Saldo Saat Ini</span>
            <strong class="<?= $summary['balance'] < 0 ? 'text-danger' : 'text-positive' ?>"><?= $rupiah($summary['balance']) ?></strong>
        </div>
    </article>
    <article class="metric-panel">
        <div class="metric-icon income"><i data-lucide="trending-up"></i></div>
        <div>
            <span>Pemasukan Bulan Ini</span>
            <strong><?= $rupiah($summary['monthly_income']) ?></strong>
            <div class="mini-progress"><span style="width: <?= $summary['income_progress'] ?>%"></span></div>
        </div>
    </article>
    <article class="metric-panel">
        <div class="metric-icon expense"><i data-lucide="trending-down"></i></div>
        <div>
            <span>Pengeluaran Bulan Ini</span>
            <strong><?= $rupiah($summary['monthly_expense']) ?></strong>
            <div class="mini-progress danger"><span style="width: <?= $summary['expense_progress'] ?>%"></span></div>
        </div>
    </article>
    <article class="metric-panel">
        <div class="metric-icon savings"><i data-lucide="piggy-bank"></i></div>
        <div>
            <span>Sisa Budget</span>
            <strong><?= $rupiah($summary['remaining_budget']) ?></strong>
            <small>Net bulan ini <?= $rupiah($summary['monthly_net']) ?></small>
        </div>
    </article>
</section>

<section class="workspace-grid">
    <div class="panel chart-panel">
        <div class="panel-heading">
            <div>
                <p class="eyebrow">Arus Kas</p>
                <h3>Grafik Harian</h3>
            </div>
        </div>
        <div class="chart-wrap">
            <canvas id="cashflowChart" aria-label="Grafik pemasukan dan pengeluaran harian"></canvas>
        </div>
    </div>

    <div class="panel" id="target">
        <div class="panel-heading">
            <div>
                <p class="eyebrow">Target</p>
                <h3>Target Bulanan</h3>
            </div>
        </div>
        <form class="stack-form compact" method="post" action="<?= site_url('targets') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="period" value="<?= esc($period) ?>">
            <label>
                Target pemasukan
                <input type="text" inputmode="numeric" name="income_target" value="<?= esc($moneyInput(old('income_target', $target['income_target']))) ?>" data-money-input>
            </label>
            <label>
                Batas pengeluaran
                <input type="text" inputmode="numeric" name="expense_limit" value="<?= esc($moneyInput(old('expense_limit', $target['expense_limit']))) ?>" data-money-input>
            </label>
            <label>
                Target tabungan
                <input type="text" inputmode="numeric" name="savings_target" value="<?= esc($moneyInput(old('savings_target', $target['savings_target']))) ?>" data-money-input>
            </label>
            <button class="primary-button" type="submit">
                <i data-lucide="save"></i>
                <span>Simpan Target</span>
            </button>
        </form>
    </div>
</section>

<section class="workspace-grid lower-grid" id="transaksi">
    <div class="panel">
        <div class="panel-heading">
            <div>
                <p class="eyebrow">Input</p>
                <h3>Transaksi Baru</h3>
            </div>
        </div>
        <form class="stack-form compact" method="post" action="<?= site_url('transactions') ?>">
            <?= csrf_field() ?>
            <div class="form-row">
                <label>
                    Tanggal
                    <input type="date" name="transaction_date" value="<?= esc((string) old('transaction_date', $today)) ?>" required>
                </label>
                <label>
                    Tipe
                    <select name="type" data-transaction-type required>
                        <option value="income" <?= old('type', 'income') === 'income' ? 'selected' : '' ?>>Pemasukan</option>
                        <option value="expense" <?= old('type') === 'expense' ? 'selected' : '' ?>>Pengeluaran</option>
                    </select>
                </label>
            </div>
            <label>
                Keterangan
                <input type="text" name="description" value="<?= esc((string) old('description')) ?>" maxlength="255" required>
            </label>
            <div class="form-row">
                <label>
                    Jumlah
                    <input type="text" inputmode="numeric" name="amount" value="<?= esc($moneyInput(old('amount'))) ?>" data-money-input required>
                </label>
                <label>
                    Kategori
                    <select name="category_id" data-category-select>
                        <option value="">Tanpa kategori</option>
                        <?php foreach ($categories as $category): ?>
                            <option
                                value="<?= esc($category['id']) ?>"
                                data-type="<?= esc($category['type']) ?>"
                                <?= old('category_id') == $category['id'] ? 'selected' : '' ?>
                            >
                                <?= esc($category['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
            <button class="primary-button" type="submit">
                <i data-lucide="plus"></i>
                <span>Tambah Transaksi</span>
            </button>
        </form>
    </div>

    <div class="panel">
        <div class="panel-heading">
            <div>
                <p class="eyebrow">Kategori</p>
                <h3>Kategori Pribadi</h3>
            </div>
        </div>
        <form class="stack-form compact" method="post" action="<?= site_url('categories') ?>">
            <?= csrf_field() ?>
            <label>
                Nama kategori
                <input type="text" name="name" value="<?= esc((string) old('name')) ?>" maxlength="100" required>
            </label>
            <label>
                Tipe kategori
                <select name="type" required>
                    <option value="expense">Pengeluaran</option>
                    <option value="income">Pemasukan</option>
                </select>
            </label>
            <button class="secondary-button" type="submit">
                <i data-lucide="tags"></i>
                <span>Tambah Kategori</span>
            </button>
        </form>
        <div class="chip-list">
            <?php foreach ($categories as $category): ?>
                <span class="category-chip" style="--chip-color: <?= esc($category['color'], 'attr') ?>">
                    <?= esc($category['name']) ?>
                </span>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="workspace-grid lower-grid">
    <div class="panel table-panel">
        <div class="panel-heading table-heading">
            <div>
                <p class="eyebrow">Riwayat</p>
                <h3>Transaksi <?= esc($periodLabel) ?></h3>
            </div>
            <form class="search-form" method="get" action="<?= site_url('/') ?>">
                <input type="hidden" name="month" value="<?= esc($period) ?>">
                <label>
                    Cari
                    <input type="search" name="q" value="<?= esc($search) ?>">
                </label>
                <label>
                    Tipe
                    <select name="type">
                        <option value="">Semua</option>
                        <option value="income" <?= $type === 'income' ? 'selected' : '' ?>>Pemasukan</option>
                        <option value="expense" <?= $type === 'expense' ? 'selected' : '' ?>>Pengeluaran</option>
                    </select>
                </label>
                <button class="icon-button filled" type="submit" aria-label="Cari" title="Cari">
                    <i data-lucide="search"></i>
                </button>
                <?php if ($search !== '' || $type !== ''): ?>
                    <a class="icon-button" href="<?= site_url('/?month=' . $period) ?>" aria-label="Reset filter" title="Reset filter">
                        <i data-lucide="rotate-ccw"></i>
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <?php if ($transactions): ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Keterangan</th>
                            <th>Kategori</th>
                            <th>Tipe</th>
                            <th class="amount-cell">Jumlah</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $transaction): ?>
                            <?php $isIncome = $transaction['type'] === 'income'; ?>
                            <tr>
                                <td><?= date('d M Y', strtotime($transaction['transaction_date'])) ?></td>
                                <td><?= esc($transaction['description']) ?></td>
                                <td>
                                    <span class="category-chip small" style="--chip-color: <?= esc($transaction['category_color'] ?? '#64748b', 'attr') ?>">
                                        <?= esc($transaction['category_name'] ?? 'Tanpa kategori') ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="type-pill <?= $isIncome ? 'income' : 'expense' ?>">
                                        <?= $isIncome ? 'Pemasukan' : 'Pengeluaran' ?>
                                    </span>
                                </td>
                                <td class="amount-cell <?= $isIncome ? 'text-positive' : 'text-danger' ?>">
                                    <?= $isIncome ? '+' : '-' ?> <?= $rupiah($transaction['amount']) ?>
                                </td>
                                <td class="action-cell">
                                    <form method="post" action="<?= site_url('transactions/' . $transaction['id'] . '/delete') ?>" onsubmit="return confirm('Hapus transaksi ini?')">
                                        <?= csrf_field() ?>
                                        <button class="icon-button danger" type="submit" aria-label="Hapus transaksi" title="Hapus transaksi">
                                            <i data-lucide="trash-2"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i data-lucide="inbox"></i>
                <p>Belum ada transaksi untuk filter ini.</p>
            </div>
        <?php endif; ?>
    </div>

    <div class="panel">
        <div class="panel-heading">
            <div>
                <p class="eyebrow">Pengeluaran</p>
                <h3>Ringkasan Kategori</h3>
            </div>
        </div>
        <?php if ($categoryBreakdown): ?>
            <div class="breakdown-list">
                <?php foreach ($categoryBreakdown as $row): ?>
                    <?php $width = $maxCategory > 0 ? (int) round(((float) $row['total'] / $maxCategory) * 100) : 0; ?>
                    <div class="breakdown-item">
                        <div>
                            <span><?= esc($row['name']) ?></span>
                            <strong><?= $rupiah($row['total']) ?></strong>
                        </div>
                        <div class="bar-track">
                            <span style="--bar-color: <?= esc($row['color'], 'attr') ?>; width: <?= $width ?>%"></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state compact">
                <i data-lucide="circle-dollar-sign"></i>
                <p>Belum ada pengeluaran bulan ini.</p>
            </div>
        <?php endif; ?>
    </div>
</section>

<script>
    window.financeChartData = <?= json_encode($chartData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
</script>
<?= $this->endSection() ?>
