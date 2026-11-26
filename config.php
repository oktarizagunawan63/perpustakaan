<?php
// Start output buffering untuk menangkap output yang tidak diinginkan
if (!ob_get_level()) ob_start();

// Start session dengan nama berbeda per port agar tidak bentrok
if (session_status() === PHP_SESSION_NONE) {
    $port = $_SERVER['SERVER_PORT'] ?? '80';
    session_name('PERPUSTAKAAN_' . $port); // Session berbeda per port
    session_start();
}

// Konfigurasi Database
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'db_perpustakaan');
define('NAMA_PERPUS', 'Perpustakaan Kampus');

// SQL untuk hitung antrian peminjaman buku yang sama
define('SQL_STATUS_AKTIF', "status IN ('menunggu','disetujui','dipinjam')");
define('SQL_DIANTRE', '(SELECT COUNT(*) FROM peminjaman p2 WHERE p2.buku_id = p.buku_id AND p2.status = "menunggu" AND p2.id < p.id)');
define('SQL_POSISI_ANTREAN', '(SELECT COUNT(*) FROM peminjaman p2 WHERE p2.buku_id = a.buku_id AND p2.status = "menunggu")');

// Konstanta lainnya
define('MAKS_PINJAM', 3);
define('MAKS_PERPANJANG', 1);
define('DENDA_PER_HARI', 1000);
define('BATAS_AMBIL', 3); // hari untuk mengambil buku setelah disetujui
define('BATAS_GILIRAN', 3); // hari giliran antrean
define('LABEL_FORMAT', ['fisik' => 'Buku fisik', 'ebook' => 'E-book']);
define('PILIHAN_LAMA', [7, 14]); // pilihan durasi pinjam (hari)
define('LAMA_PINJAM', 7); // durasi pinjam default (hari)
define('DENDA_KONDISI', ['baik' => 0, 'rusak' => 25000, 'hilang' => 50000]);
define('ALASAN_TOLAK', [
    'Buku tidak ditemukan di rak',
    'Eksemplar rusak atau sedang diperbaiki',
    'Buku hanya untuk dibaca di tempat',
    'Eksemplar dipakai untuk keperluan perpustakaan',
    'Data anggota perlu dicek di meja sirkulasi',
    'Lainnya',
]);
define('DIR_FOTO', __DIR__ . '/assets/foto/');
define('DIR_SAMPUL', __DIR__ . '/assets/sampul/');
define('DIR_BERKAS', __DIR__ . '/berkas/');

// SQL helper untuk menghitung buku fisik yang stoknya sedang ditahan/dipinjam
define('SQL_DIPINJAM', '(SELECT COUNT(*) FROM peminjaman WHERE buku_id = b.id AND format = "fisik" AND status IN ("disetujui","dipinjam"))');

// Base URL - auto detect port
$port = $_SERVER['SERVER_PORT'] ?? '80';
$portStr = ($port == '80' || $port == '443') ? '' : ':' . $port;
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
define('BASE_URL', $protocol . '://localhost' . $portStr . '/');

// Koneksi PDO Database
function pdo() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER,
                DB_PASS,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
        } catch (PDOException $e) {
            die("Koneksi gagal: " . $e->getMessage());
        }
    }
    return $pdo;
}

// Query helper
function q($sql, $params = []) {
    $stmt = pdo()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

// Get current user
function user() {
    if (empty($_SESSION['user'])) return null;
    if (!isset($_SESSION['user']['role']) && !empty($_SESSION['user']['id'])) {
        $fresh = q('SELECT * FROM users WHERE id = ?', [$_SESSION['user']['id']])->fetch(PDO::FETCH_ASSOC);
        if ($fresh) {
            unset($fresh['password']);
            $_SESSION['user'] = $fresh;
        }
    }
    return $_SESSION['user'];
}

// Require admin role
function wajib_admin() {
    $user = user();
    if (!$user || $user['role'] !== 'admin') {
        redirect('login.php');
    }
}

// Require anggota role
function wajib_anggota() {
    $user = user();
    if (!$user || $user['role'] !== 'anggota') {
        redirect('login.php');
    }
}

// Require logged in
function wajib_login() {
    $user = user();
    if (!$user) {
        redirect('login.php');
    }
    return $user;
}

// URL helper
function url($path = '') {
    return BASE_URL . ltrim($path, '/');
}

// Escape HTML
function e($text) {
    return htmlspecialchars($text ?? '', ENT_QUOTES, 'UTF-8');
}

// CSRF token generation
function csrf_field() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return '<input type="hidden" name="csrf_token" value="' . e($_SESSION['csrf_token']) . '">';
}

// CSRF validation
function csrf_cek() {
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || 
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die('CSRF token tidak valid');
    }
}

// Flash message
function flash($type, $message) {
    if (!isset($_SESSION['flash'])) {
        $_SESSION['flash'] = [];
    }
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

// Display flash messages
function flash_tampil() {
    if (!isset($_SESSION['flash'])) {
        return '';
    }
    $html = '';
    foreach ($_SESSION['flash'] as $flash) {
        $type = is_array($flash['type']) ? $flash['type'][0] : $flash['type'];
        $html .= '<div class="notice notice--' . e($type) . '" role="alert">' . e($flash['message']) . '</div>';
    }
    unset($_SESSION['flash']);
    return $html;
}

// Redirect helper
function redirect($path) {
    header('Location: ' . url($path));
    exit;
}

// Logo SVG
function logo($class = '') {
    return '<svg class="' . e($class) . '" viewBox="0 0 64 64" aria-hidden="true"><use href="' . url('assets/rak.svg#rak') . '"></use></svg>';
}

// Avatar placeholder
function avatar($user, $size = 'md') {
    if (!empty($user['foto']) && is_file(DIR_FOTO . $user['foto'])) {
        return '<img src="' . url('assets/foto/' . $user['foto']) . '" alt="" class="avatar avatar--' . e($size) . '">';
    }
    $initial = strtoupper(substr($user['nama'] ?? $user['username'] ?? 'U', 0, 1));
    return '<span class="avatar avatar--' . e($size) . '">' . e($initial) . '</span>';
}

// Sampul buku
function sampul($buku, $size = 'md') {
    if (!empty($buku['sampul']) && is_file(DIR_SAMPUL . $buku['sampul'])) {
        return '<img src="' . url('assets/sampul/' . $buku['sampul']) . '" alt="" class="sampul sampul--' . e($size) . '">';
    }
    return '<span class="sampul sampul--' . e($size) . ' sampul--kosong" aria-label="Tidak ada sampul"></span>';
}

// Status layanan
function status_layanan() {
    // Default: layanan buka
    return [true, 'Layanan buka'];
}

// Required field marker
function wajib() {
    return '<abbr class="wajib" title="wajib diisi" aria-label="wajib diisi">*</abbr>';
}

// Required field notice
function keterangan_wajib() {
    return '<p class="form__note"><abbr class="wajib" aria-hidden="true">*</abbr> Wajib diisi</p>';
}

// Pengingat untuk anggota
function pengingat($user) {
    return [];
}

// Versi data untuk live update
function versi_data($user) {
    // Pakai timestamp terbaru dari tabel peminjaman supaya hanya reload kalau ada perubahan data beneran
    $v = q('SELECT MAX(GREATEST(COALESCE(UNIX_TIMESTAMP(tgl_pinjam),0), COALESCE(UNIX_TIMESTAMP(tgl_kembali),0), COALESCE(UNIX_TIMESTAMP(disetujui_at),0), COALESCE(UNIX_TIMESTAMP(diambil_at),0))) FROM peminjaman')->fetchColumn();
    return $v ?: '0';
}

// Cek akses baca isi buku (e-book atau sedang pinjam fisik)
function akses_isi($user, $buku) {
    // Admin bisa akses semua PDF
    if ($user && $user['role'] === 'admin' && $buku['file_pdf']) {
        return ['format' => 'admin'];
    }
    
    // Kalau ada file PDF dan user login
    if ($buku['file_pdf'] && $user) {
        // Cek apakah sedang pinjam buku ini (fisik atau ebook) dan belum lewat jatuh tempo
        $pinjam = q('SELECT id, format FROM peminjaman WHERE user_id = ? AND buku_id = ? AND status = "dipinjam" AND jatuh_tempo >= CURDATE()', 
                    [$user['id'], $buku['id']])->fetch();
        if ($pinjam) {
            return $pinjam;
        }
    }
    return null;
}

// Hitung denda yang belum lunas
function denda_belum_lunas($user_id) {
    return (int) q('SELECT COALESCE(SUM(denda + denda_kondisi), 0) FROM peminjaman 
                    WHERE user_id = ? AND tgl_kembali IS NOT NULL AND denda_lunas IS NULL', 
                   [$user_id])->fetchColumn();
}

// Koneksi Database (mysqli untuk kompatibilitas)
function getConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        die("Koneksi gagal: " . $conn->connect_error);
    }
    
    return $conn;
}

// Format tanggal Indonesia
function tgl($date, $long = false) {
    if (!$date) return '—';
    $bulan = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agt', 'Sep', 'Okt', 'Nov', 'Des'];
    $bulanPanjang = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    $hari = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    
    $timestamp = is_numeric($date) ? $date : strtotime($date);
    $d = date('j', $timestamp);
    $m = (int)date('n', $timestamp);
    $y = date('Y', $timestamp);
    
    if ($long) {
        $namaHari = $hari[date('w', $timestamp)];
        return "$namaHari, $d " . $bulanPanjang[$m] . " $y";
    }
    
    return "$d " . $bulan[$m] . " $y";
}

// Format rupiah
function rupiah($angka) {
    return 'Rp' . number_format($angka, 0, ',', '.');
}

// Hitung denda pinjaman
function denda_pinjaman($pinjaman) {
    if (!$pinjaman['tgl_ajuan'] && $pinjaman['format'] === 'fisik') {
        $terlambat = max(0, (strtotime('today') - strtotime($pinjaman['jatuh_tempo'])) / 86400);
        return $terlambat * 1000; // Rp 1000 per hari
    }
    return 0;
}

// Status pinjaman untuk badge dan kelas kartu
function status_pinjaman($pinjaman) {
    $status = $pinjaman['status'] ?? 'dipinjam';
    if ($status === 'menunggu') return ['wait', 'Menunggu validasi'];
    if ($status === 'disetujui') return ['wait', 'Siap diambil'];
    if ($status === 'ditolak') return ['late', 'Ditolak'];
    if ($status === 'kedaluwarsa') return ['late', 'Kedaluwarsa'];
    if ($status === 'dibatalkan') return ['wait', 'Dibatalkan'];
    if ($status === 'dikembalikan') return ['ok', 'Dikembalikan'];

    $hari = (strtotime($pinjaman['jatuh_tempo']) - strtotime('today')) / 86400;
    if ($hari < 0) return ['late', 'Terlambat'];
    if ($hari <= 2) return ['soon', 'Segera'];
    return ['ok', 'Normal'];
}

// Tandai pesanan disetujui yang melewati batas_ambil sebagai kedaluwarsa
function cek_kedaluwarsa() {
    static $sudah = false;
    if ($sudah) return;
    $sudah = true;
    q('UPDATE peminjaman SET status = "kedaluwarsa" WHERE status = "disetujui" AND batas_ambil IS NOT NULL AND batas_ambil < NOW()');
}

// Selisih hari dari sekarang
function selisih_hari($tanggal) {
    return abs(floor((strtotime('today') - strtotime($tanggal)) / 86400));
}

// Validasi data anggota
function validasi_anggota($data, $userId = null) {
    $masukan = [
        'nama' => trim($data['nama'] ?? ''),
        'email' => trim($data['email'] ?? ''),
        'username' => trim($data['username'] ?? ''),
        'telepon' => trim($data['telepon'] ?? '')
    ];
    $galat = [];
    
    if (mb_strlen($masukan['nama']) < 3) $galat['nama'] = 'Nama minimal 3 karakter.';
    if (!filter_var($masukan['email'], FILTER_VALIDATE_EMAIL)) $galat['email'] = 'Format email tidak valid.';
    
    // Cek email duplikat
    $cekEmail = q('SELECT id FROM users WHERE email = ? AND id != ?', [$masukan['email'], $userId ?? 0])->fetch();
    if ($cekEmail) $galat['email'] = 'Email sudah terdaftar.';
    
    // Cek username duplikat jika diisi
    if ($masukan['username'] !== '') {
        if (mb_strlen($masukan['username']) < 3 || mb_strlen($masukan['username']) > 20) {
            $galat['username'] = 'Username 3-20 karakter.';
        } else {
            $cekUsername = q('SELECT id FROM users WHERE username = ? AND id != ?', [$masukan['username'], $userId ?? 0])->fetch();
            if ($cekUsername) $galat['username'] = 'Username sudah dipakai.';
        }
    }
    
    return [$masukan, $galat];
}

// Daftarkan anggota baru
function daftarkan_anggota($data) {
    [$masukan, $galat] = validasi_anggota($data);
    
    $password = trim($data['password'] ?? '');
    if (strlen($password) < 8) $galat['password'] = 'Kata sandi minimal 8 karakter.';
    
    if ($galat) return [null, $galat];
    
    // Generate nomor anggota
    $tahun = date('y');
    $lastNo = q("SELECT no_anggota FROM users WHERE no_anggota LIKE 'AGT-$tahun%' ORDER BY no_anggota DESC LIMIT 1")->fetchColumn();
    $urutan = $lastNo ? ((int) substr($lastNo, -3)) + 1 : 1;
    $noAnggota = sprintf('AGT-%s%03d', $tahun, $urutan);
    
    q('INSERT INTO users (no_anggota, nama, email, telepon, password, role) VALUES (?, ?, ?, ?, ?, "anggota")',
        [$noAnggota, $masukan['nama'], $masukan['email'], $masukan['telepon'] ?: null, password_hash($password, PASSWORD_DEFAULT)]);
    
    $id = (int) pdo()->lastInsertId();
    $akun = q('SELECT * FROM users WHERE id = ?', [$id])->fetch(PDO::FETCH_ASSOC);
    return [$akun, []];
}

// Simpan foto upload
function simpan_foto($file) {
    if ($file['error'] === UPLOAD_ERR_NO_FILE) return [null, null];
    if ($file['error'] !== UPLOAD_ERR_OK) return [null, 'Gagal mengunggah file.'];
    if ($file['size'] > 2 * 1024 * 1024) return [null, 'Ukuran foto maksimal 2 MB.'];
    
    $mime = mime_content_type($file['tmp_name']);
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'])) {
        return [null, 'Format foto harus JPG, PNG, atau WebP.'];
    }
    
    $ext = match($mime) {
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp'
    };
    
    $namaFile = uniqid('foto_') . '.' . $ext;
    if (!is_dir(DIR_FOTO)) mkdir(DIR_FOTO, 0755, true);
    
    if (!move_uploaded_file($file['tmp_name'], DIR_FOTO . $namaFile)) {
        return [null, 'Gagal menyimpan foto.'];
    }
    
    return [$namaFile, null];
}

// Opsional field marker
function opsional() {
    return '<span class="opsional">opsional</span>';
}

// Database helper (alias untuk PDO)
function db() {
    return pdo();
}

// Batas waktu ambil buku setelah disetujui
function batas_ambil($pinjaman) {
    if (!empty($pinjaman['batas_ambil'])) {
        return substr($pinjaman['batas_ambil'], 0, 10);
    }
    return date('Y-m-d', strtotime($pinjaman['tgl_setuju'] . ' +' . BATAS_AMBIL . ' days'));
}

// Batas waktu giliran antrean
function batas_giliran($tglTersedia) {
    return date('Y-m-d', strtotime($tglTersedia . ' +' . BATAS_GILIRAN . ' days'));
}

// Hitung denda keterlambatan
function hitung_denda($jatuhTempo, $sampai = null) {
    $sampai = $sampai ? strtotime($sampai) : strtotime('today');
    $terlambat = max(0, ($sampai - strtotime($jatuhTempo)) / 86400);
    return (int) ($terlambat * DENDA_PER_HARI);
}

// Catat pinjaman baru
function catat_pinjaman($user, $bukuId, $hari, $format, $admin = false) {
    if (!in_array($hari, PILIHAN_LAMA)) $hari = LAMA_PINJAM;
    if (!in_array($format, ['fisik', 'ebook'])) $format = 'fisik';

    // Cek kuota
    $jumlah = (int) q('SELECT COUNT(*) FROM peminjaman WHERE user_id = ? AND ' . SQL_STATUS_AKTIF, [$user['id']])->fetchColumn();
    if ($jumlah >= MAKS_PINJAM) return [false, 'Kuota pinjaman penuh (' . MAKS_PINJAM . ' buku). Kembalikan salah satu dulu.'];

    // Cek tunggakan denda
    if (denda_belum_lunas((int) $user['id'])) return [false, 'Ada denda yang belum lunas. Bayar dulu di meja sirkulasi.'];

    // Cek sudah pinjam buku ini
    if (q('SELECT 1 FROM peminjaman WHERE user_id = ? AND buku_id = ? AND ' . SQL_STATUS_AKTIF, [$user['id'], $bukuId])->fetch()) {
        return [false, 'Buku ini sudah sedang dipinjam.'];
    }

    $buku = q('SELECT b.*, b.stok - ' . SQL_DIPINJAM . ' AS tersedia FROM buku b WHERE b.id = ?', [$bukuId])->fetch();
    if (!$buku) return [false, 'Buku tidak ditemukan.'];

    if ($format === 'ebook') {
        if (!$buku['file_pdf']) return [false, 'Buku ini tidak tersedia dalam format e-book.'];
        // E-book: langsung aktif
        $jatuh = date('Y-m-d', strtotime("+{$hari} days"));
        q('INSERT INTO peminjaman (user_id, buku_id, format, status, durasi, tgl_pinjam, tgl_setuju, tgl_ambil, disetujui_at, diambil_at, jatuh_tempo) VALUES (?, ?, "ebook", "dipinjam", ?, CURDATE(), CURDATE(), CURDATE(), NOW(), NOW(), ?)',
            [$user['id'], $bukuId, $hari, $jatuh]);
        return [true, '"' . $buku['judul'] . '" (e-book) dipinjam ' . $hari . ' hari.'];
    }

    // Buku fisik: cek ketersediaan
    $giliran = q('SELECT id FROM antrean WHERE user_id = ? AND buku_id = ? AND tgl_tersedia IS NOT NULL', [$user['id'], $bukuId])->fetch();
    if ($buku['tersedia'] <= 0 && !$giliran) return [false, 'Stok buku fisik sedang habis.'];

    if ($admin) {
        // Admin: langsung ambil
        $jatuh = date('Y-m-d', strtotime("+{$hari} days"));
        q('INSERT INTO peminjaman (user_id, buku_id, format, status, durasi, tgl_pinjam, tgl_setuju, tgl_ambil, disetujui_at, diambil_at, jatuh_tempo) VALUES (?, ?, "fisik", "dipinjam", ?, CURDATE(), CURDATE(), CURDATE(), NOW(), NOW(), ?)',
            [$user['id'], $bukuId, $hari, $jatuh]);
    } else {
        // Anggota: perlu persetujuan petugas, jatuh_tempo sementara diisi tanggal pesan dan dihitung ulang saat diambil
        $jatuh = date('Y-m-d', strtotime("+{$hari} days"));
        q('INSERT INTO peminjaman (user_id, buku_id, format, status, durasi, tgl_pinjam, jatuh_tempo) VALUES (?, ?, "fisik", "menunggu", ?, CURDATE(), ?)',
            [$user['id'], $bukuId, $hari, $jatuh]);
    }

    // Hapus dari antrean jika ada
    if ($giliran) q('DELETE FROM antrean WHERE id = ?', [$giliran['id']]);

    $aksi = $admin ? 'dipinjamkan' : 'dipesan';
    return [true, '"' . $buku['judul'] . '" (fisik) ' . $aksi . ' untuk ' . $hari . ' hari.'];
}


// Fungsi kirim email dengan SMTP
function kirim_email($to, $nama, $subject, $message) {
    require_once __DIR__ . '/vendor/PHPMailer/PHPMailer.php';
    require_once __DIR__ . '/vendor/PHPMailer/SMTP.php';
    require_once __DIR__ . '/vendor/PHPMailer/Exception.php';
    
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com'; // Ganti dengan SMTP host lu
        $mail->SMTPAuth   = true;
        $mail->Username   = 'your-email@gmail.com'; // Ganti dengan email lu
        $mail->Password   = 'your-app-password'; // Ganti dengan app password
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        
        // Recipients
        $mail->setFrom('noreply@perpustakaan.local', NAMA_PERPUS);
        $mail->addAddress($to, $nama);
        
        // Content
        $mail->isHTML(false);
        $mail->Subject = $subject;
        $mail->Body    = $message;
        
        $mail->send();
        return [true, null];
    } catch (Exception $e) {
        return [false, "Email gagal dikirim: {$mail->ErrorInfo}"];
    }
}
