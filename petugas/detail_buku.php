<?php
require_once '../config/database.php';
require_once '../includes/session.php';
require_once '../includes/book_detail_helper.php';

requirePetugas();
$conn = getConnection();

$bookId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

// Handle stock update
if (isset($_POST['update_stok']) && $bookId > 0) {
    $stokBaru = max(0, (int) ($_POST['stok'] ?? 0));
    $statusDb = $stokBaru > 0 ? 'tersedia' : 'tidak';

    $stmt = $conn->prepare("UPDATE buku SET stok = ?, status = ? WHERE id_buku = ?");
    if ($stmt) {
        $stmt->bind_param('isi', $stokBaru, $statusDb, $bookId);
        $stmt->execute();
        $stmt->close();
    }

    header('Location: detail_buku.php?id=' . $bookId . '&notif=stok_ok');
    exit;
}

$book = $bookId > 0 ? getBookDetailById($conn, $bookId) : null;
$history = $book ? getBookLoanHistory($conn, $bookId, 10) : [];
$activeBorrowers = $book ? getActiveBorrowers($conn, $bookId) : [];
$ratingInfo = $book ? getBookRatingInfo($conn, $bookId) : ['total_ulasan' => 0, 'avg_rating' => 0];

$statusKetersediaan = $book ? getReadableBookAvailability($book) : 'dipinjam';
$coverPath = (!empty($book['cover']) && file_exists('../' . $book['cover']))
    ? '../' . $book['cover']
    : '../uploads/covers/default-cover.png';

$notif = $_GET['notif'] ?? '';
$msg = '';
if ($notif === 'stok_ok') {
    $msg = 'Stok buku berhasil diperbarui.';
}

function fmtDateTimePetugas(?string $dateValue): string
{
    if (!$dateValue) {
        return '-';
    }
    $ts = strtotime($dateValue);
    return $ts ? date('d/m/Y H:i', $ts) : '-';
}

function mapStatusPetugas(string $status): array
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

function renderStarsPetugas(float $rating): string
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
$page_sub = 'Informasi buku untuk operasional petugas';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="../assets/icons/cozy-tp.png" type="image/png">
    <title>Detail Buku - Petugas Cozy-Library</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/petugas/petugas.css?v=<?= @filemtime('../assets/css/petugas/petugas.css') ?: time() ?>">
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
                        <i class="fas fa-arrow-left"></i> Kembali ke Buku
                    </a>
                </div>

                <?php if ($msg): ?>
                    <div class="bookd-notice bookd-notice-success">
                        <i class="fas fa-check-circle"></i>
                        <?= htmlspecialchars($msg) ?>
                    </div>
                <?php endif; ?>

                <?php if (!$book): ?>
                    <div class="bookd-card bookd-section">
                        <h3 class="bookd-section-title">Data buku tidak ditemukan</h3>
                        <p>ID buku tidak valid atau sudah dihapus.</p>
                    </div>
                <?php else: ?>

                    <!-- Layout Utama -->
                    <div class="bookd-layout">
                        <div class="bookd-card bookd-cover">
                            <?php if (!empty($coverPath) && file_exists($coverPath)): ?>
                                <img src="<?= htmlspecialchars($coverPath) ?>" alt="Cover <?= htmlspecialchars($book['judul_buku']) ?>">
                            <?php else: ?>
                                <div class="bookd-cover-fallback"><i class="fas fa-book"></i></div>
                            <?php endif; ?>

                            <?php if ($ratingInfo['total_ulasan'] > 0): ?>
                            <div style="margin-top: 16px; text-align: center;">
                                <?= renderStarsPetugas($ratingInfo['avg_rating']) ?>
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
                                    <i class="fas fa-box"></i> Stok: <?= number_format((int) $book['stok']) ?>
                                </span>
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

                            </div>

                            <div class="bookd-desc">
                                <?= htmlspecialchars($book['deskripsi'] ?: 'Tidak ada deskripsi buku.') ?>
                            </div>

                            <!-- Informasi Peminjam Aktif -->
                            <div class="bookd-borrower-card">
                                <div class="bookd-borrower-title">
                                    <i class="fas fa-user-clock"></i> Informasi Peminjam Aktif
                                </div>
                                <?php if (!empty($activeBorrowers)): ?>
                                    <?php foreach ($activeBorrowers as $borrower):
                                        $jatuhTempo = strtotime($borrower['tgl_kembali_rencana'] ?? '');
                                        $sisaHari = $jatuhTempo ? (int) ceil(($jatuhTempo - time()) / 86400) : 0;
                                    ?>
                                    <div class="bookd-borrower-grid" style="margin-bottom: 10px;">
                                        <div class="bookd-borrower-field">
                                            <div class="bookd-borrower-field-label">Nama Peminjam</div>
                                            <div class="bookd-borrower-field-value"><?= htmlspecialchars($borrower['nama_anggota'] ?: '-') ?></div>
                                        </div>
                                        <div class="bookd-borrower-field">
                                            <div class="bookd-borrower-field-label">Kelas / NIS</div>
                                            <div class="bookd-borrower-field-value"><?= htmlspecialchars(($borrower['kelas'] ?: '-') . ' / ' . ($borrower['nis'] ?: '-')) ?></div>
                                        </div>
                                        <div class="bookd-borrower-field">
                                            <div class="bookd-borrower-field-label">Tanggal Pinjam</div>
                                            <div class="bookd-borrower-field-value"><?= fmtDateTimePetugas($borrower['tgl_pinjam'] ?? null) ?></div>
                                        </div>
                                        <div class="bookd-borrower-field">
                                            <div class="bookd-borrower-field-label">Jatuh Tempo (<?= $sisaHari > 0 ? $sisaHari . ' hari lagi' : ($sisaHari == 0 ? 'Hari ini' : abs($sisaHari) . ' hari terlambat') ?>)</div>
                                            <div class="bookd-borrower-field-value"><?= fmtDateTimePetugas($borrower['tgl_kembali_rencana'] ?? null) ?></div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="bookd-borrower-empty">
                                        <i class="fas fa-inbox"></i> Buku ini tidak sedang dipinjam oleh siapa pun.
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Stok Update -->
                            <div class="bookd-actions">
                                <form method="POST" class="bookd-inline-form">
                                    <label for="stok">Update stok:</label>
                                    <input id="stok" type="number" name="stok" min="0" value="<?= (int) $book['stok'] ?>" required>
                                    <button type="submit" name="update_stok" class="bookd-btn bookd-btn-primary">
                                        <i class="fas fa-save"></i> Simpan Stok
                                    </button>
                                </form>
                            </div>

                            <!-- Quick Actions -->
                            <div class="bookd-quick-actions">
                                <a href="transaksi.php" class="bookd-quick-action">
                                    <i class="fas fa-handshake"></i>
                                    <span>Kelola Peminjaman</span>
                                </a>
                                <a href="transaksi.php" class="bookd-quick-action">
                                    <i class="fas fa-undo-alt"></i>
                                    <span>Kelola Pengembalian</span>
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Riwayat Peminjaman Ringkas -->
                    <div class="bookd-card bookd-section">
                        <h3 class="bookd-section-title">Ringkas Riwayat Peminjaman</h3>
                        <div class="bookd-table-wrap">
                            <table class="bookd-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Peminjam</th>
                                        <th>Pinjam</th>
                                        <th>Jatuh Tempo</th>
                                        <th>Kembali</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php if (!$history): ?>
                                    <tr>
                                        <td colspan="6">
                                            <div class="bookd-empty">
                                                <i class="fas fa-inbox"></i>
                                                <p>Belum ada riwayat peminjaman buku ini.</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($history as $item):
                                        $statusInfo = mapStatusPetugas((string) $item['status_transaksi']);
                                    ?>
                                        <tr>
                                            <td>#<?= (int) $item['id_transaksi'] ?></td>
                                            <td><?= htmlspecialchars($item['nama_anggota'] ?: '-') ?></td>
                                            <td><?= fmtDateTimePetugas($item['tgl_pinjam'] ?? null) ?></td>
                                            <td><?= fmtDateTimePetugas($item['tgl_kembali_rencana'] ?? null) ?></td>
                                            <td><?= fmtDateTimePetugas($item['tgl_kembali_aktual'] ?? null) ?></td>
                                            <td>
                                                <span class="status-badge <?= $statusInfo['class'] ?>">
                                                    <?= htmlspecialchars($statusInfo['label']) ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
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
<script src="../assets/js/script.js"></script>
</body>
</html>
