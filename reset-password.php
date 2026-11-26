<?php
require __DIR__ . '/config.php';

// Kalau udah login, redirect
if (user()) {
    redirect(user()['role'] === 'admin' ? 'admin/index.php' : 'index.php');
}

$token = $_GET['token'] ?? '';
$error = '';
$success = false;

// Validasi token
$reset = null;
if ($token) {
    $reset = q('SELECT pr.*, u.nama, u.email FROM password_resets pr 
                JOIN users u ON u.id = pr.user_id 
                WHERE pr.token = ? AND pr.expires_at > NOW()', [$token])->fetch();
    
    if (!$reset) {
        $error = 'Link reset password tidak valid atau sudah kadaluarsa.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $reset) {
    csrf_cek();
    
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    
    if (strlen($password) < 8) {
        $error = 'Password minimal 8 karakter.';
    } elseif ($password !== $password_confirm) {
        $error = 'Konfirmasi password tidak cocok.';
    } else {
        // Update password
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        q('UPDATE users SET password = ? WHERE id = ?', [$hashed, $reset['user_id']]);
        
        // Hapus token yang sudah dipakai
        q('DELETE FROM password_resets WHERE user_id = ?', [$reset['user_id']]);
        
        // Hapus token yang sudah expired (cleanup)
        q('DELETE FROM password_resets WHERE expires_at < NOW()');
        
        $success = true;
    }
}

$judul = 'Reset Kata Sandi';
require __DIR__ . '/partials/auth_head.php';
?>

<div class="auth-page">
  <div class="auth-card">
    <div class="auth-card__logo"><?= logo() ?></div>
    <h1>Reset Kata Sandi</h1>

    <?php if ($success): ?>
      <div class="notice notice--ok">Password Anda berhasil diubah. Silakan login dengan password baru.</div>
      <a class="btn btn--block" href="<?= url('login.php') ?>">Login Sekarang</a>
    <?php elseif ($error): ?>
      <div class="notice notice--error"><?= e($error) ?></div>
      <?php if (!$reset): ?>
        <p>Link reset password tidak valid atau sudah kadaluarsa. Silakan minta link reset yang baru.</p>
        <a class="btn btn--block btn--ghost" href="<?= url('lupa-password.php') ?>">Minta Link Baru</a>
      <?php endif ?>
    <?php else: ?>
      <p class="auth-card__desc">Masukkan password baru untuk akun <strong><?= e($reset['email']) ?></strong></p>

      <form class="form" method="post">
        <?= csrf_field() ?>
        
        <div class="field">
          <label for="password">Password Baru <?= wajib() ?></label>
          <input 
            id="password" 
            name="password" 
            type="password" 
            required 
            autofocus
            minlength="8"
            placeholder="Minimal 8 karakter">
        </div>

        <div class="field">
          <label for="password_confirm">Konfirmasi Password <?= wajib() ?></label>
          <input 
            id="password_confirm" 
            name="password_confirm" 
            type="password" 
            required
            minlength="8"
            placeholder="Ketik ulang password baru">
        </div>

        <?= keterangan_wajib() ?>

        <button class="btn btn--block" type="submit">Ubah Password</button>
      </form>

      <p class="auth-card__links">
        <a href="<?= url('login.php') ?>">Kembali ke Login</a>
      </p>
    <?php endif ?>
  </div>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
