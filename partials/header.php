<?php
/** @var string $judul  judul halaman; $menu = kunci menu aktif; $admin = true untuk panel petugas */
$u = user();
[$buka, $statusLayanan] = status_layanan();
$menu ??= '';
$admin ??= false;
$navAktif = fn(string $k) => $menu === $k ? ' aria-current="page"' : '';
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(($judul ?? '') ? $judul . ' — ' . NAMA_PERPUS : NAMA_PERPUS) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&family=Newsreader:ital,opsz,wght@1,6..72,400&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= url('assets/style.css') ?>">
<script src="<?= url('assets/app.js') ?>" defer></script>
</head>
<body class="<?= $admin ? 'is-admin' : '' ?>">
<a class="skip" href="#isi">Langsung ke konten</a>

<header class="topbar">
  <div class="wrap topbar__in">
    <a class="brand" href="<?= url('index.php') ?>">
      <?= logo('brand__mark') ?>
      <span class="brand__text">Perpustakaan<small>Kampus</small></span>
    </a>

    <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="nav">Menu</button>

    <nav class="nav" id="nav" aria-label="Utama">
      <a href="<?= url('katalog.php') ?>"<?= $navAktif('katalog') ?>>Katalog</a>
      <a href="<?= url('index.php#cara-pinjam') ?>">Cara meminjam</a>
      <a href="<?= url('index.php#jam') ?>">Jam layanan</a>
      <span class="nav__sep" aria-hidden="true"></span>
      <?php if ($u): ?>
        <?php if ($u['role'] === 'admin'): ?>
          <a href="<?= url('admin/index.php') ?>"<?= $admin ? ' aria-current="page"' : '' ?>>Panel petugas</a>
        <?php else: ?>
          <a href="<?= url('akun.php') ?>"<?= $navAktif('akun') ?>>Akun saya</a>
        <?php endif ?>
        <a href="<?= url('logout.php') ?>">Keluar</a>
      <?php else: ?>
        <a href="<?= url('login.php') ?>"<?= $navAktif('login') ?>>Masuk</a>
        <a class="btn btn--sm btn--light" href="<?= url('daftar.php') ?>">Daftar anggota</a>
      <?php endif ?>
    </nav>
  </div>

  <?php if ($admin): ?>
  <div class="subnav">
    <div class="wrap subnav__in" aria-label="Panel petugas">
      <a href="<?= url('admin/index.php') ?>"<?= $navAktif('dash') ?>>Ringkasan</a>
      <a href="<?= url('admin/sirkulasi.php') ?>"<?= $navAktif('sirkulasi') ?>>Sirkulasi</a>
      <a href="<?= url('admin/buku.php') ?>"<?= $navAktif('buku') ?>>Koleksi</a>
      <a href="<?= url('admin/anggota.php') ?>"<?= $navAktif('anggota') ?>>Anggota</a>
      <span class="subnav__status <?= $buka ? 'is-open' : '' ?>"><?= e($statusLayanan) ?></span>
    </div>
  </div>
  <?php endif ?>
</header>

<main id="isi">
<?php if ($f = flash_tampil()): ?><div class="wrap flash"><?= $f ?></div><?php endif ?>
