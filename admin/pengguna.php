<?php
require_once '../config/database.php';
require_once '../includes/session.php';
requireAdmin();

$conn = getConnection();
$msg = '';
$msgType = '';

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

// Hitung statistik
$totalAdmin = $conn->query("SELECT COUNT(*) as total FROM pengguna WHERE level='admin'")->fetch_assoc()['total'];
$totalPetugas = $conn->query("SELECT COUNT(*) as total FROM pengguna WHERE level='petugas'")->fetch_assoc()['total'];
$totalPengguna = $conn->query("SELECT COUNT(*) as total FROM pengguna")->fetch_assoc()['total'];

// CRUD Operations
if (isset($_POST['add'])) {
    $u = trim($_POST['username']);
    $n = trim($_POST['nama_pengguna']);
    $e = trim($_POST['email']);
    
    // FIX: Paksa level menjadi petugas agar admin tidak bisa membuat admin baru
    $lv = 'petugas'; 
    $p = password_hash($_POST['password'], PASSWORD_DEFAULT);
    
    $stmt = $conn->prepare("SELECT id_pengguna FROM pengguna WHERE username=?");
    $stmt->bind_param("s", $u);
    $stmt->execute();
    $stmt->store_result();
    
    if ($stmt->num_rows > 0) {
        $msg = 'Username sudah digunakan!';
        $msgType = 'warning';
    } else {
        $stmt = $conn->prepare("INSERT INTO pengguna(username, password, nama_pengguna, email, level) VALUES(?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $u, $p, $n, $e, $lv);
        if ($stmt->execute()) {
            $msg = 'Petugas berhasil ditambahkan!';
            $msgType = 'success';
        } else {
            $msg = 'Gagal: ' . $conn->error;
            $msgType = 'danger';
        }
    }
    $stmt->close();
}

if (isset($_POST['delete'])) {
    $id = (int)$_POST['id_pengguna'];
    if ($id == getPenggunaId()) {
        $msg = 'Tidak bisa hapus akun sendiri!';
        $msgType = 'warning';
    } else {
        $stmt = $conn->prepare("DELETE FROM pengguna WHERE id_pengguna=?");
        $stmt->bind_param("i", $id);
        $msg = $stmt->execute() ? 'Pengguna dihapus!' : 'Gagal!';
        $msgType = $stmt->execute() ? 'success' : 'danger';
        $stmt->close();
    }
}

if (isset($_POST['edit'])) {
    $id = (int)$_POST['id_pengguna'];
    $n = trim($_POST['nama_pengguna']);
    $e = trim($_POST['email']);
    $lv = $_POST['level'];
    
    $stmt = $conn->prepare("UPDATE pengguna SET nama_pengguna=?, email=?, level=? WHERE id_pengguna=?");
    $stmt->bind_param("sssi", $n, $e, $lv, $id);
    $msg = $stmt->execute() ? 'Data diperbarui!' : 'Gagal!';
    $msgType = $stmt->execute() ? 'success' : 'danger';
    $stmt->close();
}

if (isset($_POST['reset_pw'])) {
    $id = (int)$_POST['id_pengguna'];
    $pw = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
    
    $stmt = $conn->prepare("UPDATE pengguna SET password=? WHERE id_pengguna=?");
    $stmt->bind_param("si", $pw, $id);
    $msg = $stmt->execute() ? 'Password berhasil direset!' : 'Gagal!';
    $msgType = $stmt->execute() ? 'success' : 'danger';
    $stmt->close();
}

$users = $conn->query("SELECT * FROM pengguna ORDER BY 
    CASE level 
        WHEN 'admin' THEN 1 
        WHEN 'petugas' THEN 2 
        ELSE 3 
    END, nama_pengguna");

$editUser = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $s = $conn->prepare("SELECT * FROM pengguna WHERE id_pengguna=?");
    $s->bind_param("i", $id);
    $s->execute();
    $editUser = $s->get_result()->fetch_assoc();
    $s->close();
}

$page_title = 'Manajemen Pengguna';
$page_sub = 'Kelola akun admin dan petugas';
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengguna — Admin Cozy-Library</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin/dashboard.css?v=<?= @filemtime('../assets/css/admin/dashboard.css') ?: time() ?>">
    <link rel="stylesheet" href="../assets/css/admin/pengguna.css?v=<?= @filemtime('../assets/css/admin/pengguna.css') ?: time() ?>">
    <link rel="stylesheet" href="../assets/css/responsive-fix.css?v=<?= @filemtime('../assets/css/responsive-fix.css') ?: time() ?>">
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

                <div class="page-header">
                    <div>
                        <h1 class="page-header-title">Manajemen Pengguna</h1>
                        <p class="page-header-sub">Kelola akun admin dan petugas Cozy-Library</p>
                    </div>
                    <button class="btn-primary" onclick="document.getElementById('addModal').style.display='flex'">
                        <i class="fas fa-plus"></i>
                        Tambah Petugas Baru
                    </button>
                </div>

                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon blue"><i class="fas fa-users"></i></div>
                        <div class="stat-info">
                            <h3>Total Pengguna</h3>
                            <div class="stat-number"><?= $totalPengguna ?></div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon purple"><i class="fas fa-crown"></i></div>
                        <div class="stat-info">
                            <h3>Administrator</h3>
                            <div class="stat-number"><?= $totalAdmin ?></div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon green"><i class="fas fa-user-tie"></i></div>
                        <div class="stat-info">
                            <h3>Petugas</h3>
                            <div class="stat-number"><?= $totalPetugas ?></div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Nama Lengkap</th>
                                    <th>Username</th>
                                    <th>Email</th>
                                    <th>Level</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if($users && $users->num_rows > 0): $no=1; while($r=$users->fetch_assoc()): ?>
                                <tr>
                                    <td class="text-muted text-sm"><?= $no++ ?></td>
                                    <td><span class="fw-600"><?= htmlspecialchars($r['nama_pengguna']) ?></span></td>
                                    <td>@<?= htmlspecialchars($r['username']) ?></td>
                                    <td><?= htmlspecialchars($r['email'] ?? '—') ?></td>
                                    <td>
                                        <span
                                            class="badge <?= $r['level'] === 'admin' ? 'badge-admin' : 'badge-petugas' ?>">
                                            <i class="fas <?= $r['level'] === 'admin' ? 'fa-crown' : 'fa-user' ?>"></i>
                                            <?= ucfirst($r['level']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-btns">
                                            <a href="?edit=<?= $r['id_pengguna'] ?>" class="btn-action btn-edit"
                                                title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button class="btn-action btn-reset" title="Reset Password"
                                                onclick="showReset(<?= $r['id_pengguna'] ?>,'<?= htmlspecialchars(addslashes($r['nama_pengguna'])) ?>')">
                                                <i class="fas fa-key"></i>
                                            </button>
                                            <?php if($r['id_pengguna'] != getPenggunaId()): ?>
                                            <form method="POST" onsubmit="return confirm('Hapus pengguna ini?')"
                                                style="display:inline">
                                                <input type="hidden" name="id_pengguna"
                                                    value="<?= $r['id_pengguna'] ?>">
                                                <button type="submit" name="delete" class="btn-action btn-delete"
                                                    title="Hapus">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; else: ?>
                                <tr>
                                    <td colspan="6">
                                        <div class="empty-state">
                                            <div class="empty-state-ico">👤</div>
                                            <div class="empty-state-title">Belum ada pengguna</div>
                                            <p class="empty-state-sub">Klik tombol "Tambah Pengguna" untuk menambahkan
                                            </p>
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

    <div id="addModal" class="modal-overlay" onclick="if(event.target===this)this.style.display='none'">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title"><i class="fas fa-user-plus"
                        style="color: var(--primary-500); margin-right: 8px;"></i>Tambah Petugas Baru</h3>
                <button class="modal-close" onclick="document.getElementById('addModal').style.display='none'"><i
                        class="fas fa-times"></i></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="form-grid">
                        <div class="form-group form-full">
                            <label class="form-label">Nama Lengkap <span
                                    style="color: var(--danger-500);">*</span></label>
                            <input type="text" name="nama_pengguna" class="form-control" required
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
                            <input type="email" name="email" class="form-control" placeholder="nama@email.com">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Level <span style="color: var(--danger-500);">*</span></label>
                            <input type="text" class="form-control" value="👤 Petugas" readonly 
                                style="background: var(--neutral-100); cursor: not-allowed;">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-edit"
                        onclick="document.getElementById('addModal').style.display='none'" style="padding: 10px 20px;">
                        <i class="fas fa-times"></i> Batal
                    </button>
                    <button type="submit" name="add" class="btn-primary">
                        <i class="fas fa-save"></i> Simpan Petugas
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php if ($editUser): ?>
    <div id="editModal" class="modal-overlay" onclick="if(event.target===this)window.location.href='pengguna.php'">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title"><i class="fas fa-user-edit"
                        style="color: var(--info-500); margin-right: 8px;"></i>Edit Pengguna</h3>
                <a href="pengguna.php" class="modal-close"><i class="fas fa-times"></i></a>
            </div>
            <form method="POST">
                <input type="hidden" name="id_pengguna" value="<?= $editUser['id_pengguna'] ?>">
                <div class="modal-body">
                    <div class="form-grid">
                        <div class="form-group form-full">
                            <label class="form-label">Nama Lengkap <span
                                    style="color: var(--danger-500);">*</span></label>
                            <input type="text" name="nama_pengguna" class="form-control"
                                value="<?= htmlspecialchars($editUser['nama_pengguna']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Username</label>
                            <input type="text" class="form-control"
                                value="<?= htmlspecialchars($editUser['username']) ?>" readonly
                                style="background: var(--neutral-100);">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control"
                                value="<?= htmlspecialchars($editUser['email'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Level <span style="color: var(--danger-500);">*</span></label>
                            <select name="level" class="form-control">
                                <option value="petugas" <?= $editUser['level'] === 'petugas' ? 'selected' : '' ?>>👤
                                    Petugas</option>
                                <option value="admin" <?= $editUser['level'] === 'admin' ? 'selected' : '' ?>>👑 Admin
                                </option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <a href="pengguna.php" class="btn-edit" style="padding: 10px 20px;"><i class="fas fa-times"></i>
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

    <div id="resetModal" class="modal-overlay" onclick="if(event.target===this)this.style.display='none'">
        <div class="modal" style="max-width: 400px;">
            <div class="modal-header">
                <h3 class="modal-title"><i class="fas fa-key"
                        style="color: var(--warning-500); margin-right: 8px;"></i>Reset Password</h3>
                <button class="modal-close" onclick="document.getElementById('resetModal').style.display='none'"><i
                        class="fas fa-times"></i></button>
            </div>
            <form method="POST">
                <input type="hidden" name="id_pengguna" id="resetId">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label">Password Baru <span style="color: var(--danger-500);">*</span></label>
                        <input type="password" name="new_password" class="form-control" required
                            placeholder="Minimal 6 karakter">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-edit"
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