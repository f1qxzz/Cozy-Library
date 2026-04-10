<?php
require_once '../config/database.php';
require_once '../includes/session.php';
requireAnggota();

$conn = getConnection();
$id = getAnggotaId();

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

$trans = $conn->query("SELECT t.*, b.judul_buku, b.pengarang, b.penerbit, b.cover, d.total_denda, d.status_bayar 
                       FROM transaksi t 
                       JOIN buku b ON t.id_buku = b.id_buku 
                       LEFT JOIN denda d ON t.id_transaksi = d.id_transaksi 
                       WHERE t.id_anggota = $id 
                       ORDER BY t.tgl_pinjam DESC");

// Hitung statistik
$totalPinjam = $trans->num_rows;
$totalDenda = 0;
$trans->data_seek(0);
while($r = $trans->fetch_assoc()) {
    if ($r['total_denda'] > 0 && $r['status_bayar'] === 'belum') {
        $totalDenda += $r['total_denda'];
    }
}
$trans->data_seek(0);

$page_title = 'Riwayat Peminjaman';
$page_sub   = 'Lihat semua aktivitas peminjaman Anda';
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="../assets/icons/cozy-tp.png" type="image/png">
    <title>Riwayat — Cozy-Library</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/anggota/dashboard.css?v=<?= @filemtime('../assets/css/anggota/dashboard.css') ?: time() ?>">
    <link rel="stylesheet" href="../assets/css/anggota/riwayat.css?v=<?= @filemtime('../assets/css/anggota/riwayat.css') ?: time() ?>">
    <link rel="stylesheet" href="../assets/css/responsive-fix.css?v=<?= @filemtime('../assets/css/responsive-fix.css') ?: time() ?>">
<link rel="stylesheet" href="../assets/css/print.css?v=<?= @filemtime('../assets/css/print.css') ?: time() ?>">
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
                        <h1 class="page-header-title">Riwayat Peminjaman</h1>
                        <p class="page-header-sub">Lihat semua aktivitas peminjaman Anda</p>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-book"></i></div>
                        <div class="stat-info">
                            <h3>Total Pinjaman</h3>
                            <div class="stat-number"><?= $totalPinjam ?></div>
                            <div class="stat-sub">transaksi</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-coins"></i></div>
                        <div class="stat-info">
                            <h3>Total Denda</h3>
                            <div class="stat-number">Rp <?= number_format($totalDenda, 0, ',', '.') ?></div>
                            <div class="stat-sub">belum dibayar</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-star"></i></div>
                        <div class="stat-info">
                            <h3>Ulasan</h3>
                            <div class="stat-number">0</div>
                            <div class="stat-sub">yang ditulis</div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h2><i class="fas fa-history"></i> Daftar Riwayat Peminjaman</h2>
                        <span class="badge-total">
                            <i class="fas fa-list"></i> <?= $totalPinjam ?> transaksi
                        </span>
                    </div>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Cover</th>
                                    <th>Buku</th>
                                    <th>Tgl Pinjam</th>
                                    <th>Jatuh Tempo</th>
                                    <th>Tgl Kembali</th>
                                    <th>Status</th>
                                    <th>Denda</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($trans && $trans->num_rows > 0): $no = 1; while($r = $trans->fetch_assoc()): 
                                    $late = strtotime($r['tgl_kembali_rencana']) < time() && $r['status_transaksi'] === 'Dipinjam';
                                ?>
                                <tr>
                                    <td class="book-cover-cell">
                                        <?php if (!empty($r['cover']) && file_exists('../' . $r['cover'])): ?>
                                        <img src="../<?= htmlspecialchars($r['cover']) ?>" alt="Cover"
                                            class="book-cover-img">
                                        <?php else: ?>
                                        <div class="book-cover-placeholder">
                                            <i class="fas fa-book"></i>
                                        </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="fw-600"><?= htmlspecialchars($r['judul_buku']) ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($r['pengarang']) ?></small>
                                    </td>
                                    <td><?= date('d/m/Y', strtotime($r['tgl_pinjam'])) ?></td>
                                    <td><?= date('d/m/Y', strtotime($r['tgl_kembali_rencana'])) ?></td>
                                    <td>
                                        <?php if ($r['tgl_kembali_aktual']): ?>
                                        <?= date('d/m/Y', strtotime($r['tgl_kembali_aktual'])) ?>
                                        <?php else: ?>
                                        <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($r['status_transaksi'] === 'Dikembalikan'): ?>
                                        <span class="badge badge-success">
                                            <i class="fas fa-check-circle"></i> Dikembalikan
                                        </span>
                                        <?php elseif ($r['status_transaksi'] === 'Pending'): ?>
                                        <span class="badge badge-warning">
                                            <i class="fas fa-hourglass-half"></i> Menunggu Persetujuan
                                        </span>
                                        <?php elseif ($late): ?>
                                        <span class="badge badge-danger">
                                            <i class="fas fa-exclamation-triangle"></i> Terlambat
                                        </span>
                                        <?php else: ?>
                                        <span class="badge" style="background:rgba(59,130,246,0.15);color:#2563eb;">
                                            <i class="fas fa-book-open"></i> Dipinjam
                                        </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($r['total_denda'] > 0): ?>
                                        <span
                                            class="badge <?= $r['status_bayar'] === 'sudah' ? 'badge-success' : 'badge-danger' ?>">
                                            <i class="fas fa-coins"></i>
                                            Rp <?= number_format($r['total_denda'], 0, ',', '.') ?>
                                            <br>
                                            <small><?= $r['status_bayar'] === 'sudah' ? 'Lunas' : 'Belum' ?></small>
                                        </span>
                                        <?php else: ?>
                                        <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endwhile; else: ?>
                                <tr>
                                    <td colspan="7">
                                        <div class="empty-state">
                                            <div class="empty-state-ico">📋</div>
                                            <div class="empty-state-title">Belum ada riwayat peminjaman</div>
                                            <div class="empty-state-sub">
                                                Anda belum pernah meminjam buku.
                                                <a href="pinjam.php">Pinjam buku sekarang</a>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </main>
        </div>
    </div>
    <script src="../assets/js/script.js"></script>
</body>

</html>
