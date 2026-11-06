<?php
require __DIR__ . '/config.php';

$u = wajib_login();
if ($u['role'] === 'admin') redirect('admin/index.php');

$galat = [];
$masukan = [];
$usulanBaru = ['judul' => '', 'penulis' => '', 'alasan' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_cek();

    if (isset($_POST['hapus_favorit'])) {
        q('DELETE FROM favorit WHERE user_id = ? AND buku_id = ?', [$u['id'], (int) $_POST['hapus_favorit']]);
        flash('ok', 'Dihapus dari daftar baca.');
        redirect('akun.php#daftar-baca');
    }

    if (isset($_POST['usulan'])) {
        $usulanBaru = array_map('trim', array_merge($usulanBaru, array_intersect_key($_POST, $usulanBaru)));
        if (mb_strlen($usulanBaru['judul']) < 3 || mb_strlen($usulanBaru['judul']) > 200) $galat['usulan_judul'] = 'Isi judul buku (3–200 karakter).';
        if (mb_strlen($usulanBaru['penulis']) > 150) $galat['usulan_penulis'] = 'Nama penulis maksimal 150 karakter.';
        if (mb_strlen($usulanBaru['alasan']) > 1000) $galat['usulan_alasan'] = 'Alasan maksimal 1.000 karakter.';
        if (!$galat) {
            q('INSERT INTO usulan (user_id, judul, penulis, alasan) VALUES (?, ?, ?, ?)',
                [$u['id'], $usulanBaru['judul'], $usulanBaru['penulis'] ?: null, $usulanBaru['alasan'] ?: null]);
            flash('ok', 'Usulan “' . $usulanBaru['judul'] . '” terkirim ke petugas. Pantau statusnya di bawah.');
            redirect('akun.php#usulan');
        }
    }

    if (isset($_POST['ganti_foto'])) {
        [$fotoBaru, $galatFoto] = simpan_foto($_FILES['foto'] ?? ['error' => UPLOAD_ERR_NO_FILE]);
        if ($galatFoto) {
            $galat['foto'] = $galatFoto;
        } elseif ($fotoBaru) {
            $fotoLama = q('SELECT foto FROM users WHERE id = ?', [$u['id']])->fetchColumn();
            q('UPDATE users SET foto = ? WHERE id = ?', [$fotoBaru, $u['id']]);
            if ($fotoLama && is_file(DIR_FOTO . $fotoLama)) unlink(DIR_FOTO . $fotoLama);
            $_SESSION['user']['foto'] = $fotoBaru;
            flash('ok', 'Foto profil diperbarui.');
            redirect('akun.php');
        }
    }

    if (isset($_POST['hapus_foto'])) {
        $fotoLama = q('SELECT foto FROM users WHERE id = ?', [$u['id']])->fetchColumn();
        q('UPDATE users SET foto = NULL WHERE id = ?', [$u['id']]);
        if ($fotoLama && is_file(DIR_FOTO . $fotoLama)) unlink(DIR_FOTO . $fotoLama);
        $_SESSION['user']['foto'] = null;
        flash('ok', 'Foto profil dihapus.');
        redirect('akun.php#profil');
    }

    if (isset($_POST['profil'])) {
        [$masukan, $galat] = validasi_anggota($_POST, (int) $u['id']);
        if (!$galat) {
            q('UPDATE users SET nama = ?, email = ?, username = ?, telepon = ? WHERE id = ?',
                [$masukan['nama'], $masukan['email'], $masukan['username'] ?: null, $masukan['telepon'] ?: null, $u['id']]);
            $_SESSION['user'] = array_merge($u, $masukan);
            flash('ok', 'Profil diperbarui.');
            redirect('akun.php#profil');
        }
    }

    if (isset($_POST['sandi'])) {
        $hash = q('SELECT password FROM users WHERE id = ?', [$u['id']])->fetchColumn();
        $baru = $_POST['sandi_baru'] ?? '';
        if (($_POST['sandi_lama'] ?? '') === '') $galat['sandi_lama'] = 'Kata sandi saat ini wajib diisi.';
        elseif (!password_verify($_POST['sandi_lama'], $hash)) $galat['sandi_lama'] = 'Kata sandi saat ini salah.';
        if (strlen($baru) < 8) $galat['sandi_baru'] = 'Kata sandi baru minimal 8 karakter.';
        elseif ($baru !== ($_POST['sandi_ulang'] ?? '')) $galat['sandi_ulang'] = 'Ulangi kata sandi baru dengan persis sama.';
        if (!$galat) {
            q('UPDATE users SET password = ? WHERE id = ?', [password_hash($baru, PASSWORD_DEFAULT), $u['id']]);
            flash('ok', 'Kata sandi diganti. Pakai kata sandi baru saat masuk berikutnya.');
            redirect('akun.php#profil');
        }
    }
}

$akun = q('SELECT * FROM users WHERE id = ?', [$u['id']])->fetch();
$r = q('SELECT
          COALESCE(SUM(tgl_kembali IS NULL), 0) aktif,
          COALESCE(SUM(tgl_kembali IS NULL AND tgl_ajuan IS NULL AND format = "fisik" AND jatuh_tempo < CURDATE()), 0) telat,
          COUNT(*) total,
          COALESCE(SUM(denda + denda_kondisi), 0) denda_tercatat
        FROM peminjaman WHERE user_id = ?', [$u['id']])->fetch();
$favorit = q('SELECT b.*, b.stok - ' . SQL_DIPINJAM . ' AS tersedia FROM favorit f JOIN buku b ON b.id = f.buku_id
              WHERE f.user_id = ? ORDER BY f.dibuat DESC, b.judul', [$u['id']])->fetchAll();
$usulan = q('SELECT * FROM usulan WHERE user_id = ? ORDER BY dibuat DESC, id DESC', [$u['id']])->fetchAll();

$profil = ($masukan ?: $akun);
$err = fn(string $f) => isset($galat[$f]) ? '<p class="field__error">' . e($galat[$f]) . '</p>' : '';
$inv = fn(string $f) => isset($galat[$f]) ? ' aria-invalid="true"' : '';
$labelUsulan = ['baru' => ['', 'Terkirim'], 'diproses' => ['tag--accent', 'Diproses'], 'ditambahkan' => ['tag--accent', 'Ditambahkan'], 'ditolak' => ['tag--late', 'Ditolak']];

$judul = 'Akun saya';
$menu = 'akun';
$live = true;
require __DIR__ . '/partials/header.php';
?>

<div class="wrap account">
  <header class="account__head">
    <div>
      <p class="eyebrow">Akun anggota</p>
      <div class="account__hello"><?= avatar($akun, 'lg') ?><h1>Halo, <?= e(explode(' ', $akun['nama'])[0]) ?>.</h1></div>
      <p class="muted">
        <?php if (!$r['aktif']): ?>Tidak ada buku yang sedang kamu pinjam.
        <?php else: ?>Kamu sedang meminjam <?= (int) $r['aktif'] ?> buku<?= $r['telat'] ? ', ' . (int) $r['telat'] . ' di antaranya terlambat' : '' ?>.<?php endif ?>
      </p>
      <a class="btn" href="<?= url('pinjaman.php') ?>">Lihat pinjaman saya</a>
    </div>

    <div class="member-card" aria-label="Kartu anggota">
      <span class="member-card__org"><?= e(NAMA_PERPUS) ?></span>
      <span class="member-card__name"><?= e($akun['nama']) ?><?php if ($akun['username']): ?><small>@<?= e($akun['username']) ?></small><?php endif ?></span>
      <span class="member-card__no"><?= e($akun['no_anggota']) ?></span>
      <span class="member-card__bars" aria-hidden="true"></span>
    </div>
  </header>

  <dl class="stats stats--3">
    <div><dt>Sedang dipinjam</dt><dd><?= (int) $r['aktif'] ?><small> / <?= MAKS_PINJAM ?></small></dd><span><a href="<?= url('pinjaman.php') ?>">Lihat daftarnya</a></span></div>
    <div class="<?= $r['telat'] ? 'is-alert' : '' ?>"><dt>Terlambat</dt><dd><?= (int) $r['telat'] ?></dd><span><?= $r['telat'] ? 'Segera kembalikan' : 'Semua tepat waktu' ?></span></div>
    <div><dt>Total pernah dipinjam</dt><dd><?= (int) $r['total'] ?></dd><span>Denda tercatat <?= rupiah((int) $r['denda_tercatat']) ?></span></div>
  </dl>

  <nav class="jump" aria-label="Bagian halaman akun">
    <a href="#daftar-baca">Daftar baca</a>
    <a href="#usulan">Usulan buku</a>
    <a href="#profil">Profil &amp; kata sandi</a>
  </nav>

  <section class="section section--tight" id="daftar-baca">
    <h2>Daftar baca</h2>
    <?php if (!$favorit): ?>
      <div class="empty empty--inline">
        <p>Belum ada buku tersimpan. Buka halaman buku, lalu klik “Simpan ke daftar baca”.</p>
        <a class="btn btn--ghost" href="<?= url('katalog.php') ?>">Buka katalog</a>
      </div>
    <?php else: ?>
      <ul class="loans">
        <?php foreach ($favorit as $f): ?>
          <li class="loan">
            <?= sampul($f, 'sm') ?>
            <div class="loan__body">
              <h3><a href="<?= url('buku.php?id=' . $f['id']) ?>"><?= e($f['judul']) ?></a></h3>
              <p class="muted"><?= e($f['penulis']) ?></p>
              <p class="loan__dates">
                <?php if ($f['stok'] > 0): ?><?= $f['tersedia'] > 0 ? 'Buku fisik tersedia ' . (int) $f['tersedia'] : 'Buku fisik sedang dipinjam semua' ?><?php endif ?>
                <?= $f['stok'] > 0 && $f['file_pdf'] ? ' · ' : '' ?><?= $f['file_pdf'] ? 'Ada e-book' : '' ?>
              </p>
            </div>
            <div class="loan__side">
              <a class="btn btn--sm" href="<?= url('buku.php?id=' . $f['id']) ?>">Lihat buku</a>
              <form method="post">
                <?= csrf_field() ?>
                <button class="btn-text loan__return" name="hapus_favorit" value="<?= $f['id'] ?>">Hapus dari daftar</button>
              </form>
            </div>
          </li>
        <?php endforeach ?>
      </ul>
    <?php endif ?>
  </section>

  <section class="section section--tight" id="usulan">
    <h2>Usulan buku</h2>
    <p class="muted">Buku yang kamu cari belum ada di katalog? Usulkan ke petugas.</p>
    <div class="account__forms">
      <form class="form panel" method="post" novalidate>
        <?= csrf_field() ?>
        <?= keterangan_wajib() ?>
        <div class="field"><label for="u-judul">Judul buku <?= wajib() ?></label>
          <input id="u-judul" name="judul" value="<?= e($usulanBaru['judul']) ?>" maxlength="200" required<?= $inv('usulan_judul') ?>><?= $err('usulan_judul') ?></div>
        <div class="field"><label for="u-penulis">Penulis <?= opsional() ?></label>
          <input id="u-penulis" name="penulis" value="<?= e($usulanBaru['penulis']) ?>" maxlength="150"><?= $err('usulan_penulis') ?></div>
        <div class="field"><label for="u-alasan">Kenapa buku ini perlu ada? <?= opsional() ?></label>
          <textarea id="u-alasan" name="alasan" rows="3" maxlength="1000"><?= e($usulanBaru['alasan']) ?></textarea><?= $err('usulan_alasan') ?></div>
        <button class="btn" name="usulan" value="1">Kirim usulan</button>
      </form>

      <div class="panel">
        <h3 class="panel__title">Usulan saya <span class="count"><?= count($usulan) ?></span></h3>
        <?php if (!$usulan): ?>
          <p class="muted">Belum ada usulan.</p>
        <?php else: ?>
          <ul class="mini-list">
            <?php foreach ($usulan as $s): [$kelas, $label] = $labelUsulan[$s['status']]; ?>
              <li>
                <div><strong><?= e($s['judul']) ?></strong>
                  <span class="sub"><?= e($s['penulis'] ?: 'Penulis tidak diisi') ?> · <?= tgl($s['dibuat']) ?></span>
                  <?php if ($s['catatan']): ?><span class="sub">Petugas: <?= e($s['catatan']) ?></span><?php endif ?></div>
                <div class="mini-list__end"><span class="tag <?= $kelas ?>"><?= $label ?></span></div>
              </li>
            <?php endforeach ?>
          </ul>
        <?php endif ?>
      </div>
    </div>
  </section>

  <section class="section section--tight" id="profil">
    <h2>Profil &amp; kata sandi</h2>
    <p class="muted">Nomor anggota <span class="mono"><?= e($akun['no_anggota']) ?></span> · anggota sejak <?= tgl($akun['dibuat'], true) ?></p>
    <?php // Form foto terpisah: tersimpan begitu dipilih, dan Enter di kolom profil tidak ikut menghapus foto ?>
    <form id="form-foto" method="post" enctype="multipart/form-data" hidden><?= csrf_field() ?><input type="hidden" name="ganti_foto" value="1"></form>
    <?php if ($akun['foto']): ?><form id="form-hapus-foto" method="post" hidden><?= csrf_field() ?></form><?php endif ?>
    <div class="account__forms">
      <form class="form panel" method="post" novalidate>
        <?= csrf_field() ?>
        <h3 class="panel__title">Data diri</h3>
        <?= keterangan_wajib() ?>
        <div class="field">
          <label for="foto">Foto profil <?= opsional() ?></label>
          <div class="foto-input">
            <?= avatar($akun, 'md') ?>
            <div>
              <input id="foto" name="foto" type="file" form="form-foto" accept="image/jpeg,image/png,image/webp" class="file"<?= $inv('foto') ?>>
              <noscript><button class="btn btn--sm btn--ghost" form="form-foto">Unggah foto</button></noscript>
              <?= $err('foto') ?>
              <?php if ($akun['foto']): ?><button class="btn btn--sm btn--ghost" form="form-hapus-foto" name="hapus_foto" value="1" data-confirm-btn="Hapus foto profil?">Hapus foto</button><?php endif ?>
            </div>
          </div>
        </div>
        <div class="field"><label for="nama">Nama lengkap <?= wajib() ?></label><input id="nama" name="nama" value="<?= e($profil['nama']) ?>" autocomplete="name" required<?= $inv('nama') ?>><?= $err('nama') ?></div>
        <div class="field"><label for="email">Email <?= wajib() ?></label><input id="email" name="email" type="email" value="<?= e($profil['email']) ?>" autocomplete="email" required<?= $inv('email') ?>><?= $err('email') ?></div>
        <div class="field"><label for="username">Username <?= opsional() ?></label><input id="username" name="username" value="<?= e($profil['username']) ?>" maxlength="21" autocomplete="off"<?= $inv('username') ?>>
          <?= $err('username') ?: '<p class="field__hint">Bebas, 3–20 karakter. Bisa dipakai untuk masuk.</p>' ?></div>
        <div class="field"><label for="telepon">No. WhatsApp <?= opsional() ?></label><input id="telepon" name="telepon" type="tel" value="<?= e($profil['telepon']) ?>" autocomplete="tel"<?= $inv('telepon') ?>><?= $err('telepon') ?></div>
        <button class="btn" name="profil" value="1">Simpan profil</button>
      </form>

      <form class="form panel" method="post" novalidate>
        <?= csrf_field() ?>
        <h3 class="panel__title">Ganti kata sandi</h3>
        <?= keterangan_wajib() ?>
        <div class="field"><label for="sandi-lama">Kata sandi saat ini <?= wajib() ?></label><input id="sandi-lama" name="sandi_lama" type="password" autocomplete="current-password" required<?= $inv('sandi_lama') ?>><?= $err('sandi_lama') ?></div>
        <div class="field"><label for="sandi-baru">Kata sandi baru <?= wajib() ?></label><input id="sandi-baru" name="sandi_baru" type="password" autocomplete="new-password" required<?= $inv('sandi_baru') ?>>
          <?= $err('sandi_baru') ?: '<p class="field__hint">Minimal 8 karakter.</p>' ?></div>
        <div class="field"><label for="sandi-ulang">Ulangi kata sandi baru <?= wajib() ?></label><input id="sandi-ulang" name="sandi_ulang" type="password" autocomplete="new-password" required<?= $inv('sandi_ulang') ?>><?= $err('sandi_ulang') ?></div>
        <button class="btn" name="sandi" value="1">Ganti kata sandi</button>
      </form>
    </div>
  </section>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
