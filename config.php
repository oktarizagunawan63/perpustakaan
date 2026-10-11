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
const LAMA_PINJAM     = 7;     // hari
const MAKS_PINJAM     = 3;     // buku aktif per anggota
const MAKS_PERPANJANG = 1;
const DENDA_PER_HARI  = 1000;  // rupiah

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
    }
    return $pdo;
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
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(419);
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

/** Sampul buatan CSS — warna tetap per judul supaya rak terlihat rapi tanpa gambar. */
function sampul(array $b, string $ukuran = 'md'): string
{
    $warna = ['#0F3D35', '#1E5A4E', '#6E5B45', '#3F5B57', '#8A7A5E', '#2D4A44', '#5E6F64', '#4A3F36', '#26332F', '#7C6A4F'];
    $c = $warna[crc32($b['judul']) % count($warna)];
    $kelas = strtok($b['no_panggil'] ?? '', ' ');
    return '<div class="cover cover--' . $ukuran . '" style="--c:' . $c . '" aria-hidden="true">'
        . '<span class="cover__no">' . e($kelas) . '</span>'
        . '<span class="cover__judul">' . e($b['judul']) . '</span>'
        . '<span class="cover__penulis">' . e($b['penulis']) . '</span></div>';
}

/**
 * Validasi + simpan anggota baru (dipakai form daftar dan panel petugas).
 * @return array{0: ?array, 1: array<string,string>} [akun baru, galat per field]
 */
function daftarkan_anggota(array $in): array
{
    $d = ['nama' => trim($in['nama'] ?? ''), 'email' => strtolower(trim($in['email'] ?? '')), 'telepon' => trim($in['telepon'] ?? '')];
    $pw = $in['password'] ?? '';
    $galat = [];
    if (mb_strlen($d['nama']) < 3) $galat['nama'] = 'Isi nama lengkap (minimal 3 huruf).';
    if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL)) $galat['email'] = 'Format email belum benar, contoh: nama@kampus.ac.id';
    elseif (q('SELECT 1 FROM users WHERE email = ?', [$d['email']])->fetch()) $galat['email'] = 'Email ini sudah terdaftar. Coba masuk saja.';
    if ($d['telepon'] !== '' && !preg_match('/^\+?[0-9 \-]{8,16}$/', $d['telepon'])) $galat['telepon'] = 'Nomor telepon hanya angka, 8–16 digit.';
    if (strlen($pw) < 8) $galat['password'] = 'Kata sandi minimal 8 karakter.';
    if ($galat) return [null, $galat];

    q('INSERT INTO users (no_anggota, nama, email, telepon, password) VALUES (?, ?, ?, ?, ?)',
        [uniqid('tmp'), $d['nama'], $d['email'], $d['telepon'] ?: null, password_hash($pw, PASSWORD_DEFAULT)]);
    $id = (int) db()->lastInsertId();
    q('UPDATE users SET no_anggota = ? WHERE id = ?', [sprintf('AGT-%s%03d', date('y'), $id), $id]);
    return [q('SELECT * FROM users WHERE id = ?', [$id])->fetch(), []];
}

/** Kolom SQL jumlah eksemplar yang sedang dipinjam. */
const SQL_DIPINJAM = '(SELECT COUNT(*) FROM peminjaman p WHERE p.buku_id = b.id AND p.tgl_kembali IS NULL)';
