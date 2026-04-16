<?php
require_once '../config/database.php';
require_once '../includes/session.php';
require_once '../includes/book_detail_helper.php';

requireAdmin();
$conn = getConnection();

$bookId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$book = $bookId > 0 ? getBookDetailById($conn, $bookId) : null;
$history = $book ? getBookLoanHistory($conn, $bookId, 20) : [];
$stats = getBookStatistics($conn);
$reviews = $book ? getBookReviews($conn, $bookId, 20) : [];
$ratingInfo = $book ? getBookRatingInfo($conn, $bookId) : ['total_ulasan' => 0, 'avg_rating' => 0];
$borrowCount = $book ? getBookBorrowCount($conn, $bookId) : 0;

$statusKetersediaan = $book ? getReadableBookAvailability($book) : 'dipinjam';
$coverPath = (!empty($book['cover']) && file_exists('../' . $book['cover']))
    ? '../' . $book['cover']
    : '../uploads/covers/default-cover.png';

function fmtDateTime(?string $dateValue): string
{
    if (!$dateValue) {
        return '-';
    }
    $ts = strtotime($dateValue);
    return $ts ? date('d/m/Y H:i', $ts) : '-';
}

function mapTransaksiStatus(string $status): array
{
    $map = [
        'Pending'       => ['label' => 'Pending',      'class' => 'status-pending'],
        'Peminjaman'    => ['label' => 'Dipinjam',      'class' => 'status-dipinjam'],
        'Dipinjam'      => ['label' => 'Dipinjam',      'class' => 'status-dipinjam'],
        'Pengembalian'  => ['label' => 'Dikembalikan',  'class' => 'status-dikembalikan'],
        'Dikembalikan'  => ['label' => 'Dikembalikan',  'class' => 'status-dikembalikan'],
        'Ditolak'       => ['label' => 'Ditolak',       'class' => 'status-ditolak'],
    ];
    return $map[$status] ?? ['label' => $status, 'class' => 'status-pending'];
}

function renderStars(float $rating): string
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
$page_sub = 'Informasi lengkap buku dan riwayat peminjaman';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="../assets/icons/cozy-tp.png" type="image/png">
    <title>Detail Buku - Admin Cozy-Library</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin/dashboard.css?v=<?= @filemtime('../assets/css/admin/dashboard.css') ?: time() ?>">
    <link rel="stylesheet" href="../assets/css/shared/book_detail.css?v=<?= @filemtime('../assets/css/shared/book_detail.css') ?: time() ?>">
    <link rel="stylesheet" href="../assets/css/responsive-fix.css?v=<?= @filemtime('../assets/css/responsive-fix.css') ?: time() ?>">
</head>
<body>
<div class="app-wrap">
    <?php include 'includes/nav.php'; ?>

    <div class="main-area">
        <?php include 'includes/header.php'; ?>

        <main class="content">
            <div class="bookd-wrap">
                <div class="bookd-topbar">
                    <a class="bookd-back" href="buku.php">
                        <i class="fas fa-arrow-left"></i> Kembali ke Manajemen Buku
                    </a>
                </div>

                <?php if (!$book): ?>
                    <div class="bookd-card bookd-section">
                        <h3 class="bookd-section-title">Data buku tidak ditemukan</h3>
                        <p>ID buku tidak valid atau sudah dihapus.</p>
                    </div>
                <?php else: ?>

                    <!-- Statistik Koleksi -->
                    <div class="bookd-card bookd-section">
                        <h3 class="bookd-section-title">Statistik Koleksi Buku</h3>
                        <div class="bookd-stats">
                            <div class="bookd-stat">
                                <div class="bookd-stat-label">Total Judul</div>
                                <div class="bookd-stat-value"><?= number_format($stats['total_judul']) ?></div>
                            </div>
                            <div class="bookd-stat">
                                <div class="bookd-stat-label">Total Stok</div>
                                <div class="bookd-stat-value"><?= number_format($stats['total_stok']) ?></div>
                            </div>
                            <div class="bookd-stat">
                                <div class="bookd-stat-label">Judul Tersedia</div>
                                <div class="bookd-stat-value"><?= number_format($stats['judul_tersedia']) ?></div>
                            </div>
                            <div class="bookd-stat">
                                <div class="bookd-stat-label">Total Peminjaman</div>
                                <div class="bookd-stat-value"><?= number_format($borrowCount) ?></div>
                            </div>
                        </div>
                    </div>

                    <!-- Layout Utama: Cover + Detail -->
                    <div class="bookd-layout">
                        <div class="bookd-card bookd-cover">
                            <?php if (!empty($coverPath) && file_exists($coverPath)): ?>
                                <img src="<?= htmlspecialchars($coverPath) ?>" alt="Cover <?= htmlspecialchars($book['judul_buku']) ?>">
                            <?php else: ?>
                                <div class="bookd-cover-fallback"><i class="fas fa-book"></i></div>
                            <?php endif; ?>

                            <!-- Rating -->
                            <?php if ($ratingInfo['total_ulasan'] > 0): ?>
                            <div style="margin-top: 16px; text-align: center;">
                                <?= renderStars($ratingInfo['avg_rating']) ?>
                                <span class="bookd-rating-text"><?= $ratingInfo['avg_rating'] ?></span>
                                <div class="bookd-rating-count"><?= $ratingInfo['total_ulasan'] ?> ulasan</div>
                            </div>
                            <?php endif; ?>
                        </div>

                        <div class="bookd-card bookd-main">
                            <h2 class="bookd-title"><?= htmlspecialchars($book['judul_buku']) ?></h2>
                            <div class="bookd-subline">ID Buku: #<?= (int) $book['id_buku'] ?></div>

                            <div class="bookd-badges">
                                <span class="bookd-badge <?= $statusKetersediaan === 'tersedia' ? 'bookd-available' : 'bookd-borrowed' ?>">
                                    <i class="fas <?= $statusKetersediaan === 'tersedia' ? 'fa-check-circle' : 'fa-book-reader' ?>"></i>
                                    <?= $statusKetersediaan === 'tersedia' ? 'Tersedia' : 'Dipinjam' ?>
                                </span>
                                <span class="bookd-badge bookd-muted">
                                    <i class="fas fa-history"></i>
                                    Riwayat: <?= (int) $book['total_riwayat_peminjaman'] ?>
                                </span>
                                <?php if ($ratingInfo['total_ulasan'] > 0): ?>
                                <span class="bookd-badge bookd-badge-info">
                                    <i class="fas fa-star"></i>
                                    <?= $ratingInfo['avg_rating'] ?> / 5
                                </span>
                                <?php endif; ?>
                            </div>

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
                                    <div class="bookd-meta-label">ISBN</div>
                                    <div class="bookd-meta-value"><?= htmlspecialchars($book['isbn'] ?: '-') ?></div>
                                </div>
                                <div class="bookd-meta-item">
                                    <div class="bookd-meta-label">Kategori</div>
                                    <div class="bookd-meta-value"><?= htmlspecialchars($book['nama_kategori'] ?: '-') ?></div>
                                </div>
                                <div class="bookd-meta-item">
                                    <div class="bookd-meta-label">Stok</div>
                                    <div class="bookd-meta-value"><?= number_format((int) $book['stok']) ?></div>
                                    <?php
                                        $maxStok = max((int) $book['stok'], 10);
                                        $pct = min(100, ((int) $book['stok'] / $maxStok) * 100);
                                        $barClass = $pct > 50 ? '' : ($pct > 0 ? 'low' : 'empty');
                                    ?>
                                    <div class="bookd-stock-bar">
                                        <div class="bookd-stock-track">
                                            <div class="bookd-stock-fill <?= $barClass ?>" style="width: <?= $pct ?>%"></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="bookd-meta-item">
                                    <div class="bookd-meta-label">Lokasi Rak</div>
                                    <div class="bookd-meta-value"><?= htmlspecialchars($book['lokasi_rak'] ?: '-') ?></div>
                                </div>
                                <div class="bookd-meta-item">
                                    <div class="bookd-meta-label">Dibuat Pada</div>
                                    <div class="bookd-meta-value"><?= fmtDateTime($book['created_at'] ?? null) ?></div>
                                </div>
                            </div>

                            <div class="bookd-desc">
                                <?= htmlspecialchars($book['deskripsi'] ?: 'Tidak ada deskripsi buku.') ?>
                            </div>

                            <div class="bookd-actions">
                                <a href="buku.php?edit=<?= (int) $book['id_buku'] ?>" class="bookd-btn bookd-btn-primary">
                                    <i class="fas fa-edit"></i> Edit Buku
                                </a>
                                <form method="POST" action="buku.php" onsubmit="return confirm('Yakin ingin menghapus buku ini?')">
                                    <input type="hidden" name="id_buku" value="<?= (int) $book['id_buku'] ?>">
                                    <button type="submit" name="delete" class="bookd-btn bookd-btn-danger">
                                        <i class="fas fa-trash"></i> Hapus Buku
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Tabs: Riwayat + Ulasan -->
                    <div class="bookd-card bookd-section">
                        <div class="bookd-tabs">
                            <button class="bookd-tab active" onclick="switchTab(event, 'tab-history')">
                                <i class="fas fa-history"></i>&nbsp; Riwayat Peminjaman
                            </button>
                            <button class="bookd-tab" onclick="switchTab(event, 'tab-reviews')">
                                <i class="fas fa-star"></i>&nbsp; Ulasan (<?= $ratingInfo['total_ulasan'] ?>)
                            </button>
                        </div>

                        <!-- Tab: Riwayat -->
                        <div id="tab-history" class="bookd-tab-content active">
                            <div class="bookd-table-wrap">
                                <table class="bookd-table">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Nama Peminjam</th>
                                            <th>Tgl Pinjam</th>
                                            <th>Jatuh Tempo</th>
                                            <th>Tgl Kembali</th>
                                            <th>Status</th>
                                            <th>Diproses Oleh</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php if (!$history): ?>
                                        <tr>
                                            <td colspan="7">
                                                <div class="bookd-empty">
                                                    <i class="fas fa-inbox"></i>
                                                    <p>Belum ada riwayat peminjaman untuk buku ini.</p>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($history as $item):
                                            $statusInfo = mapTransaksiStatus((string) $item['status_transaksi']);
                                        ?>
                                            <tr>
                                                <td>#<?= (int) $item['id_transaksi'] ?></td>
                                                <td><?= htmlspecialchars($item['nama_anggota'] ?: '-') ?></td>
                                                <td><?= fmtDateTime($item['tgl_pinjam'] ?? null) ?></td>
                                                <td><?= fmtDateTime($item['tgl_kembali_rencana'] ?? null) ?></td>
                                                <td><?= fmtDateTime($item['tgl_kembali_aktual'] ?? null) ?></td>
                                                <td>
                                                    <span class="status-badge <?= $statusInfo['class'] ?>">
                                                        <?= htmlspecialchars($statusInfo['label']) ?>
                                                    </span>
                                                </td>
                                                <td><?= htmlspecialchars($item['nama_petugas'] ?: '-') ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Tab: Ulasan -->
                        <div id="tab-reviews" class="bookd-tab-content">
                            <?php if (!$reviews): ?>
                                <div class="bookd-empty">
                                    <i class="fas fa-comment-slash"></i>
                                    <p>Belum ada ulasan untuk buku ini.</p>
                                </div>
                            <?php else: ?>
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
                                                    <?= renderStars((float) $review['rating']) ?>
                                                    &nbsp;·&nbsp; <?= fmtDateTime($review['created_at'] ?? null) ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="bookd-review-text"><?= htmlspecialchars($review['ulasan']) ?></div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                <?php endif; ?>
            </div>
        </main>
    </div>
</div>

<script>
function switchTab(e, tabId) {
    document.querySelectorAll('.bookd-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.bookd-tab-content').forEach(c => c.classList.remove('active'));
    e.currentTarget.classList.add('active');
    document.getElementById(tabId).classList.add('active');
}
if (window.history.replaceState) {
    window.history.replaceState(null, null, window.location.href);
}
</script>
<script src="../assets/js/script.js"></script>
</body>
</html>
