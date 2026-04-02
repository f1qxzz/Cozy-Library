-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Waktu pembuatan: 02 Apr 2026 pada 16.26
-- Versi server: 10.4.32-MariaDB
-- Versi PHP: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `perpus_30`
--

-- --------------------------------------------------------

--
-- Struktur dari tabel `anggota`
--

CREATE TABLE `anggota` (
  `id_anggota` int(11) NOT NULL,
  `nis` varchar(20) NOT NULL,
  `nama_anggota` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `kelas` varchar(20) NOT NULL,
  `alamat` text DEFAULT NULL,
  `no_telepon` varchar(20) DEFAULT NULL,
  `foto` varchar(255) DEFAULT NULL,
  `status` enum('aktif','nonaktif') NOT NULL DEFAULT 'aktif',
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `anggota`
--

INSERT INTO `anggota` (`id_anggota`, `nis`, `nama_anggota`, `username`, `password`, `email`, `kelas`, `alamat`, `no_telepon`, `foto`, `status`, `created_at`) VALUES
(4, '2024001', 'Kuncoro', 'Kun', '$2y$10$TXtxc59Bv2u6sxXd8MfB8.U9WPiOc5YvzuMJhb/uVTVVB3muq6zRS', 'kun@gmail.com', 'XII RPL', NULL, NULL, NULL, 'aktif', '2026-03-29 15:04:54'),
(5, '12345', 'Taufiq', 'f1q', '$2y$10$LOuG8IawsdGPCWSwukdueOe3EWe3Op/ZLOwvbERtOk4SrhhgMJACq', 'f1q123@gmail.com', 'XII RPL', NULL, NULL, NULL, 'aktif', '2026-04-02 19:50:17');

-- --------------------------------------------------------

--
-- Struktur dari tabel `buku`
--

CREATE TABLE `buku` (
  `id_buku` int(11) NOT NULL,
  `id_kategori` int(11) DEFAULT NULL,
  `judul_buku` varchar(200) NOT NULL,
  `pengarang` varchar(100) NOT NULL,
  `penerbit` varchar(100) NOT NULL,
  `tahun_terbit` year(4) NOT NULL,
  `isbn` varchar(30) DEFAULT NULL,
  `deskripsi` text DEFAULT NULL,
  `stok` int(11) NOT NULL DEFAULT 1,
  `status` enum('tersedia','tidak') NOT NULL DEFAULT 'tersedia',
  `cover` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `buku`
--

INSERT INTO `buku` (`id_buku`, `id_kategori`, `judul_buku`, `pengarang`, `penerbit`, `tahun_terbit`, `isbn`, `deskripsi`, `stok`, `status`, `cover`, `created_at`) VALUES
(1, 1, 'Laskar Pelangi', 'Andrea Hirata', 'Bentang Pustaka', '2005', '978-979-1478-21-8', 'Novel tentang anak-anak di Belitung', 3, 'tersedia', 'uploads/covers/cover_69c933393919e3.81906466.jpg', '2026-03-26 08:59:12'),
(2, 1, 'Bumi Manusia', 'Pramoedya Ananta Toer', 'Hasta Mitra', '1980', '978-979-661-714-4', 'Tetralogi Buru', 2, 'tersedia', 'uploads/cover/cover_1774585301_69c605d5619d7.jpg', '2026-03-26 08:59:12'),
(3, 2, 'Sejarah Indonesia Modern', 'M.C. Ricklefs', 'Serambi', '2008', '978-979-024-067-5', 'Sejarah Indonesia dari 1200 sampai sekarang', 2, 'tersedia', 'uploads/covers/cover_69c93413720cf9.06662067.jpg', '2026-03-26 08:59:12'),
(4, 3, 'Matematika Kelas X', 'Kemendikbud', 'Kemendikbud', '2017', '', 'Buku teks matematika kelas XII', 5, 'tersedia', 'uploads/covers/cover_69c933d28149a2.04725767.jpg', '2026-03-26 08:59:12'),
(5, 5, 'Pemrograman PHP Modern', 'Rizky Abdulah', 'Informatika', '2022', '978-602-02-7680-3', 'Panduan lengkap PHP dan MySQL', 2, 'tersedia', 'uploads/cover/cover_1774585536_69c606c0a6759.jpg', '2026-03-26 08:59:12'),
(6, 6, 'Fisika Dasar', 'Halliday & Resnick', 'Erlangga', '2018', '978-979-095-879-3', 'Fisika untuk mahasiswa dan pelajar', 3, 'tersedia', 'uploads/cover/cover_1774585435_69c6065b0bb13.jpg', '2026-03-26 08:59:12'),
(7, 1, 'Satu Porsi Mie Ayam Sebelum Mati', 'Brian Khrisna', 'Mediakita', '2019', '', 'Buku Satu Porsi Mie Ayam menceritakan tentang kehidupan, pertemanan, dan realita sosial yang dibalut dengan cerita sederhana namun penuh makna. Ceritanya ringan tapi menyentuh, cocok untuk pembaca remaja hingga dewasa.', 7, 'tersedia', 'uploads/covers/cover_69c93191503155.45953577.jpg', '2026-03-29 21:05:05'),
(8, 1, 'Hujan', 'Tere Liye', 'Gramedia Pustaka Utama', '2016', '', 'Hujan menceritakan kisah Lail dan Esok dalam dunia masa depan yang mengalami bencana besar. Cerita ini mengangkat tema cinta, kehilangan, dan pengorbanan dengan latar teknologi dan perubahan iklim.', 8, 'tersedia', 'uploads/covers/cover_69c934c0da0e60.12046590.jpg', '2026-03-29 21:18:40'),
(9, 1, '3274 MDPL', 'Agung Pamuji', 'Gradien Mediatama', '2014', '', '3274 MDPL menceritakan kisah petualangan sekelompok anak muda yang mendaki gunung dengan ketinggian 3.274 meter di atas permukaan laut. Cerita ini penuh dengan persahabatan, perjuangan, dan tantangan alam yang menguji mental dan fisik mereka.', 4, 'tersedia', 'uploads/covers/cover_69c935afecda60.49141725.jpg', '2026-03-29 21:22:39'),
(16, 1, 'Rindu', 'Tere Liye', 'Republika', '2014', '', 'Perjalanan haji penuh makna.', 5, 'tersedia', 'uploads/covers/cover_69c938485c4739.28295467.jpg', '2026-03-29 21:30:10'),
(18, 2, 'Rich Dad Poor Dad', 'Robert Kiyosaki', 'Plata', '1997', '', 'Mindset keuangan.', 4, 'tersedia', 'uploads/covers/cover_69c938697658c8.51540378.jpg', '2026-03-29 21:30:10'),
(22, 5, 'The Pragmatic Programmer', 'Andrew Hunt', 'Addison-Wesley', '1999', '', 'Panduan programmer profesional.', 2, 'tersedia', 'uploads/covers/cover_69c9381c18a0f5.74979458.jpg', '2026-03-29 21:30:10'),
(23, 6, 'A Brief History of Time', 'Stephen Hawking', 'Bantam', '1988', '', 'Tentang alam semesta.', 4, 'tersedia', 'uploads/covers/cover_69c937fd86b394.74935208.jpg', '2026-03-29 21:30:10'),
(24, 6, 'The Selfish Gene', 'Richard Dawkins', 'Oxford', '1976', '', 'Teori evolusi gen.', 2, 'tersedia', 'uploads/covers/cover_69c937d26a1578.12592098.jpg', '2026-03-29 21:30:10');

-- --------------------------------------------------------

--
-- Struktur dari tabel `denda`
--

CREATE TABLE `denda` (
  `id_denda` int(11) NOT NULL,
  `id_transaksi` int(11) NOT NULL,
  `jumlah_hari` int(11) NOT NULL DEFAULT 0,
  `tarif_per_hari` int(11) NOT NULL DEFAULT 1000,
  `total_denda` int(11) NOT NULL DEFAULT 0,
  `status_bayar` enum('belum','sudah') NOT NULL DEFAULT 'belum',
  `tgl_bayar` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `kategori`
--

CREATE TABLE `kategori` (
  `id_kategori` int(11) NOT NULL,
  `nama_kategori` varchar(100) NOT NULL,
  `deskripsi` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `kategori`
--

INSERT INTO `kategori` (`id_kategori`, `nama_kategori`, `deskripsi`, `created_at`) VALUES
(1, 'Fiksi', 'Novel, cerpen, dan karya fiksi lainnya', '2026-03-26 08:59:12'),
(2, 'Non-Fiksi', 'Buku pengetahuan dan ilmu umum', '2026-03-26 08:59:12'),
(3, 'Pelajaran', 'Buku teks dan pelajaran sekolah', '2026-03-26 08:59:12'),
(4, 'Referensi', 'Kamus, ensiklopedia, dan referensi', '2026-03-26 08:59:12'),
(5, 'Teknologi', 'Buku pemrograman dan teknologi informasi', '2026-03-26 08:59:12'),
(6, 'Sains', 'Buku ilmu pengetahuan alam', '2026-03-26 08:59:12');

-- --------------------------------------------------------

--
-- Struktur dari tabel `pengguna`
--

CREATE TABLE `pengguna` (
  `id_pengguna` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `nama_pengguna` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `level` enum('admin','petugas') NOT NULL DEFAULT 'petugas',
  `foto` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `pengguna`
--

INSERT INTO `pengguna` (`id_pengguna`, `username`, `password`, `nama_pengguna`, `email`, `level`, `foto`, `created_at`) VALUES
(1, 'f1qxzz', '$2y$10$f6175iWTlIJHHV4t2ikm6elP8qTSHfd.wIgwdiKL.i68cLTba.vyO', 'Administrator', 'admin@perpus.com', 'admin', 'uploads/foto_profil/foto_1_1774582391.jpg', '2026-03-26 08:59:12'),
(2, 'petugas', '$2y$10$tsoxQrF/.B0gFfynCWTq5.6Wye74UGFhb.Bszy7ebUd5e6YZa8obW', 'Petugas Perpustakaan', 'petugas@perpus.com', 'petugas', 'uploads/foto_profil/foto_2_1774582501.jpg', '2026-03-26 08:59:12');

-- --------------------------------------------------------

--
-- Struktur dari tabel `permintaan_pinjam`
--

CREATE TABLE `permintaan_pinjam` (
  `id_permintaan` int(11) NOT NULL,
  `no_request` varchar(20) NOT NULL,
  `id_anggota` int(11) NOT NULL,
  `id_buku` int(11) NOT NULL,
  `tgl_request` datetime NOT NULL DEFAULT current_timestamp(),
  `tgl_mulai` date NOT NULL,
  `tgl_selesai` date NOT NULL,
  `status` enum('Pending','Disetujui','Ditolak') NOT NULL DEFAULT 'Pending',
  `tgl_aksi` datetime DEFAULT NULL,
  `id_admin` int(11) DEFAULT NULL,
  `catatan` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `transaksi`
--

CREATE TABLE `transaksi` (
  `id_transaksi` int(11) NOT NULL,
  `id_anggota` int(11) NOT NULL,
  `id_buku` int(11) NOT NULL,
  `id_petugas` int(11) DEFAULT NULL,
  `tgl_pinjam` datetime NOT NULL,
  `tgl_kembali_rencana` datetime NOT NULL,
  `tgl_kembali_aktual` datetime DEFAULT NULL,
  `status_transaksi` enum('Pending','Peminjaman','Dipinjam','Pengembalian','Dikembalikan','Ditolak') NOT NULL DEFAULT 'Peminjaman',
  `catatan` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `ulasan_buku`
--

CREATE TABLE `ulasan_buku` (
  `id_ulasan` int(11) NOT NULL,
  `id_anggota` int(11) NOT NULL,
  `id_buku` int(11) NOT NULL,
  `rating` tinyint(1) NOT NULL DEFAULT 5,
  `ulasan` text NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indeks untuk tabel `anggota`
--
ALTER TABLE `anggota`
  ADD PRIMARY KEY (`id_anggota`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `nis` (`nis`);

--
-- Indeks untuk tabel `buku`
--
ALTER TABLE `buku`
  ADD PRIMARY KEY (`id_buku`),
  ADD KEY `id_kategori` (`id_kategori`);

--
-- Indeks untuk tabel `denda`
--
ALTER TABLE `denda`
  ADD PRIMARY KEY (`id_denda`),
  ADD KEY `id_transaksi` (`id_transaksi`);

--
-- Indeks untuk tabel `kategori`
--
ALTER TABLE `kategori`
  ADD PRIMARY KEY (`id_kategori`);

--
-- Indeks untuk tabel `pengguna`
--
ALTER TABLE `pengguna`
  ADD PRIMARY KEY (`id_pengguna`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indeks untuk tabel `permintaan_pinjam`
--
ALTER TABLE `permintaan_pinjam`
  ADD PRIMARY KEY (`id_permintaan`),
  ADD UNIQUE KEY `no_request` (`no_request`),
  ADD KEY `id_anggota` (`id_anggota`),
  ADD KEY `id_buku` (`id_buku`);

--
-- Indeks untuk tabel `transaksi`
--
ALTER TABLE `transaksi`
  ADD PRIMARY KEY (`id_transaksi`),
  ADD KEY `id_anggota` (`id_anggota`),
  ADD KEY `id_buku` (`id_buku`),
  ADD KEY `id_petugas` (`id_petugas`);

--
-- Indeks untuk tabel `ulasan_buku`
--
ALTER TABLE `ulasan_buku`
  ADD PRIMARY KEY (`id_ulasan`),
  ADD KEY `id_anggota` (`id_anggota`),
  ADD KEY `id_buku` (`id_buku`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `anggota`
--
ALTER TABLE `anggota`
  MODIFY `id_anggota` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT untuk tabel `buku`
--
ALTER TABLE `buku`
  MODIFY `id_buku` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT untuk tabel `denda`
--
ALTER TABLE `denda`
  MODIFY `id_denda` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `kategori`
--
ALTER TABLE `kategori`
  MODIFY `id_kategori` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT untuk tabel `pengguna`
--
ALTER TABLE `pengguna`
  MODIFY `id_pengguna` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT untuk tabel `permintaan_pinjam`
--
ALTER TABLE `permintaan_pinjam`
  MODIFY `id_permintaan` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT untuk tabel `transaksi`
--
ALTER TABLE `transaksi`
  MODIFY `id_transaksi` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT untuk tabel `ulasan_buku`
--
ALTER TABLE `ulasan_buku`
  MODIFY `id_ulasan` int(11) NOT NULL AUTO_INCREMENT;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `buku`
--
ALTER TABLE `buku`
  ADD CONSTRAINT `buku_ibfk_1` FOREIGN KEY (`id_kategori`) REFERENCES `kategori` (`id_kategori`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `denda`
--
ALTER TABLE `denda`
  ADD CONSTRAINT `denda_ibfk_1` FOREIGN KEY (`id_transaksi`) REFERENCES `transaksi` (`id_transaksi`) ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `permintaan_pinjam`
--
ALTER TABLE `permintaan_pinjam`
  ADD CONSTRAINT `permintaan_pinjam_ibfk_1` FOREIGN KEY (`id_anggota`) REFERENCES `anggota` (`id_anggota`) ON DELETE CASCADE,
  ADD CONSTRAINT `permintaan_pinjam_ibfk_2` FOREIGN KEY (`id_buku`) REFERENCES `buku` (`id_buku`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `transaksi`
--
ALTER TABLE `transaksi`
  ADD CONSTRAINT `transaksi_ibfk_1` FOREIGN KEY (`id_anggota`) REFERENCES `anggota` (`id_anggota`) ON UPDATE CASCADE,
  ADD CONSTRAINT `transaksi_ibfk_2` FOREIGN KEY (`id_buku`) REFERENCES `buku` (`id_buku`) ON UPDATE CASCADE,
  ADD CONSTRAINT `transaksi_ibfk_3` FOREIGN KEY (`id_petugas`) REFERENCES `pengguna` (`id_pengguna`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `ulasan_buku`
--
ALTER TABLE `ulasan_buku`
  ADD CONSTRAINT `ulasan_ibfk_1` FOREIGN KEY (`id_anggota`) REFERENCES `anggota` (`id_anggota`) ON UPDATE CASCADE,
  ADD CONSTRAINT `ulasan_ibfk_2` FOREIGN KEY (`id_buku`) REFERENCES `buku` (`id_buku`) ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
