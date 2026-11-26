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
<link rel="stylesheet" href="<?= url('assets/style.css?v=2') ?>">
<script src="<?= url('assets/app.js?v=2') ?>" defer></script>
</head>
<body class="<?= $admin ? 'is-admin' : '' ?>"<?= $live ? ' data-live="' . url('perubahan.php') . '" data-versi="' . versi_data($u) . '"' : '' ?>>
<a class="skip" href="#isi">Langsung ke konten</a>

<header class="topbar">
  <div class="wrap topbar__in">
    <?php if (basename($_SERVER['SCRIPT_NAME']) !== 'index.php'): ?>
      <a class="back" href="<?= url($admin ? 'admin/index.php' : 'index.php') ?>" data-back aria-label="Kembali ke halaman sebelumnya">
        <svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
        <span>Kembali</span>
      </a>
    <?php endif ?>
    <a class="brand" href="<?= url('index.php') ?>">
      <?= logo('brand__mark') ?>
      <span class="brand__text">Perpustakaan<small>Kampus</small></span>
    </a>

    <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="nav">Menu</button>

    <nav class="nav" id="nav" aria-label="Utama">
      <a href="<?= url('katalog.php') ?>"<?= $navAktif('katalog') ?>>Katalog</a>
      <a href="<?= url('katalog.php#cara-pinjam') ?>">Cara meminjam</a>
      <span class="nav__sep" aria-hidden="true"></span>
      <?php if ($u): ?>
        <?php if ($u['role'] === 'admin'): ?>
          <a href="<?= url('admin/index.php') ?>"<?= $admin ? ' aria-current="page"' : '' ?>>Panel petugas</a>
        <?php else: ?>
          <a href="<?= url('pinjaman.php') ?>"<?= $navAktif('pinjaman') ?>>Pinjaman saya<?php if ($jmlPinjam): ?>
            <span class="nav__badge<?= $jmlTelat ? ' is-late' : '' ?>" title="<?= $jmlPinjam ?> buku dipinjam<?= $jmlTelat ? ", $jmlTelat terlambat" : '' ?>"><?= $jmlPinjam ?><span class="sr-only"> buku dipinjam<?= $jmlTelat ? ", $jmlTelat terlambat" : '' ?></span></span><?php endif ?><?php if ($jmlPesan): ?>
            <span class="nav__badge is-late" title="<?= $jmlPesan ?> pemberitahuan baru">!<span class="sr-only"> <?= $jmlPesan ?> pemberitahuan baru</span></span><?php endif ?></a>
          <a class="nav__akun" href="<?= url('akun.php') ?>"<?= $navAktif('akun') ?>><?= avatar($u) ?><?= e(!empty($u['username']) ? '@' . $u['username'] : 'Akun saya') ?></a>
        <?php endif ?>
        <a href="<?= url('logout.php') ?>">Keluar</a>
      <?php else: ?>
        <a href="<?= url('login.php') ?>"<?= $navAktif('login') ?>>Masuk</a>
        <a class="btn btn--sm btn--light" href="<?= url('daftar.php') ?>">Daftar anggota</a>
      <?php endif ?>
    </nav>
  </div>

  <?php if ($admin): ?>
  <aside class="admin-sidebar">
    <div class="admin-sidebar__header">
      <span class="admin-sidebar__title">Panel Admin</span>
      <span class="admin-sidebar__user"><?= avatar($u, 'sm') ?><?= e($u['nama']) ?></span>
    </div>
    <nav class="admin-sidebar__nav" aria-label="Menu admin">
      <a href="<?= url('admin/index.php') ?>"<?= $navAktif('dash') ? ' class="is-active"' : '' ?>>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
        Ringkasan
      </a>
      <a href="<?= url('admin/sirkulasi.php') ?>"<?= $navAktif('sirkulasi') ? ' class="is-active"' : '' ?>>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 3h5v5M4 20L21 3M21 16v5h-5M15 15l6 6M4 4l5 5"/></svg>
        Peminjaman & Pengembalian
        <?php if ($jmlMenunggu): ?><span class="badge badge--alert"><?= $jmlMenunggu ?></span><?php endif ?>
      </a>
      <a href="<?= url('admin/denda.php') ?>"<?= $navAktif('denda') ? ' class="is-active"' : '' ?>>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
        Denda
      </a>
      <a href="<?= url('admin/buku.php') ?>"<?= $navAktif('buku') ? ' class="is-active"' : '' ?>>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
        Koleksi Buku
      </a>
      <a href="<?= url('admin/kategori.php') ?>"<?= $navAktif('kategori') ? ' class="is-active"' : '' ?>>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
        Kategori
      </a>
      <a href="<?= url('admin/anggota.php') ?>"<?= $navAktif('anggota') ? ' class="is-active"' : '' ?>>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        Anggota
      </a>
      <a href="<?= url('admin/usulan.php') ?>"<?= $navAktif('usulan') ? ' class="is-active"' : '' ?>>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M17 8l-5-5-5 5M12 3v12"/></svg>
        Usulan Buku
        <?php if ($jmlUsulan): ?><span class="badge"><?= $jmlUsulan ?></span><?php endif ?>
      </a>
      <a href="<?= url('admin/laporan.php') ?>"<?= $navAktif('laporan') ? ' class="is-active"' : '' ?>>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
        Laporan
      </a>
      <div class="admin-sidebar__status">
        <span class="status-dot <?= $buka ? 'is-open' : '' ?>"></span>
        <?= e($statusLayanan) ?>
      </div>
    </nav>
  </aside>
  <?php endif ?>
</header>

<main id="isi">
<?php if ($f = flash_tampil()): ?><div class="wrap flash"><?= $f ?></div><?php endif ?>
