<?php /* admin/includes/nav.php — v3.0 */
$cp = basename($_SERVER['PHP_SELF']);
?>
<!-- LEFT sidebar overlay -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <div class="brand-icon">
            <img src="../assets/icons/cozy-tp.png" alt="Cozy-Library" style="width:36px;height:36px;object-fit:contain;border-radius:8px;">
        </div>
        <div>
            <div class="brand-name">Cozy-Library</div>
            <div class="brand-role">Administrator</div>
        </div>
    </div>

    <nav class="sidebar-nav">
        <span class="nav-section-label">Utama</span>
        <a href="dashboard.php" class="nav-link <?= $cp==='dashboard.php'?'active':'' ?>">
            <svg viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
            <span>Dashboard</span>
        </a>

        <span class="nav-section-label">Manajemen</span>
        <a href="pengguna.php" class="nav-link <?= $cp==='pengguna.php'?'active':'' ?>">
            <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            <span>Pengguna</span>
        </a>
        <a href="anggota.php" class="nav-link <?= $cp==='anggota.php'?'active':'' ?>">
            <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            <span>Anggota</span>
        </a>

        <span class="nav-section-label">Koleksi</span>
        <a href="kategori.php" class="nav-link <?= $cp==='kategori.php'?'active':'' ?>">
            <svg viewBox="0 0 24 24"><polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/></svg>
            <span>Kategori</span>
        </a>
        <a href="buku.php" class="nav-link <?= $cp==='buku.php'?'active':'' ?>">
            <svg viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
            <span>Buku</span>
        </a>

        <span class="nav-section-label">Transaksi</span>
        <a href="transaksi.php" class="nav-link <?= $cp==='transaksi.php'?'active':'' ?>">
            <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
            <span>Transaksi</span>
        </a>
        <a href="denda.php" class="nav-link <?= $cp==='denda.php'?'active':'' ?>">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <span>Denda</span>
        </a>
        <a href="laporan.php" class="nav-link <?= $cp==='laporan.php'?'active':'' ?>">
            <svg viewBox="0 0 24 24"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
            <span>Laporan</span>
        </a>

        <span class="nav-section-label">Akun</span>
        <a href="profil.php" class="nav-link <?= $cp==='profil.php'?'active':'' ?>">
            <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            <span>Profil Saya</span>
        </a>
        <a href="../index.php" class="nav-link">
            <svg viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
            <span>Beranda</span>
        </a>
    </nav>

    <div class="sidebar-foot">
        <a href="logout.php" class="nav-link logout">
            <svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
            <span>Logout</span>
        </a>
    </div>
</aside>
