<?php
require __DIR__ . '/koneksi.php';

if (user_saat_ini($koneksi)) {
    header('Location: index.php');
    exit;
}

// Sudah ada admin aktif?
$ada_admin = (int) $koneksi->query(
    "SELECT COUNT(*) AS n FROM users WHERE peran = 'admin' AND status = 'aktif'"
)->fetch_assoc()['n'] > 0;

$error = '';
$lama  = ['nama' => '', 'username' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $nama     = trim($_POST['nama'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $pw       = (string) ($_POST['password'] ?? '');
    $pw2      = (string) ($_POST['password2'] ?? '');
    $lama     = ['nama' => $nama, 'username' => $username];

    $err = [];
    if ($nama === '' || mb_strlen($nama) > 100) {
        $err[] = 'Nama lengkap wajib diisi (maksimal 100 karakter).';
    }
    if (!preg_match('/^[A-Za-z0-9_.]{3,50}$/', $username)) {
        $err[] = 'Username 3–50 karakter: huruf, angka, titik, atau garis bawah.';
    }
    if (mb_strlen($pw) < 8) {
        $err[] = 'Password minimal 8 karakter.';
    }
    if ($pw !== $pw2) {
        $err[] = 'Konfirmasi password tidak cocok.';
    }

    if (!$err) {
        $s = $koneksi->prepare("SELECT id FROM users WHERE username = ?");
        $s->bind_param('s', $username);
        $s->execute();
        if ($s->get_result()->fetch_assoc()) {
            $err[] = 'Username sudah dipakai, pilih yang lain.';
        }
        $s->close();
    }

    if (!$err) {
        $peran  = $ada_admin ? 'user' : 'admin';
        $status = $ada_admin ? 'pending' : 'aktif';
        $hash   = password_hash($pw, PASSWORD_DEFAULT);

        $s = $koneksi->prepare(
            "INSERT INTO users (username, password, nama_lengkap, peran, status) VALUES (?, ?, ?, ?, ?)"
        );
        $s->bind_param('sssss', $username, $hash, $nama, $peran, $status);
        $s->execute();
        $uid = (int) $s->insert_id;
        $s->close();

        if (!$ada_admin) {
            // Admin pertama mewarisi seluruh data lama yang belum berpemilik
            $koneksi->query("UPDATE transaksi SET user_id = $uid WHERE user_id IS NULL");
            $koneksi->query("UPDATE kategori  SET user_id = $uid WHERE user_id IS NULL");
            $koneksi->query("UPDATE saldo     SET user_id = $uid WHERE user_id IS NULL");
        }

        // Pastikan punya baris saldo & kategori
        $s = $koneksi->prepare("INSERT IGNORE INTO saldo (user_id, saldo_awal) VALUES (?, 0)");
        $s->bind_param('i', $uid);
        $s->execute();
        $s->close();

        $s = $koneksi->prepare("SELECT COUNT(*) AS n FROM kategori WHERE user_id = ?");
        $s->bind_param('i', $uid);
        $s->execute();
        $punya_kat = (int) $s->get_result()->fetch_assoc()['n'];
        $s->close();
        if ($punya_kat === 0) {
            seed_kategori_untuk_user($koneksi, $uid);
        }

        $pesan = $ada_admin
            ? 'Pendaftaran berhasil! Akunmu menunggu persetujuan admin sebelum bisa dipakai.'
            : 'Akun admin berhasil dibuat. Silakan login.';
        header('Location: login.php?msg=' . urlencode($pesan));
        exit;
    }

    $error = implode('<br>', array_map('e', $err));
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buat Akun — FinTrack</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
</head>
<body class="auth-body">
    <div class="auth-wrap">
        <div class="auth-card">
            <div class="auth-brand">
                <span class="logo"><i class="fas fa-user-plus"></i></span>
                <h1>Buat Akun</h1>
                <p>
                    <?= $ada_admin
                        ? 'Akun baru perlu disetujui admin sebelum aktif'
                        : 'Kamu akan menjadi admin pertama aplikasi ini' ?>
                </p>
            </div>

            <?php if (!$ada_admin): ?>
                <div class="alert alert-info">
                    <i class="fas fa-crown"></i> Belum ada admin. Akun pertama ini otomatis jadi <strong>admin</strong>
                    dan mewarisi seluruh data yang sudah ada.
                </div>
            <?php endif; ?>
            <?php if ($error !== ''): ?>
                <div class="alert alert-danger"><i class="fas fa-triangle-exclamation"></i> <?= $error ?></div>
            <?php endif; ?>

            <form method="post" action="register.php" novalidate>
                <?= csrf_field() ?>
                <div class="form-group">
                    <label for="nama"><i class="fas fa-id-card"></i> Nama Lengkap</label>
                    <input type="text" id="nama" name="nama" class="form-control" required
                           value="<?= e($lama['nama']) ?>">
                </div>
                <div class="form-group">
                    <label for="username"><i class="fas fa-at"></i> Username</label>
                    <input type="text" id="username" name="username" class="form-control" required
                           pattern="[A-Za-z0-9_.]{3,50}" value="<?= e($lama['username']) ?>">
                </div>
                <div class="form-group">
                    <label for="password"><i class="fas fa-lock"></i> Password</label>
                    <div class="input-icon">
                        <input type="password" id="password" name="password" class="form-control" required minlength="8">
                        <button type="button" class="toggle-eye" data-target="password" aria-label="Tampilkan password">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <div class="pw-meter"><span id="pwBar"></span></div>
                    <small id="pwHint" class="form-text">Minimal 8 karakter.</small>
                </div>
                <div class="form-group">
                    <label for="password2"><i class="fas fa-lock"></i> Konfirmasi Password</label>
                    <div class="input-icon">
                        <input type="password" id="password2" name="password2" class="form-control" required minlength="8">
                        <button type="button" class="toggle-eye" data-target="password2" aria-label="Tampilkan password">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary btn-block mt-2">
                    <i class="fas fa-user-plus"></i> Buat Akun
                </button>
            </form>

            <p class="auth-alt">Sudah punya akun? <a href="login.php">Masuk</a></p>
        </div>
    </div>

    <script>
        document.querySelectorAll('.toggle-eye').forEach((btn) => {
            btn.addEventListener('click', () => {
                const inp = document.getElementById(btn.dataset.target);
                const show = inp.type === 'password';
                inp.type = show ? 'text' : 'password';
                btn.querySelector('i').className = show ? 'fas fa-eye-slash' : 'fas fa-eye';
            });
        });

        // Indikator kekuatan password
        const pw = document.getElementById('password');
        const bar = document.getElementById('pwBar');
        const hint = document.getElementById('pwHint');
        const tingkat = [
            { w: '20%', c: '#ef4444', t: 'Sangat lemah' },
            { w: '40%', c: '#f59e0b', t: 'Lemah' },
            { w: '65%', c: '#eab308', t: 'Cukup' },
            { w: '85%', c: '#22c55e', t: 'Kuat' },
            { w: '100%', c: '#16a34a', t: 'Sangat kuat' },
        ];
        pw.addEventListener('input', () => {
            const v = pw.value;
            let skor = 0;
            if (v.length >= 8) skor++;
            if (v.length >= 12) skor++;
            if (/[A-Z]/.test(v) && /[a-z]/.test(v)) skor++;
            if (/\d/.test(v)) skor++;
            if (/[^A-Za-z0-9]/.test(v)) skor++;
            if (!v) { bar.style.width = '0'; hint.textContent = 'Minimal 8 karakter.'; return; }
            const lv = tingkat[Math.min(skor, 5) - 1] || tingkat[0];
            bar.style.width = lv.w;
            bar.style.background = lv.c;
            hint.textContent = 'Kekuatan: ' + lv.t;
        });
    </script>
</body>
</html>
