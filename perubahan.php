<?php
// Dipanggil dasbor tiap beberapa detik untuk tahu apakah datanya berubah. Hanya mengembalikan sidik, bukan isi data.
require __DIR__ . '/config.php';

$u = user();
session_write_close(); // permintaan berkala tidak boleh menahan halaman lain di sesi yang sama
header('Content-Type: application/json');
header('Cache-Control: no-store');
if (!$u) {
    http_response_code(401);
    exit('{}');
}
echo json_encode(['versi' => versi_data($u)]);
