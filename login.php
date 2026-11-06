<?php
require __DIR__ . '/config.php';

$lanjut = $_GET['lanjut'] ?? $_POST['lanjut'] ?? '';
// Hanya izinkan path lokal agar tidak bisa dipakai untuk open redirect
if (!preg_match('#^/(?!/)#', $lanjut)) $lanjut = '';

$email = '';
$galat = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_cek();
    $email = trim($_POST['email'] ?? '');
    $akun = q('SELECT * FROM users WHERE email = ?', [$email])->fetch();
    if (!$akun || !password_verify($_POST['password'] ?? '', $akun['password'])) {
        $galat = 'Email atau kata sandi tidak cocok.';
    } elseif (!$akun['aktif']) {
        $galat = 'Akun ini sedang dinonaktifkan. Hubungi petugas di meja sirkulasi.';
    } else {
        session_regenerate_id(true);
        unset($akun['password']);
        $_SESSION['user'] = $akun;
        if ($akun['role'] === 'anggota') {
            foreach (pengingat($akun) as [$kls, $pesan]) flash(['late' => 'error', 'soon' => 'info', 'ok' => 'ok'][$kls], $pesan);
        }
        if ($lanjut) { header('Location: ' . $lanjut); exit; }
        redirect($akun['role'] === 'admin' ? 'admin/index.php' : 'akun.php');
    }
}

$judul = 'Masuk';
$menu = 'login';
require __DIR__ . '/partials/header.php';
?>

<div class="wrap auth">
  <?php $tab = 'masuk'; require __DIR__ . '/partials/auth_head.php'; ?>
  <h1 class="sr-only">Masuk</h1>

  <form class="form auth__form" method="post" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="lanjut" value="<?= e($lanjut) ?>">
    <?php if ($galat): ?><div class="notice notice--error" role="alert"><?= e($galat) ?></div><?php endif ?>

    <div class="field">
      <label for="email">Email</label>
      <input id="email" name="email" type="email" value="<?= e($email) ?>" autocomplete="username" required autofocus>
    </div>
    <div class="field">
      <label for="password">Kata sandi</label>
      <div class="pw">
        <input id="password" name="password" type="password" autocomplete="current-password" required>
        <button class="btn-text" type="button" data-toggle-pw="password">Tampilkan</button>
      </div>
    </div>
    <p class="form__note">Lupa kata sandi? Hubungi meja sirkulasi.</p>
    <button class="btn btn--block" type="submit">Masuk</button>
    <p class="form__alt">Belum jadi anggota? <a href="<?= url('daftar.php') ?>">Daftar sekarang</a></p>
  </form>
  </div>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
