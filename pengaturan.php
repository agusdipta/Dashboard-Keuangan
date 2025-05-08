<?php
$koneksi = new mysqli("localhost", "root", "", "db_keuangan");

// Ambil saldo awal
$res_saldo = $koneksi->query("SELECT * FROM saldo WHERE id = 1");
if($res_saldo->num_rows > 0) {
    $row_saldo = $res_saldo->fetch_assoc();
    $saldo_awal = $row_saldo['saldo_awal'];
} else {
    // Jika tidak ada data, buat data default
    $koneksi->query("INSERT INTO saldo (id, saldo_awal) VALUES (1, 0)");
    $saldo_awal = 0;
}

// Proses update saldo awal
$pesan = "";
if (isset($_POST['update_saldo'])) {
    $saldo_baru = $_POST['saldo_awal'];
    $sql = "UPDATE saldo SET saldo_awal = $saldo_baru WHERE id = 1";
    
    if ($koneksi->query($sql)) {
        $pesan = "<div class='alert alert-success'>Saldo awal berhasil diperbarui!</div>";
        $saldo_awal = $saldo_baru;
    } else {
        $pesan = "<div class='alert alert-danger'>Gagal memperbarui saldo awal: " . $koneksi->error . "</div>";
    }
}

// Proses backup database
if (isset($_POST['backup_db'])) {
    // Fungsi backup sederhana
    $tables = array();
    $result = $koneksi->query("SHOW TABLES");
    while ($row = $result->fetch_row()) {
        $tables[] = $row[0];
    }
    
    $backup_file = 'backup_' . date("Y-m-d_H-i-s") . '.sql';
    $handle = fopen($backup_file, 'w');
    
    foreach ($tables as $table) {
        $result = $koneksi->query("SELECT * FROM $table");
        $num_fields = $result->field_count;
        
        fwrite($handle, "DROP TABLE IF EXISTS $table;\n");
        $row2 = $koneksi->query("SHOW CREATE TABLE $table")->fetch_row();
        fwrite($handle, $row2[1] . ";\n\n");
        
        while ($row = $result->fetch_row()) {
            fwrite($handle, "INSERT INTO $table VALUES(");
            for ($j=0; $j < $num_fields; $j++) {
                if (isset($row[$j])) {
                    fwrite($handle, "'" . $koneksi->real_escape_string($row[$j]) . "'");
                } else {
                    fwrite($handle, "NULL");
                }
                if ($j < ($num_fields-1)) {
                    fwrite($handle, ',');
                }
            }
            fwrite($handle, ");\n");
        }
        fwrite($handle, "\n\n");
    }
    
    fclose($handle);
    $pesan = "<div class='alert alert-success'>Backup database berhasil dibuat: $backup_file</div>";
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengaturan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="app-container">
        <nav class="sidebar">
            <div class="sidebar-header">
                <h3><i class="fas fa-wallet"></i> FinTrack</h3>
            </div>
            <ul class="sidebar-menu">
                <li><a href="index.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="laporan.php"><i class="fas fa-chart-line"></i> Laporan</a></li>
                <li class="active"><a href="pengaturan.php"><i class="fas fa-cog"></i> Pengaturan</a></li>
            </ul>
        </nav>

        <main class="content">
            <header class="content-header">
                <h1><i class="fas fa-cog"></i> Pengaturan</h1>
                <div class="user-info">
                    <span><?= date('d F Y') ?></span>
                </div>
            </header>

            <?= $pesan ?>

            <div class="row">
                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h2>Pengaturan Saldo</h2>
                        </div>
                        <div class="card-body">
                            <form action="pengaturan.php" method="post">
                                <div class="mb-3">
                                    <label for="saldo_awal" class="form-label">Saldo Awal</label>
                                    <div class="input-group">
                                        <span class="input-group-text">Rp</span>
                                        <input type="number" class="form-control" id="saldo_awal" name="saldo_awal" value="<?= $saldo_awal ?>" required>
                                    </div>
                                    <div class="form-text">Saldo awal akan digunakan untuk perhitungan saldo akhir.</div>
                                </div>
                                <button type="submit" name="update_saldo" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Simpan Perubahan
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h2>Backup Database</h2>
                        </div>
                        <div class="card-body">
                            <p>Buat backup database untuk menyimpan data keuangan Anda.</p>
                            <form action="pengaturan.php" method="post">
                                <button type="submit" name="backup_db" class="btn btn-success">
                                    <i class="fas fa-database"></i> Backup Database
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-12">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h2>Tema Aplikasi</h2>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label">Pilih Tema</label>
                                <div class="d-flex gap-3">
                                    <div class="theme-option active" data-theme="default">
                                        <div class="theme-preview" style="background: linear-gradient(to bottom, #4361ee, #3f37c9);"></div>
                                        <span>Default</span>
                                    </div>
                                    <div class="theme-option" data-theme="green">
                                        <div class="theme-preview" style="background: linear-gradient(to bottom, #2e7d32, #1b5e20);"></div>
                                        <span>Green</span>
                                    </div>
                                    <div class="theme-option" data-theme="purple">
                                        <div class="theme-preview" style="background: linear-gradient(to bottom, #7b1fa2, #4a148c);"></div>
                                        <span>Purple</span>
                                    </div>
                                    <div class="theme-option" data-theme="dark">
                                        <div class="theme-preview" style="background: linear-gradient(to bottom, #424242, #212121);"></div>
                                        <span>Dark</span>
                                    </div>
                                </div>
                            </div>
                            <button type="button" id="saveTheme" class="btn btn-primary">
                                <i class="fas fa-save"></i> Simpan Tema
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="script.js"></script>
    <script>
        // Tema aplikasi
        const themeOptions = document.querySelectorAll('.theme-option');
        let selectedTheme = localStorage.getItem('appTheme') || 'default';
        
        // Set tema aktif berdasarkan localStorage
        themeOptions.forEach(option => {
            if (option.dataset.theme === selectedTheme) {
                option.classList.add('active');
            } else {
                option.classList.remove('active');
            }
        });
        
        // Tambahkan event listener untuk opsi tema
        themeOptions.forEach(option => {
            option.addEventListener('click', () => {
                themeOptions.forEach(opt => opt.classList.remove('active'));
                option.classList.add('active');
                selectedTheme = option.dataset.theme;
            });
        });
        
        // Simpan tema
        document.getElementById('saveTheme').addEventListener('click', () => {
            localStorage.setItem('appTheme', selectedTheme);
            document.documentElement.setAttribute('data-theme', selectedTheme);
            alert('Tema berhasil disimpan!');
        });
        
        // Terapkan tema saat halaman dimuat
        document.documentElement.setAttribute('data-theme', selectedTheme);
    </script>
</body>
</html>
