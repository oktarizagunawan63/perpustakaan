<?php
require __DIR__ . '/config.php';

if (user()) redirect('akun.php');

$in = ['nama' => '', 'email' => '', 'telepon' => ''];
$galat = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_cek();
    $in = array_merge($in, array_intersect_key($_POST, $in));
    if (($_POST['password'] ?? '') !== ($_POST['password2'] ?? '')) {
        $galat['password2'] = 'Kedua kata sandi belum sama.';
    } else {
        [$akun, $galat] = daftarkan_anggota($_POST);
        if ($akun) {
            session_regenerate_id(true);
            unset($akun['password']);
            $_SESSION['user'] = $akun;
            flash('ok', 'Selamat datang! Nomor anggotamu ' . $akun['no_anggota'] . '. Sekarang kamu bisa langsung meminjam buku dari katalog.');
            redirect('akun.php');
        }
    }
}

$err = fn(string $f) => isset($galat[$f]) ? '<p class="field__error" id="' . $f . '-err">' . e($galat[$f]) . '</p>' : '';
$aria = fn(string $f) => isset($galat[$f]) ? ' aria-invalid="true" aria-describedby="' . $f . '-err"' : '';

$judul = 'Daftar anggota';
require __DIR__ . '/partials/header.php';
?>

<div class="wrap auth">
  <?php $tab = 'daftar'; require __DIR__ . '/partials/auth_head.php'; ?>
  <h1 class="sr-only">Daftar anggota</h1>

  <form class="form auth__form" method="post" novalidate>
    <p class="form__lead">Gratis untuk mahasiswa, dosen, dan staf. Nomor anggota langsung terbit — pinjam hingga <?= MAKS_PINJAM ?> buku selama <?= min(PILIHAN_LAMA) ?>–<?= max(PILIHAN_LAMA) ?> hari.</p>
    <?= csrf_field() ?>
    <?php if ($galat): ?><div class="notice notice--error" role="alert">Ada <?= count($galat) ?> isian yang perlu diperbaiki.</div><?php endif ?>

    <div class="field">
      <label for="nama">Nama lengkap</label>
      <input id="nama" name="nama" value="<?= e($in['nama']) ?>" autocomplete="name" required<?= $aria('nama') ?>>
      <?= $err('nama') ?>
    </div>
    <div class="field">
      <label for="email">Email</label>
      <input id="email" name="email" type="email" value="<?= e($in['email']) ?>" autocomplete="email" required<?= $aria('email') ?>>
      <?= $err('email') ?>
    </div>
    <div class="field">
      <label for="telepon">No. WhatsApp <span class="opt">opsional</span></label>
      <input id="telepon" name="telepon" type="tel" inputmode="tel" value="<?= e($in['telepon']) ?>" autocomplete="tel"<?= $aria('telepon') ?>>
      <p class="field__hint">Untuk pengingat sebelum jatuh tempo.</p>
      <?= $err('telepon') ?>
    </div>
    <div class="field-row">
      <div class="field">
        <label for="password">Kata sandi</label>
        <input id="password" name="password" type="password" minlength="8" autocomplete="new-password" required<?= $aria('password') ?>>
        <?= $err('password') ?: '<p class="field__hint">Minimal 8 karakter.</p>' ?>
      </div>
      <div class="field">
        <label for="password2">Ulangi kata sandi</label>
        <input id="password2" name="password2" type="password" autocomplete="new-password" required<?= $aria('password2') ?>>
        <?= $err('password2') ?>
      </div>
    </div>
    <button class="btn btn--block" type="submit">Buat akun anggota</button>
    <p class="form__alt">Sudah punya akun? <a href="<?= url('login.php') ?>">Masuk</a></p>
  </form>
  </div>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
