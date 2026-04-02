<?php
require_once '../config/database.php';
require_once '../includes/session.php';
requireAnggota();

// Definisikan konstanta DENDA_PER_HARI jika belum ada
if (!defined('DENDA_PER_HARI')) {
    define('DENDA_PER_HARI', 1000);
}

$conn = getConnection();
$id = getAnggotaId();
$msg = ''; $msgType = '';

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

if (isset($_POST['kembalikan'])) {
    $id_trans = (int)$_POST['id_transaksi'];
    // Validasi milik anggota ini dan status Dipinjam (sudah disetujui admin)
    $chk = $conn->query("SELECT * FROM transaksi WHERE id_transaksi=$id_trans AND id_anggota=$id AND status_transaksi='Dipinjam'")->fetch_assoc();
    if (!$chk) { 
        $msg = 'Transaksi tidak valid atau belum disetujui oleh Admin/Petugas!'; 
        $msgType = 'danger'; 
    } else {
        $now = date('Y-m-d H:i:s');
        $conn->query("UPDATE transaksi SET status_transaksi='Dikembalikan', tgl_kembali_aktual='$now' WHERE id_transaksi=$id_trans");
        $conn->query("UPDATE buku SET stok = stok + 1, status = 'tersedia' WHERE id_buku={$chk['id_buku']}");
        // Hitung denda
        if (strtotime($now) > strtotime($chk['tgl_kembali_rencana'])) {
            $hari = max(1, floor((time() - strtotime($chk['tgl_kembali_rencana'])) / 86400));
            $total = $hari * DENDA_PER_HARI;
            $conn->query("INSERT IGNORE INTO denda(id_transaksi, jumlah_hari, tarif_per_hari, total_denda) VALUES($id_trans, $hari, " . DENDA_PER_HARI . ", $total)");
            $msg = "Pengembalian dicatat. Anda terlambat $hari hari. Denda: Rp " . number_format($total, 0, ',', '.') . ". Harap bayar ke petugas.";
            $msgType = 'warning';
        } else { 
            $msg = 'Pengembalian berhasil! Terima kasih telah membaca.'; 
            $msgType = 'success'; 
        }
    }
}

$aktif = $conn->query("SELECT t.*, b.judul_buku, b.pengarang, b.cover 
                       FROM transaksi t 
                       JOIN buku b ON t.id_buku = b.id_buku 
                       WHERE t.id_anggota = $id AND t.status_transaksi IN ('Pending','Dipinjam') 
                       ORDER BY t.tgl_kembali_rencana");

$page_title = 'Kembalikan Buku';
$page_sub   = 'Kembalikan buku yang sudah selesai dibaca';
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kembalikan Buku — Cozy-Library</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/anggota/dashboard.css?v=<?= @filemtime('../assets/css/anggota/dashboard.css') ?: time() ?>">
    <link rel="stylesheet" href="../assets/css/anggota/kembali.css?v=<?= @filemtime('../assets/css/anggota/kembali.css') ?: time() ?>">
    <link rel="stylesheet" href="../assets/css/responsive-fix.css?v=<?= @filemtime('../assets/css/responsive-fix.css') ?: time() ?>">
</head>

<body>

<div class="app-wrap">
    <?php include 'includes/nav.php'; ?>

    <div class="main-area">
        <?php include 'includes/header.php'; ?>
            <!-- CONTENT -->
            <main class="content">
                <?php if ($msg): ?>
                <div class="alert alert-<?= $msgType ?>">
                    <i
                        class="fas <?= $msgType === 'success' ? 'fa-check-circle' : ($msgType === 'warning' ? 'fa-exclamation-triangle' : 'fa-times-circle') ?>"></i>
                    <?= htmlspecialchars($msg) ?>
                </div>
                <?php endif; ?>

                <div class="page-header">
                    <div>
                        <h1 class="page-header-title">Kembalikan Buku</h1>
                        <p class="page-header-sub">Kembalikan buku yang sudah selesai Anda baca</p>
                    </div>
                    <a href="pinjam.php" class="btn-success">
                        <i class="fas fa-plus-circle"></i> Pinjam Buku
                    </a>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h2><i class="fas fa-undo-alt"></i> Daftar Buku Dipinjam &amp; Menunggu Persetujuan</h2>
                    </div>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Cover</th>
                                    <th>Buku</th>
                                    <th>Tgl Pinjam</th>
                                    <th>Jatuh Tempo</th>
                                    <th>Status</th>
                                    <th>Sisa Waktu</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($aktif && $aktif->num_rows > 0): while($r = $aktif->fetch_assoc()):
                                    $sisa = floor((strtotime($r['tgl_kembali_rencana']) - time()) / 86400);
                                    $late = $sisa < 0;
                                    $isPending = $r['status_transaksi'] === 'Pending';
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
                                        <?php if ($isPending): ?>
                                        <span class="badge badge-warning">
                                            <i class="fas fa-hourglass-half"></i> Menunggu Persetujuan
                                        </span>
                                        <?php else: ?>
                                        <span class="badge badge-success">
                                            <i class="fas fa-check-circle"></i> Dipinjam
                                        </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($isPending): ?>
                                        <span class="badge" style="background:#e2e8f0;color:#64748b;">
                                            <i class="fas fa-clock"></i> Menunggu admin
                                        </span>
                                        <?php elseif ($late):
                                            $d = abs($sisa);
                                            $denda_est = $d * DENDA_PER_HARI;
                                        ?>
                                        <span class="badge badge-danger">
                                            <i class="fas fa-exclamation-triangle"></i> Terlambat <?= $d ?> hari
                                        </span>
                                        <br>
                                        <small class="text-muted">
                                            <i class="fas fa-coins"></i> Denda: Rp
                                            <?= number_format($denda_est, 0, ',', '.') ?>
                                        </small>
                                        <?php elseif ($sisa <= 2): ?>
                                        <span class="badge badge-warning">
                                            <i class="fas fa-clock"></i> Segera (<?= $sisa ?> hari)
                                        </span>
                                        <?php else: ?>
                                        <span class="badge badge-success">
                                            <i class="fas fa-check-circle"></i> <?= $sisa ?> hari
                                        </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($isPending): ?>
                                        <span class="text-muted" style="font-size:0.85em;">
                                            <i class="fas fa-info-circle"></i> Belum bisa dikembalikan
                                        </span>
                                        <?php else: ?>
                                        <form method="POST"
                                            onsubmit="return confirm('Yakin ingin mengembalikan buku &quot;<?= htmlspecialchars(addslashes($r['judul_buku'])) ?>&quot;?')">
                                            <input type="hidden" name="id_transaksi" value="<?= $r['id_transaksi'] ?>">
                                            <button type="submit" name="kembalikan" class="btn-success btn-sm">
                                                <i class="fas fa-undo-alt"></i> Kembalikan
                                            </button>
                                        </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endwhile; else: ?>
                                <tr>
                                    <td colspan="7">
                                        <div class="empty-state">
                                            <div class="empty-state-ico">📚</div>
                                            <div class="empty-state-title">Tidak ada buku yang perlu dikembalikan</div>
                                            <div class="empty-state-sub">
                                                Anda belum meminjam buku saat ini.
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

                <!-- Informasi Denda -->
                <div class="card" style="margin-top: 20px;">
                    <div class="card-header">
                        <h2><i class="fas fa-info-circle"></i> Informasi Denda</h2>
                    </div>
                    <div style="padding: 20px;">
                        <div style="display: flex; align-items: center; gap: 16px; flex-wrap: wrap;">
                            <div style="flex: 1;">
                                <p style="color: var(--neutral-600); margin-bottom: 8px;">
                                    <i class="fas fa-clock" style="color: var(--soft-purple); margin-right: 8px;"></i>
                                    Durasi peminjaman: <strong>7 hari</strong>
                                </p>
                                <p style="color: var(--neutral-600);">
                                    <i class="fas fa-coins" style="color: var(--soft-purple); margin-right: 8px;"></i>
                                    Denda keterlambatan: <strong>Rp <?= number_format(DENDA_PER_HARI, 0, ',', '.') ?> /
                                        hari</strong>
                                </p>
                            </div>
                            <div
                                style="background: rgba(155, 140, 156, 0.1); padding: 12px 20px; border-radius: var(--radius-lg);">
                                <p style="color: var(--neutral-600); margin: 0;">
                                    <i class="fas fa-lightbulb"
                                        style="color: var(--soft-purple); margin-right: 8px;"></i>
                                    Kembalikan buku tepat waktu untuk menghindari denda.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    <script src="../assets/js/script.js"></script>
</body>

</html>