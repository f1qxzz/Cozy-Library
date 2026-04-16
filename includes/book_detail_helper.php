<?php

/**
 * Helper terpusat untuk halaman detail buku lintas role.
 */

function hasBookRackColumn(mysqli $conn): bool
{
    static $checked = false;
    static $hasColumn = false;

    if ($checked) {
        return $hasColumn;
    }

    $checked = true;
    $probe = $conn->query("SHOW COLUMNS FROM buku LIKE 'lokasi_rak'");
    $hasColumn = $probe && $probe->num_rows > 0;

    if ($hasColumn) {
        return true;
    }

    // Upaya aman menambahkan kolom baru bila schema belum diperbarui.
    @$conn->query("ALTER TABLE buku ADD COLUMN lokasi_rak VARCHAR(120) DEFAULT NULL AFTER stok");
    $recheck = $conn->query("SHOW COLUMNS FROM buku LIKE 'lokasi_rak'");
    $hasColumn = $recheck && $recheck->num_rows > 0;

    return $hasColumn;
}

function getBookDetailById(mysqli $conn, int $bookId): ?array
{
    $locationExpr = hasBookRackColumn($conn) ? 'b.lokasi_rak' : "''";
    $sql = "
        SELECT
            b.id_buku,
            b.judul_buku,
            b.pengarang,
            b.penerbit,
            b.tahun_terbit,
            b.isbn,
            b.deskripsi,
            b.stok,
            {$locationExpr} AS lokasi_rak,
            b.status,
            b.cover,
            b.created_at,
            k.nama_kategori,
            ta.id_transaksi AS transaksi_aktif_id,
            ta.status_transaksi AS transaksi_aktif_status,
            ta.tgl_pinjam AS transaksi_aktif_tgl_pinjam,
            ta.tgl_kembali_rencana AS transaksi_aktif_jatuh_tempo,
            a.id_anggota AS peminjam_aktif_id,
            a.nama_anggota AS peminjam_aktif_nama,
            p.nama_pengguna AS petugas_aktif_nama,
            (
                SELECT COUNT(*)
                FROM transaksi t_hist
                WHERE t_hist.id_buku = b.id_buku
            ) AS total_riwayat_peminjaman
        FROM buku b
        LEFT JOIN kategori k ON k.id_kategori = b.id_kategori
        LEFT JOIN (
            SELECT t1.*
            FROM transaksi t1
            INNER JOIN (
                SELECT id_buku, MAX(id_transaksi) AS max_id
                FROM transaksi
                WHERE status_transaksi IN ('Peminjaman', 'Dipinjam')
                GROUP BY id_buku
            ) latest ON latest.max_id = t1.id_transaksi
        ) ta ON ta.id_buku = b.id_buku
        LEFT JOIN anggota a ON a.id_anggota = ta.id_anggota
        LEFT JOIN pengguna p ON p.id_pengguna = ta.id_petugas
        WHERE b.id_buku = ?
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $bookId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ?: null;
}

function getBookLoanHistory(mysqli $conn, int $bookId, int $limit = 20): array
{
    $limit = max(1, min($limit, 50));
    $sql = "
        SELECT
            t.id_transaksi,
            t.tgl_pinjam,
            t.tgl_kembali_rencana,
            t.tgl_kembali_aktual,
            t.status_transaksi,
            a.nama_anggota,
            p.nama_pengguna AS nama_petugas
        FROM transaksi t
        LEFT JOIN anggota a ON a.id_anggota = t.id_anggota
        LEFT JOIN pengguna p ON p.id_pengguna = t.id_petugas
        WHERE t.id_buku = ?
        ORDER BY t.tgl_pinjam DESC
        LIMIT {$limit}
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }

    $stmt->bind_param('i', $bookId);
    $stmt->execute();
    $res = $stmt->get_result();

    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $rows[] = $row;
    }

    $stmt->close();
    return $rows;
}

function getBookStatistics(mysqli $conn): array
{
    $sql = "
        SELECT
            COUNT(*) AS total_judul,
            COALESCE(SUM(stok), 0) AS total_stok,
            SUM(CASE WHEN stok > 0 THEN 1 ELSE 0 END) AS judul_tersedia,
            (
                SELECT COUNT(DISTINCT id_buku)
                FROM transaksi
                WHERE status_transaksi IN ('Peminjaman', 'Dipinjam')
            ) AS judul_dipinjam
        FROM buku
    ";

    $stats = $conn->query($sql);
    if (!$stats) {
        return [
            'total_judul' => 0,
            'total_stok' => 0,
            'judul_tersedia' => 0,
            'judul_dipinjam' => 0,
        ];
    }

    $row = $stats->fetch_assoc();
    return [
        'total_judul' => (int) ($row['total_judul'] ?? 0),
        'total_stok' => (int) ($row['total_stok'] ?? 0),
        'judul_tersedia' => (int) ($row['judul_tersedia'] ?? 0),
        'judul_dipinjam' => (int) ($row['judul_dipinjam'] ?? 0),
    ];
}

function getReadableBookAvailability(array $book): string
{
    return ((int) ($book['stok'] ?? 0) > 0) ? 'tersedia' : 'dipinjam';
}

function canBorrowBook(array $book): bool
{
    return ((int) ($book['stok'] ?? 0) > 0);
}

function getBookBorrowCount(mysqli $conn, int $bookId): int
{
    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM transaksi WHERE id_buku = ?");
    if (!$stmt) {
        return 0;
    }

    $stmt->bind_param('i', $bookId);
    $stmt->execute();
    $total = (int) ($stmt->get_result()->fetch_assoc()['total'] ?? 0);
    $stmt->close();

    return $total;
}

function getBookRatingInfo(mysqli $conn, int $bookId): array
{
    $tableCheck = $conn->query("SHOW TABLES LIKE 'ulasan_buku'");
    if (!$tableCheck || $tableCheck->num_rows === 0) {
        return ['total_ulasan' => 0, 'avg_rating' => 0.0];
    }

    $stmt = $conn->prepare("
        SELECT
            COUNT(*) AS total_ulasan,
            COALESCE(ROUND(AVG(rating), 1), 0) AS avg_rating
        FROM ulasan_buku
        WHERE id_buku = ?
    ");
    if (!$stmt) {
        return ['total_ulasan' => 0, 'avg_rating' => 0.0];
    }

    $stmt->bind_param('i', $bookId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return [
        'total_ulasan' => (int) ($row['total_ulasan'] ?? 0),
        'avg_rating' => (float) ($row['avg_rating'] ?? 0),
    ];
}

function getBookReviews(mysqli $conn, int $bookId, int $limit = 10): array
{
    $tableCheck = $conn->query("SHOW TABLES LIKE 'ulasan_buku'");
    if (!$tableCheck || $tableCheck->num_rows === 0) {
        return [];
    }

    $limit = max(1, min($limit, 50));
    $sql = "
        SELECT
            u.id_ulasan,
            u.rating,
            u.ulasan,
            u.created_at,
            a.nama_anggota,
            a.foto
        FROM ulasan_buku u
        LEFT JOIN anggota a ON a.id_anggota = u.id_anggota
        WHERE u.id_buku = ?
        ORDER BY u.created_at DESC
        LIMIT {$limit}
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }

    $stmt->bind_param('i', $bookId);
    $stmt->execute();
    $res = $stmt->get_result();

    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $rows[] = $row;
    }

    $stmt->close();
    return $rows;
}

function getActiveBorrowers(mysqli $conn, int $bookId, int $limit = 5): array
{
    $limit = max(1, min($limit, 20));
    $sql = "
        SELECT
            t.id_transaksi,
            t.tgl_pinjam,
            t.tgl_kembali_rencana,
            a.nama_anggota,
            a.nis,
            a.kelas
        FROM transaksi t
        LEFT JOIN anggota a ON a.id_anggota = t.id_anggota
        WHERE t.id_buku = ?
          AND t.status_transaksi IN ('Peminjaman', 'Dipinjam')
        ORDER BY t.tgl_pinjam DESC
        LIMIT {$limit}
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }

    $stmt->bind_param('i', $bookId);
    $stmt->execute();
    $res = $stmt->get_result();

    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $rows[] = $row;
    }

    $stmt->close();
    return $rows;
}
