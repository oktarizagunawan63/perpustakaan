<?php
require __DIR__ . '/config.php';

// Sudah login → redirect sesuai role
if (user()) {
    $u = user();
    redirect($u['role'] === 'admin' ? 'admin/index.php' : 'akun.php');
}

// Belum login → ke halaman login
redirect('login.php');
