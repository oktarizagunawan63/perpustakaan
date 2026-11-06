-- Skema + data contoh Perpustakaan
-- Import lewat phpMyAdmin (tab Import) atau: mysql -u root < database.sql
-- Data buku diambil dari halaman judul, hak cipta, sampul, dan daftar isi tiap PDF di folder berkas/

SET NAMES utf8mb4;
CREATE DATABASE IF NOT EXISTS db_perpustakaan CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE db_perpustakaan;

DROP TABLE IF EXISTS pemberitahuan;
DROP TABLE IF EXISTS usulan;
DROP TABLE IF EXISTS favorit;
DROP TABLE IF EXISTS antrean;
DROP TABLE IF EXISTS peminjaman;
DROP TABLE IF EXISTS buku;
DROP TABLE IF EXISTS kategori;
DROP TABLE IF EXISTS users;

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  no_anggota VARCHAR(20) NOT NULL UNIQUE,
  nama VARCHAR(100) NOT NULL,
  email VARCHAR(120) NOT NULL UNIQUE,
  telepon VARCHAR(20) NULL,
  password VARCHAR(255) NOT NULL,
  role ENUM('admin','anggota') NOT NULL DEFAULT 'anggota',
  aktif TINYINT(1) NOT NULL DEFAULT 1,
  dibuat TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Kategori = subkelas Dewey Decimal Classification 615 (Farmakologi & terapeutik)
CREATE TABLE kategori (
  id INT AUTO_INCREMENT PRIMARY KEY,
  kode VARCHAR(10) NOT NULL UNIQUE,
  nama VARCHAR(80) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE buku (
  id INT AUTO_INCREMENT PRIMARY KEY,
  judul VARCHAR(200) NOT NULL,
  penulis VARCHAR(150) NOT NULL,
  penerbit VARCHAR(120) NOT NULL,
  tahun SMALLINT NULL,
  isbn VARCHAR(20) NULL,
  kategori_id INT NOT NULL,
  no_panggil VARCHAR(40) NOT NULL,
  rak VARCHAR(20) NOT NULL,
  halaman SMALLINT NULL,
  bahasa VARCHAR(30) NOT NULL DEFAULT 'Indonesia',
  stok TINYINT UNSIGNED NOT NULL DEFAULT 1,
  sinopsis TEXT NULL,
  daftar_isi TEXT NULL,          -- satu bagian per baris
  file_pdf VARCHAR(150) NULL,    -- nama file di folder berkas/ (isi buku untuk peminjam)
  sampul VARCHAR(150) NULL,      -- nama file di assets/sampul/
  dibuat TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (kategori_id) REFERENCES kategori(id)
) ENGINE=InnoDB;

CREATE TABLE peminjaman (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  buku_id INT NOT NULL,
  format ENUM('fisik','ebook') NOT NULL DEFAULT 'fisik', -- buku fisik (stok terbatas) atau e-book (dibaca online)
  tgl_pinjam DATE NOT NULL,                          -- tanggal dipinjam/dipesan
  tgl_setuju DATE NULL,                              -- petugas menyetujui permintaan buku fisik (NULL = menunggu persetujuan)
  tgl_ambil DATE NULL,                               -- buku fisik diambil di meja sirkulasi (NULL = belum diambil)
  jatuh_tempo DATE NOT NULL,
  tgl_ajuan DATE NULL,                               -- buku fisik diserahkan anggota, menunggu dicek petugas
  tgl_kembali DATE NULL,                             -- pengembalian selesai (disetujui petugas)
  durasi TINYINT NOT NULL DEFAULT 7,                 -- lama pinjam yang dipilih (hari); juga dipakai saat perpanjang
  perpanjang TINYINT NOT NULL DEFAULT 0,
  denda INT NOT NULL DEFAULT 0,                      -- denda keterlambatan
  kondisi ENUM('baik','rusak','hilang') NULL,        -- kondisi buku saat dikembalikan
  denda_kondisi INT NOT NULL DEFAULT 0,              -- denda buku rusak/hilang
  denda_lunas DATE NULL,                             -- tanggal denda dibayar
  FOREIGN KEY (user_id) REFERENCES users(id),
  FOREIGN KEY (buku_id) REFERENCES buku(id),
  KEY idx_aktif (tgl_kembali, jatuh_tempo)
) ENGINE=InnoDB;

-- Antrean buku fisik yang sedang habis. tgl_tersedia terisi = giliran anggota itu, eksemplar disimpan untuknya
CREATE TABLE antrean (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  buku_id INT NOT NULL,
  dibuat DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  tgl_tersedia DATE NULL,
  UNIQUE KEY satu_antrean (user_id, buku_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (buku_id) REFERENCES buku(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE favorit (
  user_id INT NOT NULL,
  buku_id INT NOT NULL,
  dibuat TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, buku_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (buku_id) REFERENCES buku(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Pesan untuk anggota (ditolak, dibatalkan, batal otomatis); dibaca NULL = belum ditutup, setelah itu pindah ke riwayat
CREATE TABLE pemberitahuan (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  pesan VARCHAR(500) NOT NULL,
  dibuat TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  dibaca DATETIME NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Usulan buku baru dari anggota
CREATE TABLE usulan (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  judul VARCHAR(200) NOT NULL,
  penulis VARCHAR(150) NULL,
  alasan TEXT NULL,
  status ENUM('baru','diproses','ditambahkan','ditolak') NOT NULL DEFAULT 'baru',
  catatan VARCHAR(255) NULL,
  dibuat TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Akun demo — admin: admin@perpus.test / admin123 · anggota: rina@perpus.test / anggota123
INSERT INTO users (no_anggota, nama, email, telepon, password, role) VALUES
('PUS-0001', 'Petugas Sirkulasi', 'admin@perpus.test', '081200000001', '$2y$10$0hpxxBDeNsa0rbkFfyWfAeUQeJOigsLUxt4/JJmHC3zGGsE0hjo0i', 'admin'),
('AGT-24001', 'Rina Maharani', 'rina@perpus.test', '081200000002', '$2y$10$uHstLeyF1aVUrwNmeom1ruH1exgZxfg81y6zh9uTdxgean458dqmK', 'anggota'),
('AGT-24002', 'Dimas Prasetyo', 'dimas@perpus.test', '081200000003', '$2y$10$uHstLeyF1aVUrwNmeom1ruH1exgZxfg81y6zh9uTdxgean458dqmK', 'anggota'),
('AGT-24003', 'Salsabila Putri', 'salsa@perpus.test', '081200000004', '$2y$10$uHstLeyF1aVUrwNmeom1ruH1exgZxfg81y6zh9uTdxgean458dqmK', 'anggota');

INSERT INTO kategori (kode, nama) VALUES
('615.1',  'Farmakope & Standar Mutu'),
('615.32', 'Farmakognosi & Bahan Alam'),
('615.5',  'Terapi & Dosis Klinis'),
('615.7',  'Farmakologi'),
('613',    'Edukasi Kesehatan');

INSERT INTO buku (judul, penulis, penerbit, tahun, isbn, kategori_id, no_panggil, rak, halaman, bahasa, stok, sinopsis, daftar_isi, file_pdf, sampul) VALUES
('Farmakologi dan Terapi, Edisi 4', 'Sulistia G. Ganiswarna (ed.)', 'Bagian Farmakologi FKUI', 1995, '9794961124', 4, '615.7 GAN f', 'F-02', 1146, 'Indonesia', 3,
 'Buku ajar farmakologi berbahasa Indonesia susunan staf Bagian Farmakologi Fakultas Kedokteran Universitas Indonesia, disunting oleh Sulistia G. Ganiswarna bersama Rianto Setiabudy, Frans D. Suyatna, Purwantyastuti, dan Nafrialdi. Edisi keempat ini direvisi mengikuti perkembangan ilmu dan disusun dalam 17 seksi berisi 56 bab, dari pengantar farmakologi sampai interaksi obat dan farmakokinetik klinik. Ditujukan bagi mahasiswa kedokteran, farmasi, dan kedokteran gigi, serta tenaga kesehatan.',
 'Seksi I · Pengantar Farmakologi\nSeksi II · Obat Otonom\nSeksi III · Obat Susunan Saraf Pusat\nSeksi IV · Anestetik Lokal\nSeksi V · Autakoid dan Antagonis\nSeksi VI · Obat Kardiovaskular\nSeksi VII · Obat yang Mempengaruhi Air dan Elektrolit\nSeksi VIII · Oksitosik\nSeksi IX · Hormon dan Antagonis\nSeksi X · Obat Lokal\nSeksi XI · Kemoterapi Parasit\nSeksi XII · Antimikroba\nSeksi XIII · Antikanker dan Imunosupresan\nSeksi XIV · Vitamin\nSeksi XV · Obat Hematologik\nSeksi XVI · Toksikologi\nSeksi XVII · Adendum (Interaksi Obat, Farmakokinetik Klinik)',
 'farmakologi-dan-terapi-edisi-4.pdf', 'farmakologi-dan-terapi-edisi-4.jpg'),

('Basic & Clinical Pharmacology, 14th Edition', 'Bertram G. Katzung (ed.)', 'McGraw-Hill Education', 2018, '9781259641152', 4, '615.7 KAT b', 'F-02', 1264, 'Inggris', 3,
 'Buku teks farmakologi seri LANGE yang banyak dipakai sebagai rujukan di pendidikan kedokteran dan farmasi. Edisi ke-14 dibuka dengan prinsip dasar — sifat obat, reseptor dan farmakodinamik, farmakokinetik, biotransformasi, farmakogenomik — lalu membahas golongan obat per sistem tubuh, kemoterapi, toksikologi, serta farmakologi khusus pada anak dan lansia.',
 'Section I · Basic Principles\nSection II · Autonomic Drugs\nSection III · Cardiovascular-Renal Drugs\nSection IV · Drugs With Important Actions on Smooth Muscle\nSection V · Drugs That Act in the Central Nervous System\nSection VI · Drugs Used to Treat Diseases of the Blood, Inflammation, & Gout\nSection VII · Endocrine Drugs\nSection VIII · Chemotherapeutic Drugs\nSection IX · Toxicology\nSection X · Special Topics',
 'basic-clinical-pharmacology-14.pdf', 'basic-clinical-pharmacology-14.jpg'),

('Buku Ajar Farmakologi', 'Siti Maemonah, dkk.', 'Zahir Publishing', 2022, '9786234661002', 4, '615.7 MAE b', 'F-02', 270, 'Indonesia', 3,
 'Buku ajar farmakologi yang ditulis sebelas penulis dari bidang keperawatan dan farmasi. Isinya 11 bab, dari konsep dasar farmakologi, farmakokinetik, dan farmakodinamika sampai prinsip pemberian obat yang benar serta peran perawat dalam pemberian obat. Setiap bab memuat tujuan pembelajaran, materi, tugas, referensi, dan glosarium.',
 'Bab 1 · Konsep Dasar Farmakologi\nBab 2 · Farmakokinetik\nBab 3 · Farmakodinamika\nBab 4 · Farmakoterapi\nBab 5 · Toksikologi\nBab 6 · Peran Obat\nBab 7 · Penggolongan Jenis Obat\nBab 8 · Prinsip Pemberian Obat dengan Benar\nBab 9 · Bentuk Sediaan Obat\nBab 10 · Peran Perawat dalam Pemberian Obat\nBab 11 · Cara Pemberian Obat',
 'buku-ajar-farmakologi.pdf', 'buku-ajar-farmakologi.jpg'),

('Farmakope Indonesia, Edisi V', 'Kementerian Kesehatan RI', 'Kementerian Kesehatan RI', 2014, NULL, 1, '615.1 Ind f', 'F-01', 1102, 'Indonesia', 3,
 'Farmakope nasional yang menjadi acuan standar mutu bahan baku dan sediaan obat di Indonesia. Edisi V disusun sebagai revisi Farmakope Indonesia Edisi IV beserta Suplemen I, II, dan III agar sesuai perkembangan ilmu kefarmasian. Memuat ketentuan umum, sediaan umum, monografi, penjelasan, pereaksi dan larutan, serta tabel acuan.',
 'Sejarah Farmakope Indonesia\nKetentuan Umum\nSediaan Umum\nMonografi\nPenjelasan\nPereaksi, Indikator dan Larutan\nDaftar Tabel (Alkoholmetrik, Bobot Molekul, Larutan Isotonik, Kesetaraan Termometrik)\nIndeks',
 'farmakope-indonesia-edisi-5.pdf', 'farmakope-indonesia-edisi-5.jpg'),

('Suplemen I Farmakope Herbal Indonesia, Edisi II', 'Kementerian Kesehatan RI', 'Kementerian Kesehatan RI', 2022, '9786023013802', 1, '615.1 Ind f', 'F-01', 298, 'Indonesia', 3,
 'Buku standar mutu bahan baku obat tradisional yang melengkapi Farmakope Herbal Indonesia Edisi II, diterbitkan Direktorat Jenderal Kefarmasian dan Alat Kesehatan. Berisi 110 monografi simplisia dan ekstrak dari 55 tumbuhan baru, dengan persyaratan organoleptik, makroskopis, mikroskopis, dan kandungan kimia, serta lampiran metode analisis.',
 'Sejarah\nDaftar Monografi\nDaftar Lampiran\nKeputusan Menteri Kesehatan tentang Tim Penyusun\nKeputusan Menteri Kesehatan tentang Suplemen I FHI Edisi II\nKetentuan Umum\nMonografi\nLampiran',
 'suplemen-1-farmakope-herbal-indonesia-edisi-2.pdf', 'suplemen-1-farmakope-herbal-indonesia-edisi-2.jpg'),

('Farmakognosi dan Fitokimia', 'Lully Hanni Endarini', 'Pusdik SDM Kesehatan, Kementerian Kesehatan RI', 2016, NULL, 2, '615.32 END f', 'F-03', 221, 'Indonesia', 3,
 'Modul bahan ajar cetak farmasi terbitan Pusat Pendidikan SDM Kesehatan, Kementerian Kesehatan RI. Bagian pertama membahas simplisia dan kandungannya — karbohidrat, lipid, glikosida, minyak atsiri, dan alkaloida. Bagian kedua masuk ke fitokimia: skrining, ekstraksi, pemisahan, pemurnian, hingga identifikasi senyawa. Tiap topik dilengkapi latihan, ringkasan, dan tes formatif.',
 'Bab I · Pengantar Farmakognosi\nBab II · Karbohidrat\nBab III · Lipid\nBab IV · Glikosida\nBab V · Minyak Atsiri\nBab VI · Alkaloida\nBab VII · Pengantar Fitokimia\nBab VIII · Skrining Fitokimia\nBab IX · Ekstraksi\nBab X · Pemisahan\nBab XI · Pemurnian\nBab XII · Identifikasi Senyawa',
 'farmakognosi-dan-fitokimia.pdf', 'farmakognosi-dan-fitokimia.jpg'),

('Buku Saku Dosis Obat Pediatri', 'Ikatan Dokter Anak Indonesia', 'Ikatan Dokter Anak Indonesia', 2016, NULL, 3, '615.5 IKA b', 'F-04', 92, 'Indonesia', 3,
 'Panduan saku dosis obat anak yang disusun Satuan Tugas Farmasi Pediatri IDAI berdasarkan formularium pediatri dan masukan unit kerja koordinasi IDAI. Memuat obat yang sering dipakai dokter anak, dari antimikroba dan kemoterapi sampai obat gawat darurat. Disusun untuk tenaga medis dan tidak menggantikan konsultasi dengan dokter atau apoteker.',
 'Tabel Antimikroba\nDosis Obat Perinatologi\nDaftar Dosis Obat\nObat-obatan dan Dosis Penyesuaian pada Penyakit Ginjal',
 'buku-saku-dosis-obat-pediatri.pdf', 'buku-saku-dosis-obat-pediatri.jpg'),

('Edukasi Golongan Obat Bebas dan Obat Bebas Terbatas pada Siswa Kelas 12 di Pandeglang Banten', 'Candra Junaedi, dkk.', 'Jurnal Pengabmas Nusantara, Vol. 4 No. 3', 2022, NULL, 5, '613 JUN e', 'F-05', 7, 'Indonesia', 3,
 'Artikel pengabdian masyarakat oleh Candra Junaedi, Ucu Mawardi, dan Dimas Danang Indratmoko (Universitas Mathlaul Anwar Banten). Tim memperkenalkan golongan obat bebas dan obat bebas terbatas kepada siswa kelas 12 SMA Mathlaul Anwar Pandeglang memakai leaflet, lalu mengukur pemahaman lewat tanya jawab serta pre-test dan post-test. Hasilnya, pengetahuan siswa tentang swamedikasi meningkat, termasuk indikasi, dosis, cara pakai, efek samping, kontraindikasi, dan tanggal kedaluwarsa obat. Terbit di Jurnal Pengabdian Masyarakat Nusantara Vol. 4 No. 3 (September 2022), hal. 153–159, DOI 10.57214/pengabmas.v4i3.502, akses terbuka (CC BY-SA).',
 'Abstrak\nPendahuluan\nMetode\nHasil\nKesimpulan\nUcapan Terima Kasih\nDaftar Referensi',
 'edukasi-obat-bebas-pengabmas-2022.pdf', 'edukasi-obat-bebas-pengabmas-2022.jpg');

-- Peminjaman contoh (tanggal relatif terhadap hari import supaya selalu ada kasus aktif, hampir jatuh tempo, dan terlambat)
-- Contoh: Rina meminjam 2 e-book + memesan 1 buku fisik (siap diambil); Dimas buku fisik terlambat + denda rusak belum lunas;
-- Salsa sudah menyerahkan buku fisik dan menunggu dicek petugas
INSERT INTO peminjaman (user_id, buku_id, format, tgl_pinjam, tgl_ambil, jatuh_tempo, tgl_ajuan, tgl_kembali, durasi, perpanjang, denda, kondisi, denda_kondisi, denda_lunas) VALUES
(2, 3, 'ebook', CURDATE() - INTERVAL 3 DAY,  CURDATE() - INTERVAL 3 DAY,  CURDATE() + INTERVAL 4 DAY,  NULL, NULL, 7, 0, 0, NULL, 0, NULL),
(2, 1, 'ebook', CURDATE() - INTERVAL 13 DAY, CURDATE() - INTERVAL 13 DAY, CURDATE() + INTERVAL 1 DAY,  NULL, NULL, 14, 0, 0, NULL, 0, NULL),
(3, 2, 'fisik', CURDATE() - INTERVAL 7 DAY,  CURDATE() - INTERVAL 7 DAY,  CURDATE() - INTERVAL 4 DAY,  NULL, NULL, 3, 0, 0, NULL, 0, NULL),
(4, 6, 'fisik', CURDATE() - INTERVAL 5 DAY,  CURDATE() - INTERVAL 5 DAY,  CURDATE() + INTERVAL 2 DAY,  CURDATE(), NULL, 7, 0, 0, NULL, 0, NULL),
(2, 4, 'fisik', CURDATE() - INTERVAL 30 DAY, CURDATE() - INTERVAL 30 DAY, CURDATE() - INTERVAL 16 DAY, CURDATE() - INTERVAL 17 DAY, CURDATE() - INTERVAL 17 DAY, 14, 0, 0, 'baik', 0, NULL),
(2, 7, 'fisik', CURDATE() - INTERVAL 20 DAY, CURDATE() - INTERVAL 20 DAY, CURDATE() - INTERVAL 13 DAY, CURDATE() - INTERVAL 11 DAY, CURDATE() - INTERVAL 11 DAY, 7, 0, 2000, 'baik', 0, CURDATE() - INTERVAL 11 DAY),
(3, 5, 'fisik', CURDATE() - INTERVAL 15 DAY, CURDATE() - INTERVAL 15 DAY, CURDATE() - INTERVAL 8 DAY,  CURDATE() - INTERVAL 9 DAY, CURDATE() - INTERVAL 9 DAY, 7, 0, 0, 'rusak', 50000, NULL),
(2, 8, 'fisik', CURDATE(),                   NULL,                        CURDATE() + INTERVAL 7 DAY,  NULL, NULL, 7, 0, 0, NULL, 0, NULL);

UPDATE peminjaman SET tgl_setuju = tgl_pinjam WHERE tgl_ambil IS NOT NULL;

INSERT INTO favorit (user_id, buku_id) VALUES (2, 2), (2, 6);

INSERT INTO usulan (user_id, judul, penulis, alasan, status, catatan) VALUES
(2, 'Informasi Spesialite Obat (ISO) Indonesia', 'Ikatan Apoteker Indonesia', 'Sering dipakai untuk tugas mata kuliah farmakoterapi.', 'baru', NULL);
