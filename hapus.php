<?php
require __DIR__ . '/auth.php';
csrf_check();

$kembali = $_POST['kembali'] ?? 'index.php';
if (!in_array($kembali, ['index.php', 'laporan.php'], true)) {
    $kembali = 'index.php';
}

// Kumpulkan ID: bisa satu (id) atau banyak (ids[])
$ids = [];
if (isset($_POST['ids']) && is_array($_POST['ids'])) {
    foreach ($_POST['ids'] as $v) {
        $v = (int) $v;
        if ($v > 0) {
            $ids[] = $v;
        }
    }
}
if (isset($_POST['id']) && (int) $_POST['id'] > 0) {
    $ids[] = (int) $_POST['id'];
}
$ids = array_values(array_unique($ids));

$dihapus = 0;
if ($ids) {
    $placeholder = implode(',', array_fill(0, count($ids), '?'));
    $param = $ids;
    $param[] = $UID;
    $stmt = $koneksi->prepare("DELETE FROM transaksi WHERE id IN ($placeholder) AND user_id = ?");
    $stmt->bind_param(str_repeat('i', count($param)), ...$param);
    $stmt->execute();
    $dihapus = $stmt->affected_rows;
    $stmt->close();
}

if ($dihapus > 0) {
    set_flash("$dihapus transaksi berhasil dihapus.");
} else {
    set_flash('Tidak ada transaksi yang dihapus.', 'warning');
}

header('Location: ' . $kembali);
exit;
