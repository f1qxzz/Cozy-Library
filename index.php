<?php
require_once 'includes/session.php';
require_once 'config/database.php';
initSession();

$isAdmin = $isPetugas = $isAnggota = $loggedIn = false;
$username = '';

if (isset($_SESSION['pengguna_logged_in'])) {
    $loggedIn = true;
    $username = $_SESSION['pengguna_username'] ?? '';
    if ($_SESSION['pengguna_level'] === 'admin')
        $isAdmin = true;
    elseif ($_SESSION['pengguna_level'] === 'petugas')
        $isPetugas = true;
}
if (isset($_SESSION['anggota_logged_in'])) {
    $loggedIn = true;
    $username = $_SESSION['anggota_nama'] ?? '';
    $isAnggota = true;
}

$conn = getConnection();

// ── FUNGSI PENGAMAN QUERY PHP 8+ ──
function safe_query($conn, $sql)
{
    try {
        return $conn->query($sql);
    } catch (Exception $e) {
        return false;
    }
}
function get_val($conn, $sql, $col = 'c')
{
    try {
        $res = $conn->query($sql);
        if ($res && $row = $res->fetch_assoc()) {
            return $row[$col] ?? 0;
        }
    } catch (Exception $e) {
    }
    return 0;
}

// ── FUNGSI PERBAIKAN PATH GAMBAR COVER (HANYA PEMBERSIHAN PATH) ──
function get_cover($path)
{
    if (empty($path))
        return false;
    // Hapus "../" agar path gambar bisa dibaca dari folder root (index.php)
    $clean_path = str_replace('../', '', $path);
    // Langsung kembalikan path yang sudah dibersihkan, tanpa periksa file_exists()
    // Karena kita asumsikan file-nya ada di server dan diakses dari root.
    return $clean_path;
}

// ── STATS AMAN ──
$total_buku = get_val($conn, "SELECT COUNT(*) c FROM buku");
$total_anggota = get_val($conn, "SELECT COUNT(*) c FROM anggota");
$buku_tersedia = get_val($conn, "SELECT COUNT(*) c FROM buku WHERE status='tersedia'");
$total_pinjam = get_val($conn, "SELECT COUNT(*) c FROM transaksi WHERE status_transaksi='Peminjaman'");
$total_kembali = get_val($conn, "SELECT COUNT(*) c FROM transaksi WHERE status_transaksi='Pengembalian'");

// ── BUKU TERBARU ──
$res_baru = safe_query($conn, "SELECT b.*, k.nama_kategori FROM buku b LEFT JOIN kategori k ON b.id_kategori=k.id_kategori ORDER BY b.id_buku DESC LIMIT 10");
$buku_baru = [];
if ($res_baru) {
    while ($r = $res_baru->fetch_assoc())
        $buku_baru[] = $r;
}

// ── BUKU POPULER ──
$res_pop = safe_query($conn, "SELECT b.id_buku, b.judul_buku, b.pengarang, b.cover, b.status, b.tahun_terbit, b.deskripsi, b.penerbit, k.nama_kategori, COUNT(t.id_transaksi) as jml_pinjam FROM buku b LEFT JOIN transaksi t ON b.id_buku=t.id_buku LEFT JOIN kategori k ON b.id_kategori=k.id_kategori GROUP BY b.id_buku ORDER BY jml_pinjam DESC, b.id_buku DESC LIMIT 6");
$buku_pop = [];
if ($res_pop) {
    while ($r = $res_pop->fetch_assoc())
        $buku_pop[] = $r;
}

$featured = !empty($buku_pop) ? $buku_pop[0] : (!empty($buku_baru) ? $buku_baru[0] : null);

// ── KATEGORI ──
$res_kat = safe_query($conn, "SELECT k.*, COUNT(b.id_buku) as jml FROM kategori k LEFT JOIN buku b ON k.id_kategori=b.id_kategori GROUP BY k.id_kategori ORDER BY jml DESC LIMIT 8");
$kategori = [];
if ($res_kat) {
    while ($r = $res_kat->fetch_assoc())
        $kategori[] = $r;
}

// ── ULASAN TERBARU ──
$res_ulasan = safe_query($conn, "SELECT u.*, a.nama_anggota, b.judul_buku, b.pengarang FROM ulasan_buku u JOIN anggota a ON u.id_anggota=a.id_anggota JOIN buku b ON u.id_buku=b.id_buku ORDER BY u.id_ulasan DESC LIMIT 6");
$ulasan_arr = [];
if ($res_ulasan) {
    while ($u = $res_ulasan->fetch_assoc())
        $ulasan_arr[] = $u;
}

// ── LEADERBOARD ──
$res_leader = safe_query($conn, "SELECT a.nama_anggota, a.kelas, COUNT(t.id_transaksi) as jml FROM transaksi t JOIN anggota a ON t.id_anggota=a.id_anggota GROUP BY t.id_anggota ORDER BY jml DESC LIMIT 5");
$leaderboard = [];
if ($res_leader) {
    while ($r = $res_leader->fetch_assoc())
        $leaderboard[] = $r;
}

// ── DATA ANGGOTA LOGIN ──
$anggota_data = null;
if ($isAnggota && isset($_SESSION['anggota_id'])) {
    $aid = (int) $_SESSION['anggota_id'];
    $sql_anggota = "SELECT a.*, 
                   (SELECT COUNT(*) FROM transaksi WHERE id_anggota=$aid) as total_pinjam, 
                   (SELECT COUNT(*) FROM transaksi WHERE id_anggota=$aid AND status_transaksi='Peminjaman') as aktif_pinjam, 
                   COALESCE((SELECT SUM(d.total_denda) FROM denda d JOIN transaksi t ON d.id_transaksi=t.id_transaksi WHERE t.id_anggota=$aid AND d.status_bayar='belum'),0) as denda 
                   FROM anggota a WHERE a.id_anggota=$aid";
    $r_anggota = safe_query($conn, $sql_anggota);
    if ($r_anggota && $r_anggota->num_rows) {
        $anggota_data = $r_anggota->fetch_assoc();
    }
}

// ── STATS EXTRA ──
$avg_rating = get_val($conn, "SELECT AVG(rating) avg FROM ulasan_buku", 'avg');
$total_ulasan = get_val($conn, "SELECT COUNT(*) c FROM ulasan_buku");
$jatuh_tempo = get_val($conn, "SELECT COUNT(*) c FROM transaksi WHERE status_transaksi='Peminjaman' AND DATE(tgl_kembali_rencana)<=CURDATE()");
$pinjam_bulan_ini = get_val($conn, "SELECT COUNT(*) c FROM transaksi WHERE MONTH(tgl_pinjam)=MONTH(NOW()) AND YEAR(tgl_pinjam)=YEAR(NOW())");

// ── JAM BUKA ──
date_default_timezone_set('Asia/Jakarta');
$jam = (int) date('H');
$hari = (int) date('N');
$buka = ($hari <= 6 && $jam >= 7 && $jam < 16);
$jam_str = date('H:i');

// ── QUOTES ──
$quotes = [
    ['Membaca adalah jendela dunia yang tidak pernah tertutup.', 'Pepatah Indonesia'],
    ['Buku adalah teman terbaik yang tidak pernah mengecewakan.', 'Pepatah'],
    ['Satu buku yang kamu baca bisa mengubah hidupmu selamanya.', 'Nelson Mandela'],
    ['Investasi terbaik adalah investasi pada dirimu sendiri — membaca!', 'Benjamin Franklin'],
    ['Perpustakaan adalah tempat di mana masa lalu dan masa depan bertemu dengan Cozy-Library.', 'A. Whitney Brown']
];
$quote = $quotes[date('z') % count($quotes)];
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="description"
        content="Cozy-Library — Platform penyedia layanan perpustakaan digital terpadu. Temukan, pinjam, dan nikmati ribuan koleksi buku pilihan secara online.">
    <title>Cozy-Library — Platform Penyedia Layanan Perpustakaan</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link
        href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700;800&family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700&display=swap"
        rel="stylesheet">
    <script>
        (function () {
            try { if (localStorage.getItem('cozy_library_dark') === '1') document.documentElement.classList.add('dark-mode-active'); } catch (e) { }
        })();
    </script>
    <link rel="icon" href="https://i.ibb.co.com/XZjP8Xmh/Gemini-Generated-Image-rurkqhrurkqhrurk.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/index.css">
</head>

<body class="index-page">

    <div id="scroll-progress"></div>
    <button id="dark-toggle" onclick="toggleDark()" title="Toggle Dark Mode" type="button">🌙</button>
    <button id="fab-top" onclick="window.scrollTo({top:0,behavior:'smooth'})" title="Kembali ke atas">↑</button>

    <div class="topbar" id="topbar">
        <div class="topbar-left">
            <div class="topbar-item">
                <div class="topbar-dot <?= $buka ? 'dot-open' : 'dot-closed' ?>"></div>
                <span><?= $buka ? 'Cozy-Library Buka' : 'Cozy-Library Tutup' ?> · <?= $jam_str ?> WIB</span>
            </div>
            <div class="topbar-item">📚 <?= $buku_tersedia ?> buku tersedia dari <?= $total_buku ?> koleksi</div>
            <?php if ($jatuh_tempo > 0 && ($isAdmin || $isPetugas)): ?>
                <div class="topbar-item topbar-warn">⚠️ <?= $jatuh_tempo ?> buku melewati batas kembali</div>
                
            <?php endif; ?>
        </div>
        <div class="topbar-right">
            <span>📞 (021) 1234-5678</span>
        </div>
    </div>

    <nav class="nav" id="nav">
        <a href="index.php" class="nav-logo">
            <div class="nav-icon">📖</div>
            <div class="nav-name">Cozy-<span>Library</span></div>
        </a>
        <div class="nav-links">
            <a href="#kategori">Kategori</a>
            <a href="#koleksi-visual">Koleksi Visual</a>
            <a href="#leaderboard">Peringkat</a>
            <a href="#ulasan">Ulasan</a>
            <a href="#kontak">Kontak</a>
        </div>
        <div class="nav-right">
            <?php if ($loggedIn): ?>
                <span class="hero-greet-text">👋 <?= htmlspecialchars($username) ?></span>
                <?php if ($isAdmin): ?><a href="admin/dashboard.php" class="btn-primary">Dashboard Admin</a>
                <?php elseif ($isPetugas): ?><a href="petugas/dashboard.php" class="btn-primary">Dashboard Petugas</a>
                <?php else: ?><a href="anggota/dashboard.php" class="btn-primary">Dashboard Saya</a><?php endif; ?>
            <?php else: ?>
                <a href="login.php" class="btn-outline">Masuk</a>
                <a href="register.php" class="btn-primary">Daftar Gratis</a>
            <?php endif; ?>
        </div>
        <button class="hamburger" onclick="document.getElementById('mob').classList.add('open')">☰</button>
    </nav>

    <div class="drawer" id="mob">
        <button class="drawer-x" onclick="document.getElementById('mob').classList.remove('open')">✕</button>
        <div class="drawer-logo">
            <span class="drawer-logo-icon">📚</span>
            <span class="drawer-logo-text">Cozy-Library</span>
        </div>
        <div class="drawer-divider"></div>
        <a href="#featured" class="drawer-item"><span class="drawer-item-icon">⭐</span><span>Unggulan</span></a>
        <a href="#kategori" class="drawer-item"><span class="drawer-item-icon">🗂️</span><span>Kategori</span></a>
        <a href="#ulasan" class="drawer-item"><span class="drawer-item-icon">💬</span><span>Ulasan</span></a>
        <a href="#leaderboard" class="drawer-item"><span class="drawer-item-icon">🏆</span><span>Peringkat</span></a>
        <a href="#kontak" class="drawer-item"><span class="drawer-item-icon">📍</span><span>Kontak</span></a>
        <div class="drawer-divider"></div>
        <?php if ($loggedIn): ?>
            <a href="<?= $isAdmin ? 'admin/dashboard.php' : ($isPetugas ? 'petugas/dashboard.php' : 'anggota/dashboard.php') ?>"
                class="btn-primary drawer-btn">⚡ Dashboard Utama</a>
            <a href="logout.php" class="btn-logout">⏻ Keluar</a>
        <?php else: ?>
            <a href="login.php" class="btn-outline drawer-btn" style="text-align:center;display:flex;align-items:center;justify-content:center;gap:7px;">🔑 Masuk</a>
            <a href="register.php" class="btn-primary drawer-btn">✨ Daftar Gratis</a>
        <?php endif; ?>
    </div>

    <section class="hero">
        <div class="hero-bg">
            <div class="hero-bg-dots"></div>
            <div class="hero-bg-blob1"></div>
            <div class="hero-bg-blob2"></div>
        </div>

        <div class="hero-left">
            <div class="hero-tag"><span class="hero-dot"></span>Cozy-Library — Platform Perpustakaan</div>
            <br>
            <div class="hero-quote-pill">
                <div class="hero-quote-ico">💬</div>
                <div>
                    <div class="hero-quote-text"><?= htmlspecialchars($quote[0]) ?></div>
                    <div class="hero-quote-by">— <?= htmlspecialchars($quote[1]) ?></div>
                </div>
            </div>

            <h1 class="hero-h1">
                Temukan Buku<br>
                <em>Favoritmu</em><br>
                <span class="grad">Perluas Wawasanmu</span>
            </h1>

            <p class="hero-desc">Cozy-Library adalah platform terlengkap untuk manajemen perpustakaan. Cari, pinjam, dan kelola buku dengan mudah —
                akses 24/7 dari mana saja.</p>

            <div class="search-wrap">
                <div class="search-box" id="searchBox">
                    <div class="search-ico">
                        <svg viewBox="0 0 24 24">
                            <circle cx="11" cy="11" r="8" />
                            <line x1="21" y1="21" x2="16.65" y2="16.65" />
                        </svg>
                    </div>
                    <input type="text" class="search-inp" id="searchInp" placeholder="Cari judul, pengarang, atau ISBN…"
                        autocomplete="off">
                    <button class="search-btn" onclick="doSearch()">Cari →</button>
                </div>
                <div class="search-drop" id="searchDrop"></div>
                <div class="search-tags">
                    <?php
                    $pop_tags = !empty($kategori) ? array_slice(array_column($kategori, 'nama_kategori'), 0, 5) : ['Fiksi', 'Sains', 'Teknologi', 'Sejarah', 'Bahasa'];
                    foreach ($pop_tags as $t): ?>
                        <span class="stag"
                            onclick="setSearch('<?= htmlspecialchars($t) ?>')"><?= htmlspecialchars($t) ?></span>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="hero-btns" style="display:flex; gap:12px; flex-wrap:wrap; margin-bottom:30px;">
                <?php if ($isAdmin): ?><a href="admin/dashboard.php" class="btn-hero">⚡ Dashboard Admin</a>
                <?php elseif ($isPetugas): ?><a href="petugas/dashboard.php" class="btn-hero">⚡ Dashboard Petugas</a>
                <?php elseif ($isAnggota): ?><a href="anggota/katalog.php" class="btn-hero">📚 Lihat Katalog</a><a
                        href="anggota/pinjam.php" class="btn-hero2">Pinjam Buku</a>
                <?php else: ?><a href="register.php" class="btn-hero">✨ Daftar Gratis</a><a href="login.php"
                        class="btn-hero2">Masuk ke Akun</a><?php endif; ?>
            </div>

            <div class="hero-nums">
                <div class="hnum">
                    <div class="hnum-n" data-count="<?= $total_buku ?>"><?= $total_buku ?></div>
                    <div class="hnum-l">Koleksi Buku</div>
                </div>
                <div class="hnum">
                    <div class="hnum-n" data-count="<?= $total_anggota ?>"><?= $total_anggota ?></div>
                    <div class="hnum-l">Anggota Aktif</div>
                </div>
                <div class="hnum">
                    <div class="hnum-n" data-count="<?= $buku_tersedia ?>"><?= $buku_tersedia ?></div>
                    <div class="hnum-l">Buku Tersedia</div>
                </div>
            </div>
        </div>

        <div class="hero-right">
            <?php if ($featured): ?>
                <div class="featured-book-3d"
                    onclick="location.href='<?= $isAnggota ? 'anggota/katalog.php' : 'login.php' ?>'">
                    <div class="book-3d">
                        <div class="book-spine"></div>

                        <div class="book-face">
                            <?php $hero_cov = get_cover($featured['cover'] ?? '');
                            if ($hero_cov): ?>
                                <img src="<?= htmlspecialchars($hero_cov) ?>"
                                    alt="<?= htmlspecialchars($featured['judul_buku'] ?? '') ?>" class="hero-book-cover">
                            <?php endif; ?>

                            <div class="book-face-overlay">
                                <div class="book-badge">
                                    <i class="fas fa-star"></i> Terpopuler
                                </div>

                                <div class="book-content-bottom">
                                    <div class="book-label">Rekomendasi Minggu Ini</div>
                                    <div class="book-title">
                                        <?= htmlspecialchars(mb_strimwidth($featured['judul_buku'] ?? 'Judul Buku', 0, 40, '…')) ?>
                                    </div>
                                    <div class="book-author"><?= htmlspecialchars($featured['pengarang'] ?? 'Pengarang') ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="hero-widgets">
            <div class="hw hw1">
                <div class="hw-row">
                    <div class="hw-ico" style="color: #10b981;">✅</div>
                    <div>
                        <div class="hw-label">Tersedia Sekarang</div>
                        <div class="hw-val"><?= $buku_tersedia ?> dari <?= $total_buku ?> buku</div>
                    </div>
                </div>
                <div class="rc-wrap">
                    <div class="rc-label"><span>Ketersediaan
                            koleksi</span><span><?= $total_buku > 0 ? round($buku_tersedia / $total_buku * 100) : 0 ?>%</span>
                    </div>
                    <div class="rc-track">
                        <div class="rc-fill"
                            style="width:<?= $total_buku > 0 ? round($buku_tersedia / $total_buku * 100) : 0 ?>%"></div>
                    </div>
                </div>
            </div>
            <div class="hw hw2">
                <div class="hw-row">
                    <div class="hw-ico" style="color: #f59e0b;">🕐</div>
                    <div>
                        <div class="hw-label">Status Cozy-Library</div>
                        <div class="hw-val" style="color:<?= $buka ? 'var(--c-green)' : 'var(--c-rose)' ?>">
                            <?= $buka ? 'Sedang Buka 🟢' : 'Tutup 🔴' ?></div>
                        <div class="hw-sub">Jam operasional 07.00–16.00</div>
                    </div>
                </div>
            </div>
            <div class="hw hw3">
                <div class="hw-row">
                    <div class="hw-ico" style="color: #3b82f6;">📊</div>
                    <div>
                        <div class="hw-label">Pinjaman Bulan Ini</div>
                        <div class="hw-val"><?= $pinjam_bulan_ini ?> transaksi</div>
                        <div class="hw-sub">Total kembali: <?= $total_kembali ?></div>
                    </div>
                </div>
            </div>
        </div>
        </div>
    </section>

    <div class="info-strip">
        <div class="istrip reveal">
            <div class="istrip-ico">📚</div>
            <div>
                <div class="istrip-n" data-count="<?= $total_buku ?>"><?= $total_buku ?></div>
                <div class="istrip-l">Koleksi Buku</div>
            </div>
        </div>
        <div class="istrip reveal">
            <div class="istrip-ico">✅</div>
            <div>
                <div class="istrip-n" data-count="<?= $buku_tersedia ?>"><?= $buku_tersedia ?></div>
                <div class="istrip-l">Buku Tersedia</div>
            </div>
        </div>
        <div class="istrip reveal">
            <div class="istrip-ico">🔄</div>
            <div>
                <div class="istrip-n" data-count="<?= $total_pinjam ?>"><?= $total_pinjam ?></div>
                <div class="istrip-l">Sedang Dipinjam</div>
            </div>
        </div>
        <div class="istrip reveal">
            <div class="istrip-ico">👥</div>
            <div>
                <div class="istrip-n" data-count="<?= $total_anggota ?>"><?= $total_anggota ?></div>
                <div class="istrip-l">Anggota Aktif</div>
            </div>
        </div>
            </div>

    <?php if (($isAdmin || $isPetugas) && $jatuh_tempo > 0): ?>
        <div class="alert-jt">
            <span>⚠️</span>
            <span style="font-size:.84rem;color:var(--c-text)">Ada <strong><?= $jatuh_tempo ?> buku</strong> yang sudah
                melewati batas pengembalian.</span>
            <a href="<?= $isAdmin ? 'admin' : 'petugas' ?>/transaksi.php" class="alert-jt-link">Tindak Lanjut →</a>
        </div>
    <?php endif; ?>

    <?php if ($featured): ?>
        <section class="featured-sec" id="featured">
            <div class="sec-pill">Rekomendasi Minggu Ini</div>
            <h2 class="sec-h">Buku <em>Pilihan Editor</em></h2>
            <p class="sec-sub" style="margin-bottom:32px">Dipilih berdasarkan popularitas dan ulasan terbaik dari anggota
                perpustakaan.</p>
            <div class="featured-grid reveal">
                <div class="featured-cover">
                    <?php $cov_f = get_cover($featured['cover'] ?? '');
                    if ($cov_f): ?>
                        <img src="<?= htmlspecialchars($cov_f) ?>" alt="<?= htmlspecialchars($featured['judul_buku'] ?? '') ?>"
                            class="featured-cover">
                    <?php else: ?>
                        <div class="featured-cover-bg">
                            <div class="featured-deco">📖</div>
                            <div class="featured-star" style="color:#fff;">⭐ Pilihan</div>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="featured-info">
                    <div class="featured-genre"><?= htmlspecialchars($featured['nama_kategori'] ?? 'Umum') ?></div>
                    <div class="featured-title"><?= htmlspecialchars($featured['judul_buku'] ?? 'Judul Buku') ?></div>
                    <div class="featured-author">oleh <?= htmlspecialchars($featured['pengarang'] ?? 'Anonim') ?></div>
                    <div class="featured-stars">
                        <?php $fr = round($avg_rating > 0 ? $avg_rating : 4.5);
                        for ($s = 1; $s <= 5; $s++)
                            echo '<span class="featured-star" style="color:#f59e0b;">' . ($s <= $fr ? '★' : '☆') . '</span>'; ?>
                        <em style="font-size:0.8rem; color:var(--c-gray); margin-left:6px;"><?= number_format($avg_rating > 0 ? $avg_rating : 4.5, 1) ?>/5.0
                            (<?= $total_ulasan ?> ulasan)</em>
                    </div>

                    <div class="featured-desc">
                        <?= htmlspecialchars(mb_strimwidth($featured['deskripsi'] ?? 'Salah satu koleksi terbaik Cozy-Library yang paling banyak dipinjam oleh anggota. Buku ini sangat direkomendasikan untuk memperkaya wawasan.', 0, 220, '…')) ?>
                    </div>

                    <div class="featured-meta">
                        <?php if (!empty($featured['penerbit'])): ?>
                            <div class="fmeta">🏢 <strong><?= htmlspecialchars($featured['penerbit']) ?></strong></div>
                        <?php endif; ?>
                        <?php if (!empty($featured['tahun_terbit'])): ?>
                            <div class="fmeta">📅 <strong><?= htmlspecialchars($featured['tahun_terbit']) ?></strong></div>
                        <?php endif; ?>
                        <div class="fmeta">📦 Status: <strong
                                style="color:<?= ($featured['status'] ?? '') === 'tersedia' ? 'var(--c-green)' : 'var(--c-rose)' ?>"><?= ($featured['status'] ?? '') === 'tersedia' ? 'Tersedia' : 'Dipinjam' ?></strong>
                        </div>
                    </div>
                    <a href="<?= $isAnggota ? 'anggota/pinjam.php' : 'login.php' ?>" class="featured-btn">
                        <?= $isAnggota ? '📚 Pinjam Sekarang' : '🔒 Login untuk Meminjam' ?> →
                    </a>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <section class="shelf-sec" id="koleksi-visual">
        <div class="shelf-container reveal">
            <div class="shelf-header">
                <div class="shelf-pill">Koleksi Visual</div>
                <h2 class="shelf-h">Jelajahi <em>Rak Digital</em></h2>
                <p class="shelf-sub">Koleksi buku terbaru dengan status ketersediaan real-time.</p>
            </div>

            <div class="shelf-grid">
                <?php
                // Mengambil data buku (menggunakan $buku_baru atau fallback)
                $display_books = !empty($buku_baru) ? array_slice($buku_baru, 0, 12) : [];

                foreach ($display_books as $i => $b):
                    $is_avail = (($b['status'] ?? '') === 'tersedia');
                    $cover_img = get_cover($b['cover'] ?? '');
                    ?>
                    <div class="book-card"
                        onclick="location.href='<?= $isAnggota ? 'anggota/katalog.php' : 'login.php' ?>'">
                        <div class="book-status-badge <?= $is_avail ? '' : '' ?>">
                            <?= $is_avail ? '' : '' ?>
                        </div>

                        <div class="book-card-inner">
                            <div class="book-cover-wrap">
                                <?php if ($cover_img): ?>
                                    <img src="<?= htmlspecialchars($cover_img) ?>"
                                        alt="<?= htmlspecialchars($b['judul_buku']) ?>" class="book-img">
                                <?php else: ?>
                                    <div class="book-img-placeholder">
                                        <span>📖</span>
                                    </div>
                                <?php endif; ?>

                                <div class="book-overlay">
                                    <div class="book-info-minimal">
                                        <h4 class="book-title-mini">
                                            <?= htmlspecialchars(mb_strimwidth($b['judul_buku'], 0, 45, '...')) ?></h4>
                                        <p class="book-author-mini"><?= htmlspecialchars($b['pengarang']) ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <div class="shelf-floor"></div>
    <div class="shelf-floor-r"></div>
    </div>

    <section class="sec alt" id="kategori">
        <div class="sec-hd reveal">
            <div class="sec-lft">
                <div>
                    <div class="sec-pill">Jelajahi</div>
                    <h2 class="sec-h">Kategori <em>Buku</em></h2>
                    <p class="sec-sub">Temukan buku sesuai minat dan kebutuhanmu.</p>
                </div>
            </div>
        </div>
        <div class="kat-grid">
            <?php
            $kd = ['Fiksi' => ['📖', '#2563eb', '#eff6ff'], 'Non-Fiksi' => ['📰', '#0891b2', '#ecfeff'], 'Pelajaran' => ['🎓', '#059669', '#ecfdf5'], 'Referensi' => ['📕', '#d97706', '#fffbeb'], 'Teknologi' => ['💻', '#7c3aed', '#f5f3ff'], 'Sains' => ['🔬', '#0ea5e9', '#f0f9ff'], 'Agama' => ['🕌', '#ea580c', '#fff7ed'], 'Biografi' => ['👤', '#db2777', '#fdf2f8'], 'default' => ['📚', '#2563eb', '#eff6ff']];
            $kat_show = !empty($kategori) ? $kategori : [
                ['nama_kategori' => 'Fiksi', 'jml' => 12],
                ['nama_kategori' => 'Non-Fiksi', 'jml' => 8],
                ['nama_kategori' => 'Pelajaran', 'jml' => 15],
                ['nama_kategori' => 'Teknologi', 'jml' => 9],
                ['nama_kategori' => 'Sains', 'jml' => 7],
                ['nama_kategori' => 'Referensi', 'jml' => 5]
            ];
            foreach (array_slice($kat_show, 0, 8) as $idx => $k):
                $kn = $k['nama_kategori'] ?? 'Umum';
                $d = $kd[$kn] ?? $kd['default'];
                ?>
                <a href="<?= $isAnggota ? 'anggota/katalog.php?kategori=' . ($k['id_kategori'] ?? '') : 'login.php' ?>"
                    class="kat reveal" style="transition-delay:<?= $idx * .05 ?>s;">
                    <div class="kat-icon" style="background:<?= $d[2] ?>; color:<?= $d[1] ?>;"><?= $d[0] ?></div>
                    <div>
                        <div class="kat-name"><?= htmlspecialchars($kn) ?></div>
                        <div class="kat-count"><?= $k['jml'] ?? 0 ?> buku</div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </section>


    <section class="challenge sec" id="challenge">
        <div class="challenge-grid"
            style="display:grid; grid-template-columns:repeat(auto-fit, minmax(300px, 1fr)); gap:32px;">
            <div>
                <div class="sec-pill">Komunitas</div>
                <h2 class="sec-h">Reading <em>Challenge</em></h2>
                <p class="sec-sub">Target membaca komunitas Cozy-Library tahun ini. Bergabung dan raih pencapaianmu!</p>
                <div class="challenge-stats"
                    style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-top:24px;">
                    <div class="cstat reveal">
                        <div class="cstat-ico">📚</div>
                        <div class="cstat-n" data-count="<?= $total_kembali ?>"><?= $total_kembali ?></div>
                        <div class="cstat-l">Buku Selesai Dibaca</div>
                    </div>
                    <div class="cstat reveal">
                        <div class="cstat-ico">👥</div>
                        <div class="cstat-n" data-count="<?= $total_anggota ?>"><?= $total_anggota ?></div>
                        <div class="cstat-l">Pembaca Aktif</div>
                    </div>
                    <div class="cstat reveal">
                        <div class="cstat-ico">⭐</div>
                        <div class="cstat-n"><?= number_format($avg_rating > 0 ? $avg_rating : 4.5, 1) ?></div>
                        <div class="cstat-l">Rata-rata Rating</div>
                    </div>
                    <div class="cstat reveal">
                        <div class="cstat-ico">🏆</div>
                        <div class="cstat-n"><?= !empty($leaderboard) ? ($leaderboard[0]['jml'] ?? 0) : 0 ?></div>
                        <div class="cstat-l">Rekor Pinjaman</div>
                    </div>
                </div>
            </div>
            <div class="reveal">
                <div class="ch-card">
                    <div class="ch-title">🎯 Progress Challenge 2025</div>
                    <?php
                    $target = 200;
                    $done = max($total_kembali, 0);
                    $pct_done = min(100, round($done / $target * 100));
                    $targets = [
                        ['Buku Terbaca Komunitas', $done, $target, 'linear-gradient(90deg,#2563eb,#60a5fa)'],
                        ['Anggota Aktif Bergabung', $total_anggota, 50, 'linear-gradient(90deg,#059669,#34d399)'],
                        ['Ulasan Ditulis', $total_ulasan, 100, 'linear-gradient(90deg,#d97706,#fbbf24)'],
                    ];
                    foreach ($targets as $t):
                        $pct = min(100, round(($t[1] / max($t[2], 1)) * 100));
                        ?>
                        <div class="ch-prog-row" style="margin-top:16px;">
                            <div class="ch-prog-head"
                                style="display:flex; justify-content:space-between; margin-bottom:5px;">
                                <span class="ch-prog-name"
                                    style="font-size:0.85rem; font-weight:600; color:var(--c-text);"><?= $t[0] ?></span>
                                <span class="ch-prog-val"
                                    style="font-size:0.8rem; font-weight:700; color:var(--c-purple);"><?= $t[1] ?> /
                                    <?= $t[2] ?></span>
                            </div>
                            <div class="ch-track">
                                <div class="ch-fill" style="width:<?= $pct ?>%; background:<?= $t[3] ?>"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <button class="ch-btn" style="margin-top:24px; width:100%; justify-content:center;"
                        onclick="location.href='<?= $isAnggota ? 'anggota/pinjam.php' : 'register.php' ?>'">
                        <?= $isAnggota ? 'Ikut Challenge — Pinjam Buku' : 'Daftar & Ikut Challenge' ?> 🚀
                    </button>
                </div>
            </div>
        </div>
    </section>

    <section class="leaderboard sec alt" id="leaderboard">
        <div class="sec-hd reveal">
            <div class="sec-lft">
                <div>
                    <div class="sec-pill">Papan Peringkat</div>
                    <h2 class="sec-h">Pembaca <em>Paling Aktif</em></h2>
                    <p class="sec-sub">Anggota dengan jumlah pinjaman terbanyak bulan ini.</p>
                </div>
            </div>
        </div>
        <div class="lb-grid"
            style="display:grid; grid-template-columns:repeat(auto-fit, minmax(300px, 1fr)); gap:24px;">
            <div class="lb-wrap reveal">
                <div class="lb-head">
                    <div class="lb-htitle">🏆 Top Pembaca (All Time)</div>
                </div>
                <div class="lb-list">
                    <?php
                    $medal_cls = ['gold', 'silver', 'bronze'];
                    $av_colors = ['#2563eb', '#7c3aed', '#059669', '#d97706', '#e11d48'];
                    $lb_show = !empty($leaderboard) ? $leaderboard : [
                        ['nama_anggota' => 'Budi Santoso', 'kelas' => 'XII RPL', 'jml' => 24],
                        ['nama_anggota' => 'Siti Rahayu', 'kelas' => 'XI TKJ', 'jml' => 18],
                        ['nama_anggota' => 'Andi Pratama', 'kelas' => 'X MM', 'jml' => 12]
                    ];

                    if (empty($lb_show)): ?>
                        <div class="lb-empty" style="padding:20px;text-align:center;">Belum ada data pinjaman</div>
                    <?php else:
                        foreach ($lb_show as $ri => $lb):
                            $rc = $ri < 3 ? $medal_cls[$ri] : 'other';
                            $medals = $ri < 3 ? ['🥇', '🥈', '🥉'][$ri] : ('#' . ($ri + 1));
                            $nm_split = explode(' ', $lb['nama_anggota'] ?? 'User');
                            $lbinit = strtoupper(mb_substr($nm_split[0] ?? 'U', 0, 1) . mb_substr($nm_split[1] ?? '', 0, 1));
                            ?>
                            <div class="lb-row">
                                <div class="lb-rank <?= $rc ?>"><?= $medals ?></div>
                                <div class="lb-flex-row" style="flex:1; display:flex; align-items:center; gap:10px;">
                                    <div class="lb-av"
                                        style="width:34px;height:34px;border-radius:50%;background:<?= $av_colors[$ri % 5] ?>;color:#fff;display:flex;align-items:center;justify-content:center;font-size:.75rem;font-weight:700;">
                                        <?= htmlspecialchars($lbinit) ?></div>
                                    <div>
                                        <div class="lb-name"><?= htmlspecialchars($lb['nama_anggota'] ?? 'User') ?></div>
                                        <div class="lb-kelas"><?= htmlspecialchars($lb['kelas'] ?? '—') ?></div>
                                    </div>
                                </div>
                                <div class="lb-count">
                                    <div class="lb-cnt"><?= $lb['jml'] ?? 0 ?> Pinjaman</div>
                                </div>
                            </div>
                        <?php endforeach; endif; ?>
                </div>
            </div>

            <div class="rat-card reveal" id="ulasan">
                <div class="rat-header">
                    <div class="rat-htitle" style="font-weight:700; color:var(--c-text); margin-bottom:16px;">⭐ Rating &
                        Ulasan</div>
                </div>
                <div class="rat-big" style="display:flex; gap:24px; align-items:center; flex-wrap:wrap;">
                    <div>
                        <div style="font-size:3.5rem; font-weight:800; color:var(--c-purple); line-height:1;">
                            <?= number_format($avg_rating > 0 ? $avg_rating : 4.5, 1) ?></div>
                        <div class="rat-stars" style="color:#f59e0b; font-size:1.2rem; margin-top:4px;">
                            <?php $ar = round($avg_rating > 0 ? $avg_rating : 4.5);
                            for ($s = 1; $s <= 5; $s++)
                                echo '<span>' . ($s <= $ar ? '★' : '☆') . '</span>'; ?>
                        </div>
                        <div class="rat-sub" style="font-size:.8rem; color:var(--c-gray);">dari <?= $total_ulasan ?>
                            ulasan</div>
                    </div>
                    <div style="flex:1; min-width: 150px;">
                        <?php
                        $rd = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
                        $rdr = safe_query($conn, "SELECT rating, COUNT(*) c FROM ulasan_buku GROUP BY rating");
                        if ($rdr) {
                            while ($r = $rdr->fetch_assoc())
                                $rd[(int) $r['rating']] = (int) $r['c'];
                        }
                        if ($total_ulasan == 0)
                            $rd = [5 => 12, 4 => 8, 3 => 4, 2 => 2, 1 => 1];
                        $mx = max(array_values($rd));
                        ?>
                        <div class="rat-bars">
                            <?php for ($st = 5; $st >= 1; $st--):
                                $cnt = $rd[$st] ?? 0;
                                $pct = $mx > 0 ? round($cnt / $mx * 100) : 0;
                                ?>
                                <div class="rbar">
                                    <div class="rbar-lbl"><?= $st ?></div>
                                    <div class="rbar-trk">
                                        <div class="rbar-fill" style="width:<?= $pct ?>%"></div>
                                    </div>
                                    <div class="rbar-cnt"><?= $cnt ?></div>
                                </div>
                            <?php endfor; ?>
                        </div>
                    </div>
                </div>
                <?php if (!empty($ulasan_arr)): ?>
                    <div class="rat-ulasan">
                        <?php foreach (array_slice($ulasan_arr, 0, 3) as $u):
                            $u_nm_split = explode(' ', $u['nama_anggota'] ?? 'User');
                            $uinit = strtoupper(mb_substr($u_nm_split[0] ?? 'U', 0, 1) . mb_substr($u_nm_split[1] ?? '', 0, 1));
                            ?>
                            <div class="rat-ul-item"
                                style="display:flex; align-items:flex-start; gap:10px; border-top:1px solid rgba(168,85,247,.1); padding-top:12px; margin-top:12px;">
                                <div style="flex:1;">
                                    <div class="rat-ul-text" style="font-size:0.85rem; color:var(--c-gray); font-style:italic;">
                                        "<?= htmlspecialchars(mb_strimwidth($u['ulasan'] ?? '', 0, 100, '…')) ?>"</div>
                                    <div class="rat-ul-by"
                                        style="font-size:0.75rem; color:var(--c-purple); font-weight:600; margin-top:4px;">
                                        <?= htmlspecialchars($u['nama_anggota'] ?? '') ?> ·
                                        <?= htmlspecialchars(mb_strimwidth($u['judul_buku'] ?? '', 0, 28, '…')) ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section class="sec">
        <div class="sec-hd reveal">
            <div class="sec-lft">
                <div>
                    <div class="sec-pill">Kata Pembaca</div>
                    <h2 class="sec-h">Ulasan <em>Terbaru</em></h2>
                    <p class="sec-sub">Pendapat jujur dari anggota tentang buku yang mereka baca.</p>
                </div>
            </div>
        </div>
        <div class="ulasan-grid">
            <?php
            $uls = !empty($ulasan_arr) ? $ulasan_arr : [
                ['nama_anggota' => 'Budi Santoso', 'judul_buku' => 'Laskar Pelangi', 'rating' => 5, 'ulasan' => 'Sistem peminjaman sangat mudah dan cepat! Bisa akses katalog dari rumah.'],
                ['nama_anggota' => 'Siti Rahayu', 'judul_buku' => 'Bumi Manusia', 'rating' => 5, 'ulasan' => 'Pengingat jatuh tempo sangat membantu. Tidak pernah terlambat lagi setelah pakai Cozy-Library!'],
                ['nama_anggota' => 'Andi Pratama', 'judul_buku' => 'Pemrograman PHP', 'rating' => 4, 'ulasan' => 'Interface yang intuitif dan modern. Fitur kategori memudahkan pencarian buku.'],
                ['nama_anggota' => 'Dewi Lestari', 'judul_buku' => 'Fisika Dasar', 'rating' => 5, 'ulasan' => 'Tampilan web yang cantik dan informatif. Info ketersediaan buku real-time sangat berguna!'],
                ['nama_anggota' => 'Reza Pahlawan', 'judul_buku' => 'Sejarah Indonesia', 'rating' => 4, 'ulasan' => 'Fitur riwayat peminjaman membantu saya melacak semua buku yang pernah dibaca. Keren!'],
                ['nama_anggota' => 'Nurul Hidayah', 'judul_buku' => 'Matematika XII', 'rating' => 5, 'ulasan' => 'Proses daftar hingga bisa pinjam buku sangat cepat. Cozy-Library adalah platform terbaik!'],
            ];
            foreach (array_slice($uls, 0, 6) as $idx => $u):
                $stars = $u['rating'] ?? 5;
                $nm = $u['nama_anggota'] ?? 'User';
                $nm_split = explode(' ', $nm);
                $init = strtoupper(mb_substr($nm_split[0] ?? 'U', 0, 1) . mb_substr($nm_split[1] ?? '', 0, 1));
                ?>
                <div class="ulasan-card reveal" style="transition-delay:<?= $idx * .07 ?>s">
                    <div class="ulasan-stars">
                        <?php for ($s = 1; $s <= 5; $s++)
                            echo '<span>' . ($s <= $stars ? '★' : '☆') . '</span>'; ?></div>
                    <div class="ulasan-q">
                        <div class="ulasan-text"><?= htmlspecialchars(mb_strimwidth($u['ulasan'] ?? '', 0, 120, '…')) ?>
                        </div>
                    </div>
                    <div class="ulasan-author">
                        <div class="ulasan-av"><?= htmlspecialchars($init) ?></div>
                        <div>
                            <div class="ulasan-name"><?= htmlspecialchars($nm) ?></div>
                            <div class="ulasan-buku">📖
                                <?= htmlspecialchars(mb_strimwidth($u['judul_buku'] ?? '', 0, 36, '…')) ?></div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="sec alt">
        <div class="sec-hd reveal">
            <div class="sec-lft">
                <div>
                    <div class="sec-pill">Informasi</div>
                    <h2 class="sec-h">Jam Buka & <em>Peraturan</em></h2>
                    <p class="sec-sub">Patuhi peraturan agar layanan berjalan lancar untuk semua.</p>
                </div>
            </div>
        </div>
        <div class="info-grid"
            style="display:grid; grid-template-columns:repeat(auto-fit, minmax(300px, 1fr)); gap:32px;">
            <div class="jb-card reveal">
                <div class="jb-head"
                    style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                    <div class="jb-head-t" style="font-weight:700; color:var(--c-text);">🕐 Jam Operasional</div>
                </div>
                <div class="jb-rows">
                    <?php
                    $jadwal = [
                        ['Senin', '07.00–16.00'],
                        ['Selasa', '07.00–16.00'],
                        ['Rabu', '07.00–16.00'],
                        ['Kamis', '07.00–16.00'],
                        ['Jumat', '07.00–11.30'],
                        ['Sabtu', '08.00–13.00'],
                        ['Minggu', 'Tutup']
                    ];
                    $hari_id = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
                    $hr = $hari_id[max(0, $hari - 1)];
                    foreach ($jadwal as $j):
                        $isT = ($j[0] === $hr);
                        ?>
                        <div class="jb-row"
                            style="display:flex; justify-content:space-between; padding:8px 0; border-bottom:1px solid var(--c-border); <?= $isT ? 'font-weight:700; color:var(--c-purple);' : '' ?>">
                            <span><?= $j[0] ?><?= $isT ? ' (Hari Ini)' : '' ?></span>
                            <span><?= $j[1] ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="rules-grid"
                style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:16px;">
                <?php
                $rules = [
                    ['📋', 'var(--c-purple-pale)', 'Masa Pinjam 7 Hari', 'Buku dikembalikan dalam 7 hari kalender sejak tanggal peminjaman.'],
                    ['💰', 'rgba(236,72,153,.15)', 'Denda Rp 1.000/Hari', 'Keterlambatan dikenakan denda per hari per buku yang terlambat.'],
                    ['📖', 'rgba(16,185,129,.15)', 'Maks. 3 Buku', 'Setiap anggota hanya boleh meminjam 3 buku secara bersamaan.'],
                    ['🚫', 'rgba(245,158,11,.15)', 'Jaga Kondisi Buku', 'Buku rusak atau hilang wajib diganti oleh peminjam.']
                ];
                foreach ($rules as $r): ?>
                    <div class="rule reveal"
                        style="background:var(--c-surface); padding:20px; border-radius:var(--c-radius); border:1px solid var(--c-border); display:flex; gap:14px; align-items:flex-start;">
                        <div class="rule-ico"
                            style="width:40px; height:40px; border-radius:12px; background:<?= $r[1] ?>; display:flex; align-items:center; justify-content:center; font-size:1.2rem; flex-shrink:0;">
                            <?= $r[0] ?></div>
                        <div>
                            <div class="rule-h"
                                style="font-weight:700; font-size:.9rem; color:var(--c-text); margin-bottom:4px;">
                                <?= $r[2] ?></div>
                            <div class="rule-p" style="font-size:.8rem; color:var(--c-gray); line-height:1.5;"><?= $r[3] ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="sec sec-faq-pad">
        <div class="sec-center-mb reveal">
            <div class="sec-pill sec-pill-center">Bantuan</div>
            <h2 class="sec-h" style="text-align:center;">Pertanyaan <em>Umum</em></h2>
            <p class="sec-sub sec-sub-center">Jawaban untuk pertanyaan yang paling sering ditanyakan.</p>
        </div>
        <div class="faq-wrap" style="max-width:720px; margin:0 auto;">
            <?php $faqs = [
                ['Bagaimana cara mendaftar sebagai anggota Cozy-Library?', 'Klik tombol "Daftar Gratis" di halaman utama, isi formulir dengan NIS, nama lengkap, kelas, username, dan password. Setelah mendaftar, akun langsung aktif dan siap digunakan untuk meminjam buku.'],
                ['Berapa lama masa peminjaman buku?', 'Masa peminjaman adalah 7 hari kalender terhitung dari tanggal pinjam. Lewat dari batas waktu tersebut, akan dikenakan denda Rp 1.000 per hari per buku.'],
                ['Berapa buku yang boleh dipinjam sekaligus?', 'Setiap anggota dapat meminjam maksimal 3 buku sekaligus. Peminjaman buku baru bisa dilakukan setelah salah satu buku dikembalikan.'],
                ['Bagaimana cara mengembalikan buku?', 'Login ke akun kamu, masuk ke menu "Kembalikan Buku", pilih buku yang ingin dikembalikan, lalu bawa ke Cozy-Library. Petugas akan memproses pengembalian dan memperbarui status di sistem.'],
                ['Bagaimana cara membayar denda keterlambatan?', 'Denda dibayarkan langsung ke petugas Cozy-Library saat pengembalian buku. Jumlah denda otomatis dihitung oleh sistem, dan kamu akan mendapat struk pembayaran dari petugas.'],
                ['Apakah saya bisa memberikan ulasan untuk buku yang dipinjam?', 'Ya! Setelah mengembalikan buku, kamu bisa memberikan rating bintang 1–5 dan menulis ulasan. Ulasanmu akan membantu anggota lain menemukan buku yang tepat.'],
            ];
            foreach ($faqs as $i => $f): ?>
                <div class="faq-item reveal" onclick="toggleFaq(this)">
                    <div class="faq-q"><?= htmlspecialchars($f[0]) ?><span class="faq-arr">▼</span></div>
                    <div class="faq-a">
                        <div class="faq-a-inner"><?= htmlspecialchars($f[1]) ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="sec alt" id="kontak">
        <div class="sec-hd reveal">
            <div class="sec-lft">
                <div>
                    <div class="sec-pill">Hubungi Kami</div>
                    <h2 class="sec-h">Kontak & <em>Lokasi</em></h2>
                    <p class="sec-sub">Ada pertanyaan? Tim kami siap membantu.</p>
                </div>
            </div>
        </div>
        <div class="kontak-grid"
            style="display:grid; grid-template-columns:repeat(auto-fit, minmax(300px, 1fr)); gap:32px;">
            <div class="kontak-items" style="display:flex; flex-direction:column; gap:16px;">
                <?php $ks = [
                    ['📍', 'var(--c-purple-pale)', 'Alamat', 'Jl. Mayor Kusmanto, Gedung B Lt.2<br>Klaten Jawa Tengah'],
                    ['📞', 'rgba(16,185,129,.15)', 'Telepon', '(021) 1234-5678<br>Senin–Jumat · 07.00–16.00 WIB'],
                    ['✉️', 'rgba(245,158,11,.15)', 'Email', 'info@cozylibrary.sch.id<br>Respon dalam 1×24 jam'],
                    ['💬', 'rgba(236,72,153,.15)', 'WhatsApp', '+62 812-3456-7890<br>Chat langsung dengan petugas']
                ];
                foreach ($ks as $k): ?>
                    <div class="kitem reveal"
                        style="display:flex; align-items:center; gap:16px; background:var(--c-surface); padding:20px; border-radius:var(--c-radius); border:1px solid var(--c-border);">
                        <div class="kitem-ico"
                            style="width:48px; height:48px; border-radius:12px; background:<?= $k[1] ?>; display:flex; align-items:center; justify-content:center; font-size:1.4rem; flex-shrink:0;">
                            <?= $k[0] ?></div>
                        <div>
                            <div class="kitem-h" style="font-weight:700; font-size:.9rem; color:var(--c-text);"><?= $k[2] ?>
                            </div>
                            <div class="kitem-v"
                                style="font-size:.8rem; color:var(--c-gray); margin-top:4px; line-height:1.5;"><?= $k[3] ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="reveal">
                <div class="map-card"
                    style="height:100%; min-height:300px; background:var(--c-surface); border:1px solid var(--c-border); border-radius:var(--c-radius); display:flex; flex-direction:column; justify-content:center; align-items:center; color:var(--c-gray); text-align:center; padding:32px;">
                    <div style="font-size:3rem; margin-bottom:16px;">🗺️</div>
                    <div style="font-weight:700; color:var(--c-text); margin-bottom:8px;">Lokasi Cozy-Library</div>
                    <div style="font-size:.85rem; margin-bottom:20px;">Anda dapat mengunjungi kami secara langsung di
                        area sekolah.</div>
                    <a href="https://maps.app.goo.gl/zkJ2xQZe5J3rhHga8" target="_blank" class="btn-primary"
                        style="text-decoration:none;">Buka di Google Maps →</a>
                </div>
            </div>
        </div>
    </section>

    <div class="cta-sec reveal">
        <div>
            <h2 class="cta-h">Siap Mulai Petualangan Membacamu?</h2>
            <p class="cta-sub">Bergabung sekarang dan nikmati akses ke seluruh koleksi buku di Cozy-Library.<br>Gratis
                untuk semua siswa terdaftar.</p>
        </div>
        <div class="cta-btns">  
            <a href="register.php" class="cta-b1">Daftar Sekarang</a>
            <a href="login.php" class="cta-b2">Masuk ke Akun</a>
        </div>
    </div>

    <div class="footer">
        <div class="footer-grid"
            style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:32px;">
            <div>
                <div class="foot-logo" style="display:flex; align-items:center; gap:10px; margin-bottom:10px;">
                    <div class="foot-icon"
                        style="width:36px; height:36px; border-radius:10px; background:var(--c-grad); color:white; display:flex; align-items:center; justify-content:center;">
                        📖</div>
                    <div class="foot-brand" style="font-weight:800; color:white;">Libra<span>Space</span></div>
                </div>
                <p class="foot-desc" style="font-size:0.85rem; color:rgba(255,255,255,0.7); line-height:1.6;">Platform
                    platform terpadu Cozy-Library untuk sekolah. Memudahkan pengelolaan koleksi, peminjaman, dan
                    pengembalian buku secara efisien dan transparan.</p>
            </div>
            <div>
                <div class="foot-col-title" style="font-weight:700; color:white; margin-bottom:15px;">Layanan</div>
                <div class="foot-links" style="display:flex; flex-direction:column; gap:8px;">
                    <a href="<?= $isAnggota ? 'anggota/katalog.php' : 'login.php' ?>"
                        style="color:rgba(255,255,255,0.7); font-size:0.85rem; text-decoration:none;">Katalog Buku</a>
                    <a href="<?= $isAnggota ? 'anggota/pinjam.php' : 'login.php' ?>"
                        style="color:rgba(255,255,255,0.7); font-size:0.85rem; text-decoration:none;">Pinjam Buku</a>
                    <a href="<?= $isAnggota ? 'anggota/kembali.php' : 'login.php' ?>"
                        style="color:rgba(255,255,255,0.7); font-size:0.85rem; text-decoration:none;">Kembalikan
                        Buku</a>
                    <a href="<?= $isAnggota ? 'anggota/riwayat.php' : 'login.php' ?>"
                        style="color:rgba(255,255,255,0.7); font-size:0.85rem; text-decoration:none;">Riwayat
                        Pinjaman</a>
                </div>
            </div>
            <div>
                <div class="foot-col-title" style="font-weight:700; color:white; margin-bottom:15px;">Informasi</div>
                <div class="foot-links" style="display:flex; flex-direction:column; gap:8px;">
                    <a href="#kategori"
                        style="color:rgba(255,255,255,0.7); font-size:0.85rem; text-decoration:none;">Kategori</a>
                    <a href="#koleksi-visual"
                        style="color:rgba(255,255,255,0.7); font-size:0.85rem; text-decoration:none;">Koleksi Visual</a>
                    <a href="#peringkat"
                        style="color:rgba(255,255,255,0.7); font-size:0.85rem; text-decoration:none;">Peringkat</a>
                    <a href="#ulasan"
                        style="color:rgba(255,255,255,0.7); font-size:0.85rem; text-decoration:none;">Ulasan</a>
                    <a href="#kontak"
                        style="color:rgba(255,255,255,0.7); font-size:0.85rem; text-decoration:none;">Kontak</a>
                </div>
            </div>
            <div>
                <div class="foot-col-title" style="font-weight:700; color:white; margin-bottom:15px;">Akun</div>
                <div class="foot-links" style="display:flex; flex-direction:column; gap:8px;">
                    <a href="register.php"
                        style="color:rgba(255,255,255,0.7); font-size:0.85rem; text-decoration:none;">Daftar Anggota</a>
                    <a href="login.php"
                        style="color:rgba(255,255,255,0.7); font-size:0.85rem; text-decoration:none;">Masuk</a>
                    <?php if ($isAdmin): ?><a href="admin/dashboard.php"
                            style="color:rgba(255,255,255,0.7); font-size:0.85rem; text-decoration:none;">Admin
                            Panel</a><?php endif; ?>
                    <?php if ($isPetugas): ?><a href="petugas/dashboard.php"
                            style="color:rgba(255,255,255,0.7); font-size:0.85rem; text-decoration:none;">Panel
                            Petugas</a><?php endif; ?>
                    <?php if ($isAnggota): ?><a href="anggota/profil.php"
                            style="color:rgba(255,255,255,0.7); font-size:0.85rem; text-decoration:none;">Profil
                            Saya</a><?php endif; ?>
                </div>
            </div>
        </div>
        <div class="footer-bottom"
            style="margin-top:40px; padding-top:20px; border-top:1px solid rgba(255,255,255,0.1); display:flex; justify-content:space-between; flex-wrap:wrap; gap:10px;">
            <p class="foot-copy" style="font-size:0.75rem; color:rgba(255,255,255,0.5);">© <?= date('Y') ?> Cozy-Library —
                Sistem Manajemen Perpustakaan · All rights reserved.</p>
            <div class="credit">
                <p>Developed by: <strong>@f1qxzz_</strong></p>
                <p>Inspired by: <strong>@chlszaaa</strong></p>
                <p>On Instagram</p>
                <p>© 2026 Cozy-Library Project</p>
            </div>
        </div>
    </div>

    <script>
        const DM_KEY = 'cozy_library_dark';
        function toggleDark() {
            const html = document.documentElement;
            const body = document.body;
            const btn = document.getElementById('dark-toggle');
            const isDark = html.classList.contains('dark-mode-active') || body.classList.contains('dark');

            if (!isDark) {
                html.classList.add('dark-mode-active');
                body.classList.add('dark');
                localStorage.setItem(DM_KEY, '1');
                if (btn) btn.textContent = '☀️';
            } else {
                html.classList.remove('dark-mode-active');
                body.classList.remove('dark');
                localStorage.setItem(DM_KEY, '0');
                if (btn) btn.textContent = '🌙';
            }
        }

        (function () {
            const saved = localStorage.getItem(DM_KEY);
            const html = document.documentElement;
            const body = document.body;
            const btn = document.getElementById('dark-toggle');
            if (saved === '1') {
                html.classList.add('dark-mode-active');
                body.classList.add('dark');
                if (btn) btn.textContent = '☀️';
            } else {
                html.classList.remove('dark-mode-active');
                body.classList.remove('dark');
                if (btn) btn.textContent = '🌙';
            }
        })();

        const nav = document.getElementById('nav');
        const topH = document.getElementById('topbar')?.offsetHeight || 40;
        if (nav) nav.style.top = topH + 'px';

        const prog = document.getElementById('scroll-progress');
        const fab = document.getElementById('fab-top');

        window.addEventListener('scroll', () => {
            if (nav) nav.classList.toggle('scrolled', window.scrollY > topH + 20);
            if (prog) {
                const pct = (window.scrollY / (document.documentElement.scrollHeight - window.innerHeight)) * 100;
                prog.style.width = Math.min(pct, 100) + '%';
            }
            if (fab) fab.classList.toggle('show', window.scrollY > 400);
        });

        document.querySelectorAll('a[href^="#"]').forEach(a => a.addEventListener('click', e => {
            const targetId = a.getAttribute('href');
            if (targetId === '#') return;
            const t = document.querySelector(targetId);
            if (t) {
                e.preventDefault();
                t.scrollIntoView({ behavior: 'smooth' });
                document.getElementById('mob')?.classList.remove('open');
            }
        }));

        const ro = new IntersectionObserver(es => {
            es.forEach(el => {
                if (el.isIntersecting) {
                    const sibs = [...el.target.parentElement.children].filter(c => c.classList.contains('reveal'));
                    const idx = Math.max(0, sibs.indexOf(el.target));
                    setTimeout(() => el.target.classList.add('show'), Math.min(idx, 6) * 80);
                    ro.unobserve(el.target);
                }
            });
        }, { threshold: .1 });
        document.querySelectorAll('.reveal').forEach(el => ro.observe(el));

        function animCount(el) {
            // Ambil angka target langsung dari atribut data-count
            const target = parseInt(el.getAttribute('data-count')) || parseInt(el.dataset.count) || 0;
            if (!target) return;
            let c = 0;
            const step = Math.max(1, Math.ceil(target / 50));
            const iv = setInterval(() => {
                c = Math.min(c + step, target);
                el.textContent = c + (el.dataset.sfx || '');
                if (c >= target) clearInterval(iv);
            }, 28);
        }

        const cro = new IntersectionObserver(es => {
            es.forEach(el => {
                if (el.isIntersecting) {
                    animCount(el.target);
                    cro.unobserve(el.target);
                }
            });
        }, { threshold: .5 });

        document.querySelectorAll('[data-count]').forEach(el => {
            el.dataset.sfx = el.textContent.replace(/\d/g, '').trim();
            el.textContent = '0' + el.dataset.sfx;
            cro.observe(el);
        });

        function toggleFaq(item) {
            const wasOpen = item.classList.contains('open');
            document.querySelectorAll('.faq-item.open').forEach(x => x.classList.remove('open'));
            if (!wasOpen) item.classList.add('open');
        }

        (function () {
            const inp = document.getElementById('searchInp');
            const drop = document.getElementById('searchDrop');
            if (!inp || !drop) return;
            let t;
            const cov = ['135deg,#dde8ff,#b8ccff', '135deg,#d4f0e8,#a8e0cc', '135deg,#ffe0dc,#ffbdb6', '135deg,#fff0cc,#ffd880', '135deg,#ecdeff,#d4b8ff'];
            const em = ['📘', '📗', '📕', '📙', '📓'];
            const catUrl = '<?= $isAnggota ? 'anggota/katalog.php' : 'login.php' ?>';

            inp.addEventListener('input', () => {
                clearTimeout(t);
                const q = inp.value.trim();
                if (q.length < 2) {
                    drop.classList.remove('show');
                    return;
                }
                drop.innerHTML = '<div class="sd-loading" style="padding:16px;text-align:center;"><div class="spin"></div>Mencari...</div>';
                drop.classList.add('show');
                t = setTimeout(() => {
                    fetch('api_search.php?q=' + encodeURIComponent(q))
                        .then(r => r.json())
                        .then(data => {
                            if (!data.length) {
                                drop.innerHTML = '<div class="sd-empty" style="padding:16px;text-align:center;">Tidak ditemukan — coba kata kunci lain</div>';
                                return;
                            }
                            drop.innerHTML = data.map((b, i) => `
            <div class="sd-item" onclick="location.href='${catUrl}'" style="display:flex; gap:12px; padding:12px 16px; cursor:pointer; border-bottom:1px solid var(--c-border);">
            <div class="sd-ph" style="width:40px; height:54px; border-radius:6px; background:linear-gradient(${cov[i % 5]}); display:flex; align-items:center; justify-content:center; font-size:1.2rem;">${em[i % 5]}</div>
            <div class="sd-info" style="flex:1;">
                <div class="sd-title" style="font-weight:700; font-size:.85rem; color:var(--c-text); margin-bottom:4px;">${b.judul_buku || ''}</div>
                <div class="sd-meta" style="font-size:.72rem; color:var(--c-gray);">${b.pengarang || ''} · ${b.nama_kategori || 'Umum'}</div>
            </div>
            <span class="sd-badge" style="font-size:.65rem; font-weight:700; padding:4px 8px; border-radius:100px; height:fit-content; background:${b.status === 'tersedia' ? '#dcfce7' : '#fee2e2'}; color:${b.status === 'tersedia' ? '#166534' : '#991b1b'};">${b.status === 'tersedia' ? 'Tersedia' : 'Dipinjam'}</span>
            </div>`).join('');
                        }).catch(() => drop.classList.remove('show'));
                }, 300);
            });
            document.addEventListener('click', e => {
                if (!inp.contains(e.target) && !drop.contains(e.target)) drop.classList.remove('show');
            });
            inp.addEventListener('focus', () => {
                if (inp.value.trim().length >= 2) drop.classList.add('show');
            });
        })();

        function doSearch() {
            const q = document.getElementById('searchInp')?.value.trim();
            if (q) location.href = '<?= $isAnggota ? 'anggota/katalog.php' : 'login.php' ?>?search=' + encodeURIComponent(q);
        }
        document.getElementById('searchInp')?.addEventListener('keydown', e => {
            if (e.key === 'Enter') doSearch();
        });

        function setSearch(val) {
            const inp = document.getElementById('searchInp');
            if (inp) {
                inp.value = val;
                inp.dispatchEvent(new Event('input'));
                inp.focus(); x
            }
        }

        const bro = new IntersectionObserver(es => {
            es.forEach(el => {
                if (el.isIntersecting) {
                    const fills = el.target.querySelectorAll('.ch-fill,.rbar-fill');
                    fills.forEach(f => {
                        const w = f.style.width;
                        f.style.width = '0';
                        setTimeout(() => f.style.width = w, 100);
                    });
                    bro.unobserve(el.target);
                }
            });
        }, { threshold: .2 });
        document.querySelectorAll('.ch-card,.rat-card').forEach(el => bro.observe(el));
    </script>
</body>

</html>