<?php
/**
 * Admin – Kelola Anggota
 */
require_once '../config/database.php';
require_once '../includes/session.php';
requireAdmin();
$conn = getConnection();
$msg = ''; $msgType = '';


// Hitung statistik
$totalAktif = $conn->query("SELECT COUNT(*) as total FROM anggota WHERE status='aktif'")->fetch_assoc()['total'];
$totalNonaktif = $conn->query("SELECT COUNT(*) as total FROM anggota WHERE status='nonaktif'")->fetch_assoc()['total'];
$totalAnggota = $conn->query("SELECT COUNT(*) as total FROM anggota")->fetch_assoc()['total'];

if (isset($_POST['add'])) {
    $nis=$_POST['nis']; $nama=$_POST['nama_anggota']; $uname=$_POST['username'];
    $pw=$_POST['password']; $email=$_POST['email']; $kelas=$_POST['kelas'];
    $chk=$conn->query("SELECT id_anggota FROM anggota WHERE username='$uname' OR nis='$nis'");
    if($chk->num_rows>0){ $msg='NIS atau Username sudah digunakan!'; $msgType='warning'; }
    else {
        $s=$conn->prepare("INSERT INTO anggota(nis,nama_anggota,username,password,email,kelas) VALUES(?,?,?,?,?,?)");
        $s->bind_param("ssssss",$nis,$nama,$uname,$pw,$email,$kelas);
        $msg=$s->execute()?'Anggota berhasil ditambahkan!':'Gagal: '.$conn->error;
        $msgType=$s->execute()?'success':'danger'; $s->close();
    }
}
if (isset($_POST['edit'])) {
    $id=(int)$_POST['id_anggota'];
    $nis=$_POST['nis']; $nama=$_POST['nama_anggota']; $email=$_POST['email'];
    $kelas=$_POST['kelas']; $status=$_POST['status'];
    if (!empty($_POST['password'])) {
        $pw=$_POST['password'];
        $s=$conn->prepare("UPDATE anggota SET nis=?,nama_anggota=?,email=?,kelas=?,status=?,password=? WHERE id_anggota=?");
        $s->bind_param("ssssssi",$nis,$nama,$email,$kelas,$status,$pw,$id);
    } else {
        $s=$conn->prepare("UPDATE anggota SET nis=?,nama_anggota=?,email=?,kelas=?,status=? WHERE id_anggota=?");
        $s->bind_param("sssssi",$nis,$nama,$email,$kelas,$status,$id);
    }
    $msg=$s->execute()?'Data diperbarui!':'Gagal!'; $msgType='success'; $s->close();
    if ($msg === 'Data diperbarui!') {
        unset($_GET['edit']);
    }
}
if (isset($_POST['delete'])) {
    $id=(int)$_POST['id_anggota'];
    $chk=$conn->query("SELECT COUNT(*) c FROM transaksi WHERE id_anggota=$id AND status_transaksi='Peminjaman'")->fetch_assoc()['c'];
    if($chk>0){ $msg='Anggota masih memiliki peminjaman aktif!'; $msgType='warning'; }
    else {
        $s=$conn->prepare("DELETE FROM anggota WHERE id_anggota=?");
        $s->bind_param("i",$id);
        $msg=$s->execute()?'Anggota dihapus!':'Gagal!'; $msgType='success'; $s->close();
    }
}
if (isset($_POST['reset_pw'])) {
    $id=(int)$_POST['id_anggota']; $pw=trim($_POST['new_password']);
    $s=$conn->prepare("UPDATE anggota SET password=? WHERE id_anggota=?");
    $s->bind_param("si",$pw,$id);
    $msg=$s->execute()?'Password direset!':'Gagal!'; $msgType='success'; $s->close();
}

$search=isset($_GET["search"])?trim($_GET["search"]):"";
$q="SELECT * FROM anggota";
if($search){$es=$conn->real_escape_string($search);$q.=" WHERE nama_anggota LIKE '%$es%' OR nis LIKE '%$es%' OR kelas LIKE '%$es%'";}
$q.=" ORDER BY id_anggota DESC";
$members=$conn->query($q);

$editMember=null;
if(isset($_GET['edit'])){
    $id=(int)$_GET['edit'];
    $s=$conn->prepare("SELECT * FROM anggota WHERE id_anggota=?");
    $s->bind_param("i",$id); $s->execute();
    $editMember=$s->get_result()->fetch_assoc();
}

$page_title = 'Manajemen Anggota';
$page_sub   = 'Kelola data anggota Cozy-Library';
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="../assets/icons/cozy-tp.png" type="image/png">
    <title>Anggota — Admin Cozy-Library</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin/dashboard.css?v=<?= @filemtime('../assets/css/admin/dashboard.css') ?: time() ?>">
    <link rel="stylesheet" href="../assets/css/admin/anggota.css?v=<?= @filemtime('../assets/css/admin/anggota.css') ?: time() ?>">
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
                        <h1 class="page-header-title">Manajemen Anggota</h1>
                        <p class="page-header-sub">Kelola data anggota Cozy-Library</p>
                    </div>
                    <button class="btn-primary" onclick="document.getElementById('addModal').style.display='flex'">
                        <i class="fas fa-user-plus"></i>
                        Tambah Anggota Baru
                    </button>
                </div>

                <!-- Stats Cards -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon blue"><i class="fas fa-users"></i></div>
                        <div class="stat-info">
                            <h3>Total Anggota</h3>
                            <div class="stat-number"><?= $totalAnggota ?></div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon green"><i class="fas fa-user-check"></i></div>
                        <div class="stat-info">
                            <h3>Aktif</h3>
                            <div class="stat-number"><?= $totalAktif ?></div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon amber"><i class="fas fa-user-clock"></i></div>
                        <div class="stat-info">
                            <h3>Nonaktif</h3>
                            <div class="stat-number"><?= $totalNonaktif ?></div>
                        </div>
                    </div>
                </div>

                <?php $print_title = 'Data Anggota Perpustakaan'; $print_total = $members ? $members->num_rows : 0; include '../includes/print_header.php'; ?>

                <!-- Filter & Table -->
                <div class="card">
                    <form method="GET" class="filter-bar">
                        <div class="search-wrap">
                            <input type="text" name="search" placeholder="Cari berdasarkan nama, NIS, atau kelas..."
                                value="<?= htmlspecialchars($search) ?>">
                        </div>
                        <button type="submit" class="btn-ghost btn-sm"><i class="fas fa-search"></i> Cari</button>
                        <?php if ($search): ?>
                        <a href="anggota.php" class="btn-ghost btn-sm"><i class="fas fa-times"></i> Reset</a>
                        <?php endif; ?>
                    </form>

                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>NIS</th>
                                    <th>Nama Lengkap</th>
                                    <th>Kelas</th>
                                    <th>Email</th>
                                    <th>Status</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if($members && $members->num_rows > 0): $no=1; while($r=$members->fetch_assoc()): ?>
                                <tr>
                                    <td class="text-muted text-sm"><?= $no++ ?></td>
                                    <td><span class="fw-600"><?= htmlspecialchars($r['nis']) ?></span></td>
                                    <td><?= htmlspecialchars($r['nama_anggota']) ?></td>
                                    <td><?= htmlspecialchars($r['kelas']) ?></td>
                                    <td><?= htmlspecialchars($r['email'] ?? '—') ?></td>
                                    <td>
                                        <span
                                            class="badge <?= $r['status'] === 'aktif' ? 'badge-aktif' : 'badge-nonaktif' ?>">
                                            <i
                                                class="fas <?= $r['status'] === 'aktif' ? 'fa-circle' : 'fa-circle' ?>"></i>
                                            <?= ucfirst($r['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-btns">
                                            <a href="?edit=<?= $r['id_anggota'] ?>" class="btn-action btn-edit"
                                                title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button class="btn-action btn-reset" title="Reset Password"
                                                onclick="showReset(<?= $r['id_anggota'] ?>,'<?= htmlspecialchars(addslashes($r['nama_anggota'])) ?>')">
                                                <i class="fas fa-key"></i>
                                            </button>
                                            <form method="POST"
                                                onsubmit="return confirm('Yakin ingin menghapus anggota ini?')"
                                                style="display:inline">
                                                <input type="hidden" name="id_anggota" value="<?= $r['id_anggota'] ?>">
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
                                    <td colspan="7">
                                        <div class="empty-state">
                                            <div class="empty-state-ico">👥</div>
                                            <div class="empty-state-title">Belum ada anggota</div>
                                            <p class="empty-state-sub">Klik tombol "Tambah Anggota" untuk menambahkan
                                                data anggota pertama</p>
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
                <h3 class="modal-title"><i class="fas fa-user-plus"
                        style="color: var(--primary-500); margin-right: 8px;"></i>Tambah Anggota Baru</h3>
                <button class="modal-close" onclick="document.getElementById('addModal').style.display='none'"><i
                        class="fas fa-times"></i></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">NIS <span style="color: var(--danger-500);">*</span></label>
                            <input type="text" name="nis" class="form-control" required placeholder="Contoh: 2023001">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Kelas <span style="color: var(--danger-500);">*</span></label>
                            <input type="text" name="kelas" class="form-control" required placeholder="Contoh: XII RPL">
                        </div>
                        <div class="form-group form-full">
                            <label class="form-label">Nama Lengkap <span
                                    style="color: var(--danger-500);">*</span></label>
                            <input type="text" name="nama_anggota" class="form-control" required
                                placeholder="Masukkan nama lengkap">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Username <span style="color: var(--danger-500);">*</span></label>
                            <input type="text" name="username" class="form-control" required
                                placeholder="Buat username">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Password <span style="color: var(--danger-500);">*</span></label>
                            <input type="password" name="password" class="form-control" required
                                placeholder="Min. 6 karakter">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" placeholder="email@sekolah.com">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-ghost"
                        onclick="document.getElementById('addModal').style.display='none'" style="padding: 10px 20px;">
                        <i class="fas fa-times"></i> Batal
                    </button>
                    <button type="submit" name="add" class="btn-primary">
                        <i class="fas fa-save"></i> Simpan Anggota
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php if ($editMember): ?>
    <!-- EDIT MODAL -->
    <div id="editModal" class="modal-overlay" onclick="if(event.target===this)window.location.href='anggota.php'">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title"><i class="fas fa-user-edit"
                        style="color: var(--info-500); margin-right: 8px;"></i>Edit Anggota</h3>
                <a href="anggota.php" class="modal-close"><i class="fas fa-times"></i></a>
            </div>
            <form method="POST">
                <input type="hidden" name="id_anggota" value="<?= $editMember['id_anggota'] ?>">
                <div class="modal-body">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">NIS <span style="color: var(--danger-500);">*</span></label>
                            <input type="text" name="nis" class="form-control"
                                value="<?= htmlspecialchars($editMember['nis']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Kelas <span style="color: var(--danger-500);">*</span></label>
                            <input type="text" name="kelas" class="form-control"
                                value="<?= htmlspecialchars($editMember['kelas']) ?>" required>
                        </div>
                        <div class="form-group form-full">
                            <label class="form-label">Nama Lengkap <span
                                    style="color: var(--danger-500);">*</span></label>
                            <input type="text" name="nama_anggota" class="form-control"
                                value="<?= htmlspecialchars($editMember['nama_anggota']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Username</label>
                            <input type="text" class="form-control"
                                value="<?= htmlspecialchars($editMember['username']) ?>" readonly
                                style="background: var(--neutral-100);">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control"
                                value="<?= htmlspecialchars($editMember['email'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-control">
                                <option value="aktif"
                                    <?= ($editMember['status'] ?? '') === 'aktif' ? 'selected' : '' ?>>Aktif</option>
                                <option value="nonaktif"
                                    <?= ($editMember['status'] ?? '') === 'nonaktif' ? 'selected' : '' ?>>Nonaktif
                                </option>
                            </select>
                        </div>
                        <div class="form-group form-full">
                            <label class="form-label">Password Baru</label>
                            <input type="password" name="password" class="form-control"
                                placeholder="Kosongkan jika tidak ingin mengubah">
                            <small style="color: var(--neutral-500); font-size: 0.7rem;">Isi hanya jika ingin mengganti
                                password</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <a href="anggota.php" class="btn-ghost" style="padding: 10px 20px;"><i class="fas fa-times"></i>
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

    <!-- RESET PASSWORD MODAL -->
    <div id="resetModal" class="modal-overlay" onclick="if(event.target===this)this.style.display='none'">
        <div class="modal" style="max-width: 400px;">
            <div class="modal-header">
                <h3 class="modal-title"><i class="fas fa-key"
                        style="color: var(--warning-500); margin-right: 8px;"></i>Reset Password</h3>
                <button class="modal-close" onclick="document.getElementById('resetModal').style.display='none'"><i
                        class="fas fa-times"></i></button>
            </div>
            <form method="POST">
                <input type="hidden" name="id_anggota" id="resetId">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label">Password Baru <span style="color: var(--danger-500);">*</span></label>
                        <input type="password" name="new_password" class="form-control" required
                            placeholder="Minimal 6 karakter">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-ghost"
                        onclick="document.getElementById('resetModal').style.display='none'"
                        style="padding: 10px 20px;">
                        <i class="fas fa-times"></i> Batal
                    </button>
                    <button type="submit" name="reset_pw" class="btn-primary"
                        style="background: linear-gradient(135deg, var(--warning-500), var(--warning-600));">
                        <i class="fas fa-key"></i> Reset Password
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
    function showReset(id, nama) {
        document.getElementById('resetId').value = id;
        document.getElementById('resetModal').style.display = 'flex';
    }

    // Tutup modal dengan ESC
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.getElementById('addModal').style.display = 'none';
            document.getElementById('resetModal').style.display = 'none';
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
