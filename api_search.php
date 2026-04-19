<?php
/*
 * Alur logic PHP:
 * 1) Menerima parameter pencarian dari request.
 * 2) Menjalankan query terfilter secara aman.
 * 3) Mengembalikan hasil dalam format API (JSON).
 */require_once 'config/database.php';
header('Content-Type: application/json');
$q = trim($_GET['q'] ?? '');
if (strlen($q) < 2) { echo json_encode([]); exit; }
$conn = getConnection();
$safe = $conn->real_escape_string($q);
$res = $conn->query("SELECT b.id_buku, b.judul_buku, b.pengarang, b.status, b.cover, k.nama_kategori FROM buku b LEFT JOIN kategori k ON b.id_kategori=k.id_kategori WHERE b.judul_buku LIKE '%$safe%' OR b.pengarang LIKE '%$safe%' OR b.isbn LIKE '%$safe%' LIMIT 8");
$out = [];
if ($res) while ($r = $res->fetch_assoc()) $out[] = $r;
echo json_encode($out);
