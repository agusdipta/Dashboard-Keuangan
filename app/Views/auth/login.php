<?= $this->extend('layouts/auth') ?>

<?= $this->section('content') ?>
<div class="form-heading">
    <p class="eyebrow">Masuk</p>
    <h2>Selamat datang kembali</h2>
</div>

<form class="stack-form" method="post" action="<?= site_url('login') ?>">
    <?= csrf_field() ?>

    <label>
        Email Gmail
        <input type="email" name="email" value="<?= old('email') ?>" autocomplete="email" required>
    </label>

    <label>
        Password
        <input type="password" name="password" autocomplete="current-password" required>
    </label>

    <button class="primary-button" type="submit">
        <i data-lucide="log-in"></i>
        <span>Masuk</span>
    </button>
</form>

<p class="auth-switch">Belum punya akun? <a href="<?= site_url('register') ?>">Daftar sekarang</a></p>
<?= $this->endSection() ?>
