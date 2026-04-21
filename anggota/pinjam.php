<?php
/*
 * Alur logic PHP:
 * 1) Memuat dependency utama (database, session, dan helper).
 * 2) Validasi hak akses sebelum memproses data sensitif.
 * 3) Proses input GET/POST, jalankan query, lalu siapkan data view.
 * 4) Render output halaman sesuai role dan konteks fitur.
 */require_once '../config/database.php';
require_once '../includes/session.php';
requireAnggota();

// Definisikan konstanta DENDA_PER_HARI jika belum ada
if (!defined('DENDA_PER_HARI')) {
    define('DENDA_PER_HARI', 2000);
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

if (isset($_POST['pinjam'])) {
    $id_buku = (int)$_POST['id_buku'];
    // Cek buku tersedia
    $chk = $conn->query("SELECT status, judul_buku, stok FROM buku WHERE id_buku=$id_buku")->fetch_assoc();
    if (!$chk) { 
        $msg = 'Buku tidak ditemukan!'; 
        $msgType = 'danger'; 
    } elseif ($chk['status'] === 'tidak' || $chk['stok'] < 1) { 
        $msg = 'Buku sedang tidak tersedia!'; 
        $msgType = 'warning'; 
    } else {
        // Cek apakah anggota sudah pinjam atau pending buku ini
        $dupl = $conn->query("SELECT id_transaksi FROM transaksi WHERE id_anggota=$id AND id_buku=$id_buku AND status_transaksi IN ('Pending','Peminjaman','Dipinjam')")->num_rows;
        if ($dupl > 0) { 
            $msg = 'Anda sudah meminjam atau sedang menunggu persetujuan untuk buku ini!'; 
            $msgType = 'warning'; 
        } else {
            $tgl_pinjam = date('Y-m-d H:i:s');
            $tgl_kembali = date('Y-m-d H:i:s', strtotime('+14 days'));
            $s = $conn->prepare("INSERT INTO transaksi(id_anggota, id_buku, tgl_pinjam, tgl_kembali_rencana, status_transaksi) VALUES(?, ?, ?, ?, 'Pending')");
            $s->bind_param("iiss", $id, $id_buku, $tgl_pinjam, $tgl_kembali);
            if ($s->execute()) {
                // Stok TIDAK dikurangi saat pending — dikurangi saat admin menyetujui
                $msg = 'Permintaan peminjaman berhasil dikirim! Menunggu persetujuan Admin/Petugas.'; 
                $msgType = 'success';
            } else { 
                $msg = 'Gagal: ' . $conn->error; 
                $msgType = 'danger'; 
            }
            $s->close();
        }
    }
}

$search = isset($_GET['search']) ? $_GET['search'] : '';
$search = $conn->real_escape_string($search);
$filter_kat = isset($_GET['kat']) ? (int)$_GET['kat'] : 0;
$q = "SELECT b.*, k.nama_kategori,
      (SELECT COUNT(*) FROM transaksi t WHERE t.id_buku=b.id_buku AND t.id_anggota=$id AND t.status_transaksi IN ('Pending','Peminjaman','Dipinjam')) AS sudah_pinjam
      FROM buku b LEFT JOIN kategori k ON b.id_kategori = k.id_kategori WHERE 1=1";
if ($search) $q .= " AND (b.judul_buku LIKE '%$search%' OR b.pengarang LIKE '%$search%')";
if ($filter_kat) $q .= " AND b.id_kategori = $filter_kat";
$q .= " ORDER BY b.judul_buku";
$books = $conn->query($q);
$cats = $conn->query("SELECT * FROM kategori ORDER BY nama_kategori");
$book_emojis = ['📗','📘','📕','📙','📓','📔','📒'];

$page_title = 'Pinjam Buku';
$page_sub   = 'Pilih buku yang ingin dipinjam';
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="../assets/icons/cozy-tp.png" type="image/png">
    <title>Pinjam Buku — Cozy-Library</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/anggota/dashboard.css?v=<?= @filemtime('../assets/css/anggota/dashboard.css') ?: time() ?>">
    <link rel="stylesheet" href="../assets/css/anggota/pinjam.css?v=<?= @filemtime('../assets/css/anggota/pinjam.css') ?: time() ?>">
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
                        <h1 class="page-header-title">Pinjam Buku</h1>
                        <p class="page-header-sub">Pilih buku yang ingin dipinjam dari koleksi Cozy-Library</p>
                    </div>
                    <a href="katalog.php" class="btn-secondary">
                        <i class="fas fa-search"></i> Lihat Katalog
                    </a>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h2><i class="fas fa-book-open"></i> Katalog & Peminjaman Buku</h2>
                    </div>
                    <div class="card-body">
                        <div class="search-box">
                            <form method="GET">
                                <input type="text" name="search" class="form-control"
                                    placeholder="Cari judul buku atau pengarang..."
                                    value="<?= htmlspecialchars($search) ?>">
                                <select name="kat" class="form-control">
                                    <option value="">Semua Kategori</option>
                                    <?php 
                                    $cats->data_seek(0); 
                                    while($c = $cats->fetch_assoc()): 
                                    ?>
                                    <option value="<?= $c['id_kategori'] ?>"
                                        <?= $filter_kat == $c['id_kategori'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($c['nama_kategori']) ?>
                                    </option>
                                    <?php endwhile; ?>
                                </select>
                                <button type="submit" class="btn-primary">
                                    <i class="fas fa-search"></i> Cari
                                </button>
                                <a href="pinjam.php" class="btn-secondary">
                                    <i class="fas fa-times"></i> Reset
                                </a>
                            </form>
                        </div>

                        <div class="table-responsive">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Judul Buku</th>
                                        <th>Kategori</th>
                                        <th>Pengarang</th>
                                        <th>Tahun</th>
                                        <th>Status</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($books && $books->num_rows > 0): while($r = $books->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-600"><?= htmlspecialchars($r['judul_buku']) ?></div>
                                            <small
                                                class="text-muted"><?= htmlspecialchars($r['penerbit'] ?? '') ?></small>
                                        </td>
                                        <td><?= htmlspecialchars($r['nama_kategori'] ?? '-') ?></td>
                                        <td><?= htmlspecialchars($r['pengarang']) ?></td>
                                        <td><?= $r['tahun_terbit'] ?></td>
                                        <td>
                                            <span
                                                class="badge <?= $r['status'] === 'tersedia' ? 'badge-success' : 'badge-danger' ?>">
                                                <i
                                                    class="fas <?= $r['status'] === 'tersedia' ? 'fa-check-circle' : 'fa-times-circle' ?>"></i>
                                                <?= $r['status'] === 'tersedia' ? 'Tersedia' : 'Dipinjam' ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($r['sudah_pinjam'] > 0): ?>
                                            <span class="badge badge-warning">
                                                <i class="fas fa-clock"></i> Menunggu / Dipinjam
                                            </span>
                                            <?php elseif ($r['status'] === 'tersedia'): ?>
                                            <form method="POST"
                                                onsubmit="return confirm('Yakin ingin meminjam buku &quot;<?= htmlspecialchars(addslashes($r['judul_buku'])) ?>&quot;? Permintaan akan menunggu persetujuan Admin/Petugas.')">
                                                <input type="hidden" name="id_buku" value="<?= $r['id_buku'] ?>">
                                                <button type="submit" name="pinjam" class="btn-primary btn-sm">
                                                    <i class="fas fa-book"></i> Ajukan Pinjam
                                                </button>
                                            </form>
                                            <?php else: ?>
                                            <span class="text-muted">
                                                <i class="fas fa-ban"></i> Tidak tersedia
                                            </span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endwhile; else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center">
                                            <div class="empty-state">
                                                <div class="empty-state-ico">🔍</div>
                                                <div class="empty-state-title">Buku tidak ditemukan</div>
                                                <div class="empty-state-sub">Coba kata kunci yang berbeda atau reset
                                                    filter</div>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="alert-info">
                    <i class="fas fa-info-circle"></i>
                    <span>ℹ️ Durasi peminjaman 14 hari. Denda keterlambatan Rp
                        <?= number_format(DENDA_PER_HARI, 0, ',', '.') ?>/hari.</span>
                </div>
            </main>
        </div>
    </div>
    <script src="../assets/js/script.js"></script>
</body>

</html>
