<?php
require_once '../config/database.php';
require_once '../includes/session.php';
requirePetugas();

$conn = getConnection();

// ── Date & Type filter ───────────────────────────────────────
$jenis  = isset($_GET['jenis']) && $_GET['jenis'] !== '' ? $_GET['jenis'] : 'peminjaman';
$dari   = isset($_GET['dari'])  && $_GET['dari']  !== '' ? $_GET['dari']  : null;
$sampai = isset($_GET['sampai']) && $_GET['sampai'] !== '' ? $_GET['sampai'] : null;
$reset  = isset($_GET['reset']);
if ($reset) { $dari = null; $sampai = null; $jenis = 'peminjaman'; }

// Statistik Umum
$total_buku      = $conn->query("SELECT COUNT(*) c FROM buku")->fetch_assoc()['c'];
$total_anggota   = $conn->query("SELECT COUNT(*) c FROM anggota")->fetch_assoc()['c'];
$aktif_pinjam    = $conn->query("SELECT COUNT(*) c FROM transaksi WHERE status_transaksi='Peminjaman'")->fetch_assoc()['c'];
$denda_belum     = $conn->query("SELECT COALESCE(SUM(total_denda),0) c FROM denda WHERE status_bayar='belum'")->fetch_assoc()['c'];

// Build query based on report type
$whereClause = "WHERE 1=1";
$params = []; $types = '';

if ($jenis === 'peminjaman') {
    if ($dari)   { $whereClause .= " AND t.tgl_pinjam >= ?"; $params[] = $dari;   $types .= 's'; }
    if ($sampai) { $whereClause .= " AND t.tgl_pinjam <= ?"; $params[] = $sampai; $types .= 's'; }

    $sql = "SELECT t.id_transaksi, a.nama_anggota, b.judul_buku,
                   t.tgl_pinjam, t.tgl_kembali_rencana, t.tgl_kembali_aktual, t.status_transaksi
            FROM transaksi t
            JOIN anggota a ON t.id_anggota = a.id_anggota
            JOIN buku b    ON t.id_buku    = b.id_buku
            $whereClause
            ORDER BY t.tgl_pinjam DESC";

} elseif ($jenis === 'denda') {
    if ($dari)   { $whereClause .= " AND t.tgl_kembali_aktual >= ?"; $params[] = $dari;   $types .= 's'; }
    if ($sampai) { $whereClause .= " AND t.tgl_kembali_aktual <= ?"; $params[] = $sampai; $types .= 's'; }

    $sql = "SELECT d.*, t.tgl_kembali_aktual, a.nama_anggota, b.judul_buku
            FROM denda d
            JOIN transaksi t ON d.id_transaksi = t.id_transaksi
            JOIN anggota a ON t.id_anggota = a.id_anggota
            JOIN buku b    ON t.id_buku    = b.id_buku
            $whereClause
            ORDER BY t.tgl_kembali_aktual DESC";

} elseif ($jenis === 'buku') {
    // Laporan Data Buku (Mengabaikan filter tanggal agar tidak error jika tidak ada kolom tanggal di tabel buku)
    $sql = "SELECT * FROM buku ORDER BY judul_buku ASC";

} elseif ($jenis === 'anggota') {
    // Laporan Data Anggota (Mengabaikan filter tanggal)
    $sql = "SELECT * FROM anggota ORDER BY nama_anggota ASC";
}

if ($params) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $trans_all = $stmt->get_result();
} else {
    $trans_all = $conn->query($sql);
}

$total_data = $trans_all ? $trans_all->num_rows : 0;
$rows_cache = [];
if ($trans_all && $trans_all->num_rows > 0) {
    while ($r = $trans_all->fetch_assoc()) $rows_cache[] = $r;
}

// Konfigurasi Judul & ID Laporan
$page_title = 'Laporan Peminjaman';
$prefix = 'PJM';
if ($jenis === 'denda') { $page_title = 'Laporan Denda'; $prefix = 'DND'; }
if ($jenis === 'buku') { $page_title = 'Laporan Data Buku'; $prefix = 'BKU'; }
if ($jenis === 'anggota') { $page_title = 'Laporan Data Anggota'; $prefix = 'AGT'; }

$page_sub   = 'Ringkasan data sirkulasi dan master Cozy-Library';
$no_laporan = 'PTG-' . date('Ymd') . '-' . $prefix;
$tgl_cetak  = date('d F Y');
$jam_cetak  = date('H:i') . ' WIB';
$cssVer     = @filemtime('../assets/css/petugas_laporan.css') ?: time();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" href="../assets/icons/cozy-tp.png" type="image/png">
    <title><?= $page_title ?> — Petugas Cozy-Library</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/petugas/petugas.css?v=<?= @filemtime('../assets/css/petugas/petugas.css') ?: time() ?>">
    <link rel="stylesheet" href="../assets/css/petugas/laporan.css?v=<?= $cssVer ?>">
    <link rel="stylesheet" href="../assets/css/responsive-fix.css?v=<?= @filemtime('../assets/css/responsive-fix.css') ?: time() ?>">
<style>
/* ── Extra styles for date-filter + UI ── */
.filter-card {
    background: #fff; border-radius: 14px; box-shadow: 0 2px 12px rgba(79,70,229,.07);
    padding: 20px 24px; margin-bottom: 24px; border: 1px solid #e0e7ff;
    display: flex; align-items: flex-end; gap: 16px; flex-wrap: wrap;
}
.filter-group { display: flex; flex-direction: column; gap: 6px; }
.filter-label { font-size: 0.78rem; font-weight: 600; color: #4b5563; }
.filter-input {
    padding: 9px 14px; border: 1px solid #d1d5db; border-radius: 9px;
    font-size: 0.875rem; font-family: 'Inter', sans-serif; color: #1f2937;
    outline: none; transition: border 0.2s; background: #f9fafb;
}
.filter-input:focus { border-color: #6366f1; background: #fff; box-shadow: 0 0 0 3px rgba(99,102,241,0.1); }
.btn-filter {
    padding: 9px 20px; background: #4f46e5; color: white; border: none;
    border-radius: 9px; font-size: 0.875rem; font-weight: 600; cursor: pointer;
    display: flex; align-items: center; gap: 7px; transition: background 0.2s;
}
.btn-filter:hover { background: #4338ca; }
.btn-reset {
    padding: 9px 20px; background: #f3f4f6; color: #4b5563; border: 1px solid #e5e7eb;
    border-radius: 9px; font-size: 0.875rem; font-weight: 600; cursor: pointer;
    display: flex; align-items: center; gap: 7px; text-decoration: none; transition: background 0.2s;
}
.btn-reset:hover { background: #e5e7eb; }
.btn-print {
    padding: 9px 22px; background: #10b981; color: white; border: none;
    border-radius: 9px; font-size: 0.875rem; font-weight: 600; cursor: pointer;
    display: flex; align-items: center; gap: 7px; transition: background 0.2s; margin-left: auto;
}
.btn-print:hover { background: #059669; }

.period-badge {
    display: inline-flex; align-items: center; gap: 6px; background: #eef2ff; color: #4338ca;
    padding: 4px 12px; border-radius: 20px; font-size: 0.78rem; font-weight: 600;
}

/* Report Table */
.report-card { background: #fff; border-radius: 14px; box-shadow: 0 2px 12px rgba(79,70,229,.07); border: 1px solid #e0e7ff; overflow: hidden; margin-bottom: 24px; }
.report-card-header { padding: 18px 24px; border-bottom: 1px solid #e5e7eb; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; }
.report-card-title { font-size: 1rem; font-weight: 700; color: #1e1b4b; display: flex; align-items: center; gap: 9px; }
.report-card-title i { color: #6366f1; }
.report-table { width: 100%; border-collapse: collapse; }
.report-table thead th { background: #f8fafc; padding: 11px 16px; font-size: 0.78rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #6b7280; border-bottom: 2px solid #e5e7eb; text-align: left; }
.report-table tbody td { padding: 13px 16px; font-size: 0.875rem; color: #374151; border-bottom: 1px solid #f3f4f6; }
.report-table tbody tr:hover { background: #fafbff; }
.badge-status { display: inline-flex; align-items: center; gap: 5px; padding: 4px 11px; border-radius: 20px; font-size: 0.72rem; font-weight: 700; white-space: nowrap; }
.badge-dipinjam { background: #fef3c7; color: #92400e; }
.badge-kembali  { background: #d1fae5; color: #065f46; }
.badge-terlambat{ background: #fee2e2; color: #991b1b; }
.empty-row td { text-align: center; padding: 48px 16px; color: #9ca3af; }
.empty-row .empty-ico { font-size: 2.5rem; margin-bottom: 10px; }

/* PRINT MODERNIZATION */
.print-header, .print-footer { display: none; }

@media print {
    @page { margin: 1.5cm 1.5cm; size: A4 portrait; }
    .no-print, .sidebar, .topbar, header, .filter-card, .stats-grid, .page-header { display: none !important; }
    
    .main-area { margin-left: 0 !important; width: 100% !important; padding: 0 !important; }
    .app-wrap { display: block !important; }
    body { background: white !important; font-family: 'Inter', sans-serif !important; color: #111827 !important; }
    
    .print-header { display: block !important; margin-bottom: 25px; padding-bottom: 15px; border-bottom: 2px solid #111827; }
    .print-header-top { display: flex; justify-content: space-between; align-items: flex-start; }
    .ph-brand { font-size: 1.4rem; font-weight: 800; color: #111827; letter-spacing: -0.02em; line-height: 1.2; }
    .ph-address { font-size: 0.85rem; color: #4b5563; margin-top: 4px; line-height: 1.5; }
    .ph-doc { text-align: right; }
    .ph-doc-title { font-size: 1.2rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; color: #111827; margin-bottom: 5px; }
    .ph-doc-meta { font-size: 0.8rem; color: #4b5563; margin-top: 3px; }
    
    .ph-summary { display: flex; justify-content: space-between; margin-top: 20px; font-size: 0.85rem; color: #111827; }
    
    .report-card { box-shadow: none !important; border: none !important; margin: 0 !important; padding: 0 !important; }
    .report-card-header { display: none !important; }
    .report-table { border-collapse: collapse !important; width: 100% !important; margin-top: 10px; }
    .report-table thead th { background: #f8fafc !important; color: #111827 !important; font-size: 0.75rem !important; font-weight: 700 !important; padding: 10px 8px !important; border-top: 1px solid #111827 !important; border-bottom: 2px solid #111827 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .report-table tbody td { border: none !important; border-bottom: 1px solid #e5e7eb !important; padding: 10px 8px !important; font-size: 0.85rem !important; color: #374151 !important; page-break-inside: avoid; }
    .report-table tbody tr:last-child td { border-bottom: 2px solid #111827 !important; }
    
    .badge-status { background: transparent !important; padding: 3px 8px !important; border: 1px solid #d1d5db !important; border-radius: 6px !important; font-weight: 600 !important; }
    .badge-dipinjam { border-color: #d97706 !important; color: #d97706 !important; }
    .badge-kembali  { border-color: #059669 !important; color: #059669 !important; }
    .badge-terlambat{ border-color: #dc2626 !important; color: #dc2626 !important; }

    .print-footer { display: flex !important; justify-content: space-between; align-items: flex-end; margin-top: 40px; page-break-inside: avoid; }
    .pf-note { font-size: 0.75rem; color: #6b7280; line-height: 1.5; }
    .pf-signature { text-align: center; width: 220px; }
    .pf-sign-title { font-size: 0.85rem; color: #374151; margin-bottom: 60px; }
    .pf-sign-name { font-size: 0.9rem; font-weight: 700; color: #111827; border-bottom: 1px solid #111827; padding-bottom: 4px; margin-bottom: 4px; }
    .pf-sign-role { font-size: 0.75rem; color: #4b5563; }
}
</style>
</head>
<body>
<div class="app-wrap">
<?php include 'includes/nav.php'; ?>
<div class="main-area">
<?php include 'includes/header.php'; ?>

<main class="content">
<div class="page-header no-print">
    <div>
        <h1 class="page-header-title"><?= $page_title ?></h1>
        <p class="page-header-sub">Ringkasan data sirkulasi dan master Cozy-Library</p>
    </div>
    <button class="btn-print" onclick="window.print()">
        <i class="fas fa-print"></i> Cetak Laporan
    </button>
</div>

<div class="stats-grid no-print">
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-book"></i></div>
        <div class="stat-info"><h3>Total Buku</h3><div class="stat-number"><?= $total_buku ?></div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-users"></i></div>
        <div class="stat-info"><h3>Anggota Aktif</h3><div class="stat-number"><?= $total_anggota ?></div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-exchange-alt"></i></div>
        <div class="stat-info"><h3>Buku Dipinjam</h3><div class="stat-number"><?= $aktif_pinjam ?></div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="color: #ef4444; background: #fee2e2;"><i class="fas fa-coins"></i></div>
        <div class="stat-info"><h3>Denda Belum Lunas</h3><div class="stat-number" style="font-size: 1.1rem; line-height: 1.5;">Rp <?= number_format($denda_belum, 0, ',', '.') ?></div></div>
    </div>
</div>

<form method="GET" class="filter-card no-print">
    <div class="filter-group">
        <label class="filter-label"><i class="fas fa-file-alt" style="color:#6366f1"></i> Jenis Laporan</label>
        <select name="jenis" class="filter-input" onchange="this.form.submit()">
            <option value="peminjaman" <?= $jenis == 'peminjaman' ? 'selected' : '' ?>>Peminjaman Buku</option>
            <option value="denda" <?= $jenis == 'denda' ? 'selected' : '' ?>>Denda & Keterlambatan</option>
            <option value="buku" <?= $jenis == 'buku' ? 'selected' : '' ?>>Master Data Buku</option>
            <option value="anggota" <?= $jenis == 'anggota' ? 'selected' : '' ?>>Master Data Anggota</option>
        </select>
    </div>
    <?php if ($jenis === 'peminjaman' || $jenis === 'denda'): ?>
    <div class="filter-group">
        <label class="filter-label"><i class="fas fa-calendar-alt" style="color:#6366f1"></i> Dari Tanggal</label>
        <input type="date" name="dari" class="filter-input" value="<?= htmlspecialchars($dari ?? '') ?>">
    </div>
    <div class="filter-group">
        <label class="filter-label"><i class="fas fa-calendar-check" style="color:#6366f1"></i> Sampai Tanggal</label>
        <input type="date" name="sampai" class="filter-input" value="<?= htmlspecialchars($sampai ?? '') ?>">
    </div>
    <?php else: ?>
    <div class="filter-group" style="justify-content:center; color:#6b7280; font-size:0.8rem;">
        <em>(Filter tanggal dinonaktifkan untuk Master Data)</em>
    </div>
    <?php endif; ?>

    <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Filter</button>
    <a href="laporan.php?reset=1" class="btn-reset"><i class="fas fa-undo"></i> Reset</a>
</form>

<div class="print-header">
    <div class="print-header-top">
        <div>
            <div class="ph-brand">Cozy-Library</div>
            <div class="ph-address">Jl. Pendidikan No. 1<br>Sistem Manajemen Cozy-Library</div>
        </div>
        <div class="ph-doc">
            <div class="ph-doc-title"><?= $page_title ?></div>
            <div class="ph-doc-meta">No. Dokumen: <strong><?= $no_laporan ?></strong></div>
            <div class="ph-doc-meta">Dicetak: <?= $tgl_cetak ?>, <?= $jam_cetak ?></div>
        </div>
    </div>
    <div class="ph-summary">
        <div>
            <strong>Periode Laporan:</strong> 
            <?php if ($jenis === 'buku' || $jenis === 'anggota'): ?>
                Semua Periode (Keseluruhan Data)
            <?php elseif ($dari || $sampai): ?>
                <?= $dari ? date('d M Y',strtotime($dari)) : 'Awal' ?> s/d <?= $sampai ? date('d M Y',strtotime($sampai)) : 'Sekarang' ?>
            <?php else: ?>
                Semua Periode
            <?php endif; ?>
        </div>
        <div><strong>Total Transaksi/Data:</strong> <?= $total_data ?> baris</div>
    </div>
</div>

<div class="report-card">
    <div class="report-card-header">
        <div class="report-card-title">
            <?php
               $icon = 'fa-list-alt';
               if ($jenis === 'denda') $icon = 'fa-coins';
               if ($jenis === 'buku') $icon = 'fa-book';
               if ($jenis === 'anggota') $icon = 'fa-users';
            ?>
            <i class="fas <?= $icon ?>"></i> <?= $page_title ?>
            <span class="period-badge">
                <?php if ($jenis === 'buku' || $jenis === 'anggota'): ?>
                    <i class="fas fa-database"></i> Master Data
                <?php elseif ($dari || $sampai): ?>
                    <i class="fas fa-calendar"></i>
                    <?= $dari ? date('d M Y',strtotime($dari)) : '—' ?> s/d <?= $sampai ? date('d M Y',strtotime($sampai)) : 'sekarang' ?>
                <?php else: ?>
                    <i class="fas fa-infinity"></i> Semua Periode
                <?php endif; ?>
            </span>
        </div>
        <span style="font-size:0.82rem;color:#6b7280;"><?= $total_data ?> data ditemukan</span>
    </div>
    <div style="overflow-x:auto">
    <table class="report-table">
        <?php if ($jenis === 'peminjaman'): ?>
        <thead>
            <tr>
                <th style="width:40px">No</th>
                <th>Nama Pengguna</th>
                <th>Judul Buku</th>
                <th>Tgl Pinjam</th>
                <th>Tgl Kembali</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($rows_cache): $no = 1; foreach ($rows_cache as $r):
            $late = $r['status_transaksi'] === 'Peminjaman' && strtotime($r['tgl_kembali_rencana']) < time();
            if ($r['status_transaksi'] === 'Pengembalian') {
                $bc = 'badge-kembali'; $bl = '<i class="fas fa-check-circle"></i> Dikembalikan';
            } elseif ($late) {
                $bc = 'badge-terlambat'; $bl = '<i class="fas fa-exclamation-circle"></i> Terlambat';
            } else {
                $bc = 'badge-dipinjam'; $bl = '<i class="fas fa-book-reader"></i> Dipinjam';
            }
        ?>
        <tr>
            <td style="color:#9ca3af;font-size:0.8rem"><?= $no++ ?></td>
            <td style="font-weight:600;color:#111827"><?= htmlspecialchars($r['nama_anggota']) ?></td>
            <td><?= htmlspecialchars($r['judul_buku']) ?></td>
            <td><?= date('d/m/Y', strtotime($r['tgl_pinjam'])) ?></td>
            <td><?= $r['tgl_kembali_aktual'] ? date('d/m/Y', strtotime($r['tgl_kembali_aktual'])) : '<span style="color:#9ca3af">—</span>' ?></td>
            <td><span class="badge-status <?= $bc ?>"><?= $bl ?></span></td>
        </tr>
        <?php endforeach; else: ?>
        <tr class="empty-row"><td colspan="6"><div class="empty-ico">📋</div><div style="font-weight:600;color:#374151">Tidak ada data</div><div style="font-size:0.82rem;margin-top:4px">Coba ubah filter tanggal</div></td></tr>
        <?php endif; ?>
        </tbody>

        <?php elseif ($jenis === 'denda'): ?>
        <thead>
            <tr>
                <th style="width:40px">No</th>
                <th>Nama Anggota</th>
                <th>Judul Buku</th>
                <th>Tgl Kembali</th>
                <th>Keterlambatan</th>
                <th>Total Denda</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($rows_cache): $no = 1; foreach ($rows_cache as $r):
            if ($r['status_bayar'] === 'lunas') {
                $bc = 'badge-kembali'; $bl = '<i class="fas fa-check-circle"></i> Lunas';
            } else {
                $bc = 'badge-terlambat'; $bl = '<i class="fas fa-times-circle"></i> Belum Lunas';
            }
        ?>
        <tr>
            <td style="color:#9ca3af;font-size:0.8rem"><?= $no++ ?></td>
            <td style="font-weight:600;color:#111827"><?= htmlspecialchars($r['nama_anggota']) ?></td>
            <td><?= htmlspecialchars(mb_strimwidth($r['judul_buku'], 0, 35, '...')) ?></td>
            <td><?= date('d/m/Y', strtotime($r['tgl_kembali_aktual'])) ?></td>
            <td><?= $r['jumlah_hari'] ?> hari</td>
            <td style="font-weight:600;">Rp <?= number_format($r['total_denda'], 0, ',', '.') ?></td>
            <td><span class="badge-status <?= $bc ?>"><?= $bl ?></span></td>
        </tr>
        <?php endforeach; else: ?>
        <tr class="empty-row"><td colspan="7"><div class="empty-ico">💸</div><div style="font-weight:600;color:#374151">Tidak ada denda</div><div style="font-size:0.82rem;margin-top:4px">Bagus! Semua berjalan lancar.</div></td></tr>
        <?php endif; ?>
        </tbody>
        
        <?php elseif ($jenis === 'buku'): ?>
        <thead>
            <tr>
                <th style="width:40px">No</th>
                <th>Judul Buku</th>
                <th>Penulis</th>
                <th>Penerbit</th>
                <th>Tahun</th>
                <th>Stok</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($rows_cache): $no = 1; foreach ($rows_cache as $r): ?>
        <tr>
            <td style="color:#9ca3af;font-size:0.8rem"><?= $no++ ?></td>
            <td style="font-weight:600;color:#111827"><?= htmlspecialchars($r['judul_buku'] ?? '-') ?></td>
            <td><?= htmlspecialchars($r['penulis'] ?? '-') ?></td>
            <td><?= htmlspecialchars($r['penerbit'] ?? '-') ?></td>
            <td><?= htmlspecialchars($r['tahun_terbit'] ?? '-') ?></td>
            <td style="font-weight:600;"><?= (int)($r['stok'] ?? 0) ?></td>
            <td>
                <?php if (($r['status'] ?? '') === 'tersedia'): ?>
                    <span class="badge-status badge-kembali">Tersedia</span>
                <?php else: ?>
                    <span class="badge-status badge-terlambat"><?= ucfirst($r['status'] ?? 'Habis/Dihapus') ?></span>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; else: ?>
        <tr class="empty-row"><td colspan="7"><div class="empty-ico">📚</div><div style="font-weight:600;color:#374151">Tidak ada data buku</div></td></tr>
        <?php endif; ?>
        </tbody>

        <?php elseif ($jenis === 'anggota'): ?>
        <thead>
            <tr>
                <th style="width:40px">No</th>
                <th>NIS</th>
                <th>Nama Anggota</th>
                <th>Kelas</th>
                <th>No. Telp</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($rows_cache): $no = 1; foreach ($rows_cache as $r): ?>
        <tr>
            <td style="color:#9ca3af;font-size:0.8rem"><?= $no++ ?></td>
            <td style="font-family:monospace;"><?= htmlspecialchars($r['nis'] ?? '-') ?></td>
            <td style="font-weight:600;color:#111827"><?= htmlspecialchars($r['nama_anggota'] ?? '-') ?></td>
            <td><?= htmlspecialchars($r['kelas'] ?? '-') ?></td>
            <td><?= htmlspecialchars($r['no_telp'] ?? '-') ?></td>
            <td>
                <?php if (($r['status'] ?? '') === 'aktif'): ?>
                    <span class="badge-status badge-kembali">Aktif</span>
                <?php else: ?>
                    <span class="badge-status badge-terlambat"><?= ucfirst($r['status'] ?? 'Nonaktif') ?></span>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; else: ?>
        <tr class="empty-row"><td colspan="6"><div class="empty-ico">👥</div><div style="font-weight:600;color:#374151">Tidak ada data anggota</div></td></tr>
        <?php endif; ?>
        </tbody>
        <?php endif; ?>
    </table>
    </div>
</div>

<div class="print-footer">
    <div class="pf-note">
        <strong>Catatan:</strong><br>
        Dokumen ini merupakan hasil cetak otomatis dari Sistem Manajemen Cozy-Library.<br>
        Informasi yang tertera sesuai dengan data pada sistem saat dicetak.
    </div>
    <div class="pf-signature">
        <div class="pf-sign-title">Mengetahui,</div>
        <div class="pf-sign-name"><?= htmlspecialchars(getPenggunaName()) ?></div>
        <div class="pf-sign-role">Petugas Cozy-Library</div>
    </div>
</div>

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