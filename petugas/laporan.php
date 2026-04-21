<?php
/*
 * Alur logic PHP:
 * 1) Memuat dependency utama (database, session, dan helper).
 * 2) Validasi hak akses sebelum memproses data sensitif.
 * 3) Proses input GET/POST, jalankan query, lalu siapkan data view.
 * 4) Render output halaman sesuai role dan konteks fitur.
 */require_once '../config/database.php';
require_once '../includes/session.php';
require_once '../includes/denda_helper.php';
requirePetugas();

$conn = getConnection();
syncDendaWithTransaksi($conn);


// ── Parameter Filter ─────────────────────────────────────────
$jenis = isset($_GET['jenis']) ? $_GET['jenis'] : 'peminjaman';
$dari = isset($_GET['dari']) && $_GET['dari'] !== '' ? $_GET['dari'] : null;
$sampai = isset($_GET['sampai']) && $_GET['sampai'] !== '' ? $_GET['sampai'] : null;
if (isset($_GET['reset'])) {
    $dari = null;
    $sampai = null;
}

$valid_jenis = ['anggota', 'buku', 'peminjaman', 'denda', 'pengguna'];
if (!in_array($jenis, $valid_jenis))
    $jenis = 'peminjaman';

// ── Statistik Ringkasan ──────────────────────────────────────
$total_buku = $conn->query("SELECT COUNT(*) c FROM buku")->fetch_assoc()['c'];
$buku_tersedia = $conn->query("SELECT COUNT(*) c FROM buku WHERE status='tersedia'")->fetch_assoc()['c'];
$total_anggota = $conn->query("SELECT COUNT(*) c FROM anggota")->fetch_assoc()['c'];
$total_pinjam = $conn->query("SELECT COUNT(*) c FROM transaksi")->fetch_assoc()['c'];
$aktif_pinjam = $conn->query("SELECT COUNT(*) c FROM transaksi WHERE status_transaksi IN ('Peminjaman','Dipinjam')")->fetch_assoc()['c'];
$dendaStats = getDendaStats($conn);
$total_denda = $dendaStats['total_denda'];
$denda_belum = $dendaStats['total_belum'];

// ── Build Query berdasarkan Jenis Laporan ───────────────────
$rows_cache = [];
$total_data = 0;
$table_title = '';
$table_icon = '';

// Helper date filter builder
function buildDateWhere($dari, $sampai, $col, &$params, &$types)
{
    $w = "WHERE 1=1";
    if ($dari) {
        $w .= " AND $col >= ?";
        $params[] = $dari;
        $types .= 's';
    }
    if ($sampai) {
        $w .= " AND $col <= ?";
        $params[] = $sampai;
        $types .= 's';
    }
    return $w;
}

switch ($jenis) {

    // ── 1. DATA ANGGOTA ────────────────────────────────────
    case 'anggota':
        $table_title = 'Data Anggota';
        $table_icon = 'fa-users';
        $params = [];
        $types = '';
        // Filter by tanggal tidak terlalu relevan untuk anggota, tampilkan semua + filter nama
        $sql = "SELECT a.id_anggota, a.nis, a.nama_anggota, a.email, a.kelas, a.status,
                       COUNT(t.id_transaksi) AS total_pinjam
                FROM anggota a
                LEFT JOIN transaksi t ON t.id_anggota = a.id_anggota
                GROUP BY a.id_anggota
                ORDER BY a.nama_anggota ASC";
        $res = $conn->query($sql);
        while ($r = $res->fetch_assoc())
            $rows_cache[] = $r;
        $total_data = count($rows_cache);
        break;

    // ── 2. DATA BUKU ───────────────────────────────────────
    case 'buku':
        $table_title = 'Data Buku';
        $table_icon = 'fa-book';
        $sql = "SELECT b.id_buku, b.judul_buku, k.nama_kategori, b.pengarang, b.penerbit,
                       b.tahun_terbit, b.isbn, b.stok, b.status
                FROM buku b
                LEFT JOIN kategori k ON k.id_kategori = b.id_kategori
                ORDER BY b.judul_buku ASC";
        $res = $conn->query($sql);
        while ($r = $res->fetch_assoc())
            $rows_cache[] = $r;
        $total_data = count($rows_cache);
        break;

    // ── 3. PEMINJAMAN BUKU ─────────────────────────────────
    case 'peminjaman':
    default:
        $table_title = 'Peminjaman Buku';
        $table_icon = 'fa-exchange-alt';
        $params = [];
        $types = '';
        $where = buildDateWhere($dari, $sampai, 't.tgl_pinjam', $params, $types);
        $sql = "SELECT t.id_transaksi, a.nis, a.nama_anggota, b.judul_buku,
                       t.tgl_pinjam, t.tgl_kembali_rencana, t.tgl_kembali_aktual, t.status_transaksi
                FROM transaksi t
                JOIN anggota a ON t.id_anggota = a.id_anggota
                JOIN buku b    ON t.id_buku    = b.id_buku
                $where
                ORDER BY t.tgl_pinjam DESC";
        if ($params) {
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $res = $stmt->get_result();
        } else {
            $res = $conn->query($sql);
        }
        while ($r = $res->fetch_assoc())
            $rows_cache[] = $r;
        $total_data = count($rows_cache);
        break;

    // ── 4. DENDA BUKU ─────────────────────────────────────
    case 'denda':
        $table_title = 'Denda Buku';
        $table_icon = 'fa-coins';
        $params = [];
        $types = '';
        // Cek kolom yang tersedia di tabel denda
        $col_check = $conn->query("SHOW COLUMNS FROM denda");
        $denda_cols = [];
        while ($col = $col_check->fetch_assoc())
            $denda_cols[] = $col['Field'];
        $has_tgl_denda = in_array('tgl_denda', $denda_cols);
        $has_tgl_bayar = in_array('tgl_bayar', $denda_cols);
        $has_created = in_array('created_at', $denda_cols);

        $select_tgl_denda = $has_tgl_denda ? 'd.tgl_denda' : ($has_created ? 'd.created_at AS tgl_denda' : 'NULL AS tgl_denda');
        $select_tgl_bayar = $has_tgl_bayar ? 'd.tgl_bayar' : 'NULL AS tgl_bayar';
        $order_col = $has_tgl_denda ? 'd.tgl_denda' : 'd.id_denda';
        $filter_col = $has_tgl_denda ? 'd.tgl_denda' : ($has_created ? 'd.created_at' : null);
        $canonicalDenda = getCanonicalDendaSubquery();

        if ($filter_col) {
            $where = buildDateWhere($dari, $sampai, $filter_col, $params, $types);
        } else {
            $where = "WHERE 1=1";
        }

        $sql = "SELECT d.id_denda, a.nis, a.nama_anggota, b.judul_buku,
                       d.jumlah_hari, d.tarif_per_hari, d.total_denda,
                       d.status_bayar, $select_tgl_denda, $select_tgl_bayar
                FROM {$canonicalDenda} d
                JOIN transaksi t ON t.id_transaksi = d.id_transaksi
                JOIN anggota a   ON a.id_anggota   = t.id_anggota
                JOIN buku b      ON b.id_buku       = t.id_buku
                $where
                ORDER BY $order_col DESC";
        if ($params) {
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $res = $stmt->get_result();
        } else {
            $res = $conn->query($sql);
        }
        if ($res) {
            while ($r = $res->fetch_assoc())
                $rows_cache[] = $r;
        }
        $total_data = count($rows_cache);
        break;

    // ── 5. DATA PENGGUNA ───────────────────────────────────
    case 'pengguna':
        $table_title = 'Data Pengguna';
        $table_icon = 'fa-user-shield';
        $sql = "SELECT id_pengguna, nama_pengguna, username, email, level FROM pengguna ORDER BY level, nama_pengguna ASC";
        $res = $conn->query($sql);
        while ($r = $res->fetch_assoc())
            $rows_cache[] = $r;
        $total_data = count($rows_cache);
        break;
}

$page_title = 'Laporan';
$no_laporan = 'RPT-' . date('Ymd') . '-001';
$tgl_cetak = date('d F Y');
$jam_cetak = date('H:i') . ' WIB';
$cssVer = time();

// Label jenis untuk print
$jenis_labels = [
    'anggota' => 'Data Anggota',
    'buku' => 'Data Buku',
    'peminjaman' => 'Peminjaman Buku',
    'denda' => 'Denda Buku',
    'pengguna' => 'Data Pengguna',
];
$current_label = $jenis_labels[$jenis] ?? 'Laporan';
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="../assets/icons/cozy-tp.png" type="image/png">
    <title>Laporan — Admin Cozy-Library</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <link rel="stylesheet"
        href="../assets/css/petugas/petugas.css?v=<?= @filemtime('../assets/css/petugas/petugas.css') ?: time() ?>">
    <link rel="stylesheet" href="../assets/css/petugas/laporan.css?v=<?= $cssVer ?>">
    <link rel="stylesheet"
        href="../assets/css/responsive-fix.css?v=<?= @filemtime('../assets/css/responsive-fix.css') ?: time() ?>">
    <link rel="stylesheet" href="../assets/css/print.css?v=<?= @filemtime('../assets/css/print.css') ?: time() ?>">
    <style>
        /* ── Filter Card ─────────────────────────────────────────────── */
        .filter-card {
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 2px 12px rgba(79, 70, 229, .07);
            padding: 20px 24px;
            margin-bottom: 24px;
            border: 1px solid #e0e7ff;
            display: flex;
            align-items: flex-end;
            gap: 16px;
            flex-wrap: wrap;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .filter-label {
            font-size: .78rem;
            font-weight: 600;
            color: #4b5563;
        }

        .filter-select,
        .filter-input {
            padding: 9px 14px;
            border: 1px solid #d1d5db;
            border-radius: 9px;
            font-size: .875rem;
            font-family: 'Inter', sans-serif;
            color: #1f2937;
            outline: none;
            transition: border .2s;
            background: #f9fafb;
        }

        .filter-select {
            min-width: 180px;
            cursor: pointer;
        }

        .filter-select:focus,
        .filter-input:focus {
            border-color: #6366f1;
            background: #fff;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, .1);
        }

        .btn-filter {
            padding: 9px 20px;
            background: #4f46e5;
            color: #fff;
            border: none;
            border-radius: 9px;
            font-size: .875rem;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 7px;
            transition: background .2s;
        }

        .btn-filter:hover {
            background: #4338ca;
        }

        .btn-reset {
            padding: 9px 20px;
            background: #f3f4f6;
            color: #4b5563;
            border: 1px solid #e5e7eb;
            border-radius: 9px;
            font-size: .875rem;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 7px;
            text-decoration: none;
            transition: background .2s;
        }

        .btn-reset:hover {
            background: #e5e7eb;
        }

        .btn-print {
            padding: 9px 22px;
            background: #10b981;
            color: #fff;
            border: none;
            border-radius: 9px;
            font-size: .875rem;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 7px;
            transition: background .2s;
        }

        .btn-print:hover {
            background: #059669;
        }

        .filter-divider {
            width: 1px;
            height: 38px;
            background: #e5e7eb;
            align-self: flex-end;
        }

        /* Date filter: hide for anggota/buku/pengguna */
        .date-filter-wrap {
            display: flex;
            align-items: flex-end;
            gap: 16px;
        }

        .date-filter-wrap.hidden {
            display: none !important;
        }

        /* ── Period Badge ─────────────────────────────────────────────── */
        .period-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #eef2ff;
            color: #4338ca;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: .78rem;
            font-weight: 600;
        }

        /* ── Report Card ─────────────────────────────────────────────── */
        .report-card {
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 2px 12px rgba(79, 70, 229, .07);
            border: 1px solid #e0e7ff;
            overflow: hidden;
            margin-bottom: 24px;
        }

        .report-card-header {
            padding: 18px 24px;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
        }

        .report-card-title {
            font-size: 1rem;
            font-weight: 700;
            color: #1e1b4b;
            display: flex;
            align-items: center;
            gap: 9px;
        }

        .report-card-title i {
            color: #6366f1;
        }

        /* ── Table ────────────────────────────────────────────────────── */
        .report-table {
            width: 100%;
            border-collapse: collapse;
        }

        .report-table thead th {
            background: #f8fafc;
            padding: 11px 16px;
            font-size: .78rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .05em;
            color: #6b7280;
            border-bottom: 2px solid #e5e7eb;
            text-align: left;
            white-space: nowrap;
        }

        .report-table tbody td {
            padding: 13px 16px;
            font-size: .875rem;
            color: #374151;
            border-bottom: 1px solid #f3f4f6;
            vertical-align: middle;
        }

        .report-table tbody tr:last-child td {
            border-bottom: none;
        }

        .report-table tbody tr:hover {
            background: #fafbff;
        }

        /* ── Badges ───────────────────────────────────────────────────── */
        .badge-status {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 11px;
            border-radius: 20px;
            font-size: .72rem;
            font-weight: 700;
            white-space: nowrap;
        }

        .badge-dipinjam {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-kembali {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-terlambat {
            background: #fee2e2;
            color: #991b1b;
        }

        .badge-aktif {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-nonaktif {
            background: #f3f4f6;
            color: #6b7280;
        }

        .badge-tersedia {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-habis {
            background: #fee2e2;
            color: #991b1b;
        }

        .badge-lunas {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-belum {
            background: #fee2e2;
            color: #991b1b;
        }

        .badge-admin {
            background: #ede9fe;
            color: #5b21b6;
        }

        .badge-petugas {
            background: #e0f2fe;
            color: #075985;
        }

        .badge-anggota-r {
            background: #ecfdf5;
            color: #065f46;
        }

        /* ── Empty Row ────────────────────────────────────────────────── */
        .empty-row td {
            text-align: center;
            padding: 48px 16px;
            color: #9ca3af;
        }

        /* ── Print Header/Footer ─────────────────────────────────────── */
        .print-header,
        .print-footer {
            display: none;
        }

        /* ═══════════════ PRINT STYLES ═══════════════ */
        @media print {

            /* Default: portrait untuk anggota, peminjaman, pengguna */
            @page {
                margin: 1.2cm 1.2cm;
                size: A4 portrait;
            }

            /* Landscape untuk tabel lebar: denda (10 col) & buku (9 col) */
            @page landscape {
                size: A4 landscape;
            }

            .no-print,
            .sidebar,
            .topbar,
            header,
            .filter-card,
            .stats-grid,
            .page-header {
                display: none !important;
            }

            .main-area {
                margin-left: 0 !important;
                width: 100% !important;
                padding: 0 !important;
            }

            .app-wrap {
                display: block !important;
            }

            body {
                background: white !important;
                font-family: 'Inter', sans-serif !important;
                color: #111827 !important;
            }

            /* Terapkan landscape hanya untuk denda & buku */
            body.print-landscape {
                page: landscape;
            }

            /* ── Print Header ── */
            .print-header {
                display: block !important;
                margin-bottom: 18px;
                padding-bottom: 12px;
                border-bottom: 2px solid #111827;
            }

            .print-header-top {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
            }

            .ph-brand {
                font-size: 1.2rem;
                font-weight: 800;
                color: #111827;
                letter-spacing: -0.02em;
            }

            .ph-address {
                font-size: 0.78rem;
                color: #4b5563;
                margin-top: 3px;
                line-height: 1.5;
            }

            .ph-doc {
                text-align: right;
            }

            .ph-doc-title {
                font-size: 1rem;
                font-weight: 800;
                text-transform: uppercase;
                letter-spacing: 0.05em;
                color: #111827;
            }

            .ph-doc-meta {
                font-size: 0.75rem;
                color: #4b5563;
                margin-top: 2px;
            }

            .ph-summary {
                display: flex;
                justify-content: space-between;
                margin-top: 12px;
                font-size: 0.8rem;
                color: #111827;
            }

            /* ── Table ── */
            .report-card {
                box-shadow: none !important;
                border: none !important;
                margin: 0 !important;
            }

            .report-card-header {
                display: none !important;
            }

            .report-table {
                border-collapse: collapse !important;
                width: 100% !important;
                margin-top: 8px;
                table-layout: fixed;
            }

            .report-table thead th {
                background: #f1f5f9 !important;
                color: #111827 !important;
                font-size: 0.65rem !important;
                font-weight: 700 !important;
                padding: 7px 6px !important;
                white-space: nowrap;
                border-top: 1.5px solid #111827 !important;
                border-bottom: 1.5px solid #111827 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
                overflow: hidden;
                text-overflow: ellipsis;
            }

            .report-table tbody td {
                border: none !important;
                border-bottom: 1px solid #e5e7eb !important;
                padding: 7px 6px !important;
                font-size: 0.72rem !important;
                color: #374151 !important;
                page-break-inside: avoid;
                overflow: hidden;
                text-overflow: ellipsis;
                word-break: break-word;
            }

            .report-table tbody tr:last-child td {
                border-bottom: 1.5px solid #111827 !important;
            }

            .report-table tbody tr:hover {
                background: transparent !important;
            }

            /* Sembunyikan ikon FA dalam badge saat print (hemat ruang) */
            .badge-status i {
                display: none !important;
            }

            .badge-status {
                background: transparent !important;
                padding: 2px 5px !important;
                border: 1px solid #9ca3af !important;
                border-radius: 4px !important;
                font-size: 0.65rem !important;
                font-weight: 700 !important;
                white-space: nowrap;
            }

            /* Warna border badge per status saat print */
            .badge-dipinjam {
                border-color: #d97706 !important;
                color: #d97706 !important;
            }

            .badge-kembali {
                border-color: #059669 !important;
                color: #059669 !important;
            }

            .badge-terlambat {
                border-color: #dc2626 !important;
                color: #dc2626 !important;
            }

            .badge-aktif {
                border-color: #059669 !important;
                color: #059669 !important;
            }

            .badge-nonaktif {
                border-color: #6b7280 !important;
                color: #6b7280 !important;
            }

            .badge-tersedia {
                border-color: #059669 !important;
                color: #059669 !important;
            }

            .badge-habis {
                border-color: #dc2626 !important;
                color: #dc2626 !important;
            }

            .badge-lunas {
                border-color: #059669 !important;
                color: #059669 !important;
            }

            .badge-belum {
                border-color: #dc2626 !important;
                color: #dc2626 !important;
            }

            .badge-admin {
                border-color: #5b21b6 !important;
                color: #5b21b6 !important;
            }

            .badge-petugas {
                border-color: #075985 !important;
                color: #075985 !important;
            }

            /* ── Kolom width khusus tiap laporan ── */
            /* DENDA: 10 kolom → landscape */
            .tbl-denda col.c-no {
                width: 4%;
            }

            .tbl-denda col.c-nama {
                width: 18%;
            }

            .tbl-denda col.c-nis {
                width: 9%;
            }

            .tbl-denda col.c-buku {
                width: 20%;
            }

            .tbl-denda col.c-hari {
                width: 7%;
            }

            .tbl-denda col.c-tarif {
                width: 10%;
            }

            .tbl-denda col.c-total {
                width: 11%;
            }

            .tbl-denda col.c-tgld {
                width: 9%;
            }

            .tbl-denda col.c-tglb {
                width: 9%;
            }

            .tbl-denda col.c-stat {
                width: 10%;
            }

            /* BUKU: 9 kolom → landscape */
            .tbl-buku col.c-no {
                width: 4%;
            }

            .tbl-buku col.c-judul {
                width: 22%;
            }

            .tbl-buku col.c-kat {
                width: 11%;
            }

            .tbl-buku col.c-peng {
                width: 14%;
            }

            .tbl-buku col.c-nerb {
                width: 14%;
            }

            .tbl-buku col.c-thn {
                width: 6%;
            }

            .tbl-buku col.c-isbn {
                width: 14%;
            }

            .tbl-buku col.c-stok {
                width: 6%;
            }

            .tbl-buku col.c-stat {
                width: 9%;
            }

            /* PEMINJAMAN: 8 kolom → portrait cukup */
            .tbl-pinjam col.c-no {
                width: 5%;
            }

            .tbl-pinjam col.c-nama {
                width: 22%;
            }

            .tbl-pinjam col.c-nis {
                width: 10%;
            }

            .tbl-pinjam col.c-buku {
                width: 28%;
            }

            .tbl-pinjam col.c-tglp {
                width: 10%;
            }

            .tbl-pinjam col.c-tglk {
                width: 10%;
            }

            .tbl-pinjam col.c-dnd {
                width: 7%;
            }

            .tbl-pinjam col.c-stat {
                width: 12%;
            }

            /* ── Print Footer ── */
            .print-footer {
                display: flex !important;
                justify-content: space-between;
                align-items: flex-end;
                margin-top: 30px;
                page-break-inside: avoid;
            }

            .pf-note {
                font-size: 0.7rem;
                color: #6b7280;
                line-height: 1.5;
            }

            .pf-signature {
                text-align: center;
                width: 200px;
            }

            .pf-sign-title {
                font-size: 0.8rem;
                color: #374151;
                margin-bottom: 55px;
            }

            .pf-sign-name {
                font-size: 0.85rem;
                font-weight: 700;
                color: #111827;
                border-bottom: 1px solid #111827;
                padding-bottom: 3px;
                margin-bottom: 3px;
            }

            .pf-sign-role {
                font-size: 0.7rem;
                color: #4b5563;
            }
        }
    </style>
</head>

<body>
    <div class="app-wrap">
        <?php include 'includes/nav.php'; ?>
        <div class="main-area">
            <?php include 'includes/header.php'; ?>
            <main class="content">

                <!-- ── Page Header ── -->
                <div class="page-header no-print">
                    <div>
                        <h1 class="page-header-title">Laporan</h1>
                        <p class="page-header-sub">Rekap data dan statistik Cozy-Library</p>
                    </div>
                    <button class="btn-print" onclick="window.print()"><i class="fas fa-print"></i> Cetak PDF</button>
                </div>

                <!-- ── Stats Grid ── -->
                <div class="stats-grid no-print">
                    <div class="stat-card">
                        <div class="stat-header">
                            <div class="stat-icon blue"><i class="fas fa-book"></i></div>
                        </div>
                        <div class="stat-label">Total Buku</div>
                        <div class="stat-value"><?= number_format($total_buku) ?></div>
                        <div class="stat-sub"><?= $buku_tersedia ?> unit tersedia</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-header">
                            <div class="stat-icon green"><i class="fas fa-users"></i></div>
                        </div>
                        <div class="stat-label">Anggota</div>
                        <div class="stat-value"><?= number_format($total_anggota) ?></div>
                        <div class="stat-sub">Terdaftar aktif</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-header">
                            <div class="stat-icon orange"><i class="fas fa-exchange-alt"></i></div>
                        </div>
                        <div class="stat-label">Transaksi</div>
                        <div class="stat-value"><?= number_format($total_pinjam) ?></div>
                        <div class="stat-sub"><?= $aktif_pinjam ?> sedang dipinjam</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-header">
                            <div class="stat-icon red"><i class="fas fa-coins"></i></div>
                        </div>
                        <div class="stat-label">Total Denda</div>
                        <div class="stat-value">Rp <?= number_format($total_denda, 0, ',', '.') ?></div>
                        <div class="stat-sub">Belum lunas: Rp <?= number_format($denda_belum, 0, ',', '.') ?></div>
                    </div>
                </div>

                <!-- ── Filter Card ── -->
                <form method="GET" class="filter-card no-print" id="filterForm">
                    <!-- Jenis Laporan -->
                    <div class="filter-group">
                        <label class="filter-label"><i class="fas fa-file-alt" style="color:#6366f1"></i> Jenis
                            Laporan</label>
                        <select name="jenis" class="filter-select" id="jenisSelect"
                            onchange="toggleDateFilter(this.value)">
                            <option value="anggota" <?= $jenis === 'anggota' ? 'selected' : '' ?>>Data Anggota</option>
                            <option value="buku" <?= $jenis === 'buku' ? 'selected' : '' ?>>Data Buku</option>
                            <option value="peminjaman" <?= $jenis === 'peminjaman' ? 'selected' : '' ?>>Peminjaman Buku
                            </option>
                            <option value="denda" <?= $jenis === 'denda' ? 'selected' : '' ?>>Denda Buku</option>
                            <option value="pengguna" <?= $jenis === 'pengguna' ? 'selected' : '' ?>>Data Pengguna</option>
                        </select>
                    </div>

                    <div class="filter-divider" id="divider1"></div>

                    <!-- Date Range (hanya tampil untuk peminjaman & denda) -->
                    <div class="date-filter-wrap" id="dateFilterWrap">
                        <div class="filter-group">
                            <label class="filter-label"><i class="fas fa-calendar-alt" style="color:#6366f1"></i> Dari
                                Tanggal</label>
                            <input type="date" name="dari" class="filter-input"
                                value="<?= htmlspecialchars($dari ?? '') ?>">
                        </div>
                        <div class="filter-group">
                            <label class="filter-label"><i class="fas fa-calendar-check" style="color:#6366f1"></i>
                                Sampai Tanggal</label>
                            <input type="date" name="sampai" class="filter-input"
                                value="<?= htmlspecialchars($sampai ?? '') ?>">
                        </div>
                    </div>

                    <button type="submit" class="btn-filter"><i class="fas fa-search"></i> Tampilkan</button>
                    <a href="laporan.php?jenis=<?= $jenis ?>&reset=1" class="btn-reset"><i class="fas fa-undo"></i>
                        Reset</a>
                    <button type="button" class="btn-print" onclick="window.print()"><i class="fas fa-print"></i> Cetak
                        PDF</button>
                </form>

                <!-- ── Print Header ── -->
                <div class="print-header" style="margin-bottom: 20px; border: none; padding: 0;">
                    <!-- KOP SURAT -->
                    <table
                        style="width: 100%; border-bottom: 3px solid #111827; padding-bottom: 12px; margin-bottom: 15px; border-collapse: collapse;">
                        <tr>
                            <td style="width: 80px; text-align: center; vertical-align: top;">
                                <div
                                    style="width: 64px; height: 64px; background: #111827; color: #fff; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; font-family: 'Plus Jakarta Sans', sans-serif; font-size: 26px; font-weight: 800; letter-spacing: -1px;">
                                    CL
                                </div>
                            </td>
                            <td style="text-align: center; vertical-align: middle; padding: 0 10px;">
                                <div
                                    style="font-size: 1.5rem; font-weight: 800; color: #111827; text-transform: uppercase; letter-spacing: 1px;">
                                    Sistem Manajemen Perpustakaan</div>
                                <div style="font-size: 1.2rem; font-weight: 700; color: #374151; margin-top: 2px;">
                                    Cozy-Library</div>
                                <div style="font-size: 0.85rem; color: #4b5563; margin-top: 4px;">Jl. Pendidikan No. 1,
                                    Kota Pengetahuan, Kodepos 12345</div>
                                <div style="font-size: 0.8rem; color: #6b7280;">Email: info@cozy-library.com | Telp:
                                    (021) 123-4567</div>
                            </td>
                            <td style="width: 80px;"></td>
                        </tr>
                    </table>

                    <!-- JUDUL DOKUMEN -->
                    <div style="text-align: center; margin-bottom: 20px;">
                        <h2
                            style="margin: 0; font-size: 1.2rem; font-weight: 800; color: #111827; text-transform: uppercase;">
                            Laporan <?= htmlspecialchars($current_label) ?>
                        </h2>
                        <div style="font-size: 0.85rem; color: #4b5563; margin-top: 4px;">
                            Nomor Dokumen: <strong><?= $no_laporan ?></strong>
                        </div>
                    </div>

                    <!-- INFO METADATA -->
                    <div
                        style="display: flex; justify-content: space-between; align-items: flex-end; font-size: 0.85rem; color: #374151; border-bottom: 1px dashed #d1d5db; padding-bottom: 8px; margin-bottom: 15px;">
                        <div>
                            <div style="margin-bottom: 3px;"><strong>Tanggal Cetak:</strong> <?= $tgl_cetak ?></div>
                            <div><strong>Waktu Cetak:</strong> <?= $jam_cetak ?></div>
                        </div>
                        <div style="text-align: right;">
                            <div style="margin-bottom: 3px;">
                                <strong>Periode:</strong>
                                <?php if (in_array($jenis, ['peminjaman', 'denda']) && ($dari || $sampai)): ?>
                                    <?= $dari ? date('d M Y', strtotime($dari)) : 'Awal' ?> s/d
                                    <?= $sampai ? date('d M Y', strtotime($sampai)) : 'Sekarang' ?>
                                <?php else: ?>
                                    Semua Data
                                <?php endif; ?>
                            </div>
                            <div><strong>Total Data:</strong> <?= $total_data ?> baris/item</div>
                        </div>
                    </div>
                </div>

                <!-- ══════════════ TABEL LAPORAN ══════════════ -->
                <div class="report-card">
                    <div class="report-card-header">
                        <div class="report-card-title">
                            <i class="fas <?= $table_icon ?>"></i>
                            <?= htmlspecialchars($current_label) ?>
                            <?php if (in_array($jenis, ['peminjaman', 'denda'])): ?>
                                <span class="period-badge">
                                    <?php if ($dari || $sampai): ?>
                                        <i class="fas fa-calendar"></i>
                                        <?= $dari ? date('d M Y', strtotime($dari)) : '—' ?> s/d
                                        <?= $sampai ? date('d M Y', strtotime($sampai)) : 'sekarang' ?>
                                    <?php else: ?>
                                        <i class="fas fa-infinity"></i> Semua Periode
                                    <?php endif; ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <span style="font-size:.82rem;color:#6b7280;"><?= $total_data ?> data ditemukan</span>
                    </div>
                    <div style="overflow-x:auto">

                        <?php if ($jenis === 'anggota'): ?>
                            <!-- ═══ TABLE: DATA ANGGOTA ═══ -->
                            <table id="tableLaporan" class="report-table">
                                <thead>
                                    <tr>
                                        <th style="width:40px">No</th>
                                        <th>NIS</th>
                                        <th>Nama Anggota</th>
                                        <th>Email</th>
                                        <th>Kelas</th>
                                        <th>Total Pinjam</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($rows_cache):
                                        $no = 1;
                                        foreach ($rows_cache as $r): ?>
                                            <tr>
                                                <td style="color:#9ca3af;font-size:.8rem"><?= $no++ ?></td>
                                                <td style="font-weight:600;color:#6366f1"><?= htmlspecialchars($r['nis']) ?></td>
                                                <td style="font-weight:600;color:#111827">
                                                    <?= htmlspecialchars($r['nama_anggota']) ?></td>
                                                <td style="color:#4b5563"><?= htmlspecialchars($r['email'] ?? '—') ?></td>
                                                <td><?= htmlspecialchars($r['kelas'] ?? '—') ?></td>
                                                <td style="text-align:center">
                                                    <span style="font-weight:700;color:#4f46e5"><?= $r['total_pinjam'] ?></span>
                                                    <span style="color:#9ca3af;font-size:.8rem"> buku</span>
                                                </td>
                                                <td>
                                                    <span
                                                        class="badge-status <?= $r['status'] === 'aktif' ? 'badge-aktif' : 'badge-nonaktif' ?>">
                                                        <i
                                                            class="fas <?= $r['status'] === 'aktif' ? 'fa-check-circle' : 'fa-times-circle' ?>"></i>
                                                        <?= ucfirst($r['status']) ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; endif; ?>
                                </tbody>
                            </table>

                        <?php elseif ($jenis === 'buku'): ?>
                            <!-- ═══ TABLE: DATA BUKU ═══ -->
                            <table id="tableLaporan" class="report-table tbl-buku">
                                <colgroup>
                                    <col class="c-no">
                                    <col class="c-judul">
                                    <col class="c-kat">
                                    <col class="c-peng">
                                    <col class="c-nerb">
                                    <col class="c-thn">
                                    <col class="c-isbn">
                                    <col class="c-stok">
                                    <col class="c-stat">
                                </colgroup>
                                <thead>
                                    <tr>
                                        <th style="width:40px">No</th>
                                        <th>Judul Buku</th>
                                        <th>Kategori</th>
                                        <th>Pengarang</th>
                                        <th>Penerbit</th>
                                        <th>Tahun</th>
                                        <th>ISBN</th>
                                        <th>Stok</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($rows_cache):
                                        $no = 1;
                                        foreach ($rows_cache as $r): ?>
                                            <tr>
                                                <td style="color:#9ca3af;font-size:.8rem"><?= $no++ ?></td>
                                                <td style="font-weight:600;color:#111827;max-width:220px">
                                                    <?= htmlspecialchars($r['judul_buku']) ?></td>
                                                <td>
                                                    <span
                                                        style="background:#eef2ff;color:#4338ca;padding:3px 10px;border-radius:20px;font-size:.75rem;font-weight:600">
                                                        <?= htmlspecialchars($r['nama_kategori'] ?? '—') ?>
                                                    </span>
                                                </td>
                                                <td><?= htmlspecialchars($r['pengarang']) ?></td>
                                                <td><?= htmlspecialchars($r['penerbit']) ?></td>
                                                <td><?= htmlspecialchars($r['tahun_terbit']) ?></td>
                                                <td style="font-size:.8rem;color:#6b7280"><?= htmlspecialchars($r['isbn'] ?? '—') ?>
                                                </td>
                                                <td
                                                    style="text-align:center;font-weight:700;color:<?= $r['stok'] > 0 ? '#059669' : '#dc2626' ?>">
                                                    <?= $r['stok'] ?></td>
                                                <td>
                                                    <span
                                                        class="badge-status <?= $r['status'] === 'tersedia' ? 'badge-tersedia' : 'badge-habis' ?>">
                                                        <i
                                                            class="fas <?= $r['status'] === 'tersedia' ? 'fa-check-circle' : 'fa-times-circle' ?>"></i>
                                                        <?= ucfirst($r['status']) ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; endif; ?>
                                </tbody>
                            </table>

                        <?php elseif ($jenis === 'peminjaman'): ?>
                            <!-- ═══ TABLE: PEMINJAMAN BUKU ═══ -->
                            <table id="tableLaporan" class="report-table tbl-pinjam">
                                <colgroup>
                                    <col class="c-no">
                                    <col class="c-nama">
                                    <col class="c-nis">
                                    <col class="c-buku">
                                    <col class="c-tglp">
                                    <col class="c-tglk">
                                    <col class="c-dnd">
                                    <col class="c-stat">
                                </colgroup>
                                <thead>
                                    <tr>
                                        <th style="width:40px">No</th>
                                        <th>Nama Anggota</th>
                                        <th>NIS</th>
                                        <th>Buku</th>
                                        <th>Tgl Pinjam</th>
                                        <th>Tgl Kembali</th>
                                        <th>Denda</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($rows_cache):
                                        $no = 1;
                                        foreach ($rows_cache as $r):
                                            $late = in_array($r['status_transaksi'], ['Peminjaman', 'Dipinjam'], true) && strtotime($r['tgl_kembali_rencana']) < time();
                                            if (in_array($r['status_transaksi'], ['Pengembalian', 'Dikembalikan'], true)) {
                                                $bc = 'badge-kembali';
                                                $bl = '<i class="fas fa-check-circle"></i> Pengembalian';
                                            } elseif ($late) {
                                                $bc = 'badge-terlambat';
                                                $bl = '<i class="fas fa-exclamation-circle"></i> Terlambat';
                                            } else {
                                                $bc = 'badge-dipinjam';
                                                $bl = '<i class="fas fa-book-reader"></i> Peminjaman';
                                            }
                                            ?>
                                            <tr>
                                                <td style="color:#9ca3af;font-size:.8rem"><?= $no++ ?></td>
                                                <td style="font-weight:600;color:#111827">
                                                    <?= htmlspecialchars($r['nama_anggota']) ?></td>
                                                <td style="font-weight:600;color:#6366f1"><?= htmlspecialchars($r['nis']) ?></td>
                                                <td><?= htmlspecialchars($r['judul_buku']) ?></td>
                                                <td><?= date('d/m/Y', strtotime($r['tgl_pinjam'])) ?></td>
                                                <td><?= date('d/m/Y', strtotime($r['tgl_kembali_rencana'])) ?></td>
                                                <td><span style="color:#9ca3af">-</span></td>
                                                <td><span class="badge-status <?= $bc ?>"><?= $bl ?></span></td>
                                            </tr>
                                        <?php endforeach; endif; ?>
                                </tbody>
                            </table>

                        <?php elseif ($jenis === 'denda'): ?>
                            <!-- ═══ TABLE: DENDA BUKU ═══ -->
                            <table id="tableLaporan" class="report-table tbl-denda">
                                <colgroup>
                                    <col class="c-no">
                                    <col class="c-nama">
                                    <col class="c-nis">
                                    <col class="c-buku">
                                    <col class="c-hari">
                                    <col class="c-tarif">
                                    <col class="c-total">
                                    <col class="c-tgld">
                                    <col class="c-tglb">
                                    <col class="c-stat">
                                </colgroup>
                                <thead>
                                    <tr>
                                        <th style="width:40px">No</th>
                                        <th>Nama Anggota</th>
                                        <th>NIS</th>
                                        <th>Buku</th>
                                        <th>Hari Telat</th>
                                        <th>Tarif/Hari</th>
                                        <th>Total Denda</th>
                                        <th>Tgl Denda</th>
                                        <th>Tgl Bayar</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($rows_cache):
                                        $no = 1;
                                        foreach ($rows_cache as $r): ?>
                                            <tr>
                                                <td style="color:#9ca3af;font-size:.8rem"><?= $no++ ?></td>
                                                <td style="font-weight:600;color:#111827">
                                                    <?= htmlspecialchars($r['nama_anggota']) ?></td>
                                                <td style="font-weight:600;color:#6366f1"><?= htmlspecialchars($r['nis']) ?></td>
                                                <td><?= htmlspecialchars($r['judul_buku']) ?></td>
                                                <td style="text-align:center">
                                                    <span style="font-weight:700;color:#dc2626"><?= $r['jumlah_hari'] ?></span>
                                                    <span style="color:#9ca3af;font-size:.8rem"> hari</span>
                                                </td>
                                                <td>Rp <?= number_format($r['tarif_per_hari'], 0, ',', '.') ?></td>
                                                <td
                                                    style="font-weight:700;color:<?= $r['status_bayar'] === 'sudah' ? '#059669' : '#dc2626' ?>">
                                                    Rp <?= number_format($r['total_denda'], 0, ',', '.') ?>
                                                </td>
                                                <td><?= $r['tgl_denda'] ? date('d/m/Y', strtotime($r['tgl_denda'])) : '—' ?></td>
                                                <td><?= $r['tgl_bayar'] ? date('d/m/Y', strtotime($r['tgl_bayar'])) : '<span style="color:#9ca3af">—</span>' ?>
                                                </td>
                                                <td>
                                                    <span
                                                        class="badge-status <?= $r['status_bayar'] === 'sudah' ? 'badge-lunas' : 'badge-belum' ?>">
                                                        <i
                                                            class="fas <?= $r['status_bayar'] === 'sudah' ? 'fa-check-circle' : 'fa-times-circle' ?>"></i>
                                                        <?= $r['status_bayar'] === 'sudah' ? 'Lunas' : 'Belum Lunas' ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; else: ?>
                                        <tr class="empty-row">
                                            <td colspan="10">
                                                <div style="font-size:2rem;margin-bottom:8px">💰</div>
                                                <div style="font-weight:600;color:#374151">Tidak ada data denda</div>
                                                <div style="font-size:.82rem;margin-top:4px">Coba ubah filter tanggal</div>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>

                        <?php elseif ($jenis === 'pengguna'): ?>
                            <!-- ═══ TABLE: DATA PENGGUNA ═══ -->
                            <table id="tableLaporan" class="report-table">
                                <thead>
                                    <tr>
                                        <th style="width:40px">No</th>
                                        <th>Nama Pengguna</th>
                                        <th>Username</th>
                                        <th>Email</th>
                                        <th>Level / Role</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($rows_cache):
                                        $no = 1;
                                        foreach ($rows_cache as $r):
                                            if ($r['level'] === 'admin') {
                                                $lc = 'badge-admin';
                                                $li = 'fa-user-shield';
                                            } elseif ($r['level'] === 'petugas') {
                                                $lc = 'badge-petugas';
                                                $li = 'fa-user-tie';
                                            } else {
                                                $lc = 'badge-anggota-r';
                                                $li = 'fa-user';
                                            }
                                            ?>
                                            <tr>
                                                <td style="color:#9ca3af;font-size:.8rem"><?= $no++ ?></td>
                                                <td style="font-weight:600;color:#111827">
                                                    <?= htmlspecialchars($r['nama_pengguna']) ?></td>
                                                <td style="color:#4b5563">@<?= htmlspecialchars($r['username']) ?></td>
                                                <td style="color:#4b5563"><?= htmlspecialchars($r['email'] ?? '—') ?></td>
                                                <td>
                                                    <span class="badge-status <?= $lc ?>">
                                                        <i class="fas <?= $li ?>"></i>
                                                        <?= ucfirst($r['level']) ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; endif; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>

                    </div><!-- /overflow-x:auto -->
                </div><!-- /report-card -->

                <!-- ── Print Footer ── -->
                <div class="print-footer">
                    <div class="pf-note">
                        <strong>Catatan:</strong><br>
                        Dokumen ini merupakan hasil cetak otomatis dari Sistem Manajemen Cozy-Library.<br>
                        Informasi yang tertera sesuai dengan data pada sistem saat dicetak.
                    </div>
                    <div class="pf-signature">
                        <div class="pf-sign-title">Mengetahui,</div>
                        <div class="pf-sign-name">Petugas Cozy-Library</div>
                        <div class="pf-sign-role">Penanggung Jawab Laporan</div>
                    </div>
                </div>

            </main>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script>
        // Tampilkan/sembunyikan filter tanggal sesuai jenis laporan
        function toggleDateFilter(val) {
            var dateWrap = document.getElementById('dateFilterWrap');
            var divider = document.getElementById('divider1');
            var needDate = (val === 'peminjaman' || val === 'denda');
            if (dateWrap) dateWrap.classList.toggle('hidden', !needDate);
            if (divider) divider.style.display = needDate ? '' : 'none';
        }

        // Set landscape untuk tabel lebar (denda & buku) saat print
        var jenisLandscape = ['denda', 'buku'];
        var currentJenis = '<?= $jenis ?>';

        function applyPrintMode() {
            if (jenisLandscape.indexOf(currentJenis) !== -1) {
                document.body.classList.add('print-landscape');
            } else {
                document.body.classList.remove('print-landscape');
            }
        }

        window.addEventListener('beforeprint', applyPrintMode);
        window.addEventListener('afterprint', function () {
            document.body.classList.remove('print-landscape');
        });

        // Inisiasi saat load
        $(document).ready(function () {
            toggleDateFilter($('#jenisSelect').val());
            if ($('#tableLaporan').length) {
                $('#tableLaporan').DataTable({
                    order: [[0, 'asc']],
                    pageLength: 10,
                    lengthMenu: [[5, 10, 20, 50], [5, 10, 20, 50]],
                    autoWidth: false,
                    columnDefs: [{ orderable: false, targets: -1 }],
                    language: {
                        emptyTable: 'Tidak ada data tersedia',
                        info: 'Menampilkan _START_ sampai _END_ dari _TOTAL_ data',
                        infoEmpty: 'Menampilkan 0 sampai 0 dari 0 data',
                        lengthMenu: 'Tampilkan _MENU_ entri',
                        search: 'Cari:',
                        paginate: { next: 'Berikutnya', previous: 'Sebelumnya' }
                    }
                });
            }
            if (window.history.replaceState) window.history.replaceState(null, null, window.location.href);
        });
    </script>
    <script src="../assets/js/script.js"></script>
</body>

</html>
