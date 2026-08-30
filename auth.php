<?php
/**
 * Sertakan di halaman yang butuh login.
 * Menyediakan: $koneksi, $USER (array data user), $UID (int id user).
 */
require_once __DIR__ . '/koneksi.php';

$USER = wajib_login($koneksi);
$UID  = (int) $USER['id'];
