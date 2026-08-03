<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>FinTrack</title>
    <link rel="stylesheet" href="<?= base_url('assets/app.css') ?>">
</head>
<body class="app-page">
    <aside class="sidebar" id="appSidebar" aria-label="Navigasi utama">
        <div class="sidebar-head">
            <a class="sidebar-brand" href="<?= site_url('/') ?>">
                <span class="brand-mark">FT</span>
                <span>FinTrack</span>
            </a>
            <button class="icon-button sidebar-close" type="button" data-sidebar-toggle aria-controls="appSidebar" aria-expanded="false" aria-label="Tutup menu" title="Tutup menu">
                <i data-lucide="x"></i>
            </button>
        </div>
        <nav class="sidebar-nav" aria-label="Navigasi utama">
            <a class="active" href="<?= site_url('/') ?>"><i data-lucide="layout-dashboard"></i><span>Dashboard</span></a>
            <a href="#transaksi"><i data-lucide="receipt-text"></i><span>Transaksi</span></a>
            <a href="#target"><i data-lucide="target"></i><span>Target</span></a>
        </nav>
        <div class="sidebar-user">
            <span><?= esc(session()->get('user_name')) ?></span>
            <small><?= esc(session()->get('user_email')) ?></small>
        </div>
    </aside>
    <button class="sidebar-backdrop" type="button" data-sidebar-close aria-label="Tutup menu"></button>

    <div class="app-frame">
        <header class="topbar">
            <div class="topbar-title">
                <button class="icon-button menu-button" type="button" data-sidebar-toggle aria-controls="appSidebar" aria-expanded="false" aria-label="Buka menu" title="Buka menu">
                    <i data-lucide="menu"></i>
                </button>
                <div>
                    <p class="eyebrow">Akun pribadi</p>
                    <h1><?= $this->renderSection('title') ?></h1>
                </div>
            </div>
            <form method="post" action="<?= site_url('logout') ?>">
                <?= csrf_field() ?>
                <button class="icon-button" type="submit" aria-label="Keluar" title="Keluar">
                    <i data-lucide="log-out"></i>
                </button>
            </form>
        </header>

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
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
    <script src="<?= base_url('assets/app.js') ?>"></script>
</body>
</html>
