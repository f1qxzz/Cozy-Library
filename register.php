<?php
require_once 'config/database.php';
require_once 'includes/session.php';
initSession();

if (isPenggunaLoggedIn()) {
    header('Location: ' . (isAdmin() ? 'admin/dashboard.php' : 'petugas/dashboard.php'));
    exit;
}
if (isAnggotaLoggedIn()) {
    header('Location: anggota/dashboard.php');
    exit;
}

$error   = '';
$success = '';
$old     = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $old = [
        'nis'      => trim($_POST['nis'] ?? ''),
        'nama'     => trim($_POST['nama_anggota'] ?? ''),
        'username' => trim($_POST['username'] ?? ''),
        'email'    => trim($_POST['email'] ?? ''),
        'kelas'    => trim($_POST['kelas'] ?? ''),
    ];
    $password = trim($_POST['password'] ?? '');
    $conn = getConnection();

    if (empty($old['nis']) || empty($old['nama']) || empty($old['username']) || empty($old['kelas']) || empty($password)) {
        $error = 'Semua field bertanda * wajib diisi!';
    } elseif (strlen($password) < 6) {
        $error = 'Password minimal 6 karakter.';
    } elseif (!empty($old['email']) && !filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $error = 'Email tidak valid.';
    } else {
        $chkStmt = $conn->prepare("SELECT id_anggota FROM anggota WHERE username = ? OR nis = ? OR (email <> '' AND email = ?) LIMIT 1");
        $chkStmt->bind_param('sss', $old['username'], $old['nis'], $old['email']);
        $chkStmt->execute();
        $chkResult = $chkStmt->get_result();

        if ($chkResult && $chkResult->num_rows > 0) {
            $error = 'NIS, username, atau email sudah terdaftar!';
            $chkStmt->close();
        } else {
            $chkStmt->close();
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO anggota (nis, nama_anggota, username, password, email, kelas) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param('ssssss', $old['nis'], $old['nama'], $old['username'], $hashedPassword, $old['email'], $old['kelas']);
            if ($stmt->execute()) {
                $stmt->close();
                closeConnection($conn);
                header('Location: login.php?registered=1');
                exit;
            } else {
                $error = 'Registrasi gagal. Silakan coba lagi.';
                $stmt->close();
            }
        }
    }
    closeConnection($conn);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="assets/icons/cozy-tp.png" type="image/png">
    <title>Daftar Anggota — Cozy-Library</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300..800;1,9..40,300..800&family=Playfair+Display:ital,wght@0,400..900;1,400..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/register.css">
</head>
<body class="page-transition">
    <div class="register-container">
        <div class="register-left">
            <div class="register-left-content">
                <div class="register-icon">
                    <img src="assets/icons/cozy-library.png" alt="Cozy-Library" style="width:72px;height:72px;object-fit:contain;border-radius:12px;">
                </div>
                <h1 class="register-title-large">
                    Bergabung dengan<br>
                    <span>Cozy-Library</span>
                </h1>
                <p class="register-description">
                    Daftar sebagai anggota dan nikmati kemudahan mengakses ribuan koleksi buku dari mana saja, kapan saja.
                </p>

                <ul class="benefits-list">
                    <li class="benefit-item">
                        <div class="benefit-icon"><i class="fas fa-book"></i></div>
                        <span>Akses koleksi buku digital kapan saja</span>
                    </li>
                    <li class="benefit-item">
                        <div class="benefit-icon"><i class="fas fa-pen"></i></div>
                        <span>Ajukan peminjaman langsung dari sistem</span>
                    </li>
                    <li class="benefit-item">
                        <div class="benefit-icon"><i class="fas fa-chart-line"></i></div>
                        <span>Pantau riwayat pinjaman dan status denda</span>
                    </li>
                    <li class="benefit-item">
                        <div class="benefit-icon"><i class="fas fa-star"></i></div>
                        <span>Tulis ulasan dan rating untuk buku favorit</span>
                    </li>
                    <li class="benefit-item">
                        <div class="benefit-icon"><i class="fas fa-bell"></i></div>
                        <span>Notifikasi jatuh tempo pengembalian buku</span>
                    </li>
                </ul>

                <a href="index.php" class="back-link">
                    <i class="fas fa-arrow-left"></i>
                    Kembali ke Beranda
                </a>
            </div>
        </div>

        <div class="register-right">
            <div class="register-box">
                <div class="register-header">
                    <div class="register-header-icon">
                        <img src="assets/icons/cozy-tp.png" alt="Cozy-Library" style="width:52px;height:52px;object-fit:contain;border-radius:10px;">
                    </div>
                    <h2 class="register-header-title">Buat Akun Baru</h2>
                    <p class="register-header-subtitle">Isi data diri Anda untuk mendaftar sebagai anggota</p>
                </div>

                <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
                <?php endif; ?>

                <form method="POST" novalidate>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">NIS <span>*</span></label>
                            <div class="input-wrapper">
                                <i class="fas fa-id-card input-icon"></i>
                                <input type="text" name="nis" class="form-control" placeholder="Nomor Induk Siswa"
                                    required value="<?= htmlspecialchars($old['nis'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Kelas <span>*</span></label>
                            <div class="input-wrapper">
                                <i class="fas fa-school input-icon"></i>
                                <input type="text" name="kelas" class="form-control" placeholder="Contoh: XII RPL"
                                    required value="<?= htmlspecialchars($old['kelas'] ?? '') ?>">
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Nama Lengkap <span>*</span></label>
                        <div class="input-wrapper">
                            <i class="fas fa-user input-icon"></i>
                            <input type="text" name="nama_anggota" class="form-control"
                                placeholder="Nama sesuai data sekolah" required
                                value="<?= htmlspecialchars($old['nama'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Username <span>*</span></label>
                            <div class="input-wrapper">
                                <i class="fas fa-at input-icon"></i>
                                <input type="text" name="username" class="form-control" placeholder="Buat username unik"
                                    required value="<?= htmlspecialchars($old['username'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Email</label>
                            <div class="input-wrapper">
                                <i class="fas fa-envelope input-icon"></i>
                                <input type="email" name="email" class="form-control" placeholder="email@sekolah.com"
                                    value="<?= htmlspecialchars($old['email'] ?? '') ?>">
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Password <span>*</span></label>
                        <div class="input-wrapper">
                            <i class="fas fa-lock input-icon"></i>
                            <input type="password" name="password" id="password" class="form-control"
                                placeholder="Minimal 6 karakter" required minlength="6"
                                oninput="checkPasswordStrength(this.value)">
                            <button type="button" class="password-toggle" onclick="togglePassword()">
                                <i class="fas fa-eye" id="toggleIcon"></i>
                            </button>
                        </div>

                        <div class="password-strength">
                            <div class="strength-bar" id="bar1"></div>
                            <div class="strength-bar" id="bar2"></div>
                            <div class="strength-bar" id="bar3"></div>
                            <div class="strength-bar" id="bar4"></div>
                        </div>
                        <div class="strength-text" id="strengthText"></div>
                    </div>

                    <button type="submit" name="register" class="btn-register">
                        <span>Daftar Sekarang</span>
                        <i class="fas fa-arrow-right"></i>
                    </button>
                </form>

                <div class="divider">
                    <span>sudah punya akun?</span>
                </div>

                <div class="login-link">
                    <span>Masuk dengan akun yang ada </span>
                    <a href="login.php">
                        Masuk Sekarang
                        <i class="fas fa-arrow-right"></i>
                    </a>
                </div>

                <p class="footer-text">
                    © <?= date('Y') ?> Cozy-Library · Daftar gratis untuk semua siswa terdaftar
                </p>
            </div>
        </div>
    </div>

    <script>
    function togglePassword() {
        const passwordInput = document.getElementById('password');
        const toggleIcon = document.getElementById('toggleIcon');

        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            toggleIcon.classList.remove('fa-eye');
            toggleIcon.classList.add('fa-eye-slash');
        } else {
            passwordInput.type = 'password';
            toggleIcon.classList.remove('fa-eye-slash');
            toggleIcon.classList.add('fa-eye');
        }
    }

    function checkPasswordStrength(password) {
        const bars = [
            document.getElementById('bar1'),
            document.getElementById('bar2'),
            document.getElementById('bar3'),
            document.getElementById('bar4')
        ];
        const strengthText = document.getElementById('strengthText');

        // Reset bars
        bars.forEach(bar => {
            bar.className = 'strength-bar';
        });

        if (!password) {
            strengthText.textContent = '';
            return;
        }

        let score = 0;

        // Length check
        if (password.length >= 6) score++;
        if (password.length >= 10) score++;

        // Complexity checks
        if (/[A-Z]/.test(password) && /[0-9]/.test(password)) score++;
        if (/[^A-Za-z0-9]/.test(password)) score++;

        // Update bars
        for (let i = 0; i < score; i++) {
            if (bars[i]) {
                if (score <= 2) bars[i].classList.add('weak');
                else if (score === 3) bars[i].classList.add('medium');
                else bars[i].classList.add('strong');
            }
        }

        // Update text
        const strengthLevels = ['Lemah', 'Cukup', 'Baik', 'Kuat'];
        strengthText.textContent = score > 0 ? `Kekuatan password: ${strengthLevels[score-1]}` : '';
    }

    // Prevent form resubmission
    if (window.history.replaceState) {
        window.history.replaceState(null, null, window.location.href);
    }

    // Page Exit Transition Link
    document.querySelectorAll('a:not([target="_blank"])').forEach(link => {
        link.addEventListener('click', e => {
            if(link.hostname === window.location.hostname && !link.hash) {
                e.preventDefault();
                const href = link.href;
                document.body.classList.replace('page-transition', 'page-exit');
                setTimeout(() => window.location.href = href, 350); 
            }
        });
    });
    </script>
</body>
</html>