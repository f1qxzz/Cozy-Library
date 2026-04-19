<?php /* anggota/includes/nav.php — v3.1 FIXED */
/*
 * Alur logic PHP:
 * 1) Menyusun komponen layout bersama (header/nav/print) agar konsisten.
 * 2) Membaca variabel konteks halaman dari file pemanggil.
 * 3) Merender bagian tampilan bersama tanpa duplikasi kode.
 */$cp = basename($_SERVER['PHP_SELF']);
?>
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<aside class="sidebar sidebar-anggota" id="sidebar">
    <div class="sidebar-brand">
        <div class="brand-icon brand-icon-anggota">
            <img src="../assets/icons/cozy-tp.png" alt="Cozy-Library" style="width:36px;height:36px;object-fit:contain;border-radius:8px;">
        </div>
        <div>
            <div class="brand-name">Cozy-Library</div>
            <div class="brand-role">Anggota</div>
        </div>
    </div>

    <nav class="sidebar-nav">
        <span class="nav-section-label">Utama</span>
        <a href="dashboard.php" class="nav-link <?= $cp==='dashboard.php'?'active':'' ?>">
            <i class="fas fa-home"></i>
            <span>Dashboard</span>
        </a>

        <span class="nav-section-label">Katalog</span>
        <a href="katalog.php" class="nav-link <?= in_array($cp, ['katalog.php','detail_buku.php'], true)?'active':'' ?>">
            <i class="fas fa-search"></i>
            <span>Katalog Buku</span>
        </a>

        <span class="nav-section-label">Transaksi</span>
        <a href="pinjam.php" class="nav-link <?= $cp==='pinjam.php'?'active':'' ?>">
            <i class="fas fa-plus-circle"></i>
            <span>Pinjam Buku</span>
        </a>
        <a href="kembali.php" class="nav-link <?= $cp==='kembali.php'?'active':'' ?>">
            <i class="fas fa-undo-alt"></i>
            <span>Kembalikan Buku</span>
        </a>

        <span class="nav-section-label">Riwayat</span>
        <a href="riwayat.php" class="nav-link <?= $cp==='riwayat.php'?'active':'' ?>">
            <i class="fas fa-history"></i>
            <span>Riwayat Pinjam</span>
        </a>
        <a href="ulasan.php" class="nav-link <?= $cp==='ulasan.php'?'active':'' ?>">
            <i class="fas fa-star"></i>
            <span>Ulasan Buku</span>
        </a>

        <span class="nav-section-label">Akun</span>
        <a href="profil.php" class="nav-link <?= $cp==='profil.php'?'active':'' ?>">
            <i class="fas fa-user"></i>
            <span>Profil Saya</span>
        </a>
        <a href="../index.php" class="nav-link">
            <i class="fas fa-globe"></i>
            <span>Beranda</span>
        </a>
    </nav>

    <div class="sidebar-foot">
        <a href="logout.php" class="nav-link logout">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </div>
</aside>

