<?php /* petugas/includes/header.php — v4.0 */
$page_title = $page_title ?? 'Dashboard';
$page_sub   = $page_sub   ?? 'Panel Petugas · Cozy-Library';

$conn   = getConnection();
$userId = getPenggunaId();
$stmt   = $conn->prepare("SELECT foto, nama_pengguna FROM pengguna WHERE id_pengguna = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$userData = $stmt->get_result()->fetch_assoc();
$stmt->close();

$initials = '';
$nama = $userData['nama_pengguna'] ?? getPenggunaName();
foreach (explode(' ', trim($nama)) as $w) {
    $initials .= strtoupper(mb_substr($w, 0, 1));
    if (strlen($initials) >= 2) break;
}

$fotoPath = (!empty($userData['foto']) && file_exists(dirname(__DIR__, 2) . '/' . $userData['foto']))
            ? '../' . htmlspecialchars($userData['foto'])
            : null;
?>
<header class="topbar no-print">
    <div class="topbar-left">
        <button class="sidebar-toggle" type="button" aria-label="Buka/Tutup Menu">
            <svg viewBox="0 0 24 24">
                <line x1="3" y1="6"  x2="21" y2="6"  />
                <line x1="3" y1="12" x2="21" y2="12" />
                <line x1="3" y1="18" x2="21" y2="18" />
            </svg>
        </button>
        <div>
            <div class="page-title"><?= htmlspecialchars($page_title) ?></div>
            <div class="page-breadcrumb"><?= htmlspecialchars($page_sub) ?></div>
        </div>
    </div>

    <div class="topbar-right">
        <div class="topbar-date modern-date">
            <svg viewBox="0 0 24 24" style="width:14px;height:14px;fill:none;stroke:currentColor;stroke-width:2.5;stroke-linecap:round">
                <rect x="3" y="4" width="18" height="18" rx="2"/>
                <line x1="16" y1="2" x2="16" y2="6"/>
                <line x1="8"  y1="2" x2="8"  y2="6"/>
                <line x1="3"  y1="10" x2="21" y2="10"/>
            </svg>
            <?php date_default_timezone_set('Asia/Jakarta'); echo date('d M Y'); ?>
        </div>

        <div class="topbar-user modern-user">
            <?php if ($fotoPath): ?>
            <div class="topbar-avatar">
                <img src="<?= $fotoPath ?>" alt="Foto Profil">
            </div>
            <?php else: ?>
            <div class="topbar-avatar petugas-avatar"><?= $initials ?></div>
            <?php endif; ?>
            <span class="topbar-username"><?= htmlspecialchars(getPenggunaName()) ?></span>
        </div>

        <a href="logout.php" class="modern-btn-logout no-print">
            <svg viewBox="0 0 24 24">
                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                <polyline points="16 17 21 12 16 7"/>
                <line x1="21" y1="12" x2="9" y2="12"/>
            </svg>
            <span>Logout</span>
        </a>
    </div>
</header>

<style>
/* ── Petugas Header Styles ── */
.topbar {
    background: rgba(255,255,255,0.96);
    backdrop-filter: blur(10px);
    border-bottom: 1px solid rgba(86,28,36,0.10);
    box-shadow: 0 1px 10px rgba(86,28,36,0.06);
}

.page-title {
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: 1.1rem;
    font-weight: 700;
    color: #1e1b4b;
}

.page-breadcrumb {
    font-size: 0.75rem;
    color: #9ca3af;
}

.modern-date {
    background: #F5EFE6;
    color: #561C24;
    font-size: 0.82rem;
    font-weight: 700;
    padding: 6px 14px;
    border-radius: 999px;
    display: flex;
    align-items: center;
    gap: 6px;
}

.modern-user {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 4px 12px 4px 4px;
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    border-radius: 999px;
}

.topbar-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.8rem;
    flex-shrink: 0;
}

.topbar-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.petugas-avatar {
    background: linear-gradient(135deg, #561C24, #6D2932);
    color: white;
    box-shadow: 0 2px 8px rgba(86,28,36,0.25);
}

.topbar-username {
    font-weight: 700;
    font-size: 0.85rem;
    color: #374151;
}

.modern-btn-logout {
    display: flex;
    align-items: center;
    gap: 6px;
    background: #fff1f2;
    color: #e11d48;
    padding: 8px 16px;
    border-radius: 999px;
    font-size: 0.82rem;
    font-weight: 700;
    text-decoration: none;
    transition: all 0.2s ease;
    border: 1.5px solid #fecdd3;
    white-space: nowrap;
}

.modern-btn-logout:hover {
    background: #ffe4e6;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(225,29,72,0.18);
}

.modern-btn-logout svg {
    width: 15px;
    height: 15px;
    fill: none;
    stroke: currentColor;
    stroke-width: 2.5;
    stroke-linecap: round;
    stroke-linejoin: round;
    flex-shrink: 0;
}
</style>
