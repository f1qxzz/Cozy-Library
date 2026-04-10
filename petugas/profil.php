<?php
require_once '../config/database.php';
require_once '../includes/session.php';
requirePetugas();

$conn = getConnection();

$msg     = '';
$msgType = '';
$id      = getPenggunaId();

// Ambil data user
$stmt = $conn->prepare("SELECT * FROM pengguna WHERE id_pengguna=?");
$stmt->bind_param("i", $id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Data header diambil dari $user yang sudah ada (tidak perlu query ulang)
$initialsHeader = '';
foreach (explode(' ', trim($user['nama_pengguna'] ?? getPenggunaName())) as $w) {
    $initialsHeader .= strtoupper(mb_substr($w, 0, 1));
    if (strlen($initialsHeader) >= 2) break;
}
$fotoPathHeader = (!empty($user['foto']) && file_exists('../' . $user['foto']))
            ? '../' . htmlspecialchars($user['foto'])
            : null;

/* ================= UPLOAD FOTO ================= */
if (isset($_POST['upload_foto'])) {
    $adaFile = isset($_FILES['foto']) && $_FILES['foto']['error'] !== UPLOAD_ERR_NO_FILE;

    if (!$adaFile) {
        $msg = 'Pilih file foto terlebih dahulu.';
        $msgType = 'danger';
    } elseif ($_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
        $msg = 'Upload gagal, coba lagi.';
        $msgType = 'danger';
    } else {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
        $ftype = mime_content_type($_FILES['foto']['tmp_name']);
        $fsize = $_FILES['foto']['size'];

        if (!in_array($ftype, $allowedTypes)) {
            $msg = 'Format tidak didukung. Gunakan JPG, PNG, atau WebP.';
            $msgType = 'danger';
        } elseif ($fsize > 2 * 1024 * 1024) {
            $msg = 'Ukuran file melebihi 2 MB.';
            $msgType = 'danger';
        } else {
            $ext     = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'][$ftype];
            $newName = 'foto_' . $id . '_' . time() . '.' . $ext;
            $dest    = '../uploads/foto_profil/' . $newName;

            if (!is_dir('../uploads/foto_profil/')) {
                mkdir('../uploads/foto_profil/', 0755, true);
            }

            if (move_uploaded_file($_FILES['foto']['tmp_name'], $dest)) {
                // Hapus foto lama jika ada
                if (!empty($user['foto']) && file_exists('../' . $user['foto'])) {
                    unlink('../' . $user['foto']);
                }

                $s = $conn->prepare("UPDATE pengguna SET foto=? WHERE id_pengguna=?");
                $fotoPath = 'uploads/foto_profil/' . $newName;
                $s->bind_param("si", $fotoPath, $id);
                if ($s->execute()) {
                    $user['foto'] = $fotoPath;
                    $msg = 'Foto profil berhasil diperbarui!';
                    $msgType = 'success';
                } else {
                    unlink($dest);
                    $msg = 'Gagal menyimpan foto ke database.';
                    $msgType = 'danger';
                }
                $s->close();
            } else {
                $msg = 'Gagal memindahkan file foto.';
                $msgType = 'danger';
            }
        }
    }
}

/* ================= UPDATE PROFIL ================= */
if (isset($_POST['update'])) {
    $nama  = trim($_POST['nama_pengguna']);
    $email = trim($_POST['email']);

    $s = $conn->prepare("UPDATE pengguna SET nama_pengguna=?, email=? WHERE id_pengguna=?");
    $s->bind_param("ssi", $nama, $email, $id);
    $ok = $s->execute();
    $s->close();

    $msg     = $ok ? 'Profil berhasil diperbarui!' : 'Gagal memperbarui profil!';
    $msgType = $ok ? 'success' : 'danger';

    if ($ok) {
        $stmt = $conn->prepare("SELECT * FROM pengguna WHERE id_pengguna=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}

/* ================= UBAH PASSWORD ================= */
if (isset($_POST['change_pass'])) {
    $old     = $_POST['old_password'];
    $new     = $_POST['new_password'];
    $confirm = $_POST['confirm_password'];

    if ($new !== $confirm) {
        $msg = 'Konfirmasi password tidak cocok!';
        $msgType = 'danger';
    } elseif (!password_verify($old, $user['password'])) {
        $msg = 'Password lama salah!';
        $msgType = 'danger';
    } else {
        $hash = password_hash($new, PASSWORD_DEFAULT);
        $s = $conn->prepare("UPDATE pengguna SET password=? WHERE id_pengguna=?");
        $s->bind_param("si", $hash, $id);
        $ok = $s->execute();
        $s->close();

        $msg     = $ok ? 'Password berhasil diubah!' : 'Gagal mengubah password!';
        $msgType = $ok ? 'success' : 'danger';
    }
}

// Inisial untuk avatar fallback
$initials = '';
foreach (explode(' ', trim($user['nama_pengguna'])) as $w) {
    $initials .= strtoupper(mb_substr($w, 0, 1));
    if (strlen($initials) >= 2) break;
}

$fotoSrc = (!empty($user['foto']) && file_exists('../' . $user['foto']))
           ? '../' . htmlspecialchars($user['foto'])
           : null;

$page_title = 'Profil Saya';
$page_sub   = 'Kelola informasi akun';
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="../assets/icons/cozy-tp.png" type="image/png">
    <title>Profil — Petugas Cozy-Library</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/petugas/petugas.css?v=<?= @filemtime('../assets/css/petugas/petugas.css') ?: time() ?>">
    <link rel="stylesheet" href="../assets/css/petugas/profil.css?v=<?= @filemtime('../assets/css/petugas/profil.css') ?: time() ?>">
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
                <div class="profil-alert <?= $msgType ?>">
                    <i class="fas <?= $msgType === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
                    <?= htmlspecialchars($msg) ?>
                </div>
                <?php endif; ?>

                <div class="profil-wrap">
                    <!-- LEFT COLUMN - Profile Card -->
                    <div class="profil-sidebar">
                        <!-- ID Card -->
                        <div class="id-card">
                            <div class="id-card-banner"></div>
                            <div class="id-card-body">
                                <!-- Avatar -->
                                <label for="fotoInput" style="display:block;width:fit-content;margin:0 auto">
                                    <div class="avatar-ring">
                                        <?php if ($fotoSrc): ?>
                                        <img src="<?= $fotoSrc ?>" alt="Foto Profil" class="avatar-img"
                                            id="avatarPreview">
                                        <?php else: ?>
                                        <div class="avatar-initials" id="avatarInitials">
                                            <?= htmlspecialchars($initials) ?></div>
                                        <img src="" alt="" class="avatar-img" id="avatarPreview" style="display:none">
                                        <?php endif; ?>
                                        <div class="avatar-overlay">
                                            <i class="fas fa-camera"></i>
                                            <span>Ganti</span>
                                        </div>
                                        <div class="avatar-cam">
                                            <i class="fas fa-camera"></i>
                                        </div>
                                    </div>
                                </label>

                                <div class="profil-name"><?= htmlspecialchars($user['nama_pengguna']) ?></div>
                                <div class="profil-role-badge">Petugas Perpustakaan</div>

                                <div class="id-meta">
                                    <div class="id-meta-row">
                                        <div class="id-meta-icon">
                                            <i class="fas fa-envelope"></i>
                                        </div>
                                        <div class="id-meta-content">
                                            <div class="id-meta-label">Email</div>
                                            <div class="id-meta-val"><?= htmlspecialchars($user['email'] ?? '—') ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="id-meta-row">
                                        <div class="id-meta-icon">
                                            <i class="fas fa-user"></i>
                                        </div>
                                        <div class="id-meta-content">
                                            <div class="id-meta-label">Username</div>
                                            <div class="id-meta-val"><?= htmlspecialchars($user['username']) ?></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Photo upload form -->
                        <div class="foto-upload-card">
                            <div class="foto-upload-title">
                                <i class="fas fa-cloud-upload-alt"></i>
                                Foto Profil
                            </div>
                            <form method="POST" enctype="multipart/form-data" id="fotoForm">
                                <div class="foto-drop-zone" id="dropZone"
                                    onclick="document.getElementById('fotoInput').click()">
                                    <div class="foto-drop-icon">
                                        <i class="fas fa-images"></i>
                                    </div>
                                    <div class="foto-drop-label">
                                        Seret foto ke sini atau<br><strong>klik untuk memilih</strong>
                                    </div>
                                    <div class="foto-hint">JPG, PNG, WebP · Maks. 2 MB</div>
                                    <div class="foto-filename" id="fotoFilename"></div>
                                </div>
                                <input type="file" id="fotoInput" name="foto" accept=".jpg,.jpeg,.png,.webp"
                                    style="display:none">
                                <button type="submit" name="upload_foto" class="btn-upload">
                                    <i class="fas fa-upload"></i>
                                    Simpan Foto
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- RIGHT COLUMN - Forms -->
                    <div class="profil-forms">
                        <!-- Edit Info -->
                        <div class="form-card">
                            <div class="form-card-header">
                                <div class="form-card-icon">
                                    <i class="fas fa-user-edit"></i>
                                </div>
                                <div>
                                    <div class="form-card-title">Informasi Akun</div>
                                    <div class="form-card-sub">Perbarui nama dan alamat email</div>
                                </div>
                            </div>
                            <form method="POST">
                                <div class="form-card-body">
                                    <div class="field-group">
                                        <label class="field-label">Nama Lengkap <span>*</span></label>
                                        <input type="text" name="nama_pengguna" class="field-input"
                                            value="<?= htmlspecialchars($user['nama_pengguna']) ?>" required
                                            placeholder="Masukkan nama lengkap">
                                    </div>
                                    <div class="field-group">
                                        <label class="field-label">Alamat Email</label>
                                        <input type="email" name="email" class="field-input"
                                            value="<?= htmlspecialchars($user['email'] ?? '') ?>"
                                            placeholder="contoh@email.com">
                                    </div>
                                    <div class="field-group">
                                        <label class="field-label">Username</label>
                                        <input type="text" class="field-input"
                                            value="<?= htmlspecialchars($user['username']) ?>" readonly>
                                        <div class="field-note">
                                            <i class="fas fa-info-circle"></i>
                                            Username tidak dapat diubah
                                        </div>
                                    </div>
                                </div>
                                <div class="form-card-footer">
                                    <button type="submit" name="update" class="btn-save">
                                        <i class="fas fa-save"></i>
                                        Simpan Perubahan
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- Change Password -->
                        <div class="form-card">
                            <div class="form-card-header">
                                <div class="form-card-icon">
                                    <i class="fas fa-lock"></i>
                                </div>
                                <div>
                                    <div class="form-card-title">Ubah Password</div>
                                    <div class="form-card-sub">Gunakan password yang kuat dan unik</div>
                                </div>
                            </div>
                            <form method="POST">
                                <div class="form-card-body">
                                    <div class="field-group">
                                        <label class="field-label">Password Saat Ini <span>*</span></label>
                                        <input type="password" name="old_password" class="field-input" required
                                            placeholder="Masukkan password lama">
                                    </div>
                                    <div class="field-row">
                                        <div class="field-group">
                                            <label class="field-label">Password Baru <span>*</span></label>
                                            <input type="password" name="new_password" class="field-input" required
                                                id="newPassInput" placeholder="Min. 8 karakter"
                                                oninput="checkStrength(this.value)">
                                            <div class="pass-strength" id="strengthBars">
                                                <div class="strength-bar" id="bar1"></div>
                                                <div class="strength-bar" id="bar2"></div>
                                                <div class="strength-bar" id="bar3"></div>
                                                <div class="strength-bar" id="bar4"></div>
                                            </div>
                                            <div class="strength-text" id="strengthText"></div>
                                        </div>
                                        <div class="field-group">
                                            <label class="field-label">Konfirmasi Password <span>*</span></label>
                                            <input type="password" name="confirm_password" class="field-input" required
                                                id="confirmPassInput" placeholder="Ulangi password baru"
                                                oninput="checkMatch()">
                                            <div class="field-note" id="matchNote" style="display:none"></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-card-footer">
                                    <button type="submit" name="change_pass" class="btn-save">
                                        <i class="fas fa-key"></i>
                                        Ubah Password
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
    // Foto upload handling
    const fotoInput = document.getElementById('fotoInput');
    const avatarPreview = document.getElementById('avatarPreview');
    const avatarInitials = document.getElementById('avatarInitials');
    const fotoFilename = document.getElementById('fotoFilename');
    const dropZone = document.getElementById('dropZone');

    fotoInput.addEventListener('change', function() {
        handleFile(this.files[0]);
    });

    function handleFile(file) {
        if (!file) return;

        const allowed = ['image/jpeg', 'image/png', 'image/webp'];
        if (!allowed.includes(file.type)) {
            alert('Format tidak didukung. Gunakan JPG, PNG, atau WebP.');
            fotoInput.value = '';
            return;
        }

        if (file.size > 2 * 1024 * 1024) {
            alert('Ukuran file melebihi 2 MB.');
            fotoInput.value = '';
            return;
        }

        const reader = new FileReader();
        reader.onload = e => {
            if (avatarPreview) {
                avatarPreview.src = e.target.result;
                avatarPreview.style.display = 'block';
                if (avatarInitials) avatarInitials.style.display = 'none';
            }
        };
        reader.readAsDataURL(file);

        if (fotoFilename) {
            fotoFilename.textContent = '📎 ' + file.name;
            fotoFilename.style.display = 'block';
        }
    }

    // Drag and drop
    if (dropZone) {
        dropZone.addEventListener('dragover', e => {
            e.preventDefault();
            dropZone.classList.add('dragover');
        });

        dropZone.addEventListener('dragleave', () => {
            dropZone.classList.remove('dragover');
        });

        dropZone.addEventListener('drop', e => {
            e.preventDefault();
            dropZone.classList.remove('dragover');

            const file = e.dataTransfer.files[0];
            if (file) {
                const dt = new DataTransfer();
                dt.items.add(file);
                fotoInput.files = dt.files;
                handleFile(file);
            }
        });
    }

    // Password strength indicator
    function checkStrength(val) {
        const bars = [
            document.getElementById('bar1'),
            document.getElementById('bar2'),
            document.getElementById('bar3'),
            document.getElementById('bar4')
        ];
        const txt = document.getElementById('strengthText');

        if (!bars[0] || !txt) return;

        bars.forEach(b => {
            if (b) b.className = 'strength-bar';
        });

        if (!val) {
            txt.textContent = '';
            return;
        }

        let score = 0;
        if (val.length >= 8) score++;
        if (/[A-Z]/.test(val)) score++;
        if (/[0-9]/.test(val)) score++;
        if (/[^A-Za-z0-9]/.test(val)) score++;

        const levels = ['', 'weak', 'medium', 'medium', 'strong'];
        const labels = ['', 'Lemah', 'Cukup', 'Baik', 'Kuat'];

        for (let i = 0; i < score; i++) {
            if (bars[i]) bars[i].classList.add(levels[score]);
        }

        txt.textContent = labels[score] ? 'Kekuatan: ' + labels[score] : '';
        txt.style.color = score === 4 ? 'var(--success-600)' : score >= 2 ? 'var(--warning-600)' : 'var(--danger-600)';
    }

    // Password match check
    function checkMatch() {
        const np = document.getElementById('newPassInput');
        const cp = document.getElementById('confirmPassInput');
        const note = document.getElementById('matchNote');

        if (!np || !cp || !note) return;

        if (!cp.value) {
            note.style.display = 'none';
            return;
        }

        note.style.display = 'flex';

        if (np.value === cp.value) {
            note.innerHTML = '<i class="fas fa-check-circle"></i> Password cocok';
            note.style.color = 'var(--success-600)';
        } else {
            note.innerHTML = '<i class="fas fa-exclamation-circle"></i> Password tidak cocok';
            note.style.color = 'var(--danger-600)';
        }
    }

    // Prevent form resubmission
    if (window.history.replaceState) {
        window.history.replaceState(null, null, window.location.href);
    }
    </script>
    <script src="../assets/js/script.js"></script>
</body>

</html>
