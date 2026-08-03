<?= $this->extend('layouts/auth') ?>

<?= $this->section('content') ?>
<div class="form-heading">
    <p class="eyebrow">Daftar</p>
    <h2>Buat ruang keuanganmu</h2>
</div>

<form class="stack-form" method="post" action="<?= site_url('register') ?>">
    <?= csrf_field() ?>

    <label>
        Nama
        <input type="text" name="name" value="<?= old('name') ?>" autocomplete="name" required>
    </label>

    <label>
        Email Gmail
        <input type="email" name="email" value="<?= old('email') ?>" autocomplete="email" required>
    </label>

    <label>
        Password
        <input type="password" name="password" autocomplete="new-password" minlength="8" required>
    </label>

    <label>
        Konfirmasi password
        <input type="password" name="password_confirm" autocomplete="new-password" minlength="8" required>
    </label>

    <button class="primary-button" type="submit">
        <i data-lucide="user-plus"></i>
        <span>Daftar</span>
    </button>
</form>

<p class="auth-switch">Sudah punya akun? <a href="<?= site_url('login') ?>">Masuk</a></p>
<?= $this->endSection() ?>
