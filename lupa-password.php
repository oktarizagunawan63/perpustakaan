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
            // Generate code 6 digit
            $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $expires = date('Y-m-d H:i:s', strtotime('+15 minutes'));
            
            // Hapus code lama untuk user ini
            q('DELETE FROM password_resets WHERE user_id = ?', [$user['id']]);
            
            // Simpan code baru
            q('INSERT INTO password_resets (user_id, code, expires_at) VALUES (?, ?, ?)', 
              [$user['id'], $code, $expires]);
            
            // Kirim email
            $subject = 'Kode Reset Password - ' . NAMA_PERPUS;
            $message = "Halo " . $user['nama'] . ",\n\n";
            $message .= "Kode reset password Anda adalah:\n\n";
            $message .= "    " . $code . "\n\n";
            $message .= "Kode ini akan kadaluarsa dalam 15 menit.\n\n";
            $message .= "Jika Anda tidak meminta reset password, abaikan email ini.\n\n";
            $message .= "Terima kasih,\n";
            $message .= NAMA_PERPUS;
            
            $headers = "From: noreply@perpustakaan.local\r\n";
            $headers .= "Reply-To: noreply@perpustakaan.local\r\n";
            $headers .= "X-Mailer: PHP/" . phpversion();
            
            // Untuk development, tampilkan code (nanti hapus di production)
            if (mail($user['email'], $subject, $message, $headers)) {
                $_SESSION['reset_email'] = $email;
                redirect('reset-password.php');
            } else {
                // Kalau email gagal, kasih tau code-nya (development only)
                $error = 'Gagal mengirim email. Kode reset Anda: ' . $code . ' (berlaku 15 menit)';
            }
        } else {
            // Jangan kasih tau kalau email ga ada (security)
            $_SESSION['reset_email'] = $email;
            redirect('reset-password.php');
        }
    }
}

$judul = 'Lupa Kata Sandi';
$polos = true; // Tanpa footer
require __DIR__ . '/partials/header.php';
?>

<div class="auth-page">
  <div class="auth-card">
    <div class="auth-card__logo"><?= logo() ?></div>
    <h1>Lupa Kata Sandi?</h1>
    <p class="auth-card__desc">Masukkan email Anda, kami akan mengirimkan link untuk reset kata sandi.</p>

    <?php if ($error): ?>
      <div class="notice notice--error"><?= e($error) ?></div>
    <?php endif ?>

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
  </div>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
