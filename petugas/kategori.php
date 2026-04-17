<?php
require_once '../config/database.php';
require_once '../includes/session.php';
requirePetugas();
$conn = getConnection();
$msg = ''; $msgType = '';


// Hitung statistik
$totalKategori = $conn->query("SELECT COUNT(*) as total FROM kategori")->fetch_assoc()['total'];
$totalBuku = $conn->query("SELECT COUNT(*) as total FROM buku")->fetch_assoc()['total'];

if (isset($_POST['add'])) {
    $nama = trim($_POST['nama_kategori']); $desk = trim($_POST['deskripsi']);
    $s = $conn->prepare("INSERT INTO kategori(nama_kategori,deskripsi) VALUES(?,?)");
    $s->bind_param("ss",$nama,$desk);
    $ok = $s->execute();
    $msg = $ok ? 'Kategori berhasil ditambahkan!' : 'Gagal!'; 
    $msgType = $ok ? 'success' : 'danger'; 
    $s->close();
}
if (isset($_POST['edit'])) {
    $id=(int)$_POST['id_kategori']; $nama=trim($_POST['nama_kategori']); $desk=trim($_POST['deskripsi']);
    $s=$conn->prepare("UPDATE kategori SET nama_kategori=?,deskripsi=? WHERE id_kategori=?");
    $s->bind_param("ssi",$nama,$desk,$id);
    $ok = $s->execute();
    $msg=$ok?'Kategori berhasil diperbarui!':'Gagal!'; 
    $msgType=$ok?'success':'danger';
    $s->close();
    if ($msg === 'Kategori berhasil diperbarui!') {
        unset($_GET['edit']);
    }
}
if (isset($_POST['delete'])) {
    $id=(int)$_POST['id_kategori'];
    $chk=$conn->query("SELECT COUNT(*) c FROM buku WHERE id_kategori=$id")->fetch_assoc()['c'];
    if($chk>0){ $msg='Kategori masih digunakan oleh buku!'; $msgType='warning'; }
    else {
        $s=$conn->prepare("DELETE FROM kategori WHERE id_kategori=?");
        $s->bind_param("i",$id);
        $ok = $s->execute();
        $msg=$ok?'Kategori berhasil dihapus!':'Gagal!'; 
        $msgType=$ok?'success':'danger';
        $s->close();
    }
}

$categories = $conn->query("SELECT k.*,(SELECT COUNT(*) FROM buku WHERE id_kategori=k.id_kategori) jml FROM kategori k ORDER BY nama_kategori");
$editCat = null;
if(isset($_GET['edit'])){
    $id=(int)$_GET['edit'];
    $s=$conn->prepare("SELECT * FROM kategori WHERE id_kategori=?");
    $s->bind_param("i",$id); $s->execute();
    $editCat=$s->get_result()->fetch_assoc();
}

$page_title = 'Manajemen Kategori';
$page_sub   = 'Kelola kategori buku Cozy-Library';
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="../assets/icons/cozy-tp.png" type="image/png">
    <title>Kategori — Petugas Cozy-Library</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/petugas/petugas.css?v=<?= @filemtime('../assets/css/petugas/petugas.css') ?: time() ?>">
    <link rel="stylesheet" href="../assets/css/petugas/kategori.css?v=<?= @filemtime('../assets/css/petugas/kategori.css') ?: time() ?>">
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

                <!-- Page Header -->
                <div class="page-header">
                    <div>
                        <h1 class="page-header-title">Manajemen Kategori</h1>
                        <p class="page-header-sub">Kelola kategori buku Cozy-Library</p>
                    </div>
                    <button class="btn-primary" onclick="document.getElementById('addModal').style.display='flex'">
                        <i class="fas fa-tag"></i>
                        Tambah Kategori Baru
                    </button>
                </div>

                <!-- Stats Cards -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon blue"><i class="fas fa-tags"></i></div>
                        <div class="stat-info">
                            <h3>Total Kategori</h3>
                            <div class="stat-number"><?= $totalKategori ?></div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon green"><i class="fas fa-book"></i></div>
                        <div class="stat-info">
                            <h3>Total Buku</h3>
                            <div class="stat-number"><?= $totalBuku ?></div>
                        </div>
                    </div>
                </div>

                <!-- Categories Table -->
                <div class="card">
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Nama Kategori</th>
                                    <th>Deskripsi</th>
                                    <th>Jumlah Buku</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($categories && $categories->num_rows > 0): $no = 1; while($r = $categories->fetch_assoc()): ?>
                                <tr>
                                    <td class="text-muted text-sm"><?= $no++ ?></td>
                                    <td><span class="fw-600"><?= htmlspecialchars($r['nama_kategori']) ?></span></td>
                                    <td><?= htmlspecialchars($r['deskripsi'] ?? '—') ?></td>
                                    <td><span class="badge badge-muted"><?= $r['jml'] ?> buku</span></td>
                                    <td>
                                        <div class="action-btns">
                                            <a href="?edit=<?= $r['id_kategori'] ?>" class="btn-action btn-edit"
                                                title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <form method="POST"
                                                onsubmit="return confirm('Yakin ingin menghapus kategori ini?')"
                                                style="display:inline">
                                                <input type="hidden" name="id_kategori"
                                                    value="<?= $r['id_kategori'] ?>">
                                                <button type="submit" name="delete" class="btn-action btn-delete"
                                                    title="Hapus">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; else: ?>
                                <tr>
                                    <td colspan="5">
                                        <div class="empty-state">
                                            <div class="empty-state-ico">🗂️</div>
                                            <div class="empty-state-title">Belum ada kategori</div>
                                            <p class="empty-state-sub">Klik tombol "Tambah Kategori" untuk menambahkan
                                                kategori pertama</p>
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
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title"><i class="fas fa-tag"
                        style="color: var(--primary-500); margin-right: 8px;"></i>Tambah Kategori Baru</h3>
                <button class="modal-close" onclick="document.getElementById('addModal').style.display='none'"><i
                        class="fas fa-times"></i></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="form-grid">
                        <div class="form-group form-full">
                            <label class="form-label">Nama Kategori <span
                                    style="color: var(--danger-500);">*</span></label>
                            <input type="text" name="nama_kategori" class="form-control" required
                                placeholder="Contoh: Fiksi, Sains, Sejarah...">
                        </div>
                        <div class="form-group form-full">
                            <label class="form-label">Deskripsi</label>
                            <textarea name="deskripsi" class="form-control" rows="3"
                                placeholder="Deskripsi singkat tentang kategori ini..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-ghost"
                        onclick="document.getElementById('addModal').style.display='none'" style="padding: 10px 20px;">
                        <i class="fas fa-times"></i> Batal
                    </button>
                    <button type="submit" name="add" class="btn-primary">
                        <i class="fas fa-save"></i> Simpan Kategori
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php if ($editCat): ?>
    <!-- EDIT MODAL -->
    <div id="editModal" class="modal-overlay" onclick="if(event.target===this)window.location.href='kategori.php'">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title"><i class="fas fa-edit"
                        style="color: var(--info-500); margin-right: 8px;"></i>Edit Kategori</h3>
                <a href="kategori.php" class="modal-close"><i class="fas fa-times"></i></a>
            </div>
            <form method="POST">
                <input type="hidden" name="id_kategori" value="<?= $editCat['id_kategori'] ?>">
                <div class="modal-body">
                    <div class="form-grid">
                        <div class="form-group form-full">
                            <label class="form-label">Nama Kategori <span
                                    style="color: var(--danger-500);">*</span></label>
                            <input type="text" name="nama_kategori" class="form-control"
                                value="<?= htmlspecialchars($editCat['nama_kategori']) ?>" required>
                        </div>
                        <div class="form-group form-full">
                            <label class="form-label">Deskripsi</label>
                            <textarea name="deskripsi" class="form-control"
                                rows="3"><?= htmlspecialchars($editCat['deskripsi'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <a href="kategori.php" class="btn-ghost" style="padding: 10px 20px;"><i class="fas fa-times"></i>
                        Batal</a>
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
