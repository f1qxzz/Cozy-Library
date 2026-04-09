# 📚 Cozy-Library

Sistem Manajemen Perpustakaan berbasis web yang dibangun dengan PHP native dan MySQL. Dirancang untuk mengelola koleksi buku, anggota, transaksi peminjaman, dan denda secara terpadu dengan tampilan modern dan responsif.

---

## ✨ Fitur Utama

### 👤 Tiga Level Akses
| Level | Deskripsi |
|-------|-----------|
| **Admin** | Akses penuh ke seluruh sistem, manajemen pengguna & laporan |
| **Petugas** | Kelola buku, anggota, transaksi, denda, dan laporan |
| **Anggota** | Katalog buku, pinjam, kembalikan, riwayat, dan ulasan |

### 🔑 Autentikasi
- Login satu halaman untuk semua level (auto-deteksi role)
- Password hashing dengan `password_hash()` + auto-upgrade dari plaintext lama
- Session management per-level yang terisolasi
- Register mandiri untuk anggota baru

### 📖 Manajemen Buku
- CRUD buku lengkap (judul, pengarang, penerbit, tahun, ISBN, deskripsi, stok)
- Upload cover buku (JPG/PNG, max 2 MB) dengan validasi MIME type
- Kategori buku dengan deskripsi
- Status otomatis `tersedia` / `tidak` berdasarkan stok
- Pencarian buku real-time via `api_search.php`

### 👥 Manajemen Anggota
- CRUD anggota dengan data NIS, kelas, email, nomor telepon
- Upload foto profil anggota
- Status akun `aktif` / `nonaktif`
- Registrasi mandiri oleh anggota

### 🔄 Transaksi Peminjaman
- Pengajuan pinjam oleh anggota (status Pending)
- Persetujuan / penolakan oleh admin atau petugas
- Pengembalian buku dengan pencatatan tanggal aktual
- Stok buku otomatis berkurang/bertambah saat transaksi

### 💰 Denda
- Perhitungan otomatis denda keterlambatan (Rp 1.000/hari, dapat dikonfigurasi)
- Status pembayaran `belum` / `sudah` dengan catatan tanggal pelunasan

### 📊 Laporan
- Laporan transaksi peminjaman dengan filter tanggal
- Laporan denda dengan rekap total
- Tampilan print-friendly (`@media print`)

### ⭐ Ulasan Buku
- Anggota dapat memberi rating dan ulasan setelah mengembalikan buku
- Tampilan ulasan per buku di halaman katalog

### 🏠 Beranda Publik
- Statistik koleksi buku secara real-time
- Katalog buku terbaru dengan cover
- Tampilan responsif untuk pengunjung yang belum login

---

## 🗂️ Struktur Folder

```
Cozy-Library/
├── admin/                      # Panel Administrator
│   ├── includes/
│   │   ├── header.php          # Topbar (menggunakan getPenggunaProfileData)
│   │   └── nav.php             # Sidebar navigasi admin
│   ├── dashboard.php           # Statistik & quick actions
│   ├── pengguna.php            # CRUD admin/petugas
│   ├── anggota.php             # Manajemen anggota
│   ├── buku.php                # Manajemen koleksi buku
│   ├── kategori.php            # Manajemen kategori
│   ├── transaksi.php           # Kelola transaksi peminjaman
│   ├── permintaan.php          # Persetujuan permintaan pinjam
│   ├── denda.php               # Kelola denda
│   ├── laporan.php             # Laporan & ekspor
│   ├── profil.php              # Profil admin
│   └── logout.php
│
├── petugas/                    # Panel Petugas
│   ├── includes/
│   │   ├── header.php          # Topbar petugas
│   │   └── nav.php             # Sidebar navigasi petugas
│   ├── dashboard.php
│   ├── anggota.php
│   ├── buku.php
│   ├── kategori.php
│   ├── transaksi.php
│   ├── denda.php
│   ├── laporan.php
│   ├── profil.php
│   └── logout.php
│
├── anggota/                    # Portal Anggota
│   ├── includes/
│   │   ├── header.php
│   │   └── nav.php
│   ├── dashboard.php           # Ringkasan aktivitas anggota
│   ├── katalog.php             # Jelajahi koleksi buku
│   ├── pinjam.php              # Ajukan peminjaman
│   ├── kembali.php             # Kembalikan buku
│   ├── riwayat.php             # Riwayat peminjaman
│   ├── ulasan.php              # Tulis ulasan buku
│   ├── profil.php              # Edit profil & foto
│   └── logout.php
│
├── config/
│   └── database.php            # Koneksi DB + konstanta konfigurasi
│
├── includes/
│   ├── session.php             # Helper session & getPenggunaProfileData()
│   └── upload_helper.php       # Validasi & proses upload gambar
│
├── assets/
│   ├── css/
│   │   ├── style.css           # Global styles
│   │   ├── admin/              # CSS per-halaman admin
│   │   ├── petugas/            # CSS per-halaman petugas
│   │   └── anggota/            # CSS per-halaman anggota
│   ├── js/script.js
│   ├── icons/                  # Logo & ikon aplikasi
│   └── img/default.jpg         # Gambar fallback cover buku
│
├── uploads/
│   ├── covers/                 # Cover buku
│   └── foto_profil/            # Foto profil pengguna
│
├── index.php                   # Beranda publik
├── login.php                   # Halaman login (semua level)
├── register.php                # Registrasi anggota baru
├── logout.php                  # Logout root
├── api_search.php              # API pencarian buku (JSON)
├── setup.php                   # Setup awal (opsional)
└── perpus_30.sql               # Dump database lengkap + data contoh
```

---

## 🗄️ Skema Database

| Tabel | Keterangan |
|-------|-----------|
| `anggota` | Data anggota (NIS, kelas, foto, status aktif/nonaktif) |
| `buku` | Koleksi buku (judul, pengarang, stok, cover, kategori) |
| `kategori` | Kategori / genre buku |
| `pengguna` | Akun admin & petugas (dengan level) |
| `transaksi` | Peminjaman buku (Pending → Dipinjam → Dikembalikan) |
| `denda` | Denda keterlambatan per transaksi |
| `ulasan_buku` | Rating dan ulasan dari anggota |
| `permintaan_pinjam` | Permintaan pinjam yang menunggu persetujuan |

---

## ⚙️ Instalasi

### Prasyarat
- PHP 8.0+
- MySQL / MariaDB
- Apache (XAMPP / Laragon) atau Nginx

### Langkah Instalasi

**1. Clone atau ekstrak proyek**
```bash
git clone <repo-url> Cozy-Library
# atau ekstrak zip ke folder htdocs/www
```

**2. Import database**

Lewat phpMyAdmin:
1. Buat database baru bernama `perpus_30`
2. Pilih database → Import → pilih file `perpus_30.sql`

Atau lewat terminal:
```bash
mysql -u root -p -e "CREATE DATABASE perpus_30;"
mysql -u root -p perpus_30 < perpus_30.sql
```

**3. Konfigurasi koneksi**

Buat file `.env.local` di root proyek:
```ini
DB_HOST=localhost
DB_USER=root
DB_PASS=
DB_NAME=perpus_30
DENDA_PER_HARI=1000
APP_ENV=development
APP_DEBUG=true
```

Atau edit langsung di `config/database.php`:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'perpus_30');
```

**4. Pastikan folder uploads bisa ditulis**
```bash
chmod -R 755 uploads/
```

**5. Buka di browser**
```
http://localhost/Cozy-Library/
```

---

## 🔐 Akun Default

| Role | Username | Password |
|------|----------|----------|
| Admin | `admin` | `admin123` |
| Petugas | `petugas` | `petugas123` |

> Cek tabel `pengguna` di database untuk memastikan akun yang tersedia. Anggota dapat mendaftar sendiri melalui halaman `/register.php`.

---

## 🔧 Konfigurasi

### Tarif Denda
```ini
# .env.local
DENDA_PER_HARI=1000   # Rp 1.000 per hari keterlambatan
```

### Batas Ukuran Upload Cover
Edit `includes/upload_helper.php`:
```php
define('MAX_FILE_SIZE', 2 * 1024 * 1024); // default: 2 MB
```

### Mode Production
```ini
APP_ENV=production
APP_DEBUG=false
```
Error tidak ditampilkan ke pengguna — dicatat ke PHP error log.

---

## 🏗️ Arsitektur & Helper

### Helper Profil Pengguna (`includes/session.php`)
Fungsi `getPenggunaProfileData($conn)` menghindari duplikasi query profil di setiap halaman:
```php
$_profile = getPenggunaProfileData($conn);
$userData = $_profile['userData'];  // Array data dari DB
$initials = $_profile['initials'];  // Inisial nama untuk avatar fallback
$fotoPath = $_profile['fotoPath'];  // Path relatif foto profil (atau null)
```

### Helper Upload (`includes/upload_helper.php`)
```php
// Proses & validasi upload cover buku
$result = processBookCover($_FILES['cover']);
if ($result['ok']) {
    $coverPath = $result['path']; // simpan ke DB
}

// Hapus cover lama saat edit/delete
deleteBookCover($oldCoverPath);

// Render tag <img> dengan fallback otomatis
echo bookCoverImg($buku['cover'], $buku['judul_buku'], 'cover-img');
```

### Koneksi Database (`config/database.php`)
```php
$conn = getConnection();    // Buka koneksi
closeConnection($conn);     // Tutup koneksi
```

---

## 📋 Changelog

### v2.0 — Refactoring: Hapus Duplikasi Kode
Setiap halaman admin/petugas sebelumnya memiliki blok query identik ±12 baris untuk mengambil foto & nama pengguna. Blok ini muncul di **17 file berbeda** dan telah dihapus/dipusatkan:

| File | Perubahan |
|------|-----------|
| `includes/session.php` | ➕ Ditambah fungsi `getPenggunaProfileData()` |
| `admin/includes/header.php` | ♻️ Gunakan helper, hapus query inline |
| `petugas/includes/header.php` | ♻️ Gunakan helper, hapus query inline |
| `admin/dashboard.php` | ♻️ Gunakan helper (tetap butuh vars untuk welcome box) |
| `petugas/dashboard.php` | ♻️ Gunakan helper (tetap butuh vars untuk welcome box) |
| `admin/buku.php` | 🗑️ Hapus blok duplikat |
| `admin/anggota.php` | 🗑️ Hapus blok duplikat |
| `admin/kategori.php` | 🗑️ Hapus blok duplikat |
| `admin/denda.php` | 🗑️ Hapus blok duplikat |
| `admin/laporan.php` | 🗑️ Hapus blok duplikat |
| `admin/pengguna.php` | 🗑️ Hapus blok duplikat |
| `admin/transaksi.php` | 🗑️ Hapus blok duplikat |
| `admin/permintaan.php` | 🗑️ Hapus blok duplikat |
| `petugas/buku.php` | 🗑️ Hapus blok duplikat |
| `petugas/anggota.php` | 🗑️ Hapus blok duplikat |
| `petugas/kategori.php` | 🗑️ Hapus blok duplikat |
| `petugas/denda.php` | 🗑️ Hapus blok duplikat |
| `petugas/laporan.php` | 🗑️ Hapus blok duplikat |

---

## 🤝 Kontribusi

1. Fork repository
2. Buat branch: `git checkout -b fitur/nama-fitur`
3. Commit: `git commit -m 'Tambah fitur X'`
4. Push: `git push origin fitur/nama-fitur`
5. Buat Pull Request

---

*📚 Cozy-Library — Sistem Manajemen Perpustakaan Modern*
