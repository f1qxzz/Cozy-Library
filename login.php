<?php
/*
 * Alur logic PHP:
 * 1) Validasi input form autentikasi/registrasi.
 * 2) Mengecek data pengguna ke database dan aturan keamanan.
 * 3) Menetapkan session pengguna lalu redirect ke role terkait.
 */require_once 'config/database.php';
require_once 'includes/session.php';
initSession();

// Redirect jika sudah login
if (isPenggunaLoggedIn()) {
    $dashboardPath = getPenggunaDashboardPath(getPenggunaLevel());
    if ($dashboardPath !== null) {
        header('Location: ' . $dashboardPath);
        exit;
    }

    logout();
}
if (isAnggotaLoggedIn()) {
    header('Location: anggota/dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $conn = getConnection();

    $found = false;

    // 1. Cek tabel pengguna (admin / petugas)
    $stmt = $conn->prepare("SELECT * FROM pengguna WHERE username = ? LIMIT 1");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if ($row && (password_verify($password, $row['password']) || $password === $row['password'])) {
        $normalizedLevel = normalizePenggunaLevel($row['level'], $conn);
        if ($normalizedLevel === null) {
            $found = true;
            $error = 'Role pengguna tidak valid. Hubungi administrator.';
        } else {
            $found = true;
            $_SESSION['pengguna_logged_in'] = true;
            $_SESSION['pengguna_id'] = $row['id_pengguna'];
            $_SESSION['pengguna_nama'] = $row['nama_pengguna'];
            $_SESSION['pengguna_level'] = $normalizedLevel;
            $_SESSION['pengguna_username'] = $row['username'];

            if ($password === $row['password'] && !password_verify($password, $row['password'])) {
                $newHash = password_hash($password, PASSWORD_DEFAULT);
                $updateStmt = $conn->prepare("UPDATE pengguna SET password = ? WHERE id_pengguna = ?");
                $updateStmt->bind_param("si", $newHash, $row['id_pengguna']);
                $updateStmt->execute();
                $updateStmt->close();
            }

            $dashboardPath = getPenggunaDashboardPath($normalizedLevel, $conn);
            closeConnection($conn);
            header('Location: ' . $dashboardPath);
            exit;
        }
    }

    // 2. Cek tabel anggota
    if (!$found) {
        $stmt = $conn->prepare("SELECT * FROM anggota WHERE username = ? LIMIT 1");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();

        if ($row && (password_verify($password, $row['password']) || $password === $row['password'])) {
            $found = true;
            if ($row['status'] !== 'aktif') {
                $error = 'Akun Anda tidak aktif. Hubungi petugas.';
            } else {
                $_SESSION['anggota_logged_in'] = true;
                $_SESSION['anggota_id'] = $row['id_anggota'];
                $_SESSION['anggota_nama'] = $row['nama_anggota'];
                $_SESSION['anggota_nis'] = $row['nis'];
                $_SESSION['anggota_kelas'] = $row['kelas'];

                if ($password === $row['password'] && !password_verify($password, $row['password'])) {
                    $newHash = password_hash($password, PASSWORD_DEFAULT);
                    $updateStmt = $conn->prepare("UPDATE anggota SET password = ? WHERE id_anggota = ?");
                    $updateStmt->bind_param("si", $newHash, $row['id_anggota']);
                    $updateStmt->execute();
                    $updateStmt->close();
                }

                closeConnection($conn);
                header('Location: anggota/dashboard.php');
                exit;
            }
        }
    }

    if (!$found && !$error) {
        $error = 'Username atau password salah!';
    }
    closeConnection($conn);
}

$reg_success = isset($_GET['registered']) ? 'Registrasi berhasil! Silakan masuk.' : '';
$conn = getConnection();

$query = $conn->query("SELECT COUNT(*) AS total FROM buku");
if (!$query) {
    die("Query error: " . $conn->error);
}
$data = $query->fetch_assoc();
closeConnection($conn);

$quotes = [
    ['Membaca adalah jendela dunia yang tidak pernah tertutup.', 'Pepatah Indonesia'],
    ['Buku adalah teman terbaik yang tidak pernah mengecewakan.', 'Pepatah'],
    ['Satu buku yang kamu baca bisa mengubah hidupmu selamanya.', 'Nelson Mandela'],
    ['Investasi terbaik adalah investasi pada dirimu sendiri — membaca!', 'Benjamin Franklin'],
    ['Perpustakaan adalah tempat di mana masa lalu dan masa depan bertemu dengan Cozy-Library.', 'A. Whitney Brown'],
];
$quote = $quotes[date('z') % count($quotes)];
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="assets/icons/cozy-tp.png" type="image/png">
    <title>Masuk — Cozy-Library</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300..800;1,9..40,300..800&family=Playfair+Display:ital,wght@0,400..900;1,400..900&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/login.css?v=<?= time(); ?>">
</head>

<body class="page-transition">
    <div class="login-container">
        <div class="login-left">
            <div class="login-left-content">
                <div class="login-icon">
                    <img src="assets/icons/register-icon.svg" alt="Cozy-Library"
                        style="width:72px;height:72px;object-fit:contain;border-radius:12px;">
                </div>
                <h1 class="login-title-large">
                    Selamat Datang di<br>
                    <span>Cozy-Library</span>
                </h1>
                <p class="login-description">
                    Platform manajemen perpustakaan modern untuk mengelola koleksi, anggota, dan transaksi peminjaman
                    buku secara efisien.
                </p>
                <div class="quote-box">
                    <div class="quote-text">
                        "<?= htmlspecialchars($quote[0]) ?>"
                    </div>
                    <div class="quote-author">
                        <?= htmlspecialchars($quote[1]) ?>
                    </div>
                </div>
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-book"></i></div>
                        <div class="stat-value"><?= $data['total'] ?></div>
                        <div class="stat-label">Koleksi Buku</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-clock"></i></div>
                        <div class="stat-value">14 Hari</div>
                        <div class="stat-label">Masa Pinjam</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-magic"></i></div>
                        <div class="stat-value">Auto</div>
                        <div class="stat-label">Deteksi Level</div>
                    </div>
                </div>
                <a href="index.php" class="back-link">
                    <i class="fas fa-arrow-left"></i>
                    Kembali ke Beranda
                </a>
            </div>
        </div>

        <div class="login-right">
            <div class="login-box">
                <div class="login-header">
                    <div class="login-header-icon">
                        <img src="assets/icons/login-icon.svg" alt="Login" style="width:52px;height:52px;">
                    </div>
                    <h2 class="login-header-title">Masuk ke Akun</h2>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i>
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <?php if ($reg_success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <?= htmlspecialchars($reg_success) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" novalidate>
                    <div class="form-group">
                        <label class="form-label">Username</label>
                        <div class="input-wrapper">
                            <i class="fas fa-user input-icon"></i>
                            <input type="text" name="username" class="form-control" placeholder="Masukkan username"
                                required autocomplete="username"
                                value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Password</label>
                        <div class="input-wrapper">
                            <i class="fas fa-lock input-icon"></i>
                            <input type="password" name="password" id="password" class="form-control"
                                placeholder="Masukkan password" required autocomplete="current-password">
                            <button type="button" class="password-toggle" onclick="togglePassword()">
                                <i class="fas fa-eye" id="toggleIcon"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" name="login" class="btn-login" id="loginBtn">
                        <span>Masuk ke Sistem</span>
                        <i class="fas fa-arrow-right"></i>
                    </button>
                </form>

                <div class="divider">
                    <span>atau</span>
                </div>

                <div class="register-link">
                    <span>Belum punya akun? </span>
                    <a href="register.php">
                        Daftar Sekarang
                        <i class="fas fa-arrow-right"></i>
                    </a>
                </div>

                <p class="footer-text">
                    © <?= date('Y') ?> Cozy-Library · Sistem Manajemen Perpustakaan
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

        document.getElementById('loginBtn')?.addEventListener('click', function (e) {
            const form = this.closest('form');
            if (form && form.checkValidity()) {
                this.classList.add('loading');
                this.innerHTML = '<span>Memproses...</span><i class="fas fa-spinner fa-spin"></i>';
            }
        });

        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }

        setTimeout(() => {
            document.querySelectorAll('.alert').forEach(alert => {
                alert.style.transition = 'opacity 0.5s ease';
                alert.style.opacity = '0';
                setTimeout(() => { alert.style.display = 'none'; }, 500);
            });
        }, 5000);

        // Page Exit Transition Link
        document.querySelectorAll('a:not([target="_blank"])').forEach(link => {
            link.addEventListener('click', e => {
                if (link.hostname === window.location.hostname && !link.hash) {
                    e.preventDefault();
                    const href = link.href;
                    document.body.classList.replace('page-transition', 'page-exit');
                    setTimeout(() => window.location.href = href, 350);
                }
            });
        });
    </script>
    <script src="assets/js/script.js"></script>
</body>

</html>
