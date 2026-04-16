<?php /* petugas/includes/nav.php — v3.0 */
$cp = basename($_SERVER['PHP_SELF']);
?>
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <div class="brand-icon petugas-icon">
            <img src="../assets/icons/cozy-tp.png" alt="Cozy-Library" style="width:36px;height:36px;object-fit:contain;border-radius:8px;">
        </div>
        <div>
            <div class="brand-name">Cozy-Library</div>
            <div class="brand-role">Petugas</div>
        </div>
    </div>

    <nav class="sidebar-nav">
        <span class="nav-section-label">Utama</span>
        <a href="dashboard.php" class="nav-link <?= $cp==='dashboard.php'?'active':'' ?>">
            <i class="fas fa-th-large"></i><span>Dashboard</span>
        </a>

        <span class="nav-section-label">Manajemen</span>
        <a href="anggota.php" class="nav-link <?= $cp==='anggota.php'?'active':'' ?>">
            <i class="fas fa-user-graduate"></i><span>Anggota</span>
        </a>

        <span class="nav-section-label">Koleksi</span>
        <a href="kategori.php" class="nav-link <?= $cp==='kategori.php'?'active':'' ?>">
            <i class="fas fa-tags"></i><span>Kategori</span>
        </a>
        <a href="buku.php" class="nav-link <?= in_array($cp, ['buku.php','detail_buku.php'], true)?'active':'' ?>">
            <i class="fas fa-book"></i><span>Buku</span>
        </a>

        <span class="nav-section-label">Transaksi</span>
        <a href="transaksi.php" class="nav-link <?= $cp==='transaksi.php'?'active':'' ?>">
            <i class="fas fa-exchange-alt"></i><span>Transaksi</span>
        </a>
        <a href="denda.php" class="nav-link <?= $cp==='denda.php'?'active':'' ?>">
            <i class="fas fa-coins"></i><span>Denda</span>
        </a>
        <a href="laporan.php" class="nav-link <?= $cp==='laporan.php'?'active':'' ?>">
            <i class="fas fa-chart-bar"></i><span>Laporan</span>
        </a>

        <span class="nav-section-label">Akun</span>
        <a href="profil.php" class="nav-link <?= $cp==='profil.php'?'active':'' ?>">
            <i class="fas fa-user-circle"></i><span>Profil Saya</span>
        </a>
        <a href="../index.php" class="nav-link">
            <i class="fas fa-globe"></i><span>Beranda</span>
        </a>
    </nav>

    <div class="sidebar-foot">
        <a href="logout.php" class="nav-link logout">
            <i class="fas fa-sign-out-alt"></i><span>Logout</span>
        </a>
    </div>
</aside>

