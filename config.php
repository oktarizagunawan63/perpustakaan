<?php
declare(strict_types=1);

session_start();
date_default_timezone_set('Asia/Jakarta');

// --- Sesuaikan dengan server lokal ---
const DB_HOST = '127.0.0.1';
const DB_PORT = 3306;
const DB_NAME = 'db_perpustakaan';
const DB_USER = 'root';
const DB_PASS = '';

// --- Aturan layanan ---
const NAMA_PERPUS     = 'Perpustakaan Kampus';
const PILIHAN_LAMA    = [3, 7, 14]; // pilihan lama pinjam (hari)
const LAMA_PINJAM     = 7;          // pilihan bawaan
const MAKS_PINJAM     = 3;     // buku aktif per anggota
const MAKS_PERPANJANG = 1;
const DENDA_PER_HARI  = 1000;  // rupiah, per buku per hari terlambat
// Denda kondisi buku saat dikembalikan (rupiah). Hilang: atau mengganti dengan buku yang sama.
const DENDA_KONDISI   = ['baik' => 0, 'rusak' => 50000, 'hilang' => 150000];
const BATAS_AMBIL     = 2;     // hari: buku fisik pesanan online harus diambil, lewat itu batal otomatis
const BATAS_GILIRAN   = 2;     // hari: eksemplar disimpan untuk anggota yang mendapat giliran antrean

// Jam layanan per hari (1 = Senin ... 7 = Minggu), null = tutup
const JAM_LAYANAN = [
    1 => ['08:00', '20:00'], 2 => ['08:00', '20:00'], 3 => ['08:00', '20:00'],
    4 => ['08:00', '20:00'], 5 => ['08:00', '16:30'], 6 => ['09:00', '14:00'], 7 => null,
];

// Link relatif agar jalan di subfolder htdocs maupun `php -S`
define('BASE', basename(dirname($_SERVER['SCRIPT_FILENAME'])) === 'admin' ? '..' : '.');

function url(string $path = ''): string { return BASE . '/' . $path; }

function db(): PDO
{
    static $pdo;
    if (!$pdo) {
        try {
            $pdo = new PDO('mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            exit('<p style="font:16px sans-serif;padding:2rem">Database belum tersambung. Jalankan MySQL dan import <code>database.sql</code>, lalu cek pengaturan di <code>config.php</code>.</p>');
        }
        rawat_data();
    }
    return $pdo;
}

/** Perawatan otomatis tiap permintaan: e-book kedaluwarsa, pesanan tak diambil, dan antrean. */
function rawat_data(): void
{
    // E-book berakhir otomatis setelah tanggal jatuh tempo, tanpa denda
    q("UPDATE peminjaman SET tgl_kembali = jatuh_tempo, kondisi = 'baik'
       WHERE format = 'ebook' AND tgl_kembali IS NULL AND jatuh_tempo < CURDATE()");
    // Pesanan buku fisik yang tidak diambil dibatalkan, eksemplarnya kembali tersedia
    q("DELETE FROM peminjaman WHERE format = 'fisik' AND tgl_ambil IS NULL AND tgl_kembali IS NULL
       AND tgl_pinjam < CURDATE() - INTERVAL " . BATAS_AMBIL . " DAY");
    // Giliran antrean yang tidak dipakai hangus
    q('DELETE FROM antrean WHERE tgl_tersedia < CURDATE() - INTERVAL ' . BATAS_GILIRAN . ' DAY');
    // Eksemplar kosong diberikan ke antrean terdepan
    foreach (q('SELECT DISTINCT buku_id FROM antrean WHERE tgl_tersedia IS NULL')->fetchAll(PDO::FETCH_COLUMN) as $bukuId) {
        $kosong = (int) q('SELECT b.stok - ' . SQL_DIPINJAM . ' FROM buku b WHERE b.id = ?', [$bukuId])->fetchColumn();
        if ($kosong > 0) {
            q('UPDATE antrean SET tgl_tersedia = CURDATE() WHERE buku_id = ? AND tgl_tersedia IS NULL ORDER BY dibuat, id LIMIT ' . $kosong, [$bukuId]);
        }
    }
}

function q(string $sql, array $args = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($args);
    return $st;
}

function e(?string $s): string { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }

function redirect(string $path): never { header('Location: ' . url($path)); exit; }

// --- Flash message ---
function flash(string $tipe, string $pesan): void { $_SESSION['flash'][] = [$tipe, $pesan]; }

function flash_tampil(): string
{
    $html = '';
    foreach ($_SESSION['flash'] ?? [] as [$tipe, $pesan]) {
        $html .= '<div class="notice notice--' . e($tipe) . '" role="status">' . e($pesan) . '</div>';
    }
    unset($_SESSION['flash']);
    return $html;
}

// --- CSRF ---
function csrf_field(): string
{
    $_SESSION['csrf'] ??= bin2hex(random_bytes(16));
    return '<input type="hidden" name="csrf" value="' . $_SESSION['csrf'] . '">';
}

function csrf_cek(): void
{
    $token = $_SESSION['csrf'] ?? '';
    // Token kosong tidak boleh lolos (hash_equals('', '') bernilai true)
    if ($token === '' || !hash_equals($token, (string) ($_POST['csrf'] ?? ''))) {
        http_response_code(403);
        exit('Sesi formulir kedaluwarsa. Muat ulang halaman lalu coba lagi.');
    }
}

// --- Autentikasi ---
function user(): ?array { return $_SESSION['user'] ?? null; }

function wajib_login(): array
{
    if (!user()) {
        flash('info', 'Masuk dulu untuk membuka halaman itu.');
        redirect('login.php?lanjut=' . urlencode($_SERVER['REQUEST_URI']));
    }
    // Akun yang dinonaktifkan petugas langsung kehilangan sesi
    if (!q('SELECT aktif FROM users WHERE id = ?', [user()['id']])->fetchColumn()) {
        unset($_SESSION['user']);
        flash('error', 'Akun kamu sedang dinonaktifkan. Hubungi petugas.');
        redirect('login.php');
    }
    return user();
}

function wajib_admin(): array
{
    $u = wajib_login();
    if ($u['role'] !== 'admin') { http_response_code(403); exit('Halaman ini khusus petugas.'); }
    return $u;
}

// --- Format ---
function rupiah(int $n): string { return 'Rp' . number_format($n, 0, ',', '.'); }

function tgl(?string $tanggal, bool $panjang = false): string
{
    if (!$tanggal) return '—';
    $bulan = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    $bulanPanjang = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    $t = strtotime($tanggal);
    return date('j', $t) . ' ' . ($panjang ? $bulanPanjang : $bulan)[date('n', $t) - 1] . ' ' . date('Y', $t);
}

/** Selisih hari kalender: positif = lewat dari $tanggal. */
function selisih_hari(string $tanggal, ?string $terhadap = null): int
{
    return (int) ((strtotime($terhadap ?? date('Y-m-d')) - strtotime($tanggal)) / 86400);
}

function hitung_denda(string $jatuhTempo, ?string $kembali = null): int
{
    return max(0, selisih_hari($jatuhTempo, $kembali)) * DENDA_PER_HARI;
}

/** Status jatuh tempo pinjaman aktif → [kelas css, label]. */
function status_tempo(string $jatuhTempo): array
{
    $lewat = selisih_hari($jatuhTempo);
    if ($lewat > 0)  return ['late', "Terlambat $lewat hari"];
    if ($lewat === 0) return ['soon', 'Kembalikan hari ini'];
    if ($lewat >= -2) return ['soon', 'Kembali dalam ' . -$lewat . ' hari'];
    return ['ok', 'Kembali dalam ' . -$lewat . ' hari'];
}

/** Denda keterlambatan sebuah pinjaman: e-book dan pesanan yang belum diambil tidak didenda; buku fisik dihitung sampai tanggal diserahkan. */
function denda_pinjaman(array $p): int
{
    return $p['format'] === 'ebook' || !$p['tgl_ambil'] ? 0 : hitung_denda($p['jatuh_tempo'], $p['tgl_ajuan'] ?: null);
}

/** Batas terakhir mengambil buku fisik yang dipesan online. */
function batas_ambil(array $p): string
{
    return date('Y-m-d', strtotime($p['tgl_pinjam'] . ' +' . BATAS_AMBIL . ' days'));
}

/** Status pinjaman yang belum selesai → [kelas css, label]: siap diambil, menunggu dicek, atau sisa hari. */
function status_pinjaman(array $p): array
{
    if (!$p['tgl_ambil']) return ['wait', 'Siap diambil sampai ' . tgl(batas_ambil($p))];
    if ($p['tgl_ajuan']) return ['wait', 'Menunggu pengecekan petugas'];
    [$kls, $lbl] = status_tempo($p['jatuh_tempo']);
    if ($p['format'] === 'ebook') $lbl = str_replace(['Kembali dalam', 'Kembalikan hari ini'], ['Akses berakhir dalam', 'Akses berakhir hari ini'], $lbl);
    return [$kls, $lbl];
}

const LABEL_FORMAT = ['fisik' => 'Buku fisik', 'ebook' => 'E-book'];

function status_layanan(): array
{
    $jam = JAM_LAYANAN[(int) date('N')];
    $now = date('H:i');
    if ($jam && $now >= $jam[0] && $now < $jam[1]) return [true, 'Buka sekarang · tutup ' . str_replace(':', '.', $jam[1])];
    if ($jam && $now < $jam[0]) return [false, 'Tutup · buka hari ini ' . str_replace(':', '.', $jam[0])];
    return [false, 'Tutup sekarang'];
}

/** Lambang lingkaran + rak buku; warna ikut `currentColor`. */
function logo(string $kelas = ''): string
{
    return '<svg class="logo ' . $kelas . '" viewBox="0 0 48 48" aria-hidden="true" fill="currentColor">'
        . '<circle cx="24" cy="24" r="22.5" fill="none" stroke="currentColor" stroke-width="1.5"/>'
        . '<rect x="14.5" y="12.5" width="3.4" height="20" rx=".6"/><rect x="20" y="15" width="3.4" height="17.5" rx=".6"/>'
        . '<rect x="25.6" y="14" width="3.4" height="18.5" rx=".6" transform="rotate(-22 27.3 32.5)"/>'
        . '<rect x="13" y="33.6" width="22" height="1.8" rx=".9"/></svg>';
}

/** Sampul asli (assets/sampul/) bila ada; selain itu sampul buatan CSS dengan warna tetap per judul. */
function sampul(array $b, string $ukuran = 'md'): string
{
    if (!empty($b['sampul'])) {
        return '<img class="cover cover--' . $ukuran . ' cover--foto" src="' . url('assets/sampul/' . rawurlencode($b['sampul']))
            . '" alt="" loading="lazy">';
    }
    $warna = ['#0F3D35', '#1E5A4E', '#6E5B45', '#3F5B57', '#8A7A5E', '#2D4A44', '#5E6F64', '#4A3F36', '#26332F', '#7C6A4F'];
    $c = $warna[crc32($b['judul']) % count($warna)];
    $kelas = (string) strtok($b['no_panggil'] ?? '', ' ');
    return '<div class="cover cover--' . $ukuran . '" style="--c:' . $c . '" aria-hidden="true">'
        . '<span class="cover__no">' . e($kelas) . '</span>'
        . '<span class="cover__judul">' . e($b['judul']) . '</span>'
        . '<span class="cover__penulis">' . e($b['penulis']) . '</span></div>';
}

/**
 * Validasi data anggota. $idSendiri diisi saat mengubah profil agar email sendiri tidak dianggap bentrok.
 * @return array{0: array<string,string>, 1: array<string,string>} [data bersih, galat per field]
 */
function validasi_anggota(array $in, int $idSendiri = 0): array
{
    $d = ['nama' => trim($in['nama'] ?? ''), 'email' => strtolower(trim($in['email'] ?? '')), 'telepon' => trim($in['telepon'] ?? '')];
    $galat = [];
    if (mb_strlen($d['nama']) < 3) $galat['nama'] = 'Isi nama lengkap (minimal 3 huruf).';
    if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL)) $galat['email'] = 'Format email belum benar, contoh: nama@kampus.ac.id';
    elseif (q('SELECT 1 FROM users WHERE email = ? AND id <> ?', [$d['email'], $idSendiri])->fetch()) $galat['email'] = $idSendiri ? 'Email ini sudah dipakai akun lain.' : 'Email ini sudah terdaftar. Coba masuk saja.';
    if ($d['telepon'] !== '' && !preg_match('/^\+?[0-9 \-]{8,16}$/', $d['telepon'])) $galat['telepon'] = 'Nomor telepon hanya angka, 8–16 digit.';
    return [$d, $galat];
}

/**
 * Validasi + simpan anggota baru (dipakai form daftar dan panel petugas).
 * @return array{0: ?array, 1: array<string,string>} [akun baru, galat per field]
 */
function daftarkan_anggota(array $in): array
{
    [$d, $galat] = validasi_anggota($in);
    $pw = $in['password'] ?? '';
    if (strlen($pw) < 8) $galat['password'] = 'Kata sandi minimal 8 karakter.';
    if ($galat) return [null, $galat];

    q('INSERT INTO users (no_anggota, nama, email, telepon, password) VALUES (?, ?, ?, ?, ?)',
        [uniqid('tmp'), $d['nama'], $d['email'], $d['telepon'] ?: null, password_hash($pw, PASSWORD_DEFAULT)]);
    $id = (int) db()->lastInsertId();
    q('UPDATE users SET no_anggota = ? WHERE id = ?', [sprintf('AGT-%s%03d', date('y'), $id), $id]);
    return [q('SELECT * FROM users WHERE id = ?', [$id])->fetch(), []];
}

/**
 * Boleh membuka isi buku (PDF)? Petugas selalu boleh; anggota hanya selama meminjam versi e-book-nya.
 * @return ?array pinjaman e-book aktif (anggota), [] untuk petugas, null bila tidak boleh
 */
function akses_isi(?array $u, array $b): ?array
{
    if (!$u || empty($b['file_pdf'])) return null;
    if ($u['role'] === 'admin') return [];
    return q('SELECT * FROM peminjaman WHERE user_id = ? AND buku_id = ? AND format = "ebook" AND tgl_kembali IS NULL', [$u['id'], $b['id']])->fetch() ?: null;
}

/**
 * Catat peminjaman setelah semua aturan dicek. Dipakai halaman pinjam anggota dan sirkulasi petugas.
 * E-book tidak dibatasi stok; buku fisik dibatasi jumlah eksemplar.
 * @return array{0: bool, 1: string} [berhasil, pesan]
 */
function catat_pinjaman(array $agt, int $bukuId, int $hari, string $format, bool $langsungDiambil = false): array
{
    if (!in_array($hari, PILIHAN_LAMA, true)) return [false, 'Pilih lama pinjam yang tersedia.'];
    if (!isset(LABEL_FORMAT[$format])) return [false, 'Pilih format: buku fisik atau e-book.'];
    $db = db();
    $db->beginTransaction();
    try {
        // FOR UPDATE: dua peminjam tidak bisa mengambil eksemplar fisik terakhir bersamaan
        $buku = q('SELECT b.*, b.stok - ' . SQL_DIPINJAM . ' AS tersedia FROM buku b WHERE b.id = ? FOR UPDATE', [$bukuId])->fetch();
        $pinjam = q('SELECT buku_id, jatuh_tempo, format, tgl_ambil, tgl_ajuan FROM peminjaman WHERE user_id = ? AND tgl_kembali IS NULL', [$agt['id']])->fetchAll();
        // Anggota yang sedang mendapat giliran antrean memakai eksemplar yang disimpan untuknya
        $giliran = $buku && $format === 'fisik' && q('SELECT 1 FROM antrean WHERE user_id = ? AND buku_id = ? AND tgl_tersedia IS NOT NULL', [$agt['id'], $bukuId])->fetch();
        $tunggakan = denda_belum_lunas((int) $agt['id']);
        $galat = match (true) {
            !$agt['aktif'] => $agt['nama'] . ' sedang dinonaktifkan.',
            !$buku => 'Buku tidak ditemukan.',
            $format === 'ebook' && !$buku['file_pdf'] => '“' . $buku['judul'] . '” belum punya versi e-book.',
            $format === 'fisik' && $buku['tersedia'] + ($giliran ? 1 : 0) < 1 => 'Buku fisik “' . $buku['judul'] . '” sedang habis dipinjam.' . ($buku['file_pdf'] ? ' Versi e-book masih bisa dipinjam, atau ikut antrean.' : ' Ikut antrean supaya dapat giliran.'),
            in_array($bukuId, array_map('intval', array_column($pinjam, 'buku_id')), true) => $agt['nama'] . ' masih meminjam judul ini.',
            $tunggakan > 0 => $agt['nama'] . ' masih punya denda ' . rupiah($tunggakan) . ' yang belum lunas. Lunasi dulu di meja sirkulasi.',
            count($pinjam) >= MAKS_PINJAM => $agt['nama'] . ' sudah meminjam ' . MAKS_PINJAM . ' buku (batas maksimal). Kembalikan salah satu dulu.',
            (bool) array_filter($pinjam, fn($x) => $x['format'] === 'fisik' && $x['tgl_ambil'] && !$x['tgl_ajuan'] && $x['jatuh_tempo'] < date('Y-m-d'))
                => $agt['nama'] . ' punya buku fisik yang terlambat. Kembalikan dulu ke meja sirkulasi.',
            default => null,
        };
        if ($galat) {
            $db->rollBack();
            return [false, $galat];
        }
        // Buku fisik pesanan online baru dihitung masa pinjamnya saat diambil (jatuh tempo dihitung ulang oleh petugas)
        $diambil = $format === 'ebook' || $langsungDiambil;
        $tempo = date('Y-m-d', strtotime("+$hari days"));
        q('INSERT INTO peminjaman (user_id, buku_id, format, tgl_pinjam, tgl_ambil, jatuh_tempo, durasi) VALUES (?, ?, ?, CURDATE(), ?, ?, ?)',
            [$agt['id'], $bukuId, $format, $diambil ? date('Y-m-d') : null, $tempo, $hari]);
        q('DELETE FROM antrean WHERE user_id = ? AND buku_id = ?', [$agt['id'], $bukuId]);
        $db->commit();
        $rak = $buku['rak'] !== '' ? ' (Rak ' . $buku['rak'] . ')' : '';
        return [true, match (true) {
            $format === 'ebook' => '“' . $buku['judul'] . '” (e-book) dipinjam selama ' . $hari . ' hari dan bisa dibaca sampai ' . tgl($tempo, true) . '.',
            $diambil => '“' . $buku['judul'] . '” (buku fisik) dipinjam selama ' . $hari . ' hari, kembali paling lambat ' . tgl($tempo, true) . '.',
            default => 'Buku fisik “' . $buku['judul'] . '” disiapkan untukmu. Ambil di meja sirkulasi' . $rak . ' paling lambat '
                . tgl(date('Y-m-d', strtotime('+' . BATAS_AMBIL . ' days')), true) . ' — masa pinjam ' . $hari . ' hari dihitung sejak buku diambil.',
        }];
    } catch (Throwable $t) {
        $db->rollBack();
        throw $t;
    }
}

/** Total denda dari pinjaman yang sudah selesai tapi belum dibayar. */
function denda_belum_lunas(int $userId): int
{
    return (int) q('SELECT COALESCE(SUM(denda + denda_kondisi), 0) FROM peminjaman
                    WHERE user_id = ? AND tgl_kembali IS NOT NULL AND denda_lunas IS NULL', [$userId])->fetchColumn();
}

/**
 * Pengingat untuk anggota: siap diambil, jatuh tempo dekat, terlambat, giliran antrean, denda belum lunas.
 * @return list<array{0: string, 1: string, 2: string}> [kelas, pesan, tautan]
 */
function pengingat(array $u): array
{
    $hasil = [];
    $aktif = q('SELECT p.*, b.judul FROM peminjaman p JOIN buku b ON b.id = p.buku_id
                WHERE p.user_id = ? AND p.tgl_kembali IS NULL AND p.tgl_ajuan IS NULL ORDER BY p.jatuh_tempo', [$u['id']])->fetchAll();
    foreach ($aktif as $p) {
        $judul = '“' . $p['judul'] . '”';
        if (!$p['tgl_ambil']) {
            $hasil[] = ['soon', "Buku fisik $judul siap diambil di meja sirkulasi sampai " . tgl(batas_ambil($p)) . '.', 'pinjaman.php'];
            continue;
        }
        $sisa = -selisih_hari($p['jatuh_tempo']);
        if ($sisa < 0) {
            $hasil[] = ['late', "$judul terlambat " . -$sisa . ' hari — denda berjalan ' . rupiah(denda_pinjaman($p)) . '.', 'pinjaman.php'];
        } elseif ($sisa <= 1) {
            $kapan = $sisa ? 'besok' : 'hari ini';
            $hasil[] = ['soon', $p['format'] === 'ebook' ? "Akses e-book $judul berakhir $kapan." : "$judul jatuh tempo $kapan.", 'pinjaman.php'];
        }
    }
    foreach (q('SELECT a.tgl_tersedia, b.id, b.judul FROM antrean a JOIN buku b ON b.id = a.buku_id
                WHERE a.user_id = ? AND a.tgl_tersedia IS NOT NULL', [$u['id']])->fetchAll() as $a) {
        $sampai = tgl(date('Y-m-d', strtotime($a['tgl_tersedia'] . ' +' . BATAS_GILIRAN . ' days')));
        $hasil[] = ['ok', "Giliranmu! Buku fisik “{$a['judul']}” disimpan untukmu sampai $sampai — pinjam sekarang.", 'pinjam.php?id=' . $a['id']];
    }
    if ($d = denda_belum_lunas((int) $u['id'])) {
        $hasil[] = ['late', 'Denda ' . rupiah($d) . ' belum lunas. Bayar di meja sirkulasi supaya bisa meminjam lagi.', 'pinjaman.php#tagihan'];
    }
    return $hasil;
}

/** Kolom SQL jumlah anggota yang masih menunggu buku fisik dari pinjaman `p` (yang sudah dapat giliran tidak dihitung). */
const SQL_DIANTRE = '(SELECT COUNT(*) FROM antrean a WHERE a.buku_id = p.buku_id AND a.tgl_tersedia IS NULL)';

/**
 * Kolom SQL eksemplar fisik yang sedang terpakai: dipinjam, dipesan, menunggu dicek,
 * ditambah eksemplar yang disimpan untuk giliran antrean.
 */
const SQL_DIPINJAM = '((SELECT COUNT(*) FROM peminjaman p WHERE p.buku_id = b.id AND p.format = "fisik" AND p.tgl_kembali IS NULL)
    + (SELECT COUNT(*) FROM antrean a WHERE a.buku_id = b.id AND a.tgl_tersedia IS NOT NULL))';
