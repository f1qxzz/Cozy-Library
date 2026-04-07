<?php
require_once 'includes/session.php';

// Hancurkan semua session menggunakan fungsi logout() dari session.php
logout();

// Balik ke index
header("Location: index.php");
exit;
