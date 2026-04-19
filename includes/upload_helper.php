<?php
/*
 * Alur logic PHP:
 * 1) Menyediakan fungsi utilitas terpusat untuk dipakai banyak halaman.
 * 2) Menjaga validasi input dan normalisasi data pada satu tempat.
 * 3) Mengembalikan hasil yang siap dipakai oleh controller/view.
 *//**
 * ============================================================
 *  UPLOAD HELPER — Cover Buku
 *  File: includes/upload_helper.php
 *  Berisi fungsi-fungsi bantu untuk proses upload gambar
 * ============================================================
 */

// ── Konfigurasi Upload ────────────────────────────────────────
define('UPLOAD_DIR',      __DIR__ . '/../uploads/covers/'); // path absolut folder simpan
define('UPLOAD_URL',      'uploads/covers/');              // path relatif untuk URL <img>
define('DEFAULT_COVER',   'assets/img/default.jpg');       // gambar fallback jika cover NULL
define('MAX_FILE_SIZE',   2 * 1024 * 1024);               // 2 MB dalam byte
define('ALLOWED_EXT',     ['jpg', 'jpeg', 'png']);
define('ALLOWED_MIME',    ['image/jpeg', 'image/png']);


/**
 * Memproses upload cover buku.
 *
 * @param  array       $file   Elemen dari $_FILES['cover']
 * @return array{ok:bool, path:string|null, error:string}
 *         ok    → true jika sukses / tidak ada file dipilih
 *         path  → path yang disimpan ke DB (string) atau null
 *         error → pesan error jika ok = false
 */
function processBookCover(array $file): array
{
    // ── 1. Tidak ada file yang dipilih → simpan NULL ──────────
    if ($file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['ok' => true, 'path' => null, 'error' => ''];
    }

    // ── 2. Tangani semua kode error upload dari PHP ───────────
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errMap = [
            UPLOAD_ERR_INI_SIZE   => 'File melebihi batas upload_max_filesize di php.ini.',
            UPLOAD_ERR_FORM_SIZE  => 'File melebihi batas MAX_FILE_SIZE di form.',
            UPLOAD_ERR_PARTIAL    => 'File hanya terupload sebagian, coba lagi.',
            UPLOAD_ERR_NO_TMP_DIR => 'Folder temporary tidak ditemukan di server.',
            UPLOAD_ERR_CANT_WRITE => 'Gagal menulis file ke disk.',
            UPLOAD_ERR_EXTENSION  => 'Upload dihentikan oleh ekstensi PHP.',
        ];
        $msg = $errMap[$file['error']] ?? 'Error upload tidak dikenal (kode: ' . $file['error'] . ').';
        return ['ok' => false, 'path' => null, 'error' => $msg];
    }

    // ── 3. Pastikan file benar-benar berasal dari HTTP upload ─
    //      (bukan file lokal yang dimanipulasi)
    if (!is_uploaded_file($file['tmp_name'])) {
        return ['ok' => false, 'path' => null, 'error' => 'File tidak valid (bukan hasil upload HTTP).'];
    }

    // ── 4. Validasi ukuran file ───────────────────────────────
    if ($file['size'] > MAX_FILE_SIZE) {
        $maxMB = MAX_FILE_SIZE / 1024 / 1024;
        return ['ok' => false, 'path' => null, 'error' => "Ukuran file melebihi batas {$maxMB} MB."];
    }

    // ── 5. Validasi ekstensi (dari nama file) ─────────────────
    //      Catatan: ini bukan satu-satunya penjaga, MIME juga dicek
    $originalName = $file['name'];
    $ext          = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

    if (!in_array($ext, ALLOWED_EXT, true)) {
        $allowed = implode(', ', ALLOWED_EXT);
        return ['ok' => false, 'path' => null, 'error' => "Ekstensi tidak diizinkan. Gunakan: {$allowed}."];
    }

    // ── 6. Validasi MIME type dengan finfo (baca byte nyata file)
    //      Ini lebih andal daripada percaya $_FILES['type']
    $finfo    = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);

    if (!in_array($mimeType, ALLOWED_MIME, true)) {
        return ['ok' => false, 'path' => null, 'error' => "Tipe file tidak diizinkan. Hanya JPG/PNG yang diterima."];
    }

    // ── 7. Buat folder tujuan jika belum ada ─────────────────
    if (!is_dir(UPLOAD_DIR)) {
        // 0755 = owner bisa baca-tulis-eksekusi, grup & others hanya baca
        if (!mkdir(UPLOAD_DIR, 0755, true)) {
            return ['ok' => false, 'path' => null, 'error' => 'Gagal membuat folder upload di server.'];
        }
    }

    // ── 8. Generate nama file unik ────────────────────────────
    //      Tidak pernah menggunakan nama asli (mencegah path traversal
    //      dan konflik nama). Format: cover_[uniqid]_[microsec].[ext]
    $uniqueName = 'cover_' . uniqid('', true) . '.' . $ext;
    $destPath   = UPLOAD_DIR . $uniqueName;

    // ── 9. Pindahkan file dari temp ke folder final ───────────
    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        return ['ok' => false, 'path' => null, 'error' => 'Gagal menyimpan file ke server. Periksa izin folder.'];
    }

    // ── 10. Kembalikan path relatif untuk disimpan ke DB ──────
    $dbPath = UPLOAD_URL . $uniqueName;  // contoh: "uploads/covers/cover_abc123.jpg"
    return ['ok' => true, 'path' => $dbPath, 'error' => ''];
}


/**
 * Hapus file cover lama dari server (dipanggil saat edit/delete buku).
 *
 * @param  string|null $dbPath  Path yang tersimpan di DB
 * @return bool
 */
function deleteBookCover(?string $dbPath): bool
{
    if (empty($dbPath)) return true; // tidak ada yang perlu dihapus

    // Bangun path absolut dari path DB
    $fullPath = __DIR__ . '/../' . $dbPath;

    if (file_exists($fullPath) && is_file($fullPath)) {
        return unlink($fullPath);
    }
    return true; // file sudah tidak ada, anggap sukses
}


/**
 * Tampilkan tag <img> cover buku.
 * Jika cover NULL atau file tidak ada → gunakan gambar default.
 *
 * @param  string|null $cover  Nilai kolom `cover` dari DB
 * @param  string      $alt    Teks alt gambar
 * @param  string      $class  CSS class (opsional)
 * @return string      HTML string <img ...>
 */
function bookCoverImg(?string $cover, string $alt = 'Cover Buku', string $class = '', string $basePath = '../'): string
{
    // Cek file secara fisik menggunakan path absolut
    $fileExists = !empty($cover) && file_exists(__DIR__ . '/../' . $cover);

    if ($fileExists) {
        // Tambah $basePath di depan agar src benar dari subfolder manapun
        // Contoh dari anggota/ atau admin/ : '../uploads/covers/cover_abc.jpg'
        $src = htmlspecialchars($basePath . $cover, ENT_QUOTES, 'UTF-8');
    } else {
        // Fallback gambar default
        $src = htmlspecialchars($basePath . DEFAULT_COVER, ENT_QUOTES, 'UTF-8');
    }

    $altEsc   = htmlspecialchars($alt, ENT_QUOTES, 'UTF-8');
    $classStr = $class ? ' class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '"' : '';

    return "<img src=\"{$src}\" alt=\"{$altEsc}\"{$classStr}>";
}