<?php
require __DIR__ . '/config.php';

$u = wajib_login();
$b = q('SELECT * FROM buku WHERE id = ?', [(int) ($_GET['id'] ?? 0)])->fetch();
if (!$b || !$b['file_pdf']) {
    flash('error', 'Buku ini belum punya isi digital.');
    redirect('katalog.php');
}
$pinjam = akses_isi($u, $b);
if ($pinjam === null) {
    flash('info', 'Isi lengkap “' . $b['judul'] . '” terbuka untuk peminjaman versi e-book.');
    redirect('buku.php?id=' . $b['id']);
}
[$kls, $lbl] = $pinjam ? status_pinjaman($pinjam) : ['ok', 'Akses petugas'];

$judul = $b['judul'];
$polos = true; // tanpa footer: penampil memakai seluruh tinggi layar
require __DIR__ . '/partials/header.php';
?>

<div class="reader">
  <div class="wrap reader__bar">
    <a class="reader__back" href="<?= url('buku.php?id=' . $b['id']) ?>">← Profil buku</a>
    <div class="reader__title">
      <strong><?= e($b['judul']) ?></strong>
      <span><?= e($b['penulis']) ?><?= $b['halaman'] ? ' · ' . number_format((int) $b['halaman'], 0, ',', '.') . ' halaman' : '' ?></span>
    </div>
    <span class="due due--<?= $kls ?>"><?= e($pinjam ? $lbl . ' · ' . tgl($pinjam['jatuh_tempo']) : $lbl) ?></span>
    <a class="btn btn--sm btn--ghost" href="<?= url('berkas.php?id=' . $b['id']) ?>" target="_blank" rel="noopener">Layar penuh</a>
  </div>
  <iframe class="reader__frame" src="<?= url('berkas.php?id=' . $b['id']) ?>#view=FitH" title="Isi buku <?= e($b['judul']) ?>"></iframe>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
