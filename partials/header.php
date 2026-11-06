<?php
/** @var string $judul  judul halaman; $menu = kunci menu aktif; $admin = true untuk panel petugas */
$u = user();
[$buka, $statusLayanan] = status_layanan();
$menu ??= '';
$admin ??= false;
// Hasil POST tidak ikut diperbarui otomatis supaya pesan galat dan isian formulir tidak hilang
$live = ($live ?? false) && $u && $_SERVER['REQUEST_METHOD'] === 'GET';
$navAktif = fn(string $k) => $menu === $k ? ' aria-current="page"' : '';
// Angka di menu "Pinjaman saya": jumlah pinjaman aktif, merah bila ada buku fisik terlambat yang belum diserahkan
[$jmlPinjam, $jmlTelat] = $u && $u['role'] !== 'admin'
    ? array_map('intval', q('SELECT COUNT(*), COALESCE(SUM(format = "fisik" AND tgl_ajuan IS NULL AND jatuh_tempo < CURDATE()), 0)
                             FROM peminjaman WHERE user_id = ? AND tgl_kembali IS NULL', [$u['id']])->fetch(PDO::FETCH_NUM))
    : [0, 0];
// Pemberitahuan yang belum ditutup anggota, ditandai merah di menu "Pinjaman saya"
$jmlPesan = $u && $u['role'] !== 'admin' ? (int) q('SELECT COUNT(*) FROM pemberitahuan WHERE user_id = ? AND dibaca IS NULL', [$u['id']])->fetchColumn() : 0;
// Angka di menu petugas: permintaan, pesanan, dan buku yang menunggu dicek (Sirkulasi), usulan baru (Usulan)
$jmlMenunggu = $admin ? (int) q('SELECT COUNT(*) FROM peminjaman WHERE tgl_kembali IS NULL AND (tgl_ambil IS NULL OR tgl_ajuan IS NOT NULL)')->fetchColumn() : 0;
$jmlUsulan = $admin ? (int) q('SELECT COUNT(*) FROM usulan WHERE status = "baru"')->fetchColumn() : 0;
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
<body class="<?= $admin ? 'is-admin' : '' ?>"<?= $live ? ' data-live="' . url('perubahan.php') . '" data-versi="' . versi_data($u) . '"' : '' ?>>
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
      <span class="nav__sep" aria-hidden="true"></span>
      <?php if ($u): ?>
        <?php if ($u['role'] === 'admin'): ?>
          <a href="<?= url('admin/index.php') ?>"<?= $admin ? ' aria-current="page"' : '' ?>>Panel petugas</a>
        <?php else: ?>
          <a href="<?= url('pinjaman.php') ?>"<?= $navAktif('pinjaman') ?>>Pinjaman saya<?php if ($jmlPinjam): ?>
            <span class="nav__badge<?= $jmlTelat ? ' is-late' : '' ?>" title="<?= $jmlPinjam ?> buku dipinjam<?= $jmlTelat ? ", $jmlTelat terlambat" : '' ?>"><?= $jmlPinjam ?><span class="sr-only"> buku dipinjam<?= $jmlTelat ? ", $jmlTelat terlambat" : '' ?></span></span><?php endif ?><?php if ($jmlPesan): ?>
            <span class="nav__badge is-late" title="<?= $jmlPesan ?> pemberitahuan baru">!<span class="sr-only"> <?= $jmlPesan ?> pemberitahuan baru</span></span><?php endif ?></a>
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
      <a href="<?= url('admin/sirkulasi.php') ?>"<?= $navAktif('sirkulasi') ?>>Sirkulasi<?php if ($jmlMenunggu): ?>
        <span class="nav__badge is-late" title="<?= $jmlMenunggu ?> perlu ditangani"><?= $jmlMenunggu ?><span class="sr-only"> perlu ditangani</span></span><?php endif ?></a>
      <a href="<?= url('admin/denda.php') ?>"<?= $navAktif('denda') ?>>Denda</a>
      <a href="<?= url('admin/buku.php') ?>"<?= $navAktif('buku') ?>>Koleksi</a>
      <a href="<?= url('admin/kategori.php') ?>"<?= $navAktif('kategori') ?>>Kategori</a>
      <a href="<?= url('admin/anggota.php') ?>"<?= $navAktif('anggota') ?>>Anggota</a>
      <a href="<?= url('admin/usulan.php') ?>"<?= $navAktif('usulan') ?>>Usulan<?php if ($jmlUsulan): ?>
        <span class="nav__badge" title="<?= $jmlUsulan ?> usulan baru"><?= $jmlUsulan ?><span class="sr-only"> usulan baru</span></span><?php endif ?></a>
      <a href="<?= url('admin/laporan.php') ?>"<?= $navAktif('laporan') ?>>Laporan</a>
      <span class="subnav__status <?= $buka ? 'is-open' : '' ?>"><?= e($statusLayanan) ?></span>
    </div>
  </div>
  <?php endif ?>
</header>

<main id="isi">
<?php if ($f = flash_tampil()): ?><div class="wrap flash"><?= $f ?></div><?php endif ?>
