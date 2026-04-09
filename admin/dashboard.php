<?php
require_once '../config/database.php';
require_once '../includes/session.php';
requireAdmin();
$conn = getConnection();

// ── Quick Action: Setujui / Tolak permintaan Pending ─────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi_cepat'])) {
    $aksi = $_POST['aksi_cepat'];            // 'setujui' | 'tolak'
    $id_t = (int)($_POST['id_transaksi'] ?? 0);
    if ($id_t > 0 && in_array($aksi, ['setujui', 'tolak'])) {
        $chk = $conn->prepare("SELECT status_transaksi, id_buku FROM transaksi WHERE id_transaksi = ?");
        $chk->bind_param("i", $id_t);
        $chk->execute();
        $chkRow = $chk->get_result()->fetch_assoc();
        $chk->close();

        if ($chkRow && $chkRow['status_transaksi'] === 'Pending') {
            if ($aksi === 'setujui') {
                // DIPERBAIKI: Status disamakan menjadi 'Dipinjam'
                $upd = $conn->prepare("UPDATE transaksi SET status_transaksi='Dipinjam' WHERE id_transaksi = ?");
                $upd->bind_param("i", $id_t);
                $upd->execute();
                $upd->close();
            } else {
                $upd = $conn->prepare("UPDATE transaksi SET status_transaksi='Ditolak' WHERE id_transaksi = ?");
                $upd->bind_param("i", $id_t);
                $upd->execute();
                $upd->close();
                // Kembalikan stok buku jika ditolak
                $rs = $conn->prepare("UPDATE buku SET stok=stok+1, status='tersedia' WHERE id_buku = ?");
                $rs->bind_param("i", $chkRow['id_buku']);
                $rs->execute();
                $rs->close();
            }
        }
    }
    // PRG – hindari resubmit saat refresh
    header('Location: dashboard.php');
    exit;
}
// ─────────────────────────────────────────────────────────────────────────────

// Ambil data profil untuk welcome box
$_profile = getPenggunaProfileData($conn);
$userData = $_profile['userData'];
$initials = $_profile['initials'];
$fotoPath = $_profile['fotoPath'];


function cnt($c, $q, $f = 'c') {
    $res = $c->query($q);
    return ($res ? ($res->fetch_assoc()[$f] ?? 0) : 0);
}

// Statistik utama (DIPERBAIKI)
$tb = cnt($conn, "SELECT COUNT(*) c FROM buku");
$ts = cnt($conn, "SELECT COUNT(*) c FROM buku WHERE status='tersedia'");
$ta = cnt($conn, "SELECT COUNT(*) c FROM anggota");
$tp = cnt($conn, "SELECT COUNT(*) c FROM pengguna");
$ap = cnt($conn, "SELECT COUNT(*) c FROM transaksi WHERE status_transaksi IN ('Dipinjam', 'Peminjaman')");
$tl = cnt($conn, "SELECT COUNT(*) c FROM transaksi WHERE status_transaksi IN ('Dipinjam', 'Peminjaman') AND tgl_kembali_rencana < NOW()");
$td = cnt($conn, "SELECT COALESCE(SUM(total_denda),0) s FROM denda WHERE status_bayar='belum'", 's');
$kh = cnt($conn, "SELECT COUNT(*) c FROM transaksi WHERE status_transaksi IN ('Pengembalian', 'Dikembalikan') AND DATE(tgl_kembali_aktual) = CURDATE()");

// ── Permintaan Pending untuk quick-action table ──────────────────────────────
$pending_rows = $conn->query(
    "SELECT t.*, a.nama_anggota, a.nis, b.judul_buku, b.stok AS stok_buku
     FROM transaksi t
     JOIN anggota a ON t.id_anggota = a.id_anggota
     JOIN buku b ON t.id_buku = b.id_buku
     WHERE t.status_transaksi = 'Pending'
     ORDER BY t.tgl_pinjam DESC
     LIMIT 20"
);
$jml_pending = $pending_rows ? $pending_rows->num_rows : 0;
// ─────────────────────────────────────────────────────────────────────────────

// Tabel peminjaman aktif (DIPERBAIKI)
$rows = $conn->query(
    "SELECT t.*, a.nama_anggota, a.nis, b.judul_buku, b.cover
     FROM transaksi t
     JOIN anggota a ON t.id_anggota = a.id_anggota
     JOIN buku b ON t.id_buku = b.id_buku
     WHERE t.status_transaksi IN ('Dipinjam', 'Peminjaman')
     ORDER BY t.tgl_pinjam DESC
     LIMIT 8"
);
$page_title = 'Dashboard';
$page_sub   = 'Admin Panel · Cozy-Library';
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="../assets/icons/cozy-tp.png" type="image/png">
    <title>Dashboard Admin — Cozy-Library</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin/dashboard.css?v=<?= @filemtime('../assets/css/admin/dashboard.css') ?: time() ?>">
    <link rel="stylesheet" href="../assets/css/responsive-fix.css?v=<?= @filemtime('../assets/css/responsive-fix.css') ?: time() ?>">
    <style>
        /* ── Pending table card — ikuti gaya recent-card ────────────── */
        .pending-card {
            background: white;
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-md);
            border: 1px solid var(--neutral-200);
            border-left: 4px solid #f59e0b;
            overflow: hidden;
            margin-bottom: 24px;
        }
        .pending-header {
            padding: 20px;
            border-bottom: 1px solid var(--neutral-200);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .pending-title {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .pending-title i  { font-size: 1.1rem; color: #d97706; }
        .pending-title h2 { font-size: 1.1rem; font-weight: 600; color: var(--neutral-800); }
        .pending-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #f59e0b;
            color: #fff;
            border-radius: 999px;
            font-size: 0.72rem;
            font-weight: 700;
            min-width: 22px;
            height: 22px;
            padding: 0 7px;
            margin-left: 6px;
        }

        /* ── Quick-action buttons ─────────────────────────────────── */
        .qa-btn {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 12px;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 600;
            cursor: pointer;
            white-space: nowrap;
            border: 1.5px solid transparent;
            transition: opacity .15s, transform .1s;
            text-decoration: none;
            background: none;
        }
        .qa-btn:hover    { opacity: .85; transform: translateY(-1px); }
        .qa-approve      { background: #f0fdf4; color: #15803d; border-color: #16a34a; }
        .qa-reject       { background: #fff5f5; color: #dc2626; border-color: #dc2626; }
        .qa-detail       { background: var(--primary-50); color: var(--primary-700); border-color: var(--primary-300); }

        /* ── Status badge tambahan ────────────────────────────────── */
        .status-badge.pending { background: #fffbeb; color: #b45309; }
        .status-badge.ditolak { background: var(--danger-50); color: var(--danger-600); }

        /* ── Cover fallback placeholder ──────────────────────────── */
        .cover-ph {
            width: 40px; height: 55px;
            border-radius: var(--radius-sm);
            background: var(--neutral-100);
            display: flex; align-items: center; justify-content: center;
            color: var(--neutral-400); font-size: 1rem;
        }
    </style>
</head>

<body>
    <div class="app-wrap">
        <?php include 'includes/nav.php'; ?>

        <div class="main-area">
            <?php include 'includes/header.php'; ?>

            <main class="content">

                <!-- ── Welcome Box ───────────────────────────────────── -->
                <div class="wb">
                    <div class="wb-avatar">
                        <?php if ($fotoPath): ?>
                        <img src="<?= $fotoPath ?>" alt="Foto Profil">
                        <?php else: ?>
                        <?= htmlspecialchars($initials) ?>
                        <?php endif; ?>
                    </div>
                    <div>
                        <div class="wb-name">Halo, <?= htmlspecialchars(getPenggunaName()) ?> 👋</div>
                        <div class="wb-desc">Kelola seluruh sistem perpustakaan dari satu tempat · Admin Cozy-Library</div>
                    </div>
                    <div class="wb-actions">
                        <a href="buku.php"    class="wb-btn1"><i class="fas fa-plus"></i> Tambah Buku</a>
                        <a href="laporan.php" class="wb-btn2"><i class="fas fa-chart-bar"></i> Lihat Laporan</a>
                    </div>
                </div>

                <!-- ── Stats Cards ───────────────────────────────────── -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-info">
                            <h3>Total Buku</h3>
                            <div class="stat-number"><?= $tb ?></div>
                            <div class="stat-desc success">
                                <i class="fas fa-check-circle"></i> <?= $ts ?> tersedia
                            </div>
                        </div>
                        <div class="stat-icon blue"><i class="fas fa-book"></i></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-info">
                            <h3>Aktif Dipinjam</h3>
                            <div class="stat-number"><?= $ap ?></div>
                            <div class="stat-desc <?= $tl > 0 ? 'warning' : '' ?>">
                                <i class="fas <?= $tl > 0 ? 'fa-exclamation-circle' : 'fa-check-circle' ?>"></i>
                                <?= $tl ?> terlambat
                            </div>
                        </div>
                        <div class="stat-icon red"><i class="fas fa-exchange-alt"></i></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-info">
                            <h3>Total Anggota</h3>
                            <div class="stat-number"><?= $ta ?></div>
                            <div class="stat-desc">
                                <i class="fas fa-users-cog"></i> <?= $tp ?> pengguna sistem
                            </div>
                        </div>
                        <div class="stat-icon green"><i class="fas fa-users"></i></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-info">
                            <h3>Denda Belum Lunas</h3>
                            <div class="stat-number" style="font-size:1.45rem;">Rp <?= number_format($td, 0, ',', '.') ?></div>
                            <div class="stat-desc warning">
                                <i class="fas fa-clock"></i> perlu diproses
                            </div>
                        </div>
                        <div class="stat-icon amber"><i class="fas fa-coins"></i></div>
                    </div>
                </div>

                <!-- ── Permintaan Menunggu Persetujuan (Quick Action) ─── -->
                <?php if ($jml_pending > 0): ?>
                <div class="pending-card">
                    <div class="pending-header">
                        <div class="pending-title">
                            <i class="fas fa-hourglass-half"></i>
                            <h2>Permintaan Menunggu Persetujuan</h2>
                            <span class="pending-badge"><?= $jml_pending ?></span>
                        </div>
                        <a href="transaksi.php?status=Pending" class="recent-link">
                            Lihat Semua <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Anggota</th>
                                    <th>NIS</th>
                                    <th>Buku (Stok)</th>
                                    <th>Tgl Pinjam</th>
                                    <th>Rencana Kembali</th>
                                    <th>Status</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $pending_rows->data_seek(0); while ($pr = $pending_rows->fetch_assoc()): ?>
                                <tr>
                                    <td><span class="fw-600"><?= htmlspecialchars($pr['nama_anggota']) ?></span></td>
                                    <td style="font-size:.85rem;color:var(--neutral-500);"><?= htmlspecialchars($pr['nis']) ?></td>
                                    <td>
                                        <?= htmlspecialchars(mb_strimwidth($pr['judul_buku'], 0, 28, '…')) ?>
                                        <span style="color:var(--neutral-400);font-size:.78rem;">
                                            (Stok: <?= (int)$pr['stok_buku'] ?>)
                                        </span>
                                    </td>
                                    <td><?= date('d M Y', strtotime($pr['tgl_pinjam'])) ?></td>
                                    <td><?= date('d M Y', strtotime($pr['tgl_kembali_rencana'])) ?></td>
                                    <td>
                                        <span class="status-badge pending">
                                            <i class="fas fa-clock"></i> Pending
                                        </span>
                                    </td>
                                    <td>
                                        <form method="POST" style="display:inline;margin:0;"
                                              onsubmit="return confirm('Setujui peminjaman ini?')">
                                            <input type="hidden" name="aksi_cepat"    value="setujui">
                                            <input type="hidden" name="id_transaksi" value="<?= (int)$pr['id_transaksi'] ?>">
                                            <button type="submit" class="qa-btn qa-approve">
                                                <i class="fas fa-check"></i> Setujui
                                            </button>
                                        </form>
                                        <form method="POST" style="display:inline;margin:0 0 0 4px;"
                                              onsubmit="return confirm('Tolak permintaan ini?')">
                                            <input type="hidden" name="aksi_cepat"    value="tolak">
                                            <input type="hidden" name="id_transaksi" value="<?= (int)$pr['id_transaksi'] ?>">
                                            <button type="submit" class="qa-btn qa-reject">
                                                <i class="fas fa-times"></i> Tolak
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>

                <!-- ── Peminjaman Aktif ──────────────────────────────── -->
                <div class="recent-card">
                    <div class="recent-header">
                        <div class="recent-title">
                            <i class="fas fa-clock"></i>
                            <h2>Peminjaman Aktif</h2>
                        </div>
                        <a href="transaksi.php" class="recent-link">
                            Lihat Semua <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Cover</th>
                                    <th>Anggota</th>
                                    <th>NIS</th>
                                    <th>Buku</th>
                                    <th>Tgl Pinjam</th>
                                    <th>Jatuh Tempo</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($rows && $rows->num_rows > 0): while ($r = $rows->fetch_assoc()):
                                    $late = strtotime($r['tgl_kembali_rencana']) < time();
                                ?>
                                <tr>
                                    <td class="book-cover-cell">
                                        <?php if (!empty($r['cover']) && file_exists('../' . $r['cover'])): ?>
                                        <img class="cover-thumb" src="../<?= htmlspecialchars($r['cover']) ?>" alt="">
                                        <?php else: ?>
                                        <div class="cover-ph"><i class="fas fa-book"></i></div>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="fw-600"><?= htmlspecialchars($r['nama_anggota']) ?></span></td>
                                    <td style="font-size:.85rem;color:var(--neutral-500);"><?= htmlspecialchars($r['nis']) ?></td>
                                    <td><?= htmlspecialchars(mb_strimwidth($r['judul_buku'], 0, 30, '…')) ?></td>
                                    <td><?= date('d M Y', strtotime($r['tgl_pinjam'])) ?></td>
                                    <td><?= date('d M Y', strtotime($r['tgl_kembali_rencana'])) ?></td>
                                    <td>
                                        <span class="status-badge <?= $late ? 'danger' : 'warning' ?>">
                                            <?php if ($late): ?>
                                            <i class="fas fa-exclamation-triangle"></i> Terlambat
                                            <?php else: ?>
                                            <i class="fas fa-book-open"></i> Dipinjam
                                            <?php endif; ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endwhile; else: ?>
                                <tr>
                                    <td colspan="7" style="text-align:center;padding:48px;color:var(--neutral-500);">
                                        <i class="fas fa-check-circle" style="font-size:3rem;color:#10b981;display:block;margin-bottom:12px;"></i>
                                        ✅ Tidak ada pinjaman aktif
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
    // Prevent form resubmission on refresh
    if (window.history.replaceState) {
        window.history.replaceState(null, null, window.location.href);
    }
    </script>
    <script src="../assets/js/script.js"></script>
</body>

</html>
