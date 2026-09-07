<?php
require __DIR__ . '/koneksi.php';

if (user_saat_ini($koneksi)) {
    header('Location: index.php');
    exit;
}

$error = '';
$info  = isset($_GET['msg']) ? (string) $_GET['msg'] : '';
$username_lama = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $username = trim($_POST['username'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    $ingat    = !empty($_POST['ingat']);
    $username_lama = $username;

    $stmt = $koneksi->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->bindValue(1, $username, PDO::PARAM_STR);
    $stmt->execute();
    $u = $stmt->fetch();
    $stmt->closeCursor();

    $terkunci = $u && $u['kunci_sampai'] && strtotime($u['kunci_sampai']) > time();

    if ($terkunci) {
        $sisa = (int) ceil((strtotime($u['kunci_sampai']) - time()) / 60);
        $error = "Terlalu banyak percobaan gagal. Coba lagi dalam $sisa menit.";
    } elseif (!$u || !password_verify($password, $u['password'])) {
        $error = "Username atau password salah.";
        if ($u) {
            $gagal = (int) $u['gagal_login'] + 1;
            if ($gagal >= 5) {
                $kunci = date('Y-m-d H:i:s', time() + 15 * 60);
                $s = $koneksi->prepare("UPDATE users SET gagal_login = ?, kunci_sampai = ? WHERE id = ?");
                $s->bindValue(1, $gagal, PDO::PARAM_INT);
                $s->bindValue(2, $kunci, PDO::PARAM_STR);
                $s->bindValue(3, $u['id'], PDO::PARAM_INT);
                $error = "Terlalu banyak percobaan gagal. Akun dikunci selama 15 menit.";
            } else {
                $s = $koneksi->prepare("UPDATE users SET gagal_login = ? WHERE id = ?");
                $s->bindValue(1, $gagal, PDO::PARAM_INT);
                $s->bindValue(2, $u['id'], PDO::PARAM_INT);
                if ($gagal >= 3) {
                    $error .= " Sisa " . (5 - $gagal) . " percobaan sebelum akun dikunci.";
                }
            }
            $s->execute();
            $s->closeCursor();
        }
    } elseif ($u['status'] === 'pending') {
        $error = "Akun kamu masih menunggu persetujuan admin.";
    } elseif ($u['status'] === 'nonaktif') {
        $error = "Akun kamu dinonaktifkan. Hubungi admin.";
    } else {
        $s = $koneksi->prepare("UPDATE users SET gagal_login = 0, kunci_sampai = NULL WHERE id = ?");
        $s->bindValue(1, $u['id'], PDO::PARAM_INT);
        $s->execute();
        $s->closeCursor();

        session_regenerate_id(true);
        $_SESSION['uid'] = (int) $u['id'];
        if ($ingat) {
            set_cookie_ingat($koneksi, (int) $u['id']);
        }
        header('Location: index.php');
        exit;
    }
}

$jumlah_user = (int) $koneksi->query("SELECT COUNT(*) AS n FROM users WHERE status <> 'nonaktif'")->fetch()['n'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk — FinTrack</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
    <script>(function(){try{var t=localStorage.getItem("appTheme");if(!t)t=window.matchMedia("(prefers-color-scheme: dark)").matches?"dark":"light";document.documentElement.setAttribute("data-theme",t);}catch(e){}})();</script>
</head>
<body class="auth-body">
    <div class="auth-wrap">
        <div class="auth-card">
            <div class="auth-brand">
                <span class="logo"><i class="fas fa-wallet"></i></span>
                <h1>FinTrack</h1>
                <p>Masuk untuk mengelola keuanganmu</p>
            </div>

            <?php if ($info !== ''): ?>
                <div class="alert alert-info"><i class="fas fa-circle-info"></i> <?= e($info) ?></div>
            <?php endif; ?>
            <?php if ($error !== ''): ?>
                <div class="alert alert-danger"><i class="fas fa-triangle-exclamation"></i> <?= e($error) ?></div>
            <?php endif; ?>

            <form method="post" action="login.php" novalidate>
                <?= csrf_field() ?>
                <div class="form-group">
                    <label for="username"><i class="fas fa-user"></i> Username</label>
                    <input type="text" id="username" name="username" class="form-control" required autofocus
                           value="<?= e($username_lama) ?>">
                </div>
                <div class="form-group">
                    <label for="password"><i class="fas fa-lock"></i> Password</label>
                    <div class="input-icon">
                        <input type="password" id="password" name="password" class="form-control" required>
                        <button type="button" class="toggle-eye" data-target="password" aria-label="Tampilkan password">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                <label class="check-row">
                    <input type="checkbox" name="ingat" value="1"> Ingat saya di perangkat ini
                </label>
                <button type="submit" class="btn btn-primary btn-block mt-3">
                    <i class="fas fa-right-to-bracket"></i> Masuk
                </button>
            </form>

            <p class="auth-alt">
                <?php if ($jumlah_user === 0): ?>
                    Belum ada akun. <a href="register.php">Buat akun admin pertama</a>
                <?php else: ?>
                    Belum punya akun? <a href="register.php">Buat Akun</a>
                <?php endif; ?>
            </p>
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
    </script>
</body>
</html>
