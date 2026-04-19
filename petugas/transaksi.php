<?php
/*
 * Alur logic PHP:
 * 1) Memuat dependency utama (database, session, dan helper).
 * 2) Validasi hak akses sebelum memproses data sensitif.
 * 3) Proses input GET/POST, jalankan query, lalu siapkan data view.
 * 4) Render output halaman sesuai role dan konteks fitur.
 */require_once '../config/database.php';
require_once '../includes/session.php';
require_once '../includes/denda_helper.php';
requirePetugas();
$conn = getConnection();
$msg = ''; $msgType = '';


// Hitung statistik
$totalPinjam    = $conn->query("SELECT COUNT(*) as total FROM transaksi WHERE status_transaksi IN ('Peminjaman','Dipinjam')")->fetch_assoc()['total'];
$totalKembali   = $conn->query("SELECT COUNT(*) as total FROM transaksi WHERE status_transaksi IN ('Pengembalian','Dikembalikan')")->fetch_assoc()['total'];
$totalTerlambat = $conn->query("SELECT COUNT(*) as total FROM transaksi WHERE status_transaksi IN ('Peminjaman','Dipinjam') AND tgl_kembali_rencana < NOW()")->fetch_assoc()['total'];
$totalPending   = $conn->query("SELECT COUNT(*) as total FROM transaksi WHERE status_transaksi='Pending'")->fetch_assoc()['total'];


// Aksi: Setujui -> ubah ke Dipinjam + kurangi stok + simpan petugas
if (isset($_POST['setujui'])) {
    $id_t = (int)$_POST['id_transaksi'];
    $userId = (int) (getPenggunaId() ?? 0);
    $chk  = $conn->prepare("SELECT t.id_transaksi, t.id_buku, t.status_transaksi, b.stok
                            FROM transaksi t
                            JOIN buku b ON b.id_buku = t.id_buku
                            WHERE t.id_transaksi = ? LIMIT 1");
    $chk->bind_param("i", $id_t); $chk->execute();
    $chkRow = $chk->get_result()->fetch_assoc(); $chk->close();

    if ($chkRow && $chkRow['status_transaksi'] === 'Pending') {
        if ((int)$chkRow['stok'] < 1) {
            $msg = 'Stok buku habis, tidak bisa disetujui.'; $msgType = 'warning';
        } else {
            $upd = $conn->prepare("UPDATE transaksi SET status_transaksi='Dipinjam', id_petugas=? WHERE id_transaksi = ?");
            $upd->bind_param("ii", $userId, $id_t); $upd->execute();
            $applied = $upd->affected_rows > 0;
            $upd->close();

            if ($applied) {
                $conn->query("UPDATE buku SET stok=stok-1, status=IF(stok-1>0,'tersedia','tidak') WHERE id_buku=" . (int)$chkRow['id_buku']);
            }

            $msg = 'Peminjaman berhasil disetujui. Status: Dipinjam.'; $msgType = 'success';
        }
    } else {
        $msg = 'Transaksi tidak valid atau sudah diproses.'; $msgType = 'danger';
    }
}

// Aksi: Tolak -> ubah ke Ditolak + simpan petugas
if (isset($_POST['tolak'])) {
    $id_t = (int)$_POST['id_transaksi'];
    $userId = (int) (getPenggunaId() ?? 0);
    $chk  = $conn->prepare("SELECT id_transaksi, id_buku, status_transaksi FROM transaksi WHERE id_transaksi = ?");
    $chk->bind_param("i", $id_t); $chk->execute();
    $chkRow = $chk->get_result()->fetch_assoc(); $chk->close();

    if ($chkRow && $chkRow['status_transaksi'] === 'Pending') {
        $upd = $conn->prepare("UPDATE transaksi SET status_transaksi='Ditolak', id_petugas=? WHERE id_transaksi = ?");
        $upd->bind_param("ii", $userId, $id_t); $upd->execute(); $upd->close();
        $msg = 'Peminjaman berhasil ditolak.'; $msgType = 'warning';
    } else {
        $msg = 'Transaksi tidak valid atau sudah diproses.'; $msgType = 'danger';
    }
}

// Aksi: Kembalikan -> Dikembalikan + hitung denda
if (isset($_POST['kembalikan'])) {
    $id_t        = (int)$_POST['id_transaksi'];
    $tgl_kembali = $_POST['tgl_kembali'] ?? date('Y-m-d');
    $t = $conn->query("SELECT * FROM transaksi WHERE id_transaksi=$id_t AND status_transaksi IN ('Dipinjam','Peminjaman')")->fetch_assoc();
    if ($t) {
        $days        = max(0, (strtotime($tgl_kembali) - strtotime($t['tgl_kembali_rencana'])) / (60*60*24));
        $denda_total = ceil($days) * 1000;
        $s = $conn->prepare("UPDATE transaksi SET status_transaksi='Dikembalikan', tgl_kembali_aktual=? WHERE id_transaksi=?");
        $s->bind_param("si", $tgl_kembali, $id_t); $s->execute(); $s->close();
        $conn->query("UPDATE buku SET stok=stok+1, status='tersedia' WHERE id_buku=" . (int)$t['id_buku']);
        if ($denda_total > 0) {
            upsertDendaForTransaksi($conn, $id_t, $t['tgl_kembali_rencana'], $tgl_kembali);
            $msg = "Buku dikembalikan. Denda: Rp ".number_format($denda_total,0,',','.'); $msgType = 'warning';
        } else {
            $msg = 'Buku berhasil dikembalikan. Tidak ada denda.'; $msgType = 'success';
        }
    } else {
        $msg = 'Transaksi tidak valid atau buku sudah dikembalikan.'; $msgType = 'danger';
    }
}

// Add transaction
if (isset($_POST['add'])) {
    $id_ang = (int)$_POST['id_anggota'];
    $id_buku = (int)$_POST['id_buku'];
    $tgl_pinjam = $_POST['tgl_pinjam'];
    $tgl_rencana = $_POST['tgl_kembali_rencana'];
    // Check stock
    $stok = $conn->query("SELECT stok FROM buku WHERE id_buku=$id_buku")->fetch_assoc()['stok'] ?? 0;
    if ($stok < 1) {
        $msg = 'Stok buku habis!'; $msgType='warning';
    } else {
        $s = $conn->prepare("INSERT INTO transaksi(id_anggota,id_buku,tgl_pinjam,tgl_kembali_rencana,status_transaksi) VALUES(?,?,?,?,'Dipinjam')");
        $s->bind_param("iiss",$id_ang,$id_buku,$tgl_pinjam,$tgl_rencana);
        if ($s->execute()) {
            $conn->query("UPDATE buku SET stok=stok-1, status=IF(stok-1>0,'tersedia','tidak') WHERE id_buku=$id_buku");
            $msg='Transaksi berhasil dicatat!'; $msgType='success';
        } else { $msg='Gagal: '.$conn->error; $msgType='danger'; }
        $s->close();
    }
}
// Return book
if (isset($_POST['return'])) {
    $id_t = (int)$_POST['id_transaksi'];
    $tgl_kembali = $_POST['tgl_kembali'];
    $t = $conn->query("SELECT * FROM transaksi WHERE id_transaksi=$id_t")->fetch_assoc();
    $days = max(0, (strtotime($tgl_kembali)-strtotime($t['tgl_kembali_rencana']))/(60*60*24));
    $denda_total = ceil($days) * 1000;
    $s = $conn->prepare("UPDATE transaksi SET status_transaksi='Dikembalikan',tgl_kembali_aktual=? WHERE id_transaksi=?");
    $s->bind_param("si",$tgl_kembali,$id_t); $s->execute(); $s->close();
    $conn->query("UPDATE buku SET stok=stok+1, status='tersedia' WHERE id_buku={$t['id_buku']}");
    if ($denda_total > 0) {
        upsertDendaForTransaksi($conn, $id_t, $t['tgl_kembali_rencana'], $tgl_kembali);
        $msg="Buku dikembalikan. Denda: Rp ".number_format($denda_total,0,',','.'); $msgType='warning';
    } else {
        $msg='Buku berhasil dikembalikan. Tidak ada denda.'; $msgType='success';
    }
}

$anggota_list = $conn->query("SELECT * FROM anggota WHERE status='aktif' ORDER BY nama_anggota");
$buku_list = $conn->query("SELECT * FROM buku WHERE stok>0 ORDER BY judul_buku");

$filter_status = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';
$q = "SELECT t.*,a.nama_anggota,a.nis,a.kelas,b.judul_buku,b.cover FROM transaksi t 
      JOIN anggota a ON t.id_anggota=a.id_anggota 
      JOIN buku b ON t.id_buku=b.id_buku WHERE 1=1";
if ($filter_status) {
    // Map filter value ke semua status yang relevan
    if ($filter_status === 'Peminjaman') {
        $q .= " AND t.status_transaksi IN ('Peminjaman','Dipinjam')";
    } elseif ($filter_status === 'Pengembalian') {
        $q .= " AND t.status_transaksi IN ('Pengembalian','Dikembalikan')";
    } else {
        $q .= " AND t.status_transaksi='" . $conn->real_escape_string($filter_status) . "'";
    }
}
if ($search) {
    $search = $conn->real_escape_string($search);
    $q .= " AND (a.nama_anggota LIKE '%$search%' OR b.judul_buku LIKE '%$search%')";
}
$q .= " ORDER BY FIELD(t.status_transaksi,'Pending','Peminjaman','Dipinjam','Pengembalian','Dikembalikan','Ditolak'), t.tgl_pinjam DESC";
$transaksi = $conn->query($q);

$returnItem = null;
if (isset($_GET['return'])) {
    $id = (int)$_GET['return'];
    $returnItem = $conn->query("SELECT t.*,a.nama_anggota,b.judul_buku,b.cover FROM transaksi t 
                                JOIN anggota a ON t.id_anggota=a.id_anggota 
                                JOIN buku b ON t.id_buku=b.id_buku 
                                WHERE t.id_transaksi=$id AND t.status_transaksi IN ('Dipinjam','Peminjaman')")->fetch_assoc();
}

$page_title = 'Manajemen Transaksi';
$page_sub   = 'Pencatatan Peminjaman & Pengembalian Buku';
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="../assets/icons/cozy-tp.png" type="image/png">
    <title>Transaksi - Petugas Cozy-Library</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/petugas/petugas.css?v=<?= @filemtime('../assets/css/petugas/petugas.css') ?: time() ?>">
    <link rel="stylesheet" href="../assets/css/petugas/transaksi.css?v=<?= @filemtime('../assets/css/petugas/transaksi.css') ?: time() ?>">
    <link rel="stylesheet" href="../assets/css/responsive-fix.css?v=<?= @filemtime('../assets/css/responsive-fix.css') ?: time() ?>">
<link rel="stylesheet" href="../assets/css/print.css?v=<?= @filemtime('../assets/css/print.css') ?: time() ?>">
    <style>
    /* Tombol aksi - Setujui & Tolak */
    .btn-action {
        display: inline-flex; align-items: center; gap: 4px;
        padding: 5px 11px; border-radius: 6px;
        font-size: .76rem; font-weight: 600;
        border: none; cursor: pointer;
        transition: opacity .15s, transform .1s;
        white-space: nowrap; margin: 2px 1px; line-height: 1.4;
    }
    .btn-action:hover { opacity: .83; transform: translateY(-1px); }
    .btn-setujui { background: #d1fae5; color: #065f46; }
    .btn-setujui:hover { background: #a7f3d0; }
    .btn-tolak   { background: #fee2e2; color: #991b1b; }
    .btn-tolak:hover   { background: #fecaca; }
    /* Badge status baru */
    .badge.status-pending { background: #fef3c7; color: #92400e; border-radius: 20px; padding: 3px 10px; font-size:.75rem; font-weight:600; }
    .badge.status-ditolak { background: #fee2e2; color: #991b1b; border-radius: 20px; padding: 3px 10px; font-size:.75rem; font-weight:600; }
    </style>
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
                        <h1 class="page-header-title">Manajemen Transaksi</h1>
                        <p class="page-header-sub">Pencatatan peminjaman dan pengembalian buku</p>
                    </div>
                    <button class="btn-primary" onclick="document.getElementById('addModal').style.display='flex'">
                        <i class="fas fa-plus-circle"></i>
                        Catat Peminjaman
                    </button>
                </div>

                <!-- Stats Cards -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon blue"><i class="fas fa-book-reader"></i></div>
                        <div class="stat-info">
                            <h3>Sedang Dipinjam</h3>
                            <div class="stat-number"><?= $totalPinjam ?></div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
                        <div class="stat-info">
                            <h3>Sudah Kembali</h3>
                            <div class="stat-number"><?= $totalKembali ?></div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon red"><i class="fas fa-exclamation-triangle"></i></div>
                        <div class="stat-info">
                            <h3>Terlambat</h3>
                            <div class="stat-number"><?= $totalTerlambat ?></div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon amber" style="background:#fef3c7;color:#92400e;"><i class="fas fa-hourglass-half"></i></div>
                        <div class="stat-info">
                            <h3>Menunggu Konfirmasi</h3>
                            <div class="stat-number"><?= $totalPending ?></div>
                        </div>
                    </div>
                </div>

                <?php $print_title = 'Data Transaksi Peminjaman'; $print_total = $transaksi ? $transaksi->num_rows : 0; include '../includes/print_header.php'; ?>

                <!-- Filter & Table -->
                <div class="card">
                    <form method="GET" class="filter-bar">
                        <div class="search-wrap">
                            <input type="text" name="search" placeholder="Cari anggota atau judul buku..."
                                value="<?= htmlspecialchars($search) ?>">
                        </div>
                        <select name="status" class="form-control" style="width:auto">
                            <option value="">Semua Status</option>
                            <option value="Pending" <?= $filter_status==='Pending'?'selected':'' ?>>Menunggu Konfirmasi</option>
                            <option value="Peminjaman" <?= $filter_status==='Peminjaman'?'selected':'' ?>>Sedang Dipinjam</option>
                            <option value="Pengembalian" <?= $filter_status==='Pengembalian'?'selected':'' ?>>Sudah Kembali</option>
                            <option value="Ditolak" <?= $filter_status==='Ditolak'?'selected':'' ?>>Ditolak</option>
                        </select>
                        <button type="submit" class="btn-ghost btn-sm"><i class="fas fa-filter"></i> Filter</button>
                        <?php if ($search || $filter_status): ?>
                        <a href="transaksi.php" class="btn-ghost btn-sm"><i class="fas fa-times"></i> Reset</a>
                        <?php endif; ?>
                    </form>

                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Anggota</th>
                                    <th>Buku</th>
                                    <th>Tgl Pinjam</th>
                                    <th>Jatuh Tempo</th>
                                    <th>Tgl Kembali</th>
                                    <th>Status</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($transaksi && $transaksi->num_rows > 0): $no=1; while($r=$transaksi->fetch_assoc()):
                                    $st   = $r['status_transaksi'];
                                    $late = in_array($st, ['Peminjaman','Dipinjam']) && strtotime($r['tgl_kembali_rencana']) < time();

                                    if (in_array($st, ['Pengembalian','Dikembalikan'])) {
                                        $statusClass = 'status-kembali';
                                        $statusText  = 'Kembali';
                                    } elseif ($st === 'Pending') {
                                        $statusClass = 'status-pending';
                                        $statusText  = 'Pending';
                                    } elseif ($st === 'Ditolak') {
                                        $statusClass = 'status-ditolak';
                                        $statusText  = 'Ditolak';
                                    } elseif ($st === 'Dipinjam') {
                                        $statusClass = $late ? 'status-terlambat' : 'status-dipinjam';
                                        $statusText  = $late ? 'Terlambat' : 'Dipinjam';
                                    } elseif ($late) {
                                        $statusClass = 'status-terlambat';
                                        $statusText  = 'Terlambat';
                                    } else {
                                        $statusClass = 'status-dipinjam';
                                        $statusText  = 'Dipinjam';
                                    }

                                   // DIPERBAIKI: Tombol persetujuan HANYA muncul jika statusnya Pending
                                    $butuhKonfirmasi = ($st === 'Pending');
                                    // DIPERBAIKI: Tombol kembalikan muncul jika sedang dipinjam
                                    $bisaDikembalikan = in_array($st, ['Dipinjam', 'Peminjaman']);
                                    ?>
                                <tr>
                                    <td class="text-muted text-sm"><?= $no++ ?></td>
                                    <td>
                                        <div class="fw-600"><?= htmlspecialchars($r['nama_anggota']) ?></div>
                                        <div class="text-xs text-muted">NIS: <?= htmlspecialchars($r['nis'] ?? '') ?></div>
                                    </td>
                                    <td><?= htmlspecialchars($r['judul_buku']) ?></td>
                                    <td><?= date('d/m/Y', strtotime($r['tgl_pinjam'])) ?></td>
                                    <td><?= date('d/m/Y', strtotime($r['tgl_kembali_rencana'])) ?></td>
                                    <td><?= $r['tgl_kembali_aktual'] ? date('d/m/Y', strtotime($r['tgl_kembali_aktual'])) : 'Belum kembali' ?></td>
                                    <td><span class="badge <?= $statusClass ?>"><?= $statusText ?></span></td>
                                    <td>
                                        <?php if ($butuhKonfirmasi): ?>
                                            <!-- Aksi: Setujui -->
                                            <form method="POST" style="display:inline;margin:0 2px 0 0;" onsubmit="return confirm('Setujui peminjaman ini?')">
                                                <input type="hidden" name="id_transaksi" value="<?= (int)$r['id_transaksi'] ?>">
                                                <button type="submit" name="setujui" class="btn-action btn-setujui">
                                                    <i class="fas fa-check"></i> Setuju
                                                </button>
                                            </form>
                                            <!-- Aksi: Tolak -->
                                            <form method="POST" style="display:inline;margin:0;" onsubmit="return confirm('Tolak peminjaman ini?')">
                                                <input type="hidden" name="id_transaksi" value="<?= (int)$r['id_transaksi'] ?>">
                                                <button type="submit" name="tolak" class="btn-action btn-tolak">
                                                    <i class="fas fa-times"></i> Tolak
                                                </button>
                                            </form>
                                        <?php elseif ($bisaDikembalikan): ?>
                                            <!-- Aksi: Kembalikan -->
                                            <button type="button" class="btn-sage btn-sm"
                                                onclick="openKembalikanModal(
                                                    <?= (int)$r['id_transaksi'] ?>,
                                                    '<?= addslashes(htmlspecialchars($r['nama_anggota'])) ?>',
                                                    '<?= addslashes(htmlspecialchars($r['judul_buku'])) ?>',
                                                    '<?= $r['tgl_kembali_rencana'] ?>')">
                                                <i class="fas fa-undo-alt"></i> Kembalikan
                                            </button>
                                        <?php elseif (in_array($st, ['Pengembalian','Dikembalikan'])): ?>
                                            <span class="text-muted text-xs"><i class="fas fa-check-circle"></i> Selesai</span>
                                        <?php elseif ($st === 'Ditolak'): ?>
                                            <span class="text-muted text-xs"><i class="fas fa-ban"></i> Ditolak</span>
                                        <?php else: ?>
                                            <span class="text-muted text-xs">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endwhile; else: ?>
                                <tr>
                                    <td colspan="8">
                                        <div class="empty-state">
                                            <div class="empty-state-ico"><i class="fas fa-clipboard-list"></i></div>
                                            <div class="empty-state-title">Belum ada transaksi</div>
                                            <p class="empty-state-sub">Klik tombol "Catat Peminjaman" untuk memulai
                                                transaksi</p>
                                        </div>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <?php include '../includes/print_footer.php'; ?>
            </main>
        </div>
    </div>

    <!-- ADD MODAL -->
    <div id="addModal" class="modal-overlay" onclick="if(event.target===this)this.style.display='none'">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title"><i class="fas fa-plus-circle"
                        style="color: var(--primary-500); margin-right: 8px;"></i>Catat Peminjaman Baru</h3>
                <button class="modal-close" onclick="document.getElementById('addModal').style.display='none'"><i
                        class="fas fa-times"></i></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="form-grid">
                        <div class="form-group form-full">
                            <label class="form-label">Anggota <span style="color: var(--danger-500);">*</span></label>
                            <select name="id_anggota" class="form-control" required>
                                <option value="">-- Pilih Anggota --</option>
                                <?php 
                                $anggota_list->data_seek(0);
                                while($a=$anggota_list->fetch_assoc()): 
                                ?>
                                <option value="<?= $a['id_anggota'] ?>">
                                    <?= htmlspecialchars($a['nama_anggota']) ?> (<?= $a['nis'] ?>)
                                </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="form-group form-full">
                            <label class="form-label">Buku <span style="color: var(--danger-500);">*</span></label>
                            <select name="id_buku" class="form-control" required>
                                <option value="">-- Pilih Buku --</option>
                                <?php 
                                $buku_list->data_seek(0);
                                while($b=$buku_list->fetch_assoc()): 
                                ?>
                                <option value="<?= $b['id_buku'] ?>">
                                    <?= htmlspecialchars($b['judul_buku']) ?> (stok: <?= $b['stok'] ?>)
                                </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Tanggal Pinjam <span
                                    style="color: var(--danger-500);">*</span></label>
                            <input type="date" name="tgl_pinjam" class="form-control" value="<?= date('Y-m-d') ?>"
                                required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Rencana Kembali <span
                                    style="color: var(--danger-500);">*</span></label>
                            <input type="date" name="tgl_kembali_rencana" class="form-control"
                                value="<?= date('Y-m-d', strtotime('+7 days')) ?>" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-ghost"
                        onclick="document.getElementById('addModal').style.display='none'">
                        <i class="fas fa-times"></i> Batal
                    </button>
                    <button type="submit" name="add" class="btn-primary">
                        <i class="fas fa-save"></i> Simpan Transaksi
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- KEMBALIKAN MODAL (JS-driven, no GET redirect) -->
    <div id="kembalikanModal" class="modal-overlay" onclick="if(event.target===this)closeKembalikanModal()">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">
                    <i class="fas fa-undo-alt" style="color: var(--sage-500); margin-right: 8px;"></i>
                    Proses Pengembalian
                </h3>
                <button class="modal-close" onclick="closeKembalikanModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST">
                <input type="hidden" name="id_transaksi" id="kembalikan_id">
                <div class="modal-body">
                    <div class="info-box">
                        <div class="info-box-title" id="kembalikan_anggota"></div>
                        <div class="info-box-sub" id="kembalikan_buku"></div>
                        <div class="info-box-meta">
                            <i class="far fa-calendar-alt"></i> Jatuh tempo:
                            <span id="kembalikan_jatuh_tempo"></span>
                        </div>
                    </div>
                    <div class="form-group" style="margin-top:16px;">
                        <label class="form-label">
                            Tanggal Kembali <span style="color: var(--danger-500);">*</span>
                        </label>
                        <input type="date" name="tgl_kembali" id="kembalikan_tgl"
                               class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <p class="text-muted text-xs" style="margin-top:10px;">
                        <i class="fas fa-info-circle"></i> Denda Rp 1.000/hari jika terlambat dari jatuh tempo.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-ghost" onclick="closeKembalikanModal()">
                        <i class="fas fa-times"></i> Batal
                    </button>
                    <button type="submit" name="kembalikan" class="btn-sage">
                        <i class="fas fa-check-circle"></i> Proses Pengembalian
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
    /* Modal Kembalikan */
    function openKembalikanModal(id, anggota, buku, jatuhTempo) {
        document.getElementById('kembalikan_id').value      = id;
        document.getElementById('kembalikan_anggota').textContent = anggota;
        document.getElementById('kembalikan_buku').textContent    = buku;

        // Format tanggal jatuh tempo ke "dd MMM YYYY"
        var d = new Date(jatuhTempo);
        var bulan = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Ags','Sep','Okt','Nov','Des'];
        document.getElementById('kembalikan_jatuh_tempo').textContent =
            d.getDate() + ' ' + bulan[d.getMonth()] + ' ' + d.getFullYear();

        document.getElementById('kembalikanModal').style.display = 'flex';
    }

    function closeKembalikanModal() {
        document.getElementById('kembalikanModal').style.display = 'none';
    }

    /* Tutup semua modal dengan ESC */
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.getElementById('addModal').style.display      = 'none';
            document.getElementById('kembalikanModal').style.display = 'none';
        }
    });

    /* Prevent form resubmission */
    if (window.history.replaceState) {
        window.history.replaceState(null, null, window.location.href);
    }
    </script>
    <script src="../assets/js/script.js"></script>
</body>

</html>
