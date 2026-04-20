<?php
/*
 * Alur logic PHP:
 * 1) Memuat dependency utama (database, session, dan helper).
 * 2) Validasi hak akses sebelum memproses data sensitif.
 * 3) Proses input GET/POST, jalankan query, lalu siapkan data view.
 * 4) Render output halaman sesuai role dan konteks fitur.
 */require_once '../config/database.php';
require_once '../includes/session.php';
require_once '../includes/book_detail_helper.php';

requireAnggota();
$conn = getConnection();
$anggotaId = getAnggotaId();

$bookId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$msg = '';
$msgType = '';

$book = $bookId > 0 ? getBookDetailById($conn, $bookId) : null;
$statusKetersediaan = $book ? getReadableBookAvailability($book) : 'dipinjam';
$reviews = $book ? getBookReviews($conn, $bookId, 10) : [];
$ratingInfo = $book ? getBookRatingInfo($conn, $bookId) : ['total_ulasan' => 0, 'avg_rating' => 0];

$coverPath = (!empty($book['cover']) && file_exists('../' . $book['cover']))
    ? '../' . $book['cover']
    : '../uploads/covers/default-cover.png';

$alreadyBorrowing = false;
if ($book) {
    $check = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM transaksi
        WHERE id_anggota = ?
          AND id_buku = ?
          AND status_transaksi IN ('Pending', 'Peminjaman', 'Dipinjam')
    ");
    if ($check) {
        $check->bind_param('ii', $anggotaId, $bookId);
        $check->execute();
        $alreadyBorrowing = ((int) ($check->get_result()->fetch_assoc()['total'] ?? 0)) > 0;
        $check->close();
    }
}

if (isset($_POST['pinjam']) && $book) {
    if (!canBorrowBook($book)) {
        $msg = 'Stok buku habis. Peminjaman tidak dapat diproses.';
        $msgType = 'warning';
    } elseif ($alreadyBorrowing) {
        $msg = 'Kamu sudah memiliki pinjaman/permintaan aktif untuk buku ini.';
        $msgType = 'info';
    } else {
        $tglPinjam = date('Y-m-d H:i:s');
        $tglRencana = date('Y-m-d H:i:s', strtotime('+7 days'));

        $insert = $conn->prepare("
            INSERT INTO transaksi (id_anggota, id_buku, tgl_pinjam, tgl_kembali_rencana, status_transaksi)
            VALUES (?, ?, ?, ?, 'Pending')
        ");
        if ($insert) {
            $insert->bind_param('iiss', $anggotaId, $bookId, $tglPinjam, $tglRencana);
            if ($insert->execute()) {
                $insert->close();
                header('Location: detail_buku.php?id=' . $bookId . '&notif=pinjam_ok');
                exit;
            }
            $insert->close();
        }

        $msg = 'Terjadi kesalahan saat mengirim permintaan peminjaman.';
        $msgType = 'warning';
    }
}

if (isset($_GET['notif']) && $_GET['notif'] === 'pinjam_ok') {
    $msg = 'Permintaan peminjaman berhasil dikirim. Menunggu persetujuan petugas/admin.';
    $msgType = 'success';
}

function fmtDateTimeAnggota(?string $dateValue): string
{
    if (!$dateValue) {
        return '-';
    }
    $ts = strtotime($dateValue);
    return $ts ? date('d/m/Y H:i', $ts) : '-';
}

function renderStarsAnggota(float $rating): string
{
    $html = '<span class="bookd-stars">';
    for ($i = 1; $i <= 5; $i++) {
        if ($rating >= $i) {
            $html .= '<i class="fas fa-star star-filled"></i>';
        } elseif ($rating >= $i - 0.5) {
            $html .= '<i class="fas fa-star-half-alt star-half"></i>';
        } else {
            $html .= '<i class="far fa-star star-empty"></i>';
        }
    }
    $html .= '</span>';
    return $html;
}

$page_title = 'Detail Buku';
$page_sub = 'Informasi buku dan pengajuan peminjaman';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="../assets/icons/cozy-tp.png" type="image/png">
    <title>Detail Buku - Anggota Cozy-Library</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/anggota/dashboard.css?v=<?= @filemtime('../assets/css/anggota/dashboard.css') ?: time() ?>">
    <link rel="stylesheet" href="../assets/css/shared/book_detail.css?v=<?= @filemtime('../assets/css/shared/book_detail.css') ?: time() ?>">
    <link rel="stylesheet" href="../assets/css/responsive-fix.css?v=<?= @filemtime('../assets/css/responsive-fix.css') ?: time() ?>">
</head>
<body class="bookd-page">
<div class="app-wrap">
    <?php include 'includes/nav.php'; ?>

    <div class="main-area">
        <?php include 'includes/header.php'; ?>

        <main class="content">
            <div class="bookd-wrap">
                <div class="bookd-topbar">
                    <a class="bookd-back" href="katalog.php">
                        <i class="fas fa-arrow-left"></i> Kembali ke Katalog
                    </a>
                </div>

                <?php if ($msg): ?>
                    <div class="bookd-notice <?= $msgType === 'success' ? 'bookd-notice-success' : ($msgType === 'info' ? 'bookd-notice-info' : 'bookd-notice-warning') ?> <?= $msgType === 'warning' ? 'bookd-notice-shake' : '' ?>">
                        <i class="fas <?= $msgType === 'success' ? 'fa-check-circle' : ($msgType === 'info' ? 'fa-info-circle' : 'fa-exclamation-triangle') ?>"></i>
                        <?= htmlspecialchars($msg) ?>
                    </div>
                <?php endif; ?>

                <?php if (!$book): ?>
                    <div class="bookd-card bookd-section">
                        <h3 class="bookd-section-title">Buku tidak ditemukan</h3>
                        <p>Data buku tidak tersedia atau sudah dihapus dari katalog.</p>
                    </div>
                <?php else: ?>

                    <!-- Layout Utama: Cover + Detail -->
                    <div class="bookd-layout">
                        <div class="bookd-card bookd-cover">
                            <?php if (!empty($coverPath) && file_exists($coverPath)): ?>
                                <img src="<?= htmlspecialchars($coverPath) ?>" alt="Cover <?= htmlspecialchars($book['judul_buku']) ?>">
                            <?php else: ?>
                                <div class="bookd-cover-fallback"><i class="fas fa-book"></i></div>
                            <?php endif; ?>

                            <?php if ($ratingInfo['total_ulasan'] > 0): ?>
                            <div style="margin-top: 16px; text-align: center;">
                                <?= renderStarsAnggota($ratingInfo['avg_rating']) ?>
                                <span class="bookd-rating-text"><?= $ratingInfo['avg_rating'] ?></span>
                                <div class="bookd-rating-count"><?= $ratingInfo['total_ulasan'] ?> ulasan</div>
                            </div>
                            <?php endif; ?>
                        </div>

                        <div class="bookd-card bookd-main">
                            <h2 class="bookd-title"><?= htmlspecialchars($book['judul_buku']) ?></h2>
                            <div class="bookd-subline"><?= htmlspecialchars($book['pengarang'] ?: '-') ?></div>

                            <div class="bookd-badges">
                                <span class="bookd-badge <?= $statusKetersediaan === 'tersedia' ? 'bookd-available' : 'bookd-borrowed' ?>">
                                    <i class="fas <?= $statusKetersediaan === 'tersedia' ? 'fa-check-circle' : 'fa-book-reader' ?>"></i>
                                    <?= $statusKetersediaan === 'tersedia' ? 'Tersedia' : 'Tidak Tersedia' ?>
                                </span>
                                <?php if ($ratingInfo['total_ulasan'] > 0): ?>
                                <span class="bookd-badge bookd-badge-info">
                                    <i class="fas fa-star"></i>
                                    <?= $ratingInfo['avg_rating'] ?> / 5
                                </span>
                                <?php endif; ?>
                            </div>

                            <!-- Data Anggota: Judul, Penulis, Penerbit, Tahun, Kategori, Deskripsi, Cover, Status -->
                            <div class="bookd-meta-grid">
                                <div class="bookd-meta-item">
                                    <div class="bookd-meta-label">Penulis</div>
                                    <div class="bookd-meta-value"><?= htmlspecialchars($book['pengarang'] ?: '-') ?></div>
                                </div>
                                <div class="bookd-meta-item">
                                    <div class="bookd-meta-label">Penerbit</div>
                                    <div class="bookd-meta-value"><?= htmlspecialchars($book['penerbit'] ?: '-') ?></div>
                                </div>
                                <div class="bookd-meta-item">
                                    <div class="bookd-meta-label">Tahun Terbit</div>
                                    <div class="bookd-meta-value"><?= htmlspecialchars((string) ($book['tahun_terbit'] ?: '-')) ?></div>
                                </div>
                                <div class="bookd-meta-item">
                                    <div class="bookd-meta-label">Kategori</div>
                                    <div class="bookd-meta-value"><?= htmlspecialchars($book['nama_kategori'] ?: '-') ?></div>
                                </div>
                            </div>

                            <div class="bookd-desc">
                                <?= htmlspecialchars($book['deskripsi'] ?: 'Tidak ada deskripsi buku.') ?>
                            </div>

                            <!-- Notifikasi Stok Habis -->
                            <?php if (!canBorrowBook($book)): ?>
                                <div class="bookd-notice bookd-notice-warning bookd-notice-shake">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    <div>
                                        <strong>Stok Habis!</strong><br>
                                        Buku ini sedang tidak tersedia. Silakan pilih buku lain atau tunggu hingga stok tersedia kembali.
                                    </div>
                                </div>
                            <?php elseif ($alreadyBorrowing): ?>
                                <div class="bookd-notice bookd-notice-info">
                                    <i class="fas fa-info-circle"></i>
                                    <div>
                                        <strong>Sudah Dipinjam</strong><br>
                                        Kamu sudah memiliki permintaan atau pinjaman aktif untuk buku ini.
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Tombol Aksi -->
                            <div class="bookd-actions">
                                <?php if (canBorrowBook($book) && !$alreadyBorrowing): ?>
                                    <form method="POST" onsubmit="return confirm('Ajukan peminjaman buku ini?')">
                                        <button type="submit" name="pinjam" class="bookd-btn bookd-btn-success" id="btn-pinjam">
                                            <i class="fas fa-book"></i> Pinjam Buku
                                        </button>
                                    </form>
                                <?php endif; ?>
                                <a href="katalog.php" class="bookd-btn bookd-btn-soft">
                                    <i class="fas fa-search"></i> Jelajahi Katalog
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Ulasan Buku -->
                    <?php if (!empty($reviews)): ?>
                    <div class="bookd-card bookd-section">
                        <h3 class="bookd-section-title">Ulasan Pembaca</h3>
                        <div class="bookd-reviews">
                            <?php foreach ($reviews as $review):
                                $initial = strtoupper(mb_substr($review['nama_anggota'] ?? 'A', 0, 1));
                            ?>
                            <div class="bookd-review-item">
                                <div class="bookd-review-header">
                                    <?php if (!empty($review['foto']) && file_exists('../' . $review['foto'])): ?>
                                        <img class="bookd-review-avatar" src="../<?= htmlspecialchars($review['foto']) ?>" alt="">
                                    <?php else: ?>
                                        <div class="bookd-review-avatar-placeholder"><?= $initial ?></div>
                                    <?php endif; ?>
                                    <div>
                                        <div class="bookd-review-name"><?= htmlspecialchars($review['nama_anggota'] ?: 'Anonim') ?></div>
                                        <div class="bookd-review-date">
                                            <?= renderStarsAnggota((float) $review['rating']) ?>
                                            &nbsp;·&nbsp; <?= fmtDateTimeAnggota($review['created_at'] ?? null) ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="bookd-review-text"><?= htmlspecialchars($review['ulasan']) ?></div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                <?php endif; ?>
            </div>
        </main>
    </div>
</div>

<script>
if (window.history.replaceState) {
    window.history.replaceState(null, null, window.location.href);
}
</script>
<script src="../assets/js/script.js?v=<?= @filemtime('../assets/js/script.js') ?: time() ?>"></script>
</body>
</html>
