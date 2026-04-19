<?php
/*
 * Alur logic PHP:
 * 1) Menyediakan fungsi utilitas terpusat untuk dipakai banyak halaman.
 * 2) Menjaga validasi input dan normalisasi data pada satu tempat.
 * 3) Mengembalikan hasil yang siap dipakai oleh controller/view.
 */
if (!defined('DENDA_PER_HARI')) {
    define('DENDA_PER_HARI', 1000);
}

function getCanonicalDendaSubquery(): string
{
    return "(
        SELECT d1.*
        FROM denda d1
        INNER JOIN (
            SELECT id_transaksi, MAX(id_denda) AS max_id
            FROM denda
            GROUP BY id_transaksi
        ) latest_denda ON latest_denda.max_id = d1.id_denda
    )";
}

function calculateDendaSummary(array $transaksi): array
{
    $jatuhTempo = strtotime((string) ($transaksi['tgl_kembali_rencana'] ?? ''));
    $waktuAcuanRaw = !empty($transaksi['tgl_kembali_aktual'])
        ? (string) $transaksi['tgl_kembali_aktual']
        : date('Y-m-d H:i:s');
    $waktuAcuan = strtotime($waktuAcuanRaw);

    if ($jatuhTempo === false || $waktuAcuan === false || $waktuAcuan <= $jatuhTempo) {
        return [
            'jumlah_hari' => 0,
            'tarif_per_hari' => DENDA_PER_HARI,
            'total_denda' => 0,
        ];
    }

    $jumlahHari = (int) ceil(($waktuAcuan - $jatuhTempo) / 86400);

    return [
        'jumlah_hari' => $jumlahHari,
        'tarif_per_hari' => DENDA_PER_HARI,
        'total_denda' => $jumlahHari * DENDA_PER_HARI,
    ];
}

function syncDendaWithTransaksi(mysqli $conn): void
{
    $canonicalDenda = getCanonicalDendaSubquery();
    $sql = "
        SELECT
            t.id_transaksi,
            t.tgl_kembali_rencana,
            t.tgl_kembali_aktual,
            t.status_transaksi,
            d.id_denda,
            d.status_bayar,
            d.jumlah_hari AS denda_jumlah_hari,
            d.tarif_per_hari AS denda_tarif_per_hari,
            d.total_denda AS denda_total_denda
        FROM transaksi t
        LEFT JOIN {$canonicalDenda} d ON d.id_transaksi = t.id_transaksi
        WHERE
            (
                t.status_transaksi IN ('Peminjaman', 'Dipinjam')
                AND t.tgl_kembali_rencana < NOW()
                AND t.tgl_kembali_aktual IS NULL
            )
            OR
            (
                t.status_transaksi IN ('Pengembalian', 'Dikembalikan')
                AND t.tgl_kembali_aktual IS NOT NULL
                AND t.tgl_kembali_aktual > t.tgl_kembali_rencana
            )
    ";

    $result = $conn->query($sql);
    if (!$result) {
        return;
    }

    $insertStmt = $conn->prepare("
        INSERT INTO denda (id_transaksi, jumlah_hari, tarif_per_hari, total_denda, status_bayar)
        VALUES (?, ?, ?, ?, 'belum')
    ");
    $updateStmt = $conn->prepare("
        UPDATE denda
        SET jumlah_hari = ?, tarif_per_hari = ?, total_denda = ?
        WHERE id_denda = ?
    ");

    if (!$insertStmt || !$updateStmt) {
        if ($insertStmt) {
            $insertStmt->close();
        }
        if ($updateStmt) {
            $updateStmt->close();
        }
        return;
    }

    while ($row = $result->fetch_assoc()) {
        $summary = calculateDendaSummary($row);

        if ($summary['jumlah_hari'] < 1 || $summary['total_denda'] < 1) {
            continue;
        }

        if (empty($row['id_denda'])) {
            $insertStmt->bind_param(
                "iiii",
                $row['id_transaksi'],
                $summary['jumlah_hari'],
                $summary['tarif_per_hari'],
                $summary['total_denda']
            );
            $insertStmt->execute();
            continue;
        }

        if (($row['status_bayar'] ?? 'belum') !== 'belum') {
            continue;
        }

        $perluUpdate =
            (int) ($row['denda_jumlah_hari'] ?? 0) !== $summary['jumlah_hari'] ||
            (int) ($row['denda_tarif_per_hari'] ?? 0) !== $summary['tarif_per_hari'] ||
            (int) ($row['denda_total_denda'] ?? 0) !== $summary['total_denda'];

        if ($perluUpdate) {
            $updateStmt->bind_param(
                "iiii",
                $summary['jumlah_hari'],
                $summary['tarif_per_hari'],
                $summary['total_denda'],
                $row['id_denda']
            );
            $updateStmt->execute();
        }
    }

    $insertStmt->close();
    $updateStmt->close();
}

function upsertDendaForTransaksi(
    mysqli $conn,
    int $idTransaksi,
    string $tglKembaliRencana,
    ?string $tglKembaliAktual = null
): array {
    $summary = calculateDendaSummary([
        'tgl_kembali_rencana' => $tglKembaliRencana,
        'tgl_kembali_aktual' => $tglKembaliAktual,
    ]);

    if ($summary['jumlah_hari'] < 1 || $summary['total_denda'] < 1) {
        return $summary;
    }

    $checkStmt = $conn->prepare("
        SELECT id_denda, status_bayar
        FROM denda
        WHERE id_transaksi = ?
        ORDER BY id_denda DESC
        LIMIT 1
    ");

    if (!$checkStmt) {
        return $summary;
    }

    $checkStmt->bind_param("i", $idTransaksi);
    $checkStmt->execute();
    $existing = $checkStmt->get_result()->fetch_assoc();
    $checkStmt->close();

    if ($existing) {
        if (($existing['status_bayar'] ?? 'belum') !== 'belum') {
            return $summary;
        }

        $updateStmt = $conn->prepare("
            UPDATE denda
            SET jumlah_hari = ?, tarif_per_hari = ?, total_denda = ?
            WHERE id_denda = ?
        ");

        if ($updateStmt) {
            $updateStmt->bind_param(
                "iiii",
                $summary['jumlah_hari'],
                $summary['tarif_per_hari'],
                $summary['total_denda'],
                $existing['id_denda']
            );
            $updateStmt->execute();
            $updateStmt->close();
        }

        return $summary;
    }

    $insertStmt = $conn->prepare("
        INSERT INTO denda (id_transaksi, jumlah_hari, tarif_per_hari, total_denda, status_bayar)
        VALUES (?, ?, ?, ?, 'belum')
    ");

    if ($insertStmt) {
        $insertStmt->bind_param(
            "iiii",
            $idTransaksi,
            $summary['jumlah_hari'],
            $summary['tarif_per_hari'],
            $summary['total_denda']
        );
        $insertStmt->execute();
        $insertStmt->close();
    }

    return $summary;
}
