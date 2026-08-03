<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>FinTrack</title>
    <link rel="stylesheet" href="<?= base_url('assets/app.css') ?>">
</head>
<body class="auth-page">
    <main class="auth-shell">
        <section class="auth-brand">
            <div class="brand-mark">FT</div>
            <div>
                <p class="eyebrow">FinTrack</p>
                <h1>Catatan keuangan pribadi yang rapi.</h1>
                <p class="auth-copy">Kelola pemasukan, pengeluaran, target, dan saldo dari satu ruang kerja pribadi.</p>
            </div>
        </section>

        <section class="auth-panel">
            <?php $error = session()->getFlashdata('error'); ?>
            <?php $success = session()->getFlashdata('success'); ?>
            <?php $errors = session()->getFlashdata('errors') ?? []; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?= esc($error) ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success"><?= esc($success) ?></div>
            <?php endif; ?>

            <?php if ($errors): ?>
                <div class="alert alert-danger">
                    <?php foreach ($errors as $message): ?>
                        <div><?= esc($message) ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?= $this->renderSection('content') ?>
        </section>
    </main>

    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
    <script src="<?= base_url('assets/app.js') ?>"></script>
</body>
</html>
