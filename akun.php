<?php
require __DIR__ . '/config.php';

$u = wajib_login();
if ($u['role'] === 'admin') redirect('admin/index.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['perpanjang'])) {
    csrf_cek();
    $p = q('SELECT p.*, b.judul FROM peminjaman p JOIN buku b ON b.id = p.buku_id
            WHERE p.id = ? AND p.user_id = ? AND p.tgl_kembali IS NULL', [(int) $_POST['perpanjang'], $u['id']])->fetch();
    if (!$p) {
        flash('error', 'Pinjaman tidak ditemukan.');
    } elseif ($p['perpanjang'] >= MAKS_PERPANJANG) {
        flash('error', 'Batas perpanjangan untuk “' . $p['judul'] . '” sudah terpakai.');
    } elseif (selisih_hari($p['jatuh_tempo']) > 0) {
        flash('error', 'Pinjaman yang sudah terlambat tidak bisa diperpanjang. Kembalikan ke meja sirkulasi.');
    } else {
        $baru = date('Y-m-d', strtotime($p['jatuh_tempo'] . ' +' . LAMA_PINJAM . ' days'));
        q('UPDATE peminjaman SET jatuh_tempo = ?, perpanjang = perpanjang + 1 WHERE id = ?', [$baru, $p['id']]);
        flash('ok', '“' . $p['judul'] . '” diperpanjang sampai ' . tgl($baru, true) . '.');
    }
    redirect('akun.php');
}

$aktif = q('SELECT p.*, b.judul, b.penulis, b.no_panggil FROM peminjaman p JOIN buku b ON b.id = p.buku_id
            WHERE p.user_id = ? AND p.tgl_kembali IS NULL ORDER BY p.jatuh_tempo', [$u['id']])->fetchAll();
$riwayat = q('SELECT p.*, b.judul, b.penulis FROM peminjaman p JOIN buku b ON b.id = p.buku_id
              WHERE p.user_id = ? AND p.tgl_kembali IS NOT NULL ORDER BY p.tgl_kembali DESC LIMIT 30', [$u['id']])->fetchAll();
$dendaBerjalan = array_sum(array_map(fn($p) => hitung_denda($p['jatuh_tempo']), $aktif));
$sisaKuota = MAKS_PINJAM - count($aktif);

$judul = 'Akun saya';
$menu = 'akun';
require __DIR__ . '/partials/header.php';
?>

<div class="wrap account">
  <header class="account__head">
    <div>
      <p class="eyebrow">Akun anggota</p>
      <h1>Halo, <?= e(explode(' ', $u['nama'])[0]) ?>.</h1>
      <p class="muted">
        <?php if (!$aktif): ?>Tidak ada buku yang sedang kamu pinjam.
        <?php else: ?>Kamu meminjam <?= count($aktif) ?> buku<?= $sisaKuota > 0 ? ", masih bisa pinjam $sisaKuota lagi" : ' — kuota penuh' ?>.<?php endif ?>
      </p>
    </div>

    <div class="member-card" aria-label="Kartu anggota">
      <span class="member-card__org"><?= e(NAMA_PERPUS) ?></span>
      <span class="member-card__name"><?= e($u['nama']) ?></span>
      <span class="member-card__no"><?= e($u['no_anggota']) ?></span>
      <span class="member-card__bars" aria-hidden="true"></span>
    </div>
  </header>

  <?php if ($dendaBerjalan > 0): ?>
    <div class="notice notice--error">Ada denda berjalan <strong><?= rupiah($dendaBerjalan) ?></strong> dan bertambah <?= rupiah(DENDA_PER_HARI) ?> per buku setiap hari. Segera kembalikan buku yang terlambat.</div>
  <?php endif ?>

  <section class="section section--tight">
    <h2>Sedang dipinjam</h2>
    <?php if (!$aktif): ?>
      <div class="empty empty--inline">
        <p>Rak menunggu. Cari buku di katalog, lalu pinjam di meja sirkulasi.</p>
        <a class="btn btn--ghost" href="<?= url('katalog.php') ?>">Buka katalog</a>
      </div>
    <?php else: ?>
      <ul class="loans">
        <?php foreach ($aktif as $p): [$kls, $lbl] = status_tempo($p['jatuh_tempo']); $denda = hitung_denda($p['jatuh_tempo']); ?>
          <li class="loan loan--<?= $kls ?>">
            <?= sampul($p, 'sm') ?>
            <div class="loan__body">
              <h3><a href="<?= url('buku.php?id=' . $p['buku_id']) ?>"><?= e($p['judul']) ?></a></h3>
              <p class="muted"><?= e($p['penulis']) ?></p>
              <p class="loan__dates">Dipinjam <?= tgl($p['tgl_pinjam']) ?> · Jatuh tempo <strong><?= tgl($p['jatuh_tempo']) ?></strong>
                <?= $p['perpanjang'] ? ' · sudah diperpanjang' : '' ?></p>
            </div>
            <div class="loan__side">
              <span class="due due--<?= $kls ?>"><?= e($lbl) ?></span>
              <?php if ($denda): ?><span class="loan__fine">Denda <?= rupiah($denda) ?></span><?php endif ?>
              <?php if ($kls !== 'late' && $p['perpanjang'] < MAKS_PERPANJANG): ?>
                <form method="post">
                  <?= csrf_field() ?>
                  <button class="btn btn--sm btn--ghost" name="perpanjang" value="<?= $p['id'] ?>">Perpanjang <?= LAMA_PINJAM ?> hari</button>
                </form>
              <?php endif ?>
            </div>
          </li>
        <?php endforeach ?>
      </ul>
    <?php endif ?>
  </section>

  <section class="section section--tight">
    <h2>Riwayat</h2>
    <?php if (!$riwayat): ?>
      <p class="muted">Belum ada buku yang dikembalikan.</p>
    <?php else: ?>
      <div class="table-wrap">
        <table class="table">
          <thead><tr><th>Judul</th><th>Dipinjam</th><th>Dikembalikan</th><th class="num">Denda</th></tr></thead>
          <tbody>
          <?php foreach ($riwayat as $r): ?>
            <tr>
              <td><a href="<?= url('buku.php?id=' . $r['buku_id']) ?>"><?= e($r['judul']) ?></a><span class="sub"><?= e($r['penulis']) ?></span></td>
              <td><?= tgl($r['tgl_pinjam']) ?></td>
              <td><?= tgl($r['tgl_kembali']) ?><?= $r['tgl_kembali'] > $r['jatuh_tempo'] ? ' <span class="tag tag--late">terlambat</span>' : '' ?></td>
              <td class="num"><?= $r['denda'] ? rupiah((int) $r['denda']) : '—' ?></td>
            </tr>
          <?php endforeach ?>
          </tbody>
        </table>
      </div>
    <?php endif ?>
  </section>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
