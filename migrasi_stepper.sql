-- Migrasi: tambah kolom status eksplisit dan kolom pendukung alur stepper buku fisik
-- Jalankan sekali: mysql -u root db_perpustakaan < migrasi_stepper.sql

-- 1. Tambah kolom status ENUM
ALTER TABLE peminjaman
  ADD COLUMN status ENUM('menunggu','disetujui','dipinjam','ditolak','kedaluwarsa','dibatalkan','dikembalikan')
  NOT NULL DEFAULT 'menunggu' AFTER format;

-- 2. Tambah kolom waktu persetujuan, batas ambil, waktu pengambilan, dan alasan tolak
ALTER TABLE peminjaman
  ADD COLUMN disetujui_at DATETIME NULL AFTER status,
  ADD COLUMN batas_ambil DATETIME NULL AFTER disetujui_at,
  ADD COLUMN diambil_at DATETIME NULL AFTER batas_ambil,
  ADD COLUMN alasan_tolak VARCHAR(255) NULL AFTER diambil_at;

-- 3. Backfill status dari data tanggal yang sudah ada
--    dikembalikan: tgl_kembali terisi
UPDATE peminjaman SET status = 'dikembalikan' WHERE tgl_kembali IS NOT NULL;

--    dipinjam: sudah diambil tapi belum dikembalikan
UPDATE peminjaman SET status = 'dipinjam',
  diambil_at = CONCAT(tgl_ambil, ' 00:00:00')
WHERE tgl_ambil IS NOT NULL AND tgl_kembali IS NULL;

--    disetujui: sudah disetujui tapi belum diambil
UPDATE peminjaman SET status = 'disetujui',
  disetujui_at = CONCAT(tgl_setuju, ' 00:00:00'),
  batas_ambil  = DATE_ADD(CONCAT(tgl_setuju, ' 00:00:00'), INTERVAL 3 DAY)
WHERE tgl_setuju IS NOT NULL AND tgl_ambil IS NULL AND tgl_kembali IS NULL;

--    menunggu: belum disetujui (buku fisik)
UPDATE peminjaman SET status = 'menunggu'
WHERE tgl_setuju IS NULL AND tgl_ambil IS NULL AND tgl_kembali IS NULL AND format = 'fisik';

--    ebook langsung aktif, set status dipinjam
UPDATE peminjaman SET status = 'dipinjam'
WHERE format = 'ebook' AND tgl_kembali IS NULL;
UPDATE peminjaman SET status = 'dikembalikan'
WHERE format = 'ebook' AND tgl_kembali IS NOT NULL;

-- 4. Backfill diambil_at dan disetujui_at untuk data lama yang sudah dikembalikan
UPDATE peminjaman SET diambil_at = CONCAT(tgl_ambil, ' 00:00:00')
WHERE tgl_ambil IS NOT NULL AND diambil_at IS NULL;
UPDATE peminjaman SET disetujui_at = CONCAT(tgl_setuju, ' 00:00:00')
WHERE tgl_setuju IS NOT NULL AND disetujui_at IS NULL;
