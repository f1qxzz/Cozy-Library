<?php
require_once '../config/database.php';
require_once '../includes/session.php';
requireAnggota();
$conn = getConnection();
$id   = getAnggotaId();

// Data anggota
$anggotaStmt = $conn->prepare("SELECT foto, nama_anggota, nis, kelas, email FROM anggota WHERE id_anggota = ?");
$anggotaStmt->bind_param("i", $id);
$anggotaStmt->execute();
$anggotaData = $anggotaStmt->get_result()->fetch_assoc();
$anggotaStmt->close();

// Inisial avatar fallback
$initials = '';
foreach (explode(' ', trim($anggotaData['nama_anggota'] ?? '')) as $w) {
    $initials .= strtoupper(mb_substr($w, 0, 1));
    if (strlen($initials) >= 2) break;
}

// Foto path
$fotoPath = (!empty($anggotaData['foto']) && file_exists('../' . $anggotaData['foto']))
            ? '../' . htmlspecialchars($anggotaData['foto'])
            : null;

function cnt($c, $q, $f = 'c') {
    $r = $c->query($q);
    return $r ? ($r->fetch_assoc()[$f] ?? 0) : 0;
}

$ak  = cnt($conn, "SELECT COUNT(*) c FROM transaksi WHERE id_anggota=$id AND status_transaksi IN ('Pending','Dipinjam')");
$tt  = cnt($conn, "SELECT COUNT(*) c FROM transaksi WHERE id_anggota=$id");
$dn  = cnt($conn, "SELECT COALESCE(SUM(d.total_denda),0) s FROM denda d JOIN transaksi t ON d.id_transaksi=t.id_transaksi WHERE t.id_anggota=$id AND d.status_bayar='belum'", 's');
$ul  = cnt($conn, "SELECT COUNT(*) c FROM ulasan_buku WHERE id_anggota=$id");

$rows = $conn->query(
    "SELECT t.*, b.judul_buku, b.pengarang, b.cover, b.id_buku
     FROM transaksi t
     JOIN buku b ON t.id_buku = b.id_buku
     WHERE t.id_anggota = $id AND t.status_transaksi IN ('Pending','Dipinjam')
     ORDER BY t.tgl_pinjam DESC"
);

$page_title = 'Dashboard';
$page_sub   = 'Portal Anggota · Cozy-Library';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="../assets/icons/cozy-tp.png" type="image/png">
    <title>Dashboard Anggota — Cozy-Library</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/anggota/dashboard.css?v=<?= @filemtime('../assets/css/anggota/dashboard.css') ?: time() ?>">
    <link rel="stylesheet" href="../assets/css/responsive-fix.css?v=<?= @filemtime('../assets/css/responsive-fix.css') ?: time() ?>">
</head>
<body>

<div class="app-wrap">
    <?php include 'includes/nav.php'; ?>

    <div class="main-area">
        <?php include 'includes/header.php'; ?>

        <main class="content">

            <!-- Welcome Box -->
            <div class="wb">
                <div class="wb-avatar">
                    <?php if ($fotoPath): ?>
                    <img src="<?= $fotoPath ?>" alt="Foto Profil">
                    <?php else: ?>
                    <?= htmlspecialchars($initials) ?>
                    <?php endif; ?>
                </div>
                <div>
                    <div class="wb-name">Selamat Datang, <?= htmlspecialchars(getAnggotaName()) ?> 👋</div>
                    <div class="wb-sub">
                        <i class="fas fa-id-card"></i> NIS: <?= htmlspecialchars($anggotaData['nis'] ?? '-') ?>
                        &nbsp;|&nbsp;
                        <i class="fas fa-users"></i> Kelas: <?= htmlspecialchars($anggotaData['kelas'] ?? '-') ?>
                    </div>
                </div>
                <div class="wb-actions">
                    <a href="pinjam.php" class="wb-btn1"><i class="fas fa-plus-circle"></i> Pinjam Buku</a>
                    <a href="katalog.php" class="wb-btn2"><i class="fas fa-search"></i> Lihat Katalog</a>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="srow">
                <div class="sc">
                    <div>
                        <div class="sc-l">Aktif / Pending</div>
                        <div class="sc-v"><?= $ak ?></div>
                        <div class="sc-s">buku aktif</div>
                    </div>
                    <div class="sc-i"><i class="fas fa-book-open"></i></div>
                </div>
                <div class="sc">
                    <div>
                        <div class="sc-l">Total Pinjaman</div>
                        <div class="sc-v"><?= $tt ?></div>
                        <div class="sc-s">sepanjang masa</div>
                    </div>
                    <div class="sc-i"><i class="fas fa-history"></i></div>
                </div>
                <div class="sc">
                    <div>
                        <div class="sc-l">Denda Belum Bayar</div>
                        <div class="sc-v" style="font-size:<?= $dn > 99999 ? '1.15rem' : '1.9rem' ?>">Rp <?= number_format($dn, 0, ',', '.') ?></div>
                        <div class="sc-s <?= $dn > 0 ? 'bad' : 'ok' ?>">
                            <?= $dn > 0
                                ? '<i class="fas fa-exclamation-circle"></i> Segera bayar'
                                : '<i class="fas fa-check-circle"></i> Tidak ada denda' ?>
                        </div>
                    </div>
                    <div class="sc-i"><i class="fas fa-coins"></i></div>
                </div>
                <div class="sc">
                    <div>
                        <div class="sc-l">Ulasan Ditulis</div>
                        <div class="sc-v"><?= $ul ?></div>
                        <div class="sc-s"><i class="fas fa-star"></i> ulasan buku</div>
                    </div>
                    <div class="sc-i"><i class="fas fa-star"></i></div>
                </div>
            </div>

            <!-- Two Columns: Quick Menu + Table -->
            <div class="tcols">

                <!-- Quick Menu -->
                <div class="qm">
                    <div class="qm-h"><i class="fas fa-bolt"></i> Menu Cepat</div>
                    <div class="qm-grid">
                        <a href="pinjam.php"   class="qm-btn"><i class="fas fa-plus-circle"></i><span>Pinjam Buku</span></a>
                        <a href="kembali.php"  class="qm-btn"><i class="fas fa-undo-alt"></i><span>Kembalikan</span></a>
                        <a href="katalog.php"  class="qm-btn"><i class="fas fa-search"></i><span>Katalog</span></a>
                        <a href="riwayat.php"  class="qm-btn"><i class="fas fa-history"></i><span>Riwayat</span></a>
                        <a href="ulasan.php"   class="qm-btn"><i class="fas fa-star"></i><span>Ulasan</span></a>
                        <a href="profil.php"   class="qm-btn"><i class="fas fa-user"></i><span>Profil</span></a>
                    </div>
                </div>

                <!-- Buku Aktif / Pending -->
                <div class="dc">
                    <div class="dc-h">
                        <div class="dc-t">
                            <i class="fas fa-book-open"></i> Buku Dipinjam &amp; Menunggu
                        </div>
                        <a href="kembali.php" class="dc-a">Lihat Detail <i class="fas fa-arrow-right"></i></a>
                    </div>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Cover</th>
                                    <th>Judul</th>
                                    <th>Pengarang</th>
                                    <th>Tgl Pinjam</th>
                                    <th>Jatuh Tempo</th>
                                    <th>Status</th>
                                    <th>Sisa</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($rows && $rows->num_rows > 0): while ($r = $rows->fetch_assoc()):
                                    $due       = strtotime($r['tgl_kembali_rencana']);
                                    $sisa      = (int)ceil(($due - time()) / 86400);
                                    $isPending = $r['status_transaksi'] === 'Pending';
                                    if ($isPending)     { $sc='sl-w';  $icon='fa-hourglass-half';     $st='Menunggu'; }
                                    elseif ($sisa < 0)  { $sc='sl-ov'; $icon='fa-exclamation-triangle'; $st='Terlambat '.abs($sisa).'h'; }
                                    elseif ($sisa <= 2) { $sc='sl-w';  $icon='fa-clock';              $st=$sisa.' hari lagi'; }
                                    else                { $sc='sl-ok'; $icon='fa-check-circle';        $st=$sisa.' hari lagi'; }
                                ?>
                                <tr>
                                    <td class="book-cover-cell">
                                        <?php if (!empty($r['cover']) && file_exists('../'.$r['cover'])): ?>
                                        <img class="cv" src="../<?= htmlspecialchars($r['cover']) ?>" alt="">
                                        <?php else: ?>
                                        <div class="cv-ph"><i class="fas fa-book"></i></div>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="fw"><?= htmlspecialchars(mb_strimwidth($r['judul_buku'],0,34,'…')) ?></span></td>
                                    <td class="text-sm"><?= htmlspecialchars($r['pengarang']) ?></td>
                                    <td><?= date('d M Y', strtotime($r['tgl_pinjam'])) ?></td>
                                    <td><?= $isPending ? '<span class="text-muted">—</span>' : date('d M Y', $due) ?></td>
                                    <td>
                                        <?php if ($isPending): ?>
                                        <span class="badge badge-warning" style="font-size:.78em;">
                                            <i class="fas fa-hourglass-half"></i> Pending
                                        </span>
                                        <?php else: ?>
                                        <span class="badge" style="background:rgba(59,130,246,.15);color:#2563eb;font-size:.78em;">
                                            <i class="fas fa-book-open"></i> Dipinjam
                                        </span>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="sl <?= $sc ?>"><i class="fas <?= $icon ?>"></i> <?= $st ?></span></td>
                                </tr>
                                <?php endwhile; else: ?>
                                <tr>
                                    <td colspan="7" style="text-align:center;padding:48px;color:#9b8c9c;">
                                        <i class="fas fa-smile" style="font-size:3rem;color:#b5a7b6;display:block;margin-bottom:12px;"></i>
                                        📗 Belum ada pinjaman aktif &nbsp;·&nbsp;
                                        <a href="katalog.php" style="color:#9b8c9c;font-weight:700;">
                                            Cari buku <i class="fas fa-arrow-right"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div><!-- .tcols -->

        </main>
    </div>
</div>

<script>
if (window.history.replaceState) {
    window.history.replaceState(null, null, window.location.href);
}
</script>
<script src="../assets/js/script.js"></script>
</body>
</html>
