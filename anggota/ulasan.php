<?php
require_once '../config/database.php';
require_once '../includes/session.php';
requireAnggota();

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

// TAMBAH ULASAN
if (isset($_POST['tambah'])) {
    $id_buku = (int)$_POST['id_buku']; 
    $rating = (int)$_POST['rating']; 
    $ulasan = trim($_POST['ulasan']);
    
    // Cek apakah pernah meminjam buku ini
    $chk = $conn->query("SELECT id_transaksi FROM transaksi WHERE id_anggota=$id AND id_buku=$id_buku")->num_rows;
    if ($chk == 0) { 
        $msg = 'Anda hanya bisa mengulas buku yang pernah dipinjam!'; 
        $msgType = 'warning'; 
    } else {
        $dupl = $conn->query("SELECT id_ulasan FROM ulasan_buku WHERE id_anggota=$id AND id_buku=$id_buku")->num_rows;
        if ($dupl > 0) { 
            $msg = 'Anda sudah memberikan ulasan untuk buku ini!'; 
            $msgType = 'warning'; 
        } else {
            $s = $conn->prepare("INSERT INTO ulasan_buku(id_anggota, id_buku, rating, ulasan) VALUES(?, ?, ?, ?)");
            $s->bind_param("iiis", $id, $id_buku, $rating, $ulasan);
            $msg = $s->execute() ? 'Ulasan berhasil ditambahkan! Terima kasih atas partisipasi Anda.' : 'Gagal menambahkan ulasan!'; 
            $msgType = 'success'; 
            $s->close();
        }
    }
}

// HAPUS ULASAN
if (isset($_POST['hapus'])) {
    $id_ulasan = (int)$_POST['id_ulasan'];
    $s = $conn->prepare("DELETE FROM ulasan_buku WHERE id_ulasan=? AND id_anggota=?");
    $s->bind_param("ii", $id_ulasan, $id);
    $msg = $s->execute() ? 'Ulasan berhasil dihapus!' : 'Gagal menghapus ulasan!'; 
    $msgType = 'success'; 
    $s->close();
}

// Buku yang pernah dipinjam anggota ini
$buku_pinjam = $conn->query("SELECT DISTINCT b.id_buku, b.judul_buku, b.cover 
                             FROM transaksi t 
                             JOIN buku b ON t.id_buku = b.id_buku 
                             WHERE t.id_anggota = $id 
                             ORDER BY b.judul_buku");

// Ulasan saya
$ulasan_saya = $conn->query("SELECT u.*, b.judul_buku, b.cover 
                             FROM ulasan_buku u 
                             JOIN buku b ON u.id_buku = b.id_buku 
                             WHERE u.id_anggota = $id 
                             ORDER BY u.created_at DESC");

// Semua ulasan
$semua_ulasan = $conn->query("SELECT u.*, b.judul_buku, b.cover, a.nama_anggota, a.foto as anggota_foto 
                              FROM ulasan_buku u 
                              JOIN buku b ON u.id_buku = b.id_buku 
                              JOIN anggota a ON u.id_anggota = a.id_anggota 
                              ORDER BY u.created_at DESC");

$page_title = 'Ulasan Buku';
$page_sub   = 'Bagikan pengalaman membaca Anda';
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="../assets/icons/cozy-tp.png" type="image/png">
    <title>Ulasan Buku — Cozy-Library</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/anggota/dashboard.css?v=<?= @filemtime('../assets/css/anggota/dashboard.css') ?: time() ?>">
    <link rel="stylesheet" href="../assets/css/anggota/ulasan.css?v=<?= @filemtime('../assets/css/anggota/ulasan.css') ?: time() ?>">
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
                <?php if ($msg): ?>
                <div class="alert alert-<?= $msgType ?>">
                    <i
                        class="fas <?= $msgType === 'success' ? 'fa-check-circle' : ($msgType === 'warning' ? 'fa-exclamation-triangle' : 'fa-times-circle') ?>"></i>
                    <?= htmlspecialchars($msg) ?>
                </div>
                <?php endif; ?>

                <div class="page-header">
                    <div>
                        <h1 class="page-header-title">Ulasan Buku</h1>
                        <p class="page-header-sub">Bagikan pengalaman membaca Anda dengan komunitas</p>
                    </div>
                </div>

                <!-- FORM TAMBAH ULASAN -->
                <div class="card">
                    <div class="card-header">
                        <h2><i class="fas fa-pen"></i> Tambah Ulasan Buku</h2>
                    </div>
                    <div class="card-body">
                        <?php if ($buku_pinjam && $buku_pinjam->num_rows > 0): ?>
                        <form method="POST" id="formUlasan">
                            <div class="form-row">
                                <div class="form-group">
                                    <label><i class="fas fa-book"
                                            style="color: var(--soft-purple); margin-right: 8px;"></i>Pilih Buku <span
                                            style="color: var(--danger-600);">*</span></label>
                                    <select name="id_buku" class="form-control" required>
                                        <option value="">-- Pilih Buku yang Pernah Dipinjam --</option>
                                        <?php while($b = $buku_pinjam->fetch_assoc()): ?>
                                        <option value="<?= $b['id_buku'] ?>">
                                            <?= htmlspecialchars($b['judul_buku']) ?>
                                        </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label><i class="fas fa-star"
                                            style="color: var(--star-color); margin-right: 8px;"></i>Rating <span
                                            style="color: var(--danger-600);">*</span></label>
                                    <div class="star-rating-input">
                                        <?php for($i = 1; $i <= 5; $i++): ?>
                                        <span class="star-input" data-value="<?= $i ?>"
                                            onclick="setRating(<?= $i ?>)">★</span>
                                        <?php endfor; ?>
                                        <input type="hidden" name="rating" id="rating_value" value="5">
                                        <span style="margin-left: 10px; color: var(--neutral-500);" id="rating_text">5 -
                                            Sangat Baik</span>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label><i class="fas fa-comment"
                                        style="color: var(--soft-purple); margin-right: 8px;"></i>Ulasan <span
                                        style="color: var(--danger-600);">*</span></label>
                                <textarea name="ulasan" class="form-control" rows="4" required
                                    placeholder="Tulis pengalaman membaca Anda..."></textarea>
                            </div>
                            <button type="submit" name="tambah" class="btn-primary">
                                <i class="fas fa-paper-plane"></i> Kirim Ulasan
                            </button>
                        </form>
                        <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-state-ico">📚</div>
                            <div class="empty-state-title">Anda belum memiliki riwayat peminjaman</div>
                            <p class="empty-state-sub">
                                Anda hanya bisa mengulas buku yang pernah dipinjam.
                                <a href="pinjam.php">Pinjam buku sekarang</a>
                            </p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- ULASAN SAYA -->
                <div class="card">
                    <div class="card-header">
                        <h2><i class="fas fa-user"></i> Ulasan Saya</h2>
                    </div>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Cover</th>
                                    <th>Buku</th>
                                    <th>Rating</th>
                                    <th>Ulasan</th>
                                    <th>Tanggal</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($ulasan_saya && $ulasan_saya->num_rows > 0): while($r = $ulasan_saya->fetch_assoc()): ?>
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
                                    <td><?= htmlspecialchars($r['judul_buku']) ?></td>
                                    <td>
                                        <span class="stars">
                                            <?= str_repeat('★', $r['rating']) ?>
                                            <span class="stars-empty"><?= str_repeat('★', 5 - $r['rating']) ?></span>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars(mb_strimwidth($r['ulasan'], 0, 50, '...')) ?></td>
                                    <td><?= date('d/m/Y', strtotime($r['created_at'])) ?></td>
                                    <td>
                                        <form method="POST"
                                            onsubmit="return confirm('Yakin ingin menghapus ulasan ini?')">
                                            <input type="hidden" name="id_ulasan" value="<?= $r['id_ulasan'] ?>">
                                            <button type="submit" name="hapus" class="btn-danger btn-sm">
                                                <i class="fas fa-trash"></i> Hapus
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endwhile; else: ?>
                                <tr>
                                    <td colspan="6" class="text-center">
                                        <div class="empty-state">
                                            <div class="empty-state-ico">⭐</div>
                                            <div class="empty-state-title">Belum ada ulasan dari Anda</div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- ULASAN KOMUNITAS -->
                <div class="card">
                    <div class="card-header">
                        <h2><i class="fas fa-users"></i> Ulasan Terbaru dari Komunitas</h2>
                    </div>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Cover</th>
                                    <th>Buku</th>
                                    <th>Anggota</th>
                                    <th>Rating</th>
                                    <th>Ulasan</th>
                                    <th>Tanggal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($semua_ulasan && $semua_ulasan->num_rows > 0): while($r = $semua_ulasan->fetch_assoc()): 
                                    $anggota_initials = '';
                                    foreach (explode(' ', trim($r['nama_anggota'])) as $w) {
                                        $anggota_initials .= strtoupper(mb_substr($w, 0, 1));
                                        if (strlen($anggota_initials) >= 2) break;
                                    }
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
                                    <td><?= htmlspecialchars($r['judul_buku']) ?></td>
                                    <td>
                                        <div class="user-info">
                                            <div class="avatar-small">
                                                <?php if (!empty($r['anggota_foto']) && file_exists('../' . $r['anggota_foto'])): ?>
                                                <img src="../<?= htmlspecialchars($r['anggota_foto']) ?>" alt="Foto">
                                                <?php else: ?>
                                                <?= htmlspecialchars($anggota_initials) ?>
                                                <?php endif; ?>
                                            </div>
                                            <?= htmlspecialchars($r['nama_anggota']) ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="stars">
                                            <?= str_repeat('★', $r['rating']) ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars(mb_strimwidth($r['ulasan'], 0, 50, '...')) ?></td>
                                    <td><?= date('d/m/Y', strtotime($r['created_at'])) ?></td>
                                </tr>
                                <?php endwhile; else: ?>
                                <tr>
                                    <td colspan="6" class="text-center">
                                        <div class="empty-state">
                                            <div class="empty-state-ico">💬</div>
                                            <div class="empty-state-title">Belum ada ulasan dari komunitas</div>
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
