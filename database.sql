-- Skema + data contoh Perpustakaan
-- Import lewat phpMyAdmin (tab Import) atau: mysql -u root < database.sql

SET NAMES utf8mb4;
CREATE DATABASE IF NOT EXISTS db_perpustakaan CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE db_perpustakaan;

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

-- Kategori mengikuti kelas utama Dewey Decimal Classification (DDC)
CREATE TABLE kategori (
  id INT AUTO_INCREMENT PRIMARY KEY,
  kode CHAR(3) NOT NULL UNIQUE,
  nama VARCHAR(80) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE buku (
  id INT AUTO_INCREMENT PRIMARY KEY,
  judul VARCHAR(200) NOT NULL,
  penulis VARCHAR(150) NOT NULL,
  penerbit VARCHAR(120) NOT NULL,
  tahun SMALLINT NOT NULL,
  isbn VARCHAR(20) NULL,
  kategori_id INT NOT NULL,
  no_panggil VARCHAR(40) NOT NULL,
  rak VARCHAR(20) NOT NULL,
  halaman SMALLINT NULL,
  bahasa VARCHAR(30) NOT NULL DEFAULT 'Indonesia',
  stok TINYINT UNSIGNED NOT NULL DEFAULT 1,
  sinopsis TEXT NULL,
  dibuat TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (kategori_id) REFERENCES kategori(id)
) ENGINE=InnoDB;

CREATE TABLE peminjaman (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  buku_id INT NOT NULL,
  tgl_pinjam DATE NOT NULL,
  jatuh_tempo DATE NOT NULL,
  tgl_kembali DATE NULL,
  perpanjang TINYINT NOT NULL DEFAULT 0,
  denda INT NOT NULL DEFAULT 0,
  FOREIGN KEY (user_id) REFERENCES users(id),
  FOREIGN KEY (buku_id) REFERENCES buku(id),
  KEY idx_aktif (tgl_kembali, jatuh_tempo)
) ENGINE=InnoDB;

-- Akun demo — admin: admin@perpus.test / admin123 · anggota: rina@perpus.test / anggota123
INSERT INTO users (no_anggota, nama, email, telepon, password, role) VALUES
('PUS-0001', 'Petugas Sirkulasi', 'admin@perpus.test', '081200000001', '$2y$10$0hpxxBDeNsa0rbkFfyWfAeUQeJOigsLUxt4/JJmHC3zGGsE0hjo0i', 'admin'),
('AGT-24001', 'Rina Maharani', 'rina@perpus.test', '081200000002', '$2y$10$uHstLeyF1aVUrwNmeom1ruH1exgZxfg81y6zh9uTdxgean458dqmK', 'anggota'),
('AGT-24002', 'Dimas Prasetyo', 'dimas@perpus.test', '081200000003', '$2y$10$uHstLeyF1aVUrwNmeom1ruH1exgZxfg81y6zh9uTdxgean458dqmK', 'anggota'),
('AGT-24003', 'Salsabila Putri', 'salsa@perpus.test', '081200000004', '$2y$10$uHstLeyF1aVUrwNmeom1ruH1exgZxfg81y6zh9uTdxgean458dqmK', 'anggota');

INSERT INTO kategori (kode, nama) VALUES
('000', 'Komputer & Karya Umum'),
('100', 'Filsafat & Psikologi'),
('200', 'Agama'),
('300', 'Ilmu Sosial'),
('400', 'Bahasa'),
('500', 'Sains & Matematika'),
('600', 'Teknologi Terapan'),
('700', 'Seni & Rekreasi'),
('800', 'Sastra'),
('900', 'Sejarah & Geografi');

INSERT INTO buku (judul, penulis, penerbit, tahun, isbn, kategori_id, no_panggil, rak, halaman, bahasa, stok, sinopsis) VALUES
('Laskar Pelangi', 'Andrea Hirata', 'Bentang Pustaka', 2005, '9789793062792', 9, '899.221 3 HIR l', 'C-04', 529, 'Indonesia', 4, 'Sepuluh anak dari keluarga miskin di Belitung bersekolah di sebuah SD Muhammadiyah yang nyaris ditutup. Kisah tentang guru yang tak menyerah dan mimpi yang tumbuh dari keterbatasan.'),
('Bumi Manusia', 'Pramoedya Ananta Toer', 'Lentera Dipantara', 2005, '9789799731234', 9, '899.221 3 TOE b', 'C-05', 535, 'Indonesia', 3, 'Minke, pemuda pribumi terpelajar di akhir abad ke-19, berhadapan dengan hukum kolonial yang tidak pernah berpihak padanya. Jilid pertama Tetralogi Buru.'),
('Laut Bercerita', 'Leila S. Chudori', 'Kepustakaan Populer Gramedia', 2017, '9786024246945', 9, '899.221 3 CHU l', 'C-02', 379, 'Indonesia', 3, 'Biru Laut dan kawan-kawannya, aktivis mahasiswa akhir 1990-an, diculik dan tak semuanya kembali. Diceritakan dari dua sisi: yang hilang dan keluarga yang menunggu.'),
('Cantik Itu Luka', 'Eka Kurniawan', 'Gramedia Pustaka Utama', 2015, '9786020366517', 9, '899.221 3 KUR c', 'C-03', 505, 'Indonesia', 2, 'Saga keluarga Dewi Ayu di kota fiktif Halimunda, merentang dari masa kolonial hingga pasca-1965, ditulis dengan nada realisme magis yang getir dan jenaka.'),
('Ronggeng Dukuh Paruk', 'Ahmad Tohari', 'Gramedia Pustaka Utama', 2003, '9789792200676', 9, '899.221 3 TOH r', 'C-05', 408, 'Indonesia', 2, 'Srintil menjadi ronggeng kebanggaan dukuh kecil yang miskin, sementara Rasus, teman masa kecilnya, memilih pergi. Pedesaan Jawa di tengah pergolakan 1965.'),
('Negeri 5 Menara', 'Ahmad Fuadi', 'Gramedia Pustaka Utama', 2009, '9789792248616', 9, '899.221 3 FUA n', 'C-03', 423, 'Indonesia', 3, 'Alif dari Maninjau masuk pondok pesantren di Jawa Timur dan bersama lima sahabatnya belajar bahwa kesungguhan bisa mengantar ke tempat yang dulu cuma ada di angan.'),
('Perahu Kertas', 'Dee Lestari', 'Bentang Pustaka', 2009, '9789791227780', 9, '899.221 3 LES p', 'C-04', 444, 'Indonesia', 2, 'Kugy yang ingin jadi penulis dongeng dan Keenan yang ingin jadi pelukis menempuh jalan berliku antara pilihan hidup yang realistis dan yang sungguh mereka cintai.'),
('Filosofi Teras', 'Henry Manampiring', 'Penerbit Buku Kompas', 2018, '9786024125189', 2, '188 MAN f', 'A-07', 346, 'Indonesia', 3, 'Pengantar filsafat Stoa untuk pembaca masa kini: memilah apa yang bisa dan tidak bisa dikendalikan, lalu menerapkannya untuk menghadapi kecemasan sehari-hari.'),
('Thinking, Fast and Slow', 'Daniel Kahneman', 'Farrar, Straus and Giroux', 2011, '9780374275631', 2, '153.42 KAH t', 'A-06', 499, 'Inggris', 1, 'Dua sistem berpikir manusia — yang cepat dan intuitif, serta yang lambat dan penuh pertimbangan — beserta bias yang muncul ketika keduanya salah bekerja.'),
('Atomic Habits', 'James Clear', 'Avery', 2018, '9780735211292', 2, '158.1 CLE a', 'A-06', 320, 'Inggris', 2, 'Kerangka praktis membangun kebiasaan baik dan memutus kebiasaan buruk lewat perubahan kecil yang konsisten dan sistem yang dirancang dengan sadar.'),
('Sapiens: A Brief History of Humankind', 'Yuval Noah Harari', 'Harper', 2015, '9780062316097', 10, '909 HAR s', 'D-01', 443, 'Inggris', 2, 'Bagaimana spesies kita berpindah dari pemburu-peramu menjadi penguasa planet: revolusi kognitif, pertanian, penyatuan umat manusia, dan revolusi sains.'),
('Sejarah Indonesia Modern 1200–2008', 'M.C. Ricklefs', 'Serambi Ilmu Semesta', 2008, '9789790241282', 10, '959.8 RIC s', 'D-02', 822, 'Indonesia', 2, 'Rujukan klasik sejarah Nusantara dari kedatangan Islam, era VOC dan kolonial, hingga Reformasi. Padat, rinci, dan banyak dipakai di perkuliahan.'),
('Clean Code', 'Robert C. Martin', 'Prentice Hall', 2008, '9780132350884', 1, '005.1 MAR c', 'B-01', 431, 'Inggris', 3, 'Prinsip, pola, dan praktik menulis kode yang mudah dibaca dan dirawat, lengkap dengan studi kasus refaktor kode nyata langkah demi langkah.'),
('The Pragmatic Programmer', 'David Thomas, Andrew Hunt', 'Addison-Wesley', 2019, '9780135957059', 1, '005.1 THO p', 'B-01', 352, 'Inggris', 2, 'Kumpulan nasihat tentang kerja sebagai pengembang perangkat lunak: dari tanggung jawab pribadi, desain yang lentur, sampai cara memperkirakan pekerjaan.'),
('Introduction to Algorithms', 'Thomas H. Cormen, dkk.', 'MIT Press', 2009, '9780262033848', 1, '005.1 COR i', 'B-02', 1312, 'Inggris', 2, 'Buku teks algoritma yang menjadi standar di banyak kampus: pengurutan, struktur data, graf, pemrograman dinamis, hingga NP-completeness.'),
('Database System Concepts', 'Abraham Silberschatz, dkk.', 'McGraw-Hill', 2019, '9780078022159', 1, '005.74 SIL d', 'B-02', 1376, 'Inggris', 2, 'Konsep dasar sistem basis data: model relasional, SQL, desain skema, indeks, transaksi, dan arsitektur penyimpanan.'),
('Don''t Make Me Think, Revisited', 'Steve Krug', 'New Riders', 2014, '9780321965516', 1, '006.7 KRU d', 'B-03', 216, 'Inggris', 2, 'Panduan singkat usability web dan mobile: bagaimana orang sebenarnya memakai situs, dan cara menguji desain dengan murah dan cepat.'),
('The Design of Everyday Things', 'Don Norman', 'Basic Books', 2013, '9780465050659', 8, '745.2 NOR d', 'B-05', 368, 'Inggris', 1, 'Mengapa pintu yang membingungkan adalah kesalahan desain, bukan kesalahan pengguna. Dasar-dasar affordance, signifier, umpan balik, dan pemetaan.'),
('A Brief History of Time', 'Stephen Hawking', 'Bantam', 1998, '9780553380163', 6, '523.1 HAW b', 'B-07', 212, 'Inggris', 1, 'Asal-usul alam semesta, lubang hitam, dan sifat waktu dijelaskan untuk pembaca awam tanpa rumus yang menakutkan.'),
('Cosmos', 'Carl Sagan', 'Ballantine Books', 2013, '9780345539434', 6, '520 SAG c', 'B-07', 432, 'Inggris', 1, 'Perjalanan menelusuri sejarah sains dan tempat manusia di alam semesta, ditulis dengan rasa takjub yang menular.'),
('Pengantar Ilmu Ekonomi', 'Sadono Sukirno', 'RajaGrafindo Persada', 2016, '9789794215748', 4, '330 SUK p', 'A-03', 476, 'Indonesia', 3, 'Dasar mikroekonomi dan makroekonomi dengan contoh kasus Indonesia: permintaan dan penawaran, pasar, pendapatan nasional, inflasi, dan kebijakan fiskal.'),
('Metode Penelitian Kuantitatif, Kualitatif, dan R&D', 'Sugiyono', 'Alfabeta', 2019, '9786022891547', 1, '001.42 SUG m', 'A-01', 456, 'Indonesia', 4, 'Pegangan metodologi yang paling banyak dikutip di skripsi Indonesia: perumusan masalah, sampel, instrumen, hingga analisis data.'),
('Tata Bahasa Baku Bahasa Indonesia', 'Hasan Alwi, dkk.', 'Balai Pustaka', 2017, '9789794077032', 5, '499.221 5 ALW t', 'A-04', 498, 'Indonesia', 2, 'Rujukan resmi kaidah bahasa Indonesia: fonologi, morfologi, sintaksis, dan kalimat, disusun oleh Badan Pengembangan dan Pembinaan Bahasa.'),
('Tafsir Al-Mishbah, Jilid 1', 'M. Quraish Shihab', 'Lentera Hati', 2002, '9789799048196', 3, '297.122 7 SHI t', 'A-08', 624, 'Indonesia', 1, 'Tafsir Al-Qur''an yang menekankan keserasian antarayat dan pesan moralnya, ditulis dalam bahasa Indonesia yang lugas.');

-- Peminjaman contoh (tanggal relatif terhadap hari import supaya selalu ada kasus aktif, hampir jatuh tempo, dan terlambat)
INSERT INTO peminjaman (user_id, buku_id, tgl_pinjam, jatuh_tempo, tgl_kembali, perpanjang, denda) VALUES
(2, 1,  CURDATE() - INTERVAL 3 DAY,  CURDATE() + INTERVAL 4 DAY,  NULL, 0, 0),
(2, 13, CURDATE() - INTERVAL 6 DAY,  CURDATE() + INTERVAL 1 DAY,  NULL, 0, 0),
(3, 9,  CURDATE() - INTERVAL 11 DAY, CURDATE() - INTERVAL 4 DAY,  NULL, 0, 0),
(4, 18, CURDATE() - INTERVAL 2 DAY,  CURDATE() + INTERVAL 5 DAY,  NULL, 0, 0),
(2, 8,  CURDATE() - INTERVAL 30 DAY, CURDATE() - INTERVAL 23 DAY, CURDATE() - INTERVAL 24 DAY, 0, 0),
(2, 3,  CURDATE() - INTERVAL 20 DAY, CURDATE() - INTERVAL 13 DAY, CURDATE() - INTERVAL 11 DAY, 0, 2000),
(3, 22, CURDATE() - INTERVAL 15 DAY, CURDATE() - INTERVAL 8 DAY,  CURDATE() - INTERVAL 9 DAY, 0, 0);
