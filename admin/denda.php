<?php
require_once '../config/database.php';
require_once '../includes/session.php';
requireAdmin();

// Definisikan konstanta DENDA_PER_HARI jika belum didefinisikan
if (!defined('DENDA_PER_HARI')) {
    define('DENDA_PER_HARI', 1000); // Rp 1.000 per hari
}

$conn = getConnection();
$msg = ''; $msgType = '';

// Ambil data user untuk header
$userId = getPenggunaId();
$userStmt = $conn->prepare("SELECT foto, nama_pengguna FROM pengguna WHERE id_pengguna = ?");
$userStmt->bind_param("i", $userId);
$userStmt->execute();
$userData = $userStmt->get_result()->fetch_assoc();
$userStmt->close();

// Inisial untuk avatar
$initials = '';
foreach (explode(' ', trim($userData['nama_pengguna'] ?? getPenggunaName())) as $w) {
    $initials .= strtoupper(mb_substr($w, 0, 1));
    if (strlen($initials) >= 2) break;
}
$fotoPath = (!empty($userData['foto']) && file_exists('../' . $userData['foto'])) 
            ? '../' . htmlspecialchars($userData['foto']) 
            : null;

// Proses pembayaran denda
if (isset($_POST['bayar'])) {
    $id = (int)$_POST['id_denda'];
    $s = $conn->prepare("UPDATE denda SET status_bayar='sudah', tgl_bayar=NOW() WHERE id_denda=?");
    $s->bind_param("i", $id);
    if ($s->execute()) {
        $msg = 'Denda berhasil dibayar!';
        $msgType = 'success';
    } else {
        $msg = 'Gagal membayar denda!';
        $msgType = 'danger';
    }
    $s->close();
}

// Hitung dan buat denda otomatis untuk yang terlambat
$overdue = $conn->query("SELECT t.id_transaksi, t.tgl_kembali_rencana FROM transaksi t
    LEFT JOIN denda d ON t.id_transaksi = d.id_transaksi
    WHERE t.status_transaksi = 'Peminjaman' AND t.tgl_kembali_rencana < NOW() AND d.id_denda IS NULL");
while ($od = $overdue->fetch_assoc()) {
    $hari = max(1, floor((time() - strtotime($od['tgl_kembali_rencana'])) / 86400));
    $total = $hari * DENDA_PER_HARI;
    $conn->query("INSERT INTO denda(id_transaksi, jumlah_hari, tarif_per_hari, total_denda) 
                   VALUES({$od['id_transaksi']}, $hari, " . DENDA_PER_HARI . ", $total)");
}

$filter = isset($_GET['f']) ? $_GET['f'] : 'semua';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$q = "SELECT d.*, 
             t.tgl_pinjam, t.tgl_kembali_rencana,
             a.id_anggota, a.nama_anggota, a.nis, a.kelas,
             b.judul_buku, b.isbn, b.cover
      FROM denda d
      JOIN transaksi t ON d.id_transaksi = t.id_transaksi
      JOIN anggota a ON t.id_anggota = a.id_anggota
      JOIN buku b ON t.id_buku = b.id_buku
      WHERE 1=1";

if ($filter === 'belum') $q .= " AND d.status_bayar = 'belum'";
elseif ($filter === 'sudah') $q .= " AND d.status_bayar = 'sudah'";

if (!empty($search)) {
    $search = $conn->real_escape_string($search);
    $q .= " AND (a.nama_anggota LIKE '%$search%' 
                OR a.nis LIKE '%$search%' 
                OR b.judul_buku LIKE '%$search%')";
}

$q .= " ORDER BY d.created_at DESC";
$dendas = $conn->query($q);

// Statistik
$total_denda = $conn->query("SELECT COALESCE(SUM(total_denda), 0) as total FROM denda")->fetch_assoc()['total'];
$total_belum = $conn->query("SELECT COALESCE(SUM(total_denda), 0) as total FROM denda WHERE status_bayar = 'belum'")->fetch_assoc()['total'];
$total_sudah = $conn->query("SELECT COALESCE(SUM(total_denda), 0) as total FROM denda WHERE status_bayar = 'sudah'")->fetch_assoc()['total'];
$jumlah_belum = $conn->query("SELECT COUNT(*) as jml FROM denda WHERE status_bayar = 'belum'")->fetch_assoc()['jml'];

$page_title = 'Monitoring Denda';
$page_sub   = 'Kelola denda keterlambatan pengembalian';
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Denda — Admin Cozy-Library</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin/dashboard.css?v=<?= @filemtime('../assets/css/admin/dashboard.css') ?: time() ?>">
    <link rel="stylesheet" href="../assets/css/admin/denda.css?v=<?= @filemtime('../assets/css/admin/denda.css') ?: time() ?>">
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

                <!-- Page Header -->
                <div class="page-header">
                    <div>
                        <h1 class="page-header-title">Monitoring Denda</h1>
                        <p class="page-header-sub">Kelola denda keterlambatan pengembalian buku</p>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-header">
                            <div class="stat-icon total"><i class="fas fa-coins"></i></div>
                        </div>
                        <div class="stat-label">Total Denda</div>
                        <div class="stat-value">Rp <?= number_format($total_denda, 0, ',', '.') ?></div>
                        <div class="stat-sub">Keseluruhan</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-header">
                            <div class="stat-icon belum"><i class="fas fa-hourglass-half"></i></div>
                        </div>
                        <div class="stat-label">Belum Dibayar</div>
                        <div class="stat-value">Rp <?= number_format($total_belum, 0, ',', '.') ?></div>
                        <div class="stat-sub"><?= $jumlah_belum ?> transaksi</div>
                        <div class="stat-progress">
                            <div class="stat-progress-fill"
                                style="width: <?= $total_denda > 0 ? ($total_belum / $total_denda) * 100 : 0 ?>%"></div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-header">
                            <div class="stat-icon sudah"><i class="fas fa-check-circle"></i></div>
                        </div>
                        <div class="stat-label">Sudah Dibayar</div>
                        <div class="stat-value">Rp <?= number_format($total_sudah, 0, ',', '.') ?></div>
                        <div class="stat-sub">Lunas</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-header">
                            <div class="stat-icon jumlah"><i class="fas fa-exclamation-triangle"></i></div>
                        </div>
                        <div class="stat-label">Jumlah Denda</div>
                        <div class="stat-value"><?= $dendas->num_rows ?></div>
                        <div class="stat-sub">Total transaksi denda</div>
                    </div>
                </div>

                <!-- Filter & Table -->
                <div class="card">
                    <div class="card-header">
                        <h2><i class="fas fa-list" style="margin-right: 8px; color: var(--primary-600);"></i> Daftar
                            Denda</h2>
                        <div class="filter-tabs">
                            <a href="?f=semua" class="filter-tab <?= $filter === 'semua' ? 'active' : '' ?>">
                                <i class="fas fa-list-ul"></i> Semua
                            </a>
                            <a href="?f=belum" class="filter-tab <?= $filter === 'belum' ? 'active' : '' ?>">
                                <i class="fas fa-hourglass-half"></i> Belum Bayar
                            </a>
                            <a href="?f=sudah" class="filter-tab <?= $filter === 'sudah' ? 'active' : '' ?>">
                                <i class="fas fa-check-circle"></i> Sudah Bayar
                            </a>
                        </div>
                    </div>

                    <form method="GET" class="filter-bar">
                        <div class="search-wrap">
                            <input type="text" name="search" placeholder="Cari anggota atau judul buku..."
                                value="<?= htmlspecialchars($search) ?>">
                        </div>
                        <input type="hidden" name="f" value="<?= htmlspecialchars($filter) ?>">
                        <button type="submit" class="btn-ghost btn-sm"><i class="fas fa-search"></i> Cari</button>
                        <?php if ($search || $filter !== 'semua'): ?>
                        <a href="denda.php" class="btn-ghost btn-sm"><i class="fas fa-times"></i> Reset</a>
                        <?php endif; ?>
                    </form>

                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Anggota</th>
                                    <th>Buku</th>
                                    <th>Tgl Kembali Rencana</th>
                                    <th>Telat (Hari)</th>
                                    <th>Total Denda</th>
                                    <th>Status</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($dendas && $dendas->num_rows > 0): while($r = $dendas->fetch_assoc()): ?>
                                <tr>
                                    <td class="text-muted text-sm">#<?= $r['id_denda'] ?></td>
                                    <td>
                                        <div class="fw-600"><?= htmlspecialchars($r['nama_anggota']) ?></div>
                                        <div class="text-xs text-muted">NIS: <?= htmlspecialchars($r['nis'] ?? '') ?>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($r['judul_buku']) ?></td>
                                    <td><?= date('d/m/Y', strtotime($r['tgl_kembali_rencana'])) ?></td>
                                    <td><?= $r['jumlah_hari'] ?> hari</td>
                                    <td><span class="fw-600">Rp
                                            <?= number_format($r['total_denda'], 0, ',', '.') ?></span></td>
                                    <td>
                                        <span
                                            class="badge <?= $r['status_bayar'] === 'sudah' ? 'badge-success' : 'badge-danger' ?>">
                                            <i
                                                class="fas <?= $r['status_bayar'] === 'sudah' ? 'fa-check' : 'fa-times' ?>"></i>
                                            <?= $r['status_bayar'] === 'sudah' ? 'Lunas' : 'Belum' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($r['status_bayar'] === 'belum'): ?>
                                        <form method="POST"
                                            onsubmit="return confirm('Konfirmasi pembayaran denda Rp <?= number_format($r['total_denda'], 0, ',', '.') ?>?')"
                                            style="display:inline">
                                            <input type="hidden" name="id_denda" value="<?= $r['id_denda'] ?>">
                                            <button type="submit" name="bayar" class="btn-success btn-sm">
                                                <i class="fas fa-check"></i> Bayar
                                            </button>
                                        </form>
                                        <?php else: ?>
                                        <span class="text-muted text-xs">
                                            <i class="far fa-calendar-check"></i>
                                            <?= date('d/m/Y', strtotime($r['tgl_bayar'])) ?>
                                        </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endwhile; else: ?>
                                <tr>
                                    <td colspan="8">
                                        <div class="empty-state">
                                            <div class="empty-state-ico">💰</div>
                                            <div class="empty-state-title">Tidak ada data denda</div>
                                            <p class="text-muted text-sm">Belum ada denda yang tercatat dalam sistem</p>
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

    <script>
    // Tutup modal dengan ESC
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            // Tidak ada modal di halaman ini
        }
    });

    // Prevent form resubmission
    if (window.history.replaceState) {
        window.history.replaceState(null, null, window.location.href);
    }
    </script>
    <script src="../assets/js/script.js"></script>
</body>

</html>