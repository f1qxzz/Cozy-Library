<?php
/**
 * ============================================================
 *  anggota/katalog.php  —  Katalog Buku dengan Tampilan Cover
 * ============================================================
 */
require_once '../config/database.php';
require_once '../includes/session.php';
require_once '../includes/upload_helper.php';
requireAnggota();
$conn = getConnection();

// Ambil data user untuk header
$userId = getAnggotaId();
$userStmt = $conn->prepare("SELECT foto, nama_anggota FROM anggota WHERE id_anggota = ?");
$userStmt->bind_param("i", $userId);
$userStmt->execute();
$userData = $userStmt->get_result()->fetch_assoc();
$userStmt->close();

// Inisial untuk avatar
$initials = '';
foreach (explode(' ', trim($userData['nama_anggota'] ?? getAnggotaName())) as $w) {
    $initials .= strtoupper(mb_substr($w, 0, 1));
    if (strlen($initials) >= 2) break;
}
$fotoPath = (!empty($userData['foto']) && file_exists('../' . $userData['foto'])) 
            ? '../' . htmlspecialchars($userData['foto']) 
            : null;

$search = $_GET['search'] ?? '';
$kat    = isset($_GET['kat']) ? (int)$_GET['kat'] : 0;
$cats   = $conn->query("SELECT * FROM kategori ORDER BY nama_kategori");

$q = "SELECT b.*, k.nama_kategori
      FROM buku b
      LEFT JOIN kategori k ON b.id_kategori = k.id_kategori
      WHERE 1=1";
if ($search) {
    $search = $conn->real_escape_string($search);
    $q .= " AND (b.judul_buku LIKE '%$search%' OR b.pengarang LIKE '%$search%')";
}
if ($kat)    $q .= " AND b.id_kategori = $kat";
$q .= " ORDER BY b.judul_buku";
$books = $conn->query($q);
$book_emojis = ['📗','📘','📕','📙','📓','📔','📒'];

$page_title = 'Katalog Buku';
$page_sub   = 'Jelajahi koleksi Cozy-Library';
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Katalog — Cozy-Library</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/anggota/dashboard.css?v=<?= @filemtime('../assets/css/anggota/dashboard.css') ?: time() ?>">
    <link rel="stylesheet" href="../assets/css/anggota/katalog.css?v=<?= @filemtime('../assets/css/anggota/katalog.css') ?: time() ?>">
    <link rel="stylesheet" href="../assets/css/responsive-fix.css?v=<?= @filemtime('../assets/css/responsive-fix.css') ?: time() ?>">
</head>

<body>

<div class="app-wrap">
    <?php include 'includes/nav.php'; ?>

    <div class="main-area">
        <?php include 'includes/header.php'; ?>
            <!-- CONTENT -->
            <main class="content">
                <div class="page-header">
                    <div>
                        <h1 class="page-header-title">Katalog Buku</h1>
                        <p class="page-header-sub">Temukan buku yang ingin kamu baca</p>
                    </div>
                    <a href="pinjam.php" class="btn-sage">
                        <i class="fas fa-plus-circle"></i>
                        Pinjam Buku
                    </a>
                </div>

                <!-- Filter -->
                <form method="GET" class="filter-bar">
                    <div class="search-wrap">
                        <i class="fas fa-search"></i>
                        <input type="text" name="search" placeholder="Cari judul buku atau pengarang…"
                            value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <select name="kat" class="form-control">
                        <option value="">Semua Kategori</option>
                        <?php 
                        $cats->data_seek(0);
                        while($c=$cats->fetch_assoc()): 
                        ?>
                        <option value="<?= $c['id_kategori'] ?>" <?= $kat==$c['id_kategori']?'selected':'' ?>>
                            <?= htmlspecialchars($c['nama_kategori']) ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                    <button type="submit" class="btn-sage">Cari</button>
                    <?php if ($search||$kat): ?>
                    <a href="katalog.php" class="btn-ghost">Reset</a>
                    <?php endif; ?>
                </form>

                <?php if ($books && $books->num_rows > 0): ?>
                <div class="book-grid">
                    <?php $i = 0; while($b = $books->fetch_assoc()): $i++; ?>
                    <div class="book-card">
                        <div class="book-cover">
                            <?php if (!empty($b['cover'])): ?>
                            <img src="../<?= htmlspecialchars($b['cover']) ?>"
                                alt="<?= htmlspecialchars($b['judul_buku']) ?>" class="book-cover-img">
                            <?php else: ?>
                            <div class="book-cover-inner">
                                <?= $book_emojis[$i % count($book_emojis)] ?>
                            </div>
                            <?php endif; ?>
                        </div>

                        <div class="book-info">
                            <div class="book-title"><?= htmlspecialchars($b['judul_buku']) ?></div>
                            <div class="book-author"><?= htmlspecialchars($b['pengarang']) ?></div>
                            <?php if ($b['nama_kategori']): ?>
                            <span class="badge badge-muted">
                                <i class="fas fa-tag"></i>
                                <?= htmlspecialchars($b['nama_kategori']) ?>
                            </span>
                            <?php endif; ?>
                            <div class="book-footer">
                                <span
                                    class="badge <?= $b['status']==='tersedia' ? 'status-tersedia' : 'status-terlambat' ?>">
                                    <i
                                        class="fas <?= $b['status']==='tersedia' ? 'fa-check-circle' : 'fa-times-circle' ?>"></i>
                                    <?= $b['status']==='tersedia' ? 'Tersedia' : 'Habis' ?>
                                </span>
                                <?php if ($b['status']==='tersedia'): ?>
                                <a href="pinjam.php?buku=<?= $b['id_buku'] ?>" class="btn-sage btn-sm">
                                    <i class="fas fa-book"></i> Pinjam
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
                <?php else: ?>
                <div class="card">
                    <div class="empty-state">
                        <div class="empty-state-ico">🔍</div>
                        <div class="empty-state-title">Buku tidak ditemukan</div>
                        <div class="empty-state-sub">Coba kata kunci yang berbeda atau lihat semua kategori.</div>
                    </div>
                </div>
                <?php endif; ?>

            </main>
        </div>
    </div>
    <script src="../assets/js/script.js"></script>
</body>

</html>