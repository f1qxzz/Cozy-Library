<?php
/**
 * Session Helper
 */

function initSession() {
    if (session_status() === PHP_SESSION_NONE) session_start();
}

// ---- PENGGUNA (Admin / Petugas) ----
function isPenggunaLoggedIn() {
    initSession();
    return isset($_SESSION['pengguna_logged_in']) && $_SESSION['pengguna_logged_in'] === true;
}
function isAdmin() {
    initSession();
    return isPenggunaLoggedIn() && ($_SESSION['pengguna_level'] === 'admin');
}
function isPetugas() {
    initSession();
    return isPenggunaLoggedIn() && ($_SESSION['pengguna_level'] === 'petugas');
}
function getPenggunaId()    { initSession(); return $_SESSION['pengguna_id']    ?? null; }
function getPenggunaName()  { initSession(); return $_SESSION['pengguna_nama']  ?? ''; }
function getPenggunaLevel() { initSession(); return $_SESSION['pengguna_level'] ?? ''; }

// ---- ANGGOTA ----
function isAnggotaLoggedIn() {
    initSession();
    return isset($_SESSION['anggota_logged_in']) && $_SESSION['anggota_logged_in'] === true;
}
function getAnggotaId()   { initSession(); return $_SESSION['anggota_id']   ?? null; }
function getAnggotaName() { initSession(); return $_SESSION['anggota_nama'] ?? ''; }

// ---- REQUIRE AUTH ----
function requireAdmin() {
    initSession();
    if (!isAdmin()) { header('Location: ../login.php'); exit; }
}
function requirePetugas() {
    initSession();
    if (!isPenggunaLoggedIn()) { header('Location: ../login.php'); exit; }
}
function requireAnggota() {
    initSession();
    if (!isAnggotaLoggedIn()) { header('Location: ../login.php'); exit; }
}

function logout() {
    initSession();
    session_unset();
    session_destroy();
}

// ---- USER PROFILE DATA HELPER ----
/**
 * Ambil foto, initials, dan fotoPath user yang sedang login (pengguna/petugas).
 * Menghindari duplikasi query di setiap halaman admin/petugas.
 * Butuh $conn yang sudah aktif.
 */
function getPenggunaProfileData($conn, string $basePath = '../'): array {
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

    $foto     = $userData['foto'] ?? '';
    $fotoPath = (!empty($foto) && file_exists(dirname(__DIR__) . '/' . $foto))
                ? $basePath . htmlspecialchars($foto)
                : null;

    return [
        'userData' => $userData,
        'initials' => $initials,
        'fotoPath' => $fotoPath,
    ];
}
