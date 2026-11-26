<?php
require __DIR__ . '/config.php';

// Kalau udah login, redirect ke dashboard
if (user()) {
    redirect(user()['role'] === 'admin' ? 'admin/index.php' : 'index.php');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_cek();
    
    $email = trim($_POST['email'] ?? '');
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Format email tidak valid.';
    } else {
        // Cek apakah email terdaftar
        $user = q('SELECT id, nama, email FROM users WHERE email = ?', [$email])->fetch();
        
        if ($user) {
            // Generate token
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // Hapus token lama untuk user ini
            q('DELETE FROM password_resets WHERE user_id = ?', [$user['id']]);
            
            // Simpan token baru
            q('INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)', 
              [$user['id'], $token, $expires]);
            
            // Kirim email
            $resetLink = url('reset-password.php?token=' . $token);
            $subject = 'Reset Password - ' . NAMA_PERPUS;
            $message = "Halo " . $user['nama'] . ",\n\n";
            $message .= "Anda menerima email ini karena ada permintaan reset password untuk akun Anda.\n\n";
            $message .= "Klik link berikut untuk reset password:\n";
            $message .= $resetLink . "\n\n";
            $message .= "Link ini akan kadaluarsa dalam 1 jam.\n\n";
            $message .= "Jika Anda tidak meminta reset password, abaikan email ini.\n\n";
            $message .= "Terima kasih,\n";
            $message .= NAMA_PERPUS;
            
            $headers = "From: noreply@perpustakaan.local\r\n";
            $headers .= "Reply-To: noreply@perpustakaan.local\r\n";
            $headers .= "X-Mailer: PHP/" . phpversion();
            
            if (mail($user['email'], $subject, $message, $headers)) {
                $success = 'Link reset password telah dikirim ke email Anda. Silakan cek inbox atau folder spam.';
            } else {
                $error = 'Gagal mengirim email. Silakan hubungi admin.';
            }
        } else {
            // Jangan kasih tau kalau email ga ada (security)
            $success = 'Jika email terdaftar, link reset password akan dikirim ke email Anda.';
        }
    }
}

$judul = 'Lupa Kata Sandi';
require __DIR__ . '/partials/auth_head.php';
?>

<div class="auth-page">
  <div class="auth-card">
    <div class="auth-card__logo"><?= logo() ?></div>
    <h1>Lupa Kata Sandi?</h1>
    <p class="auth-card__desc">Masukkan email Anda, kami akan mengirimkan link untuk reset kata sandi.</p>

    <?php if ($error): ?>
      <div class="notice notice--error"><?= e($error) ?></div>
    <?php endif ?>

    <?php if ($success): ?>
      <div class="notice notice--ok"><?= e($success) ?></div>
      <a class="btn btn--block" href="<?= url('login.php') ?>">Kembali ke Login</a>
    <?php else: ?>
      <form class="form" method="post">
        <?= csrf_field() ?>
        
        <div class="field">
          <label for="email">Email <?= wajib() ?></label>
          <input 
            id="email" 
            name="email" 
            type="email" 
            value="<?= e($_POST['email'] ?? '') ?>" 
            required 
            autofocus
            placeholder="nama@email.com">
        </div>

        <?= keterangan_wajib() ?>

        <button class="btn btn--block" type="submit">Kirim Link Reset</button>
      </form>

      <p class="auth-card__links">
        <a href="<?= url('login.php') ?>">Kembali ke Login</a>
        <span>·</span>
        <a href="<?= url('daftar.php') ?>">Daftar Anggota</a>
      </p>
    <?php endif ?>
  </div>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
