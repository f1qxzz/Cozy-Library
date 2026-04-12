<?php /* anggota/includes/header.php — v3.0 */
$page_title = $page_title ?? 'Dashboard';
$page_sub = $page_sub ?? 'Portal Anggota · Cozy-Library';

$conn = getConnection();
$anggotaId = getAnggotaId();
$stmt = $conn->prepare("SELECT foto, nama_anggota FROM anggota WHERE id_anggota = ?");
$stmt->bind_param("i", $anggotaId);
$stmt->execute();
$anggotaData = $stmt->get_result()->fetch_assoc();
$stmt->close();

$initials = '';
$nama = $anggotaData['nama_anggota'] ?? getAnggotaName();
foreach (explode(' ', trim($nama)) as $w) {
    $initials .= strtoupper(mb_substr($w, 0, 1));
    if (strlen($initials) >= 2)
        break;
}

$fotoPath = (!empty($anggotaData['foto']) && file_exists(dirname(__DIR__, 2) . '/' . $anggotaData['foto']))
    ? '../' . htmlspecialchars($anggotaData['foto'])
    : null;
?>
<header class="topbar no-print">
    <div class="topbar-left">
        <button class="sidebar-toggle" type="button" aria-label="Buka/Tutup Menu">
            <svg viewBox="0 0 24 24">
                <line x1="3" y1="6" x2="21" y2="6" />
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
        <div class="topbar-date">
            <?php date_default_timezone_set('Asia/Jakarta');
            echo date('d M Y'); ?>
        </div>

        <div class="topbar-user">
            <?php if ($fotoPath): ?>
                <div class="topbar-avatar">
                    <img src="<?= $fotoPath ?>" alt="Foto Profil">
                </div>
            <?php else: ?>
                <div class="topbar-avatar anggota">
                    <?= $initials ?>
                </div>
            <?php endif; ?>
            <span class="topbar-username"><?= htmlspecialchars(getAnggotaName()) ?></span>
        </div>

        <a href="logout.php" class="topbar-logout no-print">
            <svg viewBox="0 0 24 24"
                style="width:15px;height:15px;stroke:currentColor;fill:none;stroke-width:2.5;stroke-linecap:round;flex-shrink:0;">
                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
                <polyline points="16 17 21 12 16 7" />
                <line x1="21" y1="12" x2="9" y2="12" />
            </svg>
            <span>Logout</span>
        </a>
    </div>
</header>