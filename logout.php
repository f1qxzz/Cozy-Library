<?php
/*
 * Alur logic PHP:
 * 1) Menghapus session pengguna aktif.
 * 2) Membersihkan state login agar akses lama tidak tersisa.
 * 3) Mengarahkan user kembali ke halaman login/utama.
 */require_once 'includes/session.php';

// Hancurkan semua session menggunakan fungsi logout() dari session.php
logout();

// Balik ke index
header("Location: index.php");
exit;
