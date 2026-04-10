<?php
/**
 * Cozy-Library — Print Header & Footer
 * Include this file in pages that need a print-friendly header/footer.
 * 
 * Usage: 
 *   <?php $print_title = 'Data Buku'; include '../includes/print_header.php'; ?>
 *   (letakkan sebelum tabel data)
 * 
 * Dan di akhir halaman (setelah tabel):
 *   <?php include '../includes/print_footer.php'; ?>
 */

$print_no_doc = 'DOC-' . date('Ymd') . '-' . str_pad(rand(1,999), 3, '0', STR_PAD_LEFT);
$print_tgl    = date('d F Y');
$print_jam    = date('H:i') . ' WIB';
?>

<!-- ── PRINT HEADER (hanya tampil saat cetak) ── -->
<div class="print-header" style="margin-bottom: 20px; border: none; padding: 0;">
    <!-- KOP SURAT -->
    <table style="width: 100%; border-bottom: 3px solid #111827; padding-bottom: 12px; margin-bottom: 15px; border-collapse: collapse;">
        <tr>
            <td style="width: 80px; text-align: center; vertical-align: top;">
                <div style="width: 64px; height: 64px; background: #111827; color: #fff; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; font-family: 'Plus Jakarta Sans', sans-serif; font-size: 26px; font-weight: 800; letter-spacing: -1px;">
                    CL
                </div>
            </td>
            <td style="text-align: center; vertical-align: middle; padding: 0 10px;">
                <div style="font-size: 1.5rem; font-weight: 800; color: #111827; text-transform: uppercase; letter-spacing: 1px;">Sistem Manajemen Perpustakaan</div>
                <div style="font-size: 1.2rem; font-weight: 700; color: #374151; margin-top: 2px;">Cozy-Library</div>
                <div style="font-size: 0.85rem; color: #4b5563; margin-top: 4px;">Jl. Pendidikan No. 1, Kota Pengetahuan, Kodepos 12345</div>
                <div style="font-size: 0.8rem; color: #6b7280;">Email: info@cozy-library.com | Telp: (021) 123-4567</div>
            </td>
            <td style="width: 80px;">
                <!-- Ruang kosong agar teks tengah seimbang dengan sisi kiri -->
            </td>
        </tr>
    </table>

    <!-- JUDUL DOKUMEN -->
    <div style="text-align: center; margin-bottom: 20px;">
        <h2 style="margin: 0; font-size: 1.2rem; font-weight: 800; color: #111827; text-transform: uppercase;">
            <?= htmlspecialchars($print_title ?? 'Laporan Data') ?>
        </h2>
        <div style="font-size: 0.85rem; color: #4b5563; margin-top: 4px;">
            Nomor Dokumen: <strong><?= $print_no_doc ?></strong>
        </div>
    </div>

    <!-- INFO METADATA -->
    <div style="display: flex; justify-content: space-between; align-items: flex-end; font-size: 0.85rem; color: #374151; border-bottom: 1px dashed #d1d5db; padding-bottom: 8px; margin-bottom: 15px;">
        <div>
            <div style="margin-bottom: 3px;"><strong>Tanggal Cetak:</strong> <?= $print_tgl ?></div>
            <div><strong>Waktu Cetak:</strong> <?= $print_jam ?></div>
        </div>
        <div style="text-align: right;">
            <div style="margin-bottom: 3px;"><strong>Status:</strong> Dokumen Resmi (Sistem)</div>
            <?php if (isset($print_total)): ?>
            <div><strong>Total Data:</strong> <?= $print_total ?> baris/item</div>
            <?php endif; ?>
        </div>
    </div>
</div>
