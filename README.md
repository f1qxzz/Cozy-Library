# 📚 Cozy-Library

Sistem manajemen perpustakaan berbasis web yang dibangun dengan PHP dan MySQL. Mendukung tiga jenis pengguna: **Admin**, **Petugas**, dan **Anggota**.

---

## ✨ Fitur Utama

- **Admin** — kelola pengguna, anggota, buku, kategori, transaksi, denda, dan laporan
- **Petugas** — kelola anggota, buku, kategori, transaksi, denda, dan laporan
- **Anggota** — lihat katalog, pinjam buku, kembalikan buku, beri ulasan, cek riwayat

---

## 🏗️ Struktur Proyek

```
Cozy-Library/
├── index.php                   # Halaman utama (landing page publik)
├── login.php                   # Halaman login untuk semua pengguna
├── logout.php                  # Logout dari halaman utama
├── register.php                # Pendaftaran anggota baru
├── setup.php                   # Setup awal database
├── api_search.php              # API pencarian buku (JSON)
├── perpus_30.sql               # File dump database MySQL
│
├── config/
│   └── database.php            # Konfigurasi koneksi database & konstanta
│
├── includes/
│   ├── session.php             # Helper fungsi session & autentikasi
│   └── upload_helper.php       # Helper fungsi upload gambar cover buku
│
├── admin/                      # Panel Administrator
│   ├── dashboard.php
│   ├── pengguna.php            # Manajemen akun admin/petugas
│   ├── anggota.php
│   ├── buku.php
│   ├── kategori.php
│   ├── transaksi.php
│   ├── denda.php
│   ├── laporan.php
│   ├── permintaan.php
│   ├── profil.php
│   ├── logout.php
│   └── includes/
│       ├── header.php          # Topbar dengan info user
│       └── nav.php             # Sidebar navigasi admin
│
├── petugas/                    # Panel Petugas
│   ├── dashboard.php
│   ├── anggota.php
│   ├── buku.php
│   ├── kategori.php
│   ├── transaksi.php
│   ├── denda.php
│   ├── laporan.php
│   ├── profil.php
│   ├── logout.php
│   └── includes/
│       ├── header.php
│       └── nav.php
│
├── anggota/                    # Portal Anggota
│   ├── dashboard.php
│   ├── katalog.php
│   ├── pinjam.php
│   ├── kembali.php
│   ├── riwayat.php
│   ├── ulasan.php
│   ├── profil.php
│   ├── logout.php
│   └── includes/
│       ├── header.php
│       └── nav.php
│
├── assets/
│   ├── css/                    # Stylesheet per modul (admin/petugas/anggota)
│   ├── js/
│   │   └── script.js           # JavaScript global (sidebar, drag-drop, dsb.)
│   └── icons/                  # Logo Cozy-Library
│
└── uploads/                    # File yang diupload pengguna
    ├── covers/                 # Cover buku
    ├── foto_profil/            # Foto profil admin/petugas
    └── foto_anggota/           # Foto profil anggota
```

---

## ⚙️ Instalasi

### Prasyarat
- PHP >= 7.4
- MySQL / MariaDB
- Web server (Apache / Nginx) atau PHP built-in server

### Langkah-langkah

1. **Clone atau ekstrak** project ke folder web server (misal `htdocs/` atau `www/`):
   ```bash
   # Jika menggunakan XAMPP
   cp -r Cozy-Library/ /xampp/htdocs/
   ```

2. **Buat database** di MySQL:
   ```sql
   CREATE DATABASE perpus_30;
   ```

3. **Import database**:
   ```bash
   mysql -u root -p perpus_30 < perpus_30.sql
   ```

4. **Konfigurasi koneksi database** — buat file `.env.local` di root project:
   ```ini
   DB_HOST=localhost
   DB_USER=root
   DB_PASS=
   DB_NAME=perpus_30
   DENDA_PER_HARI=1000
   APP_ENV=development
   APP_DEBUG=true
   ```
   > Tanpa file `.env.local`, sistem akan menggunakan nilai default: host `localhost`, user `root`, password kosong, database `perpus_30`.

5. **Pastikan folder uploads dapat ditulis**:
   ```bash
   chmod -R 755 uploads/
   ```

6. **Akses aplikasi** di browser:
   ```
   http://localhost/Cozy-Library/
   ```

---

## 👤 Akun Default

| Role     | Username  | Password |
|----------|-----------|----------|
| Admin    | admin     | admin123 |
| Petugas  | petugas   | petugas123 |

> Anggota didaftarkan melalui halaman `/register.php`.

---

## 🔐 Sistem Autentikasi

Semua logika session dipusatkan di **`includes/session.php`**:

| Fungsi              | Kegunaan                                      |
|---------------------|-----------------------------------------------|
| `initSession()`     | Memulai session PHP jika belum aktif          |
| `logout()`          | Menghancurkan seluruh session                 |
| `requireAdmin()`    | Redirect ke login jika bukan admin            |
| `requirePetugas()`  | Redirect ke login jika bukan petugas/admin    |
| `requireAnggota()`  | Redirect ke login jika bukan anggota          |
| `isAdmin()`         | Cek apakah user adalah admin                  |
| `isPetugas()`       | Cek apakah user adalah petugas                |
| `isAnggotaLoggedIn()` | Cek apakah anggota sudah login              |

Setiap file logout di subfolder (`/admin/logout.php`, `/petugas/logout.php`, `/anggota/logout.php`) dan di root (`/logout.php`) semuanya menggunakan fungsi `logout()` ini — tidak ada duplikasi logika penghancuran session.

---

## 🗄️ Database

File `perpus_30.sql` berisi skema lengkap beserta data contoh. Tabel utama:

| Tabel              | Keterangan                           |
|--------------------|--------------------------------------|
| `pengguna`         | Akun admin dan petugas               |
| `anggota`          | Akun anggota perpustakaan            |
| `buku`             | Data koleksi buku                    |
| `kategori`         | Kategori buku                        |
| `transaksi`        | Data peminjaman dan pengembalian     |
| `denda`            | Catatan denda keterlambatan          |
| `ulasan_buku`      | Ulasan dan rating buku oleh anggota  |

---

## 🔧 Perubahan Terbaru (Refactor)

### Penghapusan Kode Duplikat

Berikut duplikasi kode yang telah dihapus agar tidak ada redundansi:

#### 1. `logout.php` (root)
**Sebelum:** File ini menduplikasi logika `session_unset()` + `session_destroy()` secara manual, padahal fungsi `logout()` sudah tersedia di `includes/session.php`.

**Sesudah:** Cukup memanggil `logout()` — konsisten dengan semua file logout lain di project ini.

```php
// Sebelum (duplikat — tidak perlu)
initSession();
session_unset();
session_destroy();

// Sesudah (gunakan fungsi yang sudah ada)
logout();
```

#### 2. `admin/profil.php` & `petugas/profil.php`
**Sebelum:** Kedua file ini menjalankan **dua query terpisah** ke tabel `pengguna` untuk ID user yang sama — satu query `SELECT *` untuk data lengkap (`$user`), dan satu lagi `SELECT foto, nama_pengguna` khusus untuk header (`$userData`).

**Sesudah:** Query kedua dihapus. Data `foto` dan `nama_pengguna` yang dibutuhkan header langsung diambil dari variabel `$user` yang sudah ada dari query pertama — menghemat satu round-trip ke database.

```php
// Sebelum (query kedua tidak perlu)
$userStmt = $conn->prepare("SELECT foto, nama_pengguna FROM pengguna WHERE id_pengguna = ?");
$userStmt->bind_param("i", $id);
$userStmt->execute();
$userData = $userStmt->get_result()->fetch_assoc();
$userStmt->close();

// Sesudah (gunakan $user dari query pertama)
// $user sudah berisi semua kolom termasuk foto dan nama_pengguna
```

---

## 📝 Catatan Pengembangan

- Konfigurasi database dibaca dari `.env.local` jika ada, atau fallback ke nilai default
- Semua upload file divalidasi tipe MIME dan ukuran di sisi server
- Denda dihitung otomatis berdasarkan konstanta `DENDA_PER_HARI` (default Rp 1.000/hari)
- CSS dipisah per modul untuk mempermudah pemeliharaan
