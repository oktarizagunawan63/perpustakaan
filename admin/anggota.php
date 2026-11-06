<?php
require __DIR__ . '/../config.php';
wajib_admin();

$galat = [];
$in = ['nama' => '', 'email' => '', 'telepon' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_cek();
    if (isset($_POST['hapus'])) {
        $a = q('SELECT id, nama, foto FROM users WHERE id = ? AND role = "anggota" AND dihapus IS NULL', [(int) $_POST['hapus']])->fetch();
        if ($a) {
            $berjalan = (int) q('SELECT COUNT(*) FROM peminjaman WHERE user_id = ? AND tgl_kembali IS NULL', [$a['id']])->fetchColumn();
            $tunggakan = denda_belum_lunas((int) $a['id']);
            if ($berjalan) {
                flash('error', $a['nama'] . ' masih punya ' . $berjalan . ' pinjaman atau permintaan berjalan. Selesaikan dulu sebelum menghapus.');
            } elseif ($tunggakan) {
                flash('error', $a['nama'] . ' masih punya denda ' . rupiah($tunggakan) . ' yang belum lunas. Lunasi dulu sebelum menghapus.');
            } else {
                db()->beginTransaction();
                foreach (['antrean', 'favorit', 'pemberitahuan'] as $tabel) q("DELETE FROM $tabel WHERE user_id = ?", [$a['id']]);
                // Nama tetap ada untuk riwayat; data kontak dan kunci masuk dibuang, email jadi bisa dipakai daftar lagi
                q('UPDATE users SET aktif = 0, dihapus = NOW(), email = ?, username = NULL, telepon = NULL, foto = NULL, password = ? WHERE id = ?',
                    ['dihapus-' . $a['id'] . '@perpus.invalid', password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT), $a['id']]);
                db()->commit();
                if ($a['foto'] && is_file(DIR_FOTO . $a['foto'])) unlink(DIR_FOTO . $a['foto']);
                flash('ok', $a['nama'] . ' dihapus dari daftar anggota. Riwayat pinjamnya tetap tersimpan di laporan.');
            }
        }
        redirect('admin/anggota.php');
    }

    if (isset($_POST['status'])) {
        $a = q('SELECT nama, aktif FROM users WHERE id = ? AND role = "anggota" AND dihapus IS NULL', [(int) $_POST['status']])->fetch();
        if ($a) {
            q('UPDATE users SET aktif = 1 - aktif WHERE id = ?', [(int) $_POST['status']]);
            flash('ok', $a['nama'] . ($a['aktif'] ? ' dinonaktifkan — tidak bisa masuk atau meminjam.' : ' diaktifkan kembali.'));
        }
        redirect('admin/anggota.php');
    }

    if (isset($_POST['reset_sandi'])) {
        $a = q('SELECT nama FROM users WHERE id = ? AND role = "anggota" AND dihapus IS NULL', [(int) $_POST['reset_sandi']])->fetch();
        if ($a) {
            $sementara = bin2hex(random_bytes(4));
            q('UPDATE users SET password = ? WHERE id = ?', [password_hash($sementara, PASSWORD_DEFAULT), (int) $_POST['reset_sandi']]);
            flash('ok', 'Kata sandi sementara ' . $a['nama'] . ': ' . $sementara . ' — catat sekarang, pesan ini tampil sekali. Minta anggota menggantinya di Akun saya.');
        }
        redirect('admin/anggota.php');
    }

    $in = array_merge($in, array_intersect_key($_POST, $in));
    [$akun, $galat] = daftarkan_anggota($_POST);
    if ($akun) {
        flash('ok', $akun['nama'] . ' terdaftar dengan nomor ' . $akun['no_anggota'] . '.');
        redirect('admin/anggota.php');
    }
}

$cari = trim($_GET['q'] ?? '');
$args = $cari !== '' ? array_fill(0, 4, "%$cari%") : [];
$daftar = q('SELECT u.*,
               (SELECT COUNT(*) FROM peminjaman p WHERE p.user_id = u.id AND p.tgl_kembali IS NULL) aktif_pinjam,
               (SELECT COUNT(*) FROM peminjaman p WHERE p.user_id = u.id AND p.tgl_kembali IS NULL AND p.tgl_ajuan IS NULL AND p.format = "fisik" AND p.jatuh_tempo < CURDATE()) telat
             FROM users u WHERE u.role = "anggota" AND u.dihapus IS NULL '
    . ($cari !== '' ? 'AND (u.nama LIKE ? OR u.email LIKE ? OR u.no_anggota LIKE ? OR u.username LIKE ?) ' : '') . 'ORDER BY u.nama', $args)->fetchAll();

$err = fn(string $f) => isset($galat[$f]) ? '<p class="field__error">' . e($galat[$f]) . '</p>' : '';
$inv = fn(string $f) => isset($galat[$f]) ? ' aria-invalid="true"' : '';

$judul = 'Anggota';
$admin = true;
$menu = 'anggota';
$live = true;
require __DIR__ . '/../partials/header.php';
?>

<div class="wrap admin">
  <div class="admin__head">
    <div><h1>Anggota</h1><p class="muted"><?= count($daftar) ?> anggota<?= $cari !== '' ? ' cocok dengan “' . e($cari) . '”' : '' ?></p></div>
  </div>

  <div class="circ circ--reverse">
    <section class="panel circ__list">
      <form class="search search--sm panel__search" role="search">
        <label class="sr-only" for="q">Cari anggota</label>
        <input id="q" name="q" type="search" value="<?= e($cari) ?>" placeholder="Nama, email, username, nomor">
        <button class="btn btn--sm btn--ghost">Cari</button>
      </form>
      <div class="table-wrap">
        <table class="table table--stack">
          <thead><tr><th>Anggota</th><th>Kontak</th><th class="num">Pinjam</th><th>Status</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($daftar as $a): ?>
            <tr class="<?= $a['aktif'] ? '' : 'is-muted' ?>">
              <td><div class="person"><?= avatar($a, 'md') ?><div><?= e($a['nama']) ?><span class="sub mono"><?= e($a['no_anggota']) ?></span><?php if ($a['username']): ?><span class="sub">@<?= e($a['username']) ?></span><?php endif ?></div></div></td>
              <td><?= e($a['email']) ?><span class="sub"><?= e($a['telepon'] ?? '—') ?></span></td>
              <td class="num" data-label="Pinjam"><?= (int) $a['aktif_pinjam'] ?: '—' ?><?= $a['telat'] ? ' <span class="tag tag--late">' . (int) $a['telat'] . ' telat</span>' : '' ?></td>
              <td><?= $a['aktif'] ? '<span class="tag tag--accent">Aktif</span>' : '<span class="tag">Nonaktif</span>' ?></td>
              <td class="actions actions--stack">
                <form method="post" data-confirm="Reset kata sandi <?= e($a['nama']) ?>? Kata sandi lamanya tidak berlaku lagi.">
                  <?= csrf_field() ?>
                  <button class="btn btn--sm btn--ghost" name="reset_sandi" value="<?= $a['id'] ?>">Reset sandi</button>
                </form>
                <form method="post"<?= $a['aktif'] ? ' data-confirm="Nonaktifkan ' . e($a['nama']) . '? Anggota tidak bisa masuk atau meminjam sampai diaktifkan lagi."' : '' ?>>
                  <?= csrf_field() ?>
                  <button class="btn btn--sm btn--ghost" name="status" value="<?= $a['id'] ?>"><?= $a['aktif'] ? 'Nonaktifkan' : 'Aktifkan' ?></button>
                </form>
                <form method="post" data-confirm="Hapus <?= e($a['nama']) ?>? Akunnya tidak bisa dipakai lagi. Riwayat pinjamnya tetap tersimpan untuk laporan.">
                  <?= csrf_field() ?>
                  <button class="btn btn--sm btn--ghost btn--hapus" name="hapus" value="<?= $a['id'] ?>">Hapus</button>
                </form>
              </td>
            </tr>
          <?php endforeach ?>
          <?php if (!$daftar): ?><tr><td colspan="5" class="muted">Tidak ada anggota yang cocok.</td></tr><?php endif ?>
          </tbody>
        </table>
      </div>
    </section>

    <section class="panel circ__form">
      <h2 class="panel__title">Daftarkan di tempat</h2>
      <p class="muted small">Untuk anggota yang mendaftar langsung di meja layanan.</p>
      <?= keterangan_wajib() ?>
      <form method="post" class="form" novalidate>
        <?= csrf_field() ?>
        <div class="field"><label for="nama">Nama lengkap <?= wajib() ?></label><input id="nama" name="nama" value="<?= e($in['nama']) ?>" required<?= $inv('nama') ?>><?= $err('nama') ?></div>
        <div class="field"><label for="email">Email <?= wajib() ?></label><input id="email" name="email" type="email" value="<?= e($in['email']) ?>" required<?= $inv('email') ?>><?= $err('email') ?></div>
        <div class="field"><label for="telepon">No. WhatsApp <?= opsional() ?></label><input id="telepon" name="telepon" type="tel" value="<?= e($in['telepon']) ?>"<?= $inv('telepon') ?>><?= $err('telepon') ?></div>
        <div class="field"><label for="password">Kata sandi awal <?= wajib() ?></label><input id="password" name="password" type="text" autocomplete="off" required<?= $inv('password') ?>>
          <?= $err('password') ?: '<p class="field__hint">Minimal 8 karakter. Sampaikan langsung ke anggota.</p>' ?></div>
        <button class="btn btn--block" type="submit">Daftarkan anggota</button>
      </form>
    </section>
  </div>
</div>

<?php require __DIR__ . '/../partials/footer.php'; ?>
