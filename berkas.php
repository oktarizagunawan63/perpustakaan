<?php
require __DIR__ . '/config.php';

// Mengirim PDF isi buku. Folder berkas/ dikunci (.htaccess), jadi satu-satunya jalan masuk lewat sini.
$u = wajib_login();
$b = q('SELECT id, judul, file_pdf FROM buku WHERE id = ?', [(int) ($_GET['id'] ?? 0)])->fetch();
if (!$b || akses_isi($u, $b) === null) {
    http_response_code(403);
    exit('Isi buku ini hanya bisa dibuka oleh peminjamnya.');
}

$path = __DIR__ . '/berkas/' . basename($b['file_pdf']);
if (!is_file($path)) {
    http_response_code(404);
    exit('File isi buku belum tersedia di folder berkas/.');
}

session_write_close(); // jangan kunci sesi selama PDF besar dikirim
while (ob_get_level()) ob_end_clean();
// shortcut: tanpa dukungan Range, viewer mengunduh PDF utuh dulu; cukup untuk server lokal, tambahkan Range bila di-hosting
header('Content-Type: application/pdf');
header('Content-Length: ' . filesize($path));
header('Content-Disposition: inline; filename="' . basename($path) . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=0, must-revalidate');
readfile($path);
