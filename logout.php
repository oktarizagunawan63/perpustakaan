<?php
require __DIR__ . '/config.php';

$_SESSION = [];
session_destroy();
session_start();
flash('info', 'Kamu sudah keluar.');
redirect('index.php');
