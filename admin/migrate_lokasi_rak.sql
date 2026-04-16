-- Tambahkan kolom lokasi_rak agar detail buku lengkap.
-- Jalankan sekali di database Cozy-Library.

ALTER TABLE buku
ADD COLUMN lokasi_rak VARCHAR(120) DEFAULT NULL AFTER stok;
