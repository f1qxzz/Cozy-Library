<?php
require_once '../config/database.php';
require_once '../includes/session.php';
require_once '../includes/upload_helper.php';
require_once '../includes/book_detail_helper.php';
requirePetugas();

$conn = getConnection();
$hasRackColumn = hasBookRackColumn($conn);
$msg = ''; $msgType = '';


// Hitung statistik
$totalBuku = $conn->query("SELECT COUNT(*) as total FROM buku")->fetch_assoc()['total'];
$totalTersedia = $conn->query("SELECT COUNT(*) as total FROM buku WHERE stok > 0")->fetch_assoc()['total'];
$totalKategori = $conn->query("SELECT COUNT(*) as total FROM kategori")->fetch_assoc()['total'];

// ============================================================
//  TAMBAH BUKU
// ============================================================
if (isset($_POST['add'])) {
    $judul  = trim($_POST['judul_buku']);
    $id_kat = (int)$_POST['id_kategori'];
    $peng   = trim($_POST['pengarang']);
    $nerbit = trim($_POST['penerbit']);
    $tahun  = (int)$_POST['tahun_terbit'];
    $isbn   = trim($_POST['isbn']);
    $desk   = trim($_POST['deskripsi']);
    $stok   = (int)$_POST['stok'];
    $lokasiRak = trim($_POST['lokasi_rak'] ?? '');
    $status = $stok > 0 ? 'tersedia' : 'tidak';

    $adaFileAdd = isset($_FILES['cover']) && $_FILES['cover']['error'] !== UPLOAD_ERR_NO_FILE;
    $coverPath = null; $uploadGagal = false;

    if ($adaFileAdd) {
        $coverResult = processBookCover($_FILES['cover']);
        if (!$coverResult['ok']) { 
            $msg = 'Upload cover gagal: ' . $coverResult['error']; 
            $msgType = 'warning'; 
            $uploadGagal = true; 
        } else { 
            $coverPath = $coverResult['path']; 
        }
    }

    if (!$uploadGagal) {
        if ($hasRackColumn) {
            $s = $conn->prepare("INSERT INTO buku (judul_buku,id_kategori,pengarang,penerbit,tahun_terbit,isbn,deskripsi,stok,lokasi_rak,status,cover) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
            $s->bind_param("sissississs", $judul, $id_kat, $peng, $nerbit, $tahun, $isbn, $desk, $stok, $lokasiRak, $status, $coverPath);
        } else {
            $s = $conn->prepare("INSERT INTO buku (judul_buku,id_kategori,pengarang,penerbit,tahun_terbit,isbn,deskripsi,stok,status,cover) VALUES (?,?,?,?,?,?,?,?,?,?)");
            $s->bind_param("sissississ", $judul, $id_kat, $peng, $nerbit, $tahun, $isbn, $desk, $stok, $status, $coverPath);
        }
        if ($s->execute()) { 
            $s->close(); 
            header('Location: buku.php?notif=tambah_ok'); 
            exit; 
        } else { 
            if ($coverPath) deleteBookCover($coverPath); 
            $msg = 'Gagal menyimpan buku: ' . $conn->error; 
            $msgType = 'danger'; 
        }
        $s->close();
    }
}

// ============================================================
//  EDIT BUKU
// ============================================================
if (isset($_POST['edit'])) {
    $id     = (int)$_POST['id_buku'];
    $judul  = trim($_POST['judul_buku']);
    $id_kat = (int)$_POST['id_kategori'];
    $peng   = trim($_POST['pengarang']);
    $nerbit = trim($_POST['penerbit']);
    $tahun  = (int)$_POST['tahun_terbit'];
    $isbn   = trim($_POST['isbn']);
    $desk   = trim($_POST['deskripsi']);
    $stok   = (int)$_POST['stok'];
    $lokasiRak = trim($_POST['lokasi_rak'] ?? '');
    $status = $_POST['status'];

    $stmtOld = $conn->prepare("SELECT cover FROM buku WHERE id_buku=?");
    $stmtOld->bind_param("i", $id); $stmtOld->execute(); $stmtOld->bind_result($oldCover); $stmtOld->fetch(); $stmtOld->close();

    $adaFileBaru = isset($_FILES['cover']) && $_FILES['cover']['error'] !== UPLOAD_ERR_NO_FILE;
    $newCover = $oldCover; $uploadGagal = false;

    if ($adaFileBaru) {
        $coverResult = processBookCover($_FILES['cover']);
        if (!$coverResult['ok']) {
            $msg = 'Upload cover gagal: ' . $coverResult['error']; 
            $msgType = 'warning'; 
            $uploadGagal = true;
        } else {
            $newCover = $coverResult['path'];
        }
    }

    if (!$uploadGagal) {
        if ($hasRackColumn) {
            $s = $conn->prepare(
                "UPDATE buku SET judul_buku=?,id_kategori=?,pengarang=?,penerbit=?,
                 tahun_terbit=?,isbn=?,deskripsi=?,stok=?,lokasi_rak=?,status=?,cover=? WHERE id_buku=?"
            );
            $s->bind_param("sissississsi", $judul, $id_kat, $peng, $nerbit, $tahun, $isbn, $desk, $stok, $lokasiRak, $status, $newCover, $id);
        } else {
            $s = $conn->prepare(
                "UPDATE buku SET judul_buku=?,id_kategori=?,pengarang=?,penerbit=?,
                 tahun_terbit=?,isbn=?,deskripsi=?,stok=?,status=?,cover=? WHERE id_buku=?"
            );
            $s->bind_param("sissississi", $judul, $id_kat, $peng, $nerbit, $tahun, $isbn, $desk, $stok, $status, $newCover, $id);
        }

        try {
            $s->execute();
            $s->close();

            if ($adaFileBaru && $newCover !== $oldCover) {
                if (!empty($oldCover) && strpos($oldCover, 'default') === false) {
                    deleteBookCover($oldCover);
                }
            }

            header('Location: buku.php?notif=edit_ok');
            exit;

        } catch (Exception $e) {
            if ($adaFileBaru && $newCover !== $oldCover) {
                deleteBookCover($newCover);
            }
            $msg = 'Gagal memperbarui buku: ' . $e->getMessage();
            $msgType = 'danger';
            $s->close();
        }
    }
}

// ============================================================
//  HAPUS BUKU
// ============================================================
if (isset($_POST['delete'])) {
    $msg = 'Akses ditolak. Petugas tidak memiliki hak untuk menghapus buku.';
    $msgType = 'warning';
}

if (empty($msg) && isset($_GET['notif'])) {
    $notifMap = [
        'tambah_ok' => ['Buku berhasil ditambahkan!', 'success'],
        'edit_ok' => ['Buku berhasil diperbarui!', 'success'],
        'hapus_ok' => ['Buku berhasil dihapus!', 'success']
    ];
    if (isset($notifMap[$_GET['notif']])) {
        [$msg, $msgType] = $notifMap[$_GET['notif']];
    }
}

$cats = $conn->query("SELECT * FROM kategori ORDER BY nama_kategori");
$search = isset($_GET['search']) ? $_GET['search'] : '';
$search = $conn->real_escape_string($search);
$filter_kat = isset($_GET['kat']) ? (int)$_GET['kat'] : 0;
$q = "SELECT b.*, k.nama_kategori, a.nama_anggota AS peminjam_aktif
      FROM buku b
      LEFT JOIN kategori k ON b.id_kategori = k.id_kategori
      LEFT JOIN (
          SELECT t1.id_buku, t1.id_anggota
          FROM transaksi t1
          INNER JOIN (
              SELECT id_buku, MAX(id_transaksi) AS max_id
              FROM transaksi
              WHERE status_transaksi IN ('Peminjaman', 'Dipinjam')
              GROUP BY id_buku
          ) tx ON tx.max_id = t1.id_transaksi
      ) ta ON ta.id_buku = b.id_buku
      LEFT JOIN anggota a ON a.id_anggota = ta.id_anggota
      WHERE 1=1";
if ($search) $q .= " AND (b.judul_buku LIKE '%$search%' OR b.pengarang LIKE '%$search%')";
if ($filter_kat) $q .= " AND b.id_kategori=$filter_kat";
$q .= " ORDER BY b.id_buku DESC";
$books = $conn->query($q);

$editBook = null;
if (isset($_GET['edit'])) {
    $eid = (int)$_GET['edit'];
    $s = $conn->prepare("SELECT * FROM buku WHERE id_buku=?");
    $s->bind_param("i", $eid); $s->execute();
    $editBook = $s->get_result()->fetch_assoc();
    $s->close();
}

$page_title = 'Manajemen Buku';
$page_sub   = 'Kelola koleksi buku Cozy-Library';
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="../assets/icons/cozy-tp.png" type="image/png">
    <title>Buku — Petugas Cozy-Library</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/petugas/petugas.css?v=<?= @filemtime('../assets/css/petugas/petugas.css') ?: time() ?>">
    <link rel="stylesheet" href="../assets/css/petugas/buku.css?v=<?= @filemtime('../assets/css/petugas/buku.css') ?: time() ?>">
    <link rel="stylesheet" href="../assets/css/responsive-fix.css?v=<?= @filemtime('../assets/css/responsive-fix.css') ?: time() ?>">
<link rel="stylesheet" href="../assets/css/print.css?v=<?= @filemtime('../assets/css/print.css') ?: time() ?>">
</head>

<body>
    <div class="app-wrap">
        <?php include 'includes/nav.php'; ?>

        <div class="main-area">
            <?php include 'includes/header.php'; ?>

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
                        <h1 class="page-header-title">Manajemen Buku</h1>
                        <p class="page-header-sub">Kelola koleksi buku Cozy-Library</p>
                    </div>
                    <div style="display:flex; gap:10px;">
                        <button class="btn-ghost" onclick="window.print()">
                            <i class="fas fa-print"></i> Cetak A4
                        </button>
                        <button class="btn-primary" onclick="document.getElementById('addModal').style.display='flex'">
                            <i class="fas fa-plus-circle"></i>
                            Tambah Buku Baru
                        </button>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-book"></i></div>
                        <div class="stat-info">
                            <h3>Total Buku</h3>
                            <div class="stat-number"><?= $totalBuku ?></div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                        <div class="stat-info">
                            <h3>Tersedia</h3>
                            <div class="stat-number"><?= $totalTersedia ?></div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-tags"></i></div>
                        <div class="stat-info">
                            <h3>Kategori</h3>
                            <div class="stat-number"><?= $totalKategori ?></div>
                        </div>
                    </div>
                </div>

                <!-- Filter & Table -->
                <div class="card">
                    <form method="GET" class="filter-bar">
                        <div class="search-wrap">
                            <i class="fas fa-search"></i>
                            <input type="text" name="search" placeholder="Cari judul atau pengarang..."
                                value="<?= htmlspecialchars($search) ?>">
                        </div>
                        <select name="kat" class="form-control" style="width:200px">
                            <option value="">Semua Kategori</option>
                            <?php $cats->data_seek(0); while($c=$cats->fetch_assoc()): ?>
                            <option value="<?= $c['id_kategori'] ?>"
                                <?= $filter_kat==$c['id_kategori']?'selected':'' ?>>
                                <?= htmlspecialchars($c['nama_kategori']) ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                        <button type="submit" class="btn-ghost btn-sm"><i class="fas fa-filter"></i> Filter</button>
                        <?php if ($search || $filter_kat): ?>
                        <a href="buku.php" class="btn-ghost btn-sm"><i class="fas fa-times"></i> Reset</a>
                        <?php endif; ?>
                    </form>

                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Cover</th>
                                    <th>Judul Buku</th>
                                    <th>Pengarang</th>
                                    <th>Kategori</th>
                                    <th>Tahun</th>
                                    <th>Stok</th>
                                    <th>Status</th>
                                    <th>Peminjam Aktif</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($books && $books->num_rows > 0): $no=1; while($b=$books->fetch_assoc()): ?>
                                <tr>
                                    <td class="text-muted text-sm"><?= $no++ ?></td>
                                    <td class="book-cover-cell">
                                        <?php if (!empty($b['cover']) && file_exists('../' . $b['cover'])): ?>
                                        <img src="../<?= htmlspecialchars($b['cover']) ?>" class="cover-thumb"
                                            alt="cover">
                                        <?php else: ?>
                                        <div class="cover-thumb"
                                            style="background: rgba(255,255,255,0.5); display: flex; align-items: center; justify-content: center;">
                                            <i class="fas fa-book" style="color: var(--neutral-400);"></i>
                                        </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="book-info-main"><?= htmlspecialchars($b['judul_buku']) ?></div>
                                        <div class="book-info-sub"><?= htmlspecialchars($b['isbn'] ?: '—') ?></div>
                                    </td>
                                    <td><?= htmlspecialchars($b['pengarang']) ?></td>
                                    <td><span
                                            class="badge badge-muted"><?= htmlspecialchars($b['nama_kategori'] ?: '—') ?></span>
                                    </td>
                                    <td><?= $b['tahun_terbit'] ?></td>
                                    <td class="fw-600"><?= $b['stok'] ?></td>
                                    <td>
                                        <?php $displayStatus = ((int)$b['stok'] > 0) ? 'tersedia' : 'dipinjam'; ?>
                                        <span
                                            class="badge <?= $displayStatus==='tersedia' ? 'status-tersedia' : 'status-terlambat' ?>">
                                            <i
                                                class="fas <?= $displayStatus==='tersedia' ? 'fa-check-circle' : 'fa-book-reader' ?>"></i>
                                            <?= $displayStatus==='tersedia' ? 'Tersedia' : 'Dipinjam' ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($b['peminjam_aktif'] ?? '-') ?></td>
                                    <td>
                                        <div class="action-btns">
                                            <a href="detail_buku.php?id=<?= $b['id_buku'] ?>" class="btn-action"
                                                title="Detail">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="?edit=<?= $b['id_buku'] ?>" class="btn-action btn-edit"
                                                title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; else: ?>
                                <tr>
                                    <td colspan="10">
                                        <div class="empty-state">
                                            <div class="empty-state-ico">📚</div>
                                            <div class="empty-state-title">Belum ada buku</div>
                                            <p class="empty-state-sub">Klik tombol "Tambah Buku" untuk menambahkan buku
                                                pertama</p>
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

    <!-- ADD MODAL -->
    <div id="addModal" class="modal-overlay" onclick="if(event.target===this)this.style.display='none'">
        <div class="modal modal-lg">
            <div class="modal-header">
                <h3 class="modal-title"><i class="fas fa-plus-circle"></i> Tambah Buku Baru</h3>
                <button class="modal-close" onclick="document.getElementById('addModal').style.display='none'"><i
                        class="fas fa-times"></i></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="form-grid">
                        <div class="form-group form-full">
                            <label class="form-label">Judul Buku <span>*</span></label>
                            <input type="text" name="judul_buku" class="form-control" required
                                placeholder="Masukkan judul buku">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Pengarang <span>*</span></label>
                            <input type="text" name="pengarang" class="form-control" required
                                placeholder="Nama pengarang">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Penerbit</label>
                            <input type="text" name="penerbit" class="form-control" placeholder="Nama penerbit">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Kategori</label>
                            <select name="id_kategori" class="form-control">
                                <option value="">-- Pilih Kategori --</option>
                                <?php $cats->data_seek(0); while($c=$cats->fetch_assoc()): ?>
                                <option value="<?= $c['id_kategori'] ?>"><?= htmlspecialchars($c['nama_kategori']) ?>
                                </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Tahun Terbit</label>
                            <input type="number" name="tahun_terbit" class="form-control" value="<?= date('Y') ?>"
                                min="1900" max="<?= date('Y') ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">ISBN</label>
                            <input type="text" name="isbn" class="form-control" placeholder="978-602-1234-56-7">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Stok <span>*</span></label>
                            <input type="number" name="stok" class="form-control" min="0" value="1" required>
                        </div>

                        <div class="form-group form-full">
                            <label class="form-label">Cover Buku</label>
                            <div class="cover-upload-area">
                                <div class="cover-preview-wrap"
                                    onclick="document.getElementById('addCoverInput').click()">
                                    <img id="addCoverPreview" src="../uploads/covers/default-cover.png" alt="Preview">
                                    <div class="overlay-hint">
                                        <i class="fas fa-camera"></i>
                                        <span>Ganti Cover</span>
                                    </div>
                                </div>
                                <div class="cover-upload-meta">
                                    <button type="button" class="cover-upload-btn"
                                        onclick="document.getElementById('addCoverInput').click()">
                                        <i class="fas fa-cloud-upload-alt"></i> Pilih File Cover
                                    </button>
                                    <div class="cover-filename" id="addCoverFilename"></div>
                                    <div class="cover-upload-hint">
                                        <i class="fas fa-info-circle"></i> Format: JPG, PNG • Maks. 2 MB • Opsional
                                    </div>
                                </div>
                            </div>
                            <input type="file" id="addCoverInput" name="cover" accept=".jpg,.jpeg,.png"
                                style="display:none" onchange="previewCover(this,'addCoverPreview','addCoverFilename')">
                        </div>
                        <div class="form-group form-full">
                            <label class="form-label">Deskripsi</label>
                            <textarea name="deskripsi" class="form-control"
                                placeholder="Deskripsi singkat tentang buku..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-ghost"
                        onclick="document.getElementById('addModal').style.display='none'">
                        <i class="fas fa-times"></i> Batal
                    </button>
                    <button type="submit" name="add" class="btn-primary">
                        <i class="fas fa-save"></i> Simpan Buku
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php if ($editBook): ?>
    <!-- EDIT MODAL -->
    <div id="editModal" class="modal-overlay" onclick="if(event.target===this)window.location.href='buku.php'">
        <div class="modal modal-lg">
            <div class="modal-header">
                <h3 class="modal-title"><i class="fas fa-edit"></i> Edit Buku</h3>
                <a href="buku.php" class="modal-close"><i class="fas fa-times"></i></a>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="id_buku" value="<?= $editBook['id_buku'] ?>">
                <div class="modal-body">
                    <div class="form-grid">
                        <div class="form-group form-full">
                            <label class="form-label">Judul Buku <span>*</span></label>
                            <input type="text" name="judul_buku" class="form-control"
                                value="<?= htmlspecialchars($editBook['judul_buku']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Pengarang <span>*</span></label>
                            <input type="text" name="pengarang" class="form-control"
                                value="<?= htmlspecialchars($editBook['pengarang']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Penerbit</label>
                            <input type="text" name="penerbit" class="form-control"
                                value="<?= htmlspecialchars($editBook['penerbit']) ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Kategori</label>
                            <select name="id_kategori" class="form-control">
                                <option value="">-- Pilih Kategori --</option>
                                <?php $cats->data_seek(0); while($c=$cats->fetch_assoc()): ?>
                                <option value="<?= $c['id_kategori'] ?>"
                                    <?= $c['id_kategori']==$editBook['id_kategori']?'selected':'' ?>>
                                    <?= htmlspecialchars($c['nama_kategori']) ?>
                                </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Tahun Terbit</label>
                            <input type="number" name="tahun_terbit" class="form-control"
                                value="<?= $editBook['tahun_terbit'] ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">ISBN</label>
                            <input type="text" name="isbn" class="form-control"
                                value="<?= htmlspecialchars($editBook['isbn']) ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Stok <span>*</span></label>
                            <input type="number" name="stok" class="form-control" min="0"
                                value="<?= $editBook['stok'] ?>" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-control">
                                <option value="tersedia" <?= $editBook['status']==='tersedia'?'selected':'' ?>>Tersedia
                                </option>
                                <option value="tidak" <?= $editBook['status']==='tidak'?'selected':'' ?>>Tidak Tersedia
                                </option>
                            </select>
                        </div>
                        <div class="form-group form-full">
                            <label class="form-label">Cover Buku</label>
                            <?php
                            $editCoverSrc = (!empty($editBook['cover']) && file_exists('../' . $editBook['cover']))
                                            ? '../' . htmlspecialchars($editBook['cover'])
                                            : '../uploads/covers/default-cover.png';
                            ?>
                            <div class="cover-upload-area">
                                <div class="cover-preview-wrap"
                                    onclick="document.getElementById('editCoverInput').click()">
                                    <img id="editCoverPreview" src="<?= $editCoverSrc ?>" alt="Cover">
                                    <div class="overlay-hint">
                                        <i class="fas fa-camera"></i>
                                        <span>Ganti Cover</span>
                                    </div>
                                </div>
                                <div class="cover-upload-meta">
                                    <button type="button" class="cover-upload-btn"
                                        onclick="document.getElementById('editCoverInput').click()">
                                        <i class="fas fa-cloud-upload-alt"></i> Ganti Cover
                                    </button>
                                    <div class="cover-filename" id="editCoverFilename"></div>
                                    <div class="cover-upload-hint">
                                        <i class="fas fa-info-circle"></i> Kosongkan jika tidak ingin mengubah cover
                                    </div>
                                </div>
                            </div>
                            <input type="file" id="editCoverInput" name="cover" accept=".jpg,.jpeg,.png"
                                style="display:none"
                                onchange="previewCover(this,'editCoverPreview','editCoverFilename')">
                        </div>
                        <div class="form-group form-full">
                            <label class="form-label">Deskripsi</label>
                            <textarea name="deskripsi"
                                class="form-control"><?= htmlspecialchars($editBook['deskripsi']) ?></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <a href="buku.php" class="btn-ghost"><i class="fas fa-times"></i> Batal</a>
                    <button type="submit" name="edit" class="btn-primary"><i class="fas fa-save"></i> Simpan
                        Perubahan</button>
                </div>
            </form>
        </div>
    </div>
    <script>
    document.getElementById('editModal').style.display = 'flex';
    </script>
    <?php endif; ?>

    <script>
    function previewCover(input, previewId, filenameId) {
        const preview = document.getElementById(previewId);
        const fnLabel = document.getElementById(filenameId);
        if (!input.files || !input.files[0]) return;

        const file = input.files[0];
        const allowedTypes = ['image/jpeg', 'image/png'];

        if (!allowedTypes.includes(file.type)) {
            alert('Hanya file JPG atau PNG yang diizinkan.');
            input.value = '';
            return;
        }

        if (file.size > 2 * 1024 * 1024) {
            alert('Ukuran file melebihi 2 MB.');
            input.value = '';
            return;
        }

        const reader = new FileReader();
        reader.onload = (e) => {
            preview.src = e.target.result;
        };
        reader.readAsDataURL(file);

        if (fnLabel) {
            fnLabel.textContent = '📎 ' + file.name;
            fnLabel.style.display = 'block';
        }
    }

    // Tutup modal dengan ESC
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.getElementById('addModal').style.display = 'none';
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
