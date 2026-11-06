<?php /** @var string $tab 'masuk' | 'daftar' */ ?>
<aside class="auth__splash" aria-hidden="true">
  <?= logo('auth__splash-logo') ?>
  <p class="auth__splash-name">Perpustakaan</p>
  <p class="auth__splash-tag">Satu kartu anggota untuk semua koleksi.</p>
  <a class="btn btn--light" href="<?= url('katalog.php') ?>" tabindex="-1">Lihat katalog</a>
</aside>

<div class="auth__card">
  <div class="auth__photo">
    <?= logo('auth__logo') ?>
    <span class="auth__name">Perpustakaan</span>
    <span class="auth__app">Layanan Anggota</span>
  </div>
  <nav class="tabs" aria-label="Akun">
    <a href="<?= url('login.php') ?>"<?= $tab === 'masuk' ? ' aria-current="page"' : '' ?>>Masuk</a>
    <a href="<?= url('daftar.php') ?>"<?= $tab === 'daftar' ? ' aria-current="page"' : '' ?>>Daftar</a>
  </nav>
