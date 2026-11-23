<?php
// Matikan semua output buffering dulu sebelum require
while (ob_get_level()) ob_end_clean();
ob_start();

// Matikan error display untuk prevent corrupt PDF
ini_set('display_errors', '0');
error_reporting(0);

require __DIR__ . '/config.php';

// Mengirim PDF isi buku. Folder berkas/ dikunci (.htaccess), jadi satu-satunya jalan masuk lewat sini.
$u = wajib_login();
$b = q('SELECT id, judul, file_pdf FROM buku WHERE id = ?', [(int) ($_GET['id'] ?? 0)])->fetch();
if (!$b || akses_isi($u, $b) === null) {
    ob_end_clean();
    http_response_code(403);
    exit('Isi buku ini hanya bisa dibuka oleh peminjamnya.');
}

$path = __DIR__ . '/berkas/' . basename($b['file_pdf']);
if (!is_file($path)) {
    ob_end_clean();
    http_response_code(404);
    exit('File isi buku belum tersedia di folder berkas/.');
}

// Buang semua output yang mungkin ada dari config.php atau session
ob_end_clean();

// Tutup session agar tidak lock
session_write_close();

// Kirim PDF
header('Content-Type: application/pdf');
header('Content-Length: ' . filesize($path));
header('Content-Disposition: inline; filename="' . basename($path) . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=0, must-revalidate');
readfile($path);
exit;
