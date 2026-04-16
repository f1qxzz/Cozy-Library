<div align="center">
    <h1>📚 Cozy-Library</h1>
    <p><b>Sistem Informasi Manajemen Perpustakaan Modern & Terpadu</b></p>
    <br>
    <img src="https://img.shields.io/badge/PHP-8.3%2B-777BB4.svg?style=for-the-badge&logo=php&logoColor=white" />
    <img src="https://img.shields.io/badge/MySQL-4479A1.svg?style=for-the-badge&logo=mysql&logoColor=white" />
    <img src="https://img.shields.io/badge/HTML5-E34F26.svg?style=for-the-badge&logo=html5&logoColor=white" />
    <img src="https://img.shields.io/badge/CSS3-1572B6.svg?style=for-the-badge&logo=css3&logoColor=white" />
    <img src="https://img.shields.io/badge/JavaScript-F7DF1E.svg?style=for-the-badge&logo=javascript&logoColor=black" />
    <br><br>
    <img src="https://img.shields.io/badge/Status-Production_Ready-16a34a.svg?style=flat-square" />
    <img src="https://img.shields.io/badge/Version-v1.0.0-0ea5e9.svg?style=flat-square" />
    <img src="https://img.shields.io/badge/License-MIT-yellow.svg?style=flat-square" />
</div>

<br>

**Cozy-Library** adalah aplikasi web lengkap (*full-stack*) yang dirancang untuk mengelola seluruh operasional perpustakaan secara digital — mulai dari sirkulasi peminjaman & pengembalian buku, manajemen anggota, hingga pengiriman notifikasi tagihan denda otomatis ke email anggota. Dibangun dengan arsitektur **Native PHP** yang ringan, aman, dan siap di-*deploy* ke server manapun.

---

## 🌟 Daftar Fitur Lengkap

### 👥 Multi-Role Authentication (3 Level Pengguna)
Sistem memiliki 3 hak akses pengguna yang masing-masing memiliki *dashboard* dan menu terpisah:

| Role | Akses | Deskripsi |
|---|---|---|
| **Admin** | `/admin/` | Kontrol penuh: CRUD buku, kategori, anggota, petugas, transaksi, laporan, konfigurasi denda |
| **Petugas** | `/petugas/` | Operator harian: proses peminjaman/pengembalian, kelola anggota, lihat laporan |
| **Anggota** | `/anggota/` | Self-service: lihat katalog, ajukan pinjam, cek riwayat, tulis ulasan buku |

### 📖 Manajemen Koleksi Buku & Kategori
- CRUD buku lengkap dengan upload gambar sampul (*cover*)
- Validasi MIME-Type pada upload file (mencegah eksploitasi server)
- Pengelompokan buku berdasarkan kategori
- Pencarian buku via API (`api_search.php`)
- **Detail Buku Berbasis Role**: Tampilan premium *Glassmorphism* dengan fitur yang disesuaikan untuk Admin (CRUD & Riwayat lengkap), Petugas (Aktif Update Stok & Info Peminjam), dan Anggota (Pengajuan Pinjam & Ulasan).

### 🔄 Sirkulasi Peminjaman & Pengembalian
- Peminjaman buku dengan kalkulasi otomatis tanggal jatuh tempo
- Pengembalian buku dengan deteksi keterlambatan otomatis
- **Auto-stok**: stok otomatis berkurang (-1) saat dipinjam dan bertambah (+1) saat dikembalikan
- Sistem permintaan peminjaman oleh anggota (`admin/permintaan.php`)

### 💸 Manajemen Denda Otomatis
- Kalkulasi denda keterlambatan real-time: **Rp1.000/hari**
- Halaman khusus rekap denda (`admin/denda.php` & `petugas/denda.php`)
- Tarif denda dapat dikonfigurasi via `.env.local`

### 📊 Laporan & Cetak Dokumen
- Laporan transaksi, anggota, dan koleksi buku
- **Smart Print Engine**: tombol "Cetak" mengaktifkan mode *Landscape A4* secara otomatis
- DataTables menampilkan seluruh record (`length: -1`) saat mencetak, lalu kembali normal setelah dialog print ditutup
- Mendukung ekspor data ke format PDF/Print

### 🤖 Otomatisasi Email (Cron Job SMTP)
Salah satu fitur andalan: sistem background yang berjalan otomatis tanpa intervensi admin.

#### 1. Pengingat H-1 (`cron/reminder_h1.php`)
- Memindai database untuk transaksi yang jatuh tempo **besok**
- Mengirim email pengingat ke anggota terkait
- Subject: `🔔 [REMINDER] Jatuh Tempo Pengembalian Buku Besok (TRX-XXX) - Cozy Library`

#### 2. Penagihan Denda Terlambat (`cron/overdue_reminder.php`)
- Memindai database untuk transaksi yang **sudah melewati** tenggat waktu
- Menghitung estimasi denda real-time (jumlah hari × Rp1.000)
- Mengirim email tagihan formal bergaya *Invoice* dengan desain premium
- Subject: `🔴 [ACTION REQUIRED] Surat Tagihan Denda Keterlambatan (TRX-XXX) - Cozy Library`
- Dilengkapi `X-Priority: 1 (Highest)` header agar ditandai sebagai email penting oleh Gmail

#### Keunggulan Engine Email:
- 📬 **PHPMailer + SMTP**: Pengiriman via Google SMTP untuk jaminan masuk *Inbox* (bukan spam)
- 🛡️ **Try-Catch Bypass**: Jika 1 email gagal, sisanya tetap terkirim tanpa crash
- 📋 **Smart Logging**: Semua aktivitas tercatat otomatis di `cron/cron.log`
- 🔄 **Fallback Mode**: Jika SMTP tidak dikonfigurasi, otomatis gunakan `mail()` bawaan server

### 🔒 Keamanan Tingkat Tinggi
- **SQL Injection Prevention**: Seluruh query menggunakan `Prepared Statements` (bind_param)
- **XSS Protection**: Output difilter konsisten menggunakan `htmlspecialchars()`
- **Password Hashing**: Bcrypt via `password_hash()` — password tidak tersimpan dalam bentuk teks biasa
- **Session Protection**: Setiap halaman dilindungi validasi session & role (`includes/session.php`)
- **Upload Validation**: Pengecekan MIME-Type asli file, bukan hanya ekstensi (`includes/upload_helper.php`)

### 🎨 Desain UI Modern
- Antarmuka **Glassmorphism** dengan backdrop-filter dan gradien halus
- Responsive layout menggunakan CSS Flexbox
- DataTables untuk tabel interaktif (sorting, searching, pagination)
- Sidebar navigasi dengan ikon

---

## 📂 Struktur Direktori Proyek

```text
Cozy-Library/
│
├── index.php                  # Landing page utama (Homepage publik)
├── login.php                  # Halaman login multi-role
├── register.php               # Registrasi akun anggota baru
├── logout.php                 # Proses logout & destroy session
├── setup.php                  # Setup awal database & admin
├── api_search.php             # API endpoint pencarian buku
├── perpus_30.sql              # File dump database (siap import)
├── .env.local                 # Konfigurasi environment (DB & SMTP)
├── README.md                  # Dokumentasi proyek (file ini)
│
├── admin/                     # === MODUL ADMIN ===
│   ├── dashboard.php          #   Dashboard statistik & overview
│   ├── buku.php               #   CRUD manajemen buku + upload cover
│   ├── kategori.php           #   CRUD kategori buku
│   ├── anggota.php            #   Manajemen data anggota perpustakaan
│   ├── pengguna.php           #   Manajemen user/petugas (akun login)
│   ├── transaksi.php          #   Proses peminjaman & pengembalian
│   ├── permintaan.php         #   Approval permintaan pinjam dari anggota
│   ├── denda.php              #   Rekap dan manajemen denda keterlambatan
│   ├── laporan.php            #   Generator laporan + cetak A4
│   ├── profil.php             #   Edit profil admin
│   └── includes/              #   Sidebar & navigasi admin
│
├── petugas/                   # === MODUL PETUGAS ===
│   ├── dashboard.php          #   Dashboard operasional harian
│   ├── buku.php               #   Lihat & kelola koleksi buku
│   ├── kategori.php           #   Lihat kategori
│   ├── anggota.php            #   Kelola data anggota
│   ├── transaksi.php          #   Proses sirkulasi peminjaman
│   ├── denda.php              #   Lihat rekap denda
│   ├── laporan.php            #   Cetak laporan petugas
│   ├── profil.php             #   Edit profil petugas
│   └── includes/              #   Sidebar & navigasi petugas
│
├── anggota/                   # === MODUL ANGGOTA ===
│   ├── dashboard.php          #   Dashboard pribadi anggota
│   ├── katalog.php            #   Browsing katalog buku
│   ├── pinjam.php             #   Form pengajuan peminjaman
│   ├── kembali.php            #   Status pengembalian
│   ├── riwayat.php            #   Riwayat transaksi pribadi
│   ├── ulasan.php             #   Tulis & lihat ulasan buku
│   ├── profil.php             #   Edit profil anggota
│   └── includes/              #   Sidebar & navigasi anggota
│
├── config/                    # === KONFIGURASI ===
│   └── database.php           #   Koneksi DB + loader .env.local
│
├── includes/                  # === LIBRARY & HELPER ===
│   ├── session.php            #   Validasi session & proteksi role
│   ├── upload_helper.php      #   Validasi & proses upload file
│   ├── print_header.php       #   Header template cetak laporan
│   ├── print_footer.php       #   Footer template cetak laporan
│   └── PHPMailer/             #   Library PHPMailer standalone
│       ├── PHPMailer.php      #     Core class PHPMailer
│       ├── SMTP.php           #     SMTP transport handler
│       └── Exception.php      #     Exception handler
│
├── cron/                      # === BACKGROUND JOBS ===
│   ├── reminder_h1.php        #   Cron: pengingat H-1 jatuh tempo
│   ├── overdue_reminder.php   #   Cron: tagihan denda terlambat
│   └── cron.log               #   Log hasil eksekusi cron
│
├── assets/                    # === ASET STATIS ===
│   └── css/, img/, js/        #   Stylesheet, gambar, dan script
│
└── uploads/                   # === UPLOAD STORAGE ===
    └── covers/                #   Penyimpanan file cover buku
```

---

## 🚀 Panduan Instalasi

### Prasyarat
- PHP 8.0+ (disarankan 8.3)
- MySQL / MariaDB
- Web Server: Laragon, XAMPP, atau hosting CPanel

### Langkah-langkah

**1. Clone atau Download Repository**
```bash
git clone https://github.com/username/Cozy-Library.git
```

**2. Import Database**
- Buka **phpMyAdmin** atau HeidiSQL
- Buat database baru bernama `perpus_30`
- Import file `perpus_30.sql` yang ada di root folder

**3. Konfigurasi Environment**
Buat file `.env.local` di root folder proyek (atau edit yang sudah ada):
```ini
; === Konfigurasi Database ===
DB_HOST=localhost
DB_USER=root
DB_PASS=
DB_NAME=perpus_30

; === Aturan Perpustakaan ===
DENDA_PER_HARI=1000

; === Konfigurasi Email SMTP (Opsional) ===
; Untuk mengaktifkan notifikasi email otomatis
; Gunakan App Password dari Google (bukan password login biasa!)
; Cara mendapatkan: Google Account > Security > 2-Step Verification > App Passwords
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USER=email.anda@gmail.com
SMTP_PASS=xxxx_xxxx_xxxx_xxxx
```

**4. Jalankan Aplikasi**
```
http://localhost/Cozy-Library/
```

**5. (Opsional) Setup Cron Job untuk Notifikasi Otomatis**

*Windows (Task Scheduler):*
```bash
php C:\path\to\Cozy-Library\cron\reminder_h1.php
php C:\path\to\Cozy-Library\cron\overdue_reminder.php
```
Jika perintah `php` tidak dikenali di Windows, gunakan path absolut PHP (contoh Laragon):
```bash
C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe C:\path\to\Cozy-Library\cron\reminder_h1.php
C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe C:\path\to\Cozy-Library\cron\overdue_reminder.php
```

*Linux/CPanel (Crontab):*
```bash
# Pengingat H-1 setiap jam 08:00 pagi
0 8 * * * php /home/user/public_html/Cozy-Library/cron/reminder_h1.php

# Tagihan denda setiap jam 09:00 pagi
0 9 * * * php /home/user/public_html/Cozy-Library/cron/overdue_reminder.php
```

---

## 🧪 Hasil Pengujian (QA Report Terbaru)

Pengujian terakhir dilakukan pada **11 April 2026** hingga **14 April 2026**.

| Kategori Uji | Status | Hasil Terbaru |
|---|---|---|
| PHP Syntax Validation | PASS | 50 file PHP diperiksa, 0 parse/syntax error |
| Database Connectivity | PASS | Koneksi DB sukses (`SELECT 1`), tabel inti terdeteksi normal |
| Endpoint Sweep | PASS | 34 endpoint PHP (root/admin/petugas/anggota), gagal: 0 |
| Public Page Smoke Test | PASS | `/`, `/index.php`, `/login.php`, `/register.php`, `/setup.php` -> HTTP 200 |
| API Search Validation | PASS | `api_search.php` respons JSON valid, validasi minimum query berjalan |
| Auth Flow Anggota | PASS | register -> login -> dashboard -> logout -> session invalid sukses |
| Auth Flow Admin | PASS | login admin sukses, role guard ke halaman anggota berjalan |
| Auth Flow Petugas | PASS | login petugas sukses, role guard ke halaman admin berjalan |
| Input Validation | PASS | invalid login, email invalid, password < 6, duplicate register tertangani |
| Static Assets | PASS | CSS/JS/icon utama ter-load HTTP 200 |
| Stability Test (Light) | PASS | 50 request berulang ke `index.php`: 50 sukses, 0 gagal |
| Email Overdue Test (SMTP) | PASS | Uji overdue email berhasil terkirim via PHPMailer |
| Full Overdue Cron Run | PASS | `cron/overdue_reminder.php` memproses 2 data telat, terkirim 2, gagal 0 |
| Book Detail UI Testing| PASS | View detail dirender dinamis per role tanpa error, UI responsif berjalan normal |

### Catatan QA
- Log cron terbaru tersedia di `cron/cron.log`.
- Pada lingkungan Windows tertentu, PHP CLI belum otomatis ada di PATH sehingga disarankan memakai path absolut `php.exe`.
- Script `npm test` belum dikonfigurasi (masih default `Error: no test specified`).

---

## 🛠️ Tech Stack

| Layer | Teknologi |
|---|---|
| **Backend** | PHP 8.3 (Native OOP) |
| **Database** | MariaDB / MySQL (MySQLi Prepared Statement) |
| **Frontend** | HTML5, CSS3 (Custom Glassmorphism), JavaScript |
| **Tabel Interaktif** | DataTables jQuery Plugin |
| **Email Engine** | PHPMailer 6.x (Standalone, SMTP Auth) |
| **Environment** | .env.local (INI Parser) |

---

<div align="center">
    <br>
    <strong>Dibuat dengan ❤️ untuk solusi perpustakaan digital Indonesia.</strong>
    <br>
    <em>Hak Cipta © 2026 Cozy-Library Teams</em>
    <br><br>
</div>
