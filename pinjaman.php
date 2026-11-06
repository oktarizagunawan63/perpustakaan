<?php
require __DIR__ . '/config.php';

$u = wajib_login();
if ($u['role'] === 'admin') redirect('admin/sirkulasi.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_cek();

    if (isset($_POST['keluar_antrean'])) {
        q('DELETE FROM antrean WHERE id = ? AND user_id = ?', [(int) $_POST['keluar_antrean'], $u['id']]);
        flash('ok', 'Kamu sudah keluar dari antrean.');
        redirect('pinjaman.php');
    }

    $id = (int) ($_POST['perpanjang'] ?? $_POST['kembalikan'] ?? $_POST['batal'] ?? 0);
    $p = q('SELECT p.*, b.judul, ' . SQL_DIANTRE . ' AS diantre FROM peminjaman p JOIN buku b ON b.id = p.buku_id
            WHERE p.id = ? AND p.user_id = ? AND p.tgl_kembali IS NULL', [$id, $u['id']])->fetch();

    if (!$p) {
        flash('error', 'Pinjaman tidak ditemukan.');
    } elseif (isset($_POST['batal'])) {
        if ($p['tgl_ambil']) {
            flash('error', '“' . $p['judul'] . '” sudah diambil, jadi tidak bisa dibatalkan. Ajukan pengembalian saja.');
        } else {
            q('DELETE FROM peminjaman WHERE id = ?', [$p['id']]);
            flash('ok', 'Pesanan buku fisik “' . $p['judul'] . '” dibatalkan.');
        }
    } elseif (!$p['tgl_ambil']) {
        flash('error', 'Ambil dulu “' . $p['judul'] . '” di meja sirkulasi.');
    } elseif (isset($_POST['perpanjang'])) {
        if ($p['tgl_ajuan']) {
            flash('error', '“' . $p['judul'] . '” sudah diserahkan dan sedang dicek petugas.');
        } elseif ($p['perpanjang'] >= MAKS_PERPANJANG) {
            flash('error', 'Batas perpanjangan untuk “' . $p['judul'] . '” sudah terpakai.');
        } elseif (selisih_hari($p['jatuh_tempo']) > 0) {
            flash('error', 'Pinjaman yang sudah terlambat tidak bisa diperpanjang. Kembalikan ke meja sirkulasi.');
        } elseif ($p['format'] === 'fisik' && $p['diantre']) {
            flash('error', '“' . $p['judul'] . '” sedang diantre anggota lain, jadi tidak bisa diperpanjang.');
        } else {
            $baru = date('Y-m-d', strtotime($p['jatuh_tempo'] . ' +' . (int) $p['durasi'] . ' days'));
            q('UPDATE peminjaman SET jatuh_tempo = ?, perpanjang = perpanjang + 1 WHERE id = ?', [$baru, $p['id']]);
            flash('ok', '“' . $p['judul'] . '” diperpanjang sampai ' . tgl($baru, true) . '.');
        }
    } elseif ($p['format'] === 'ebook') {
        // E-book: langsung selesai, akses baca ditutup
        q('UPDATE peminjaman SET tgl_kembali = CURDATE(), kondisi = "baik" WHERE id = ?', [$p['id']]);
        flash('ok', 'E-book “' . $p['judul'] . '” sudah dikembalikan. Terima kasih!');
    } elseif ($p['tgl_ajuan']) {
        flash('info', '“' . $p['judul'] . '” sudah diajukan dan sedang dicek petugas.');
    } else {
        // Buku fisik: menunggu petugas mengecek kondisi dan menyetujui
        q('UPDATE peminjaman SET tgl_ajuan = CURDATE() WHERE id = ?', [$p['id']]);
        $denda = hitung_denda($p['jatuh_tempo']);
        flash('ok', 'Pengembalian “' . $p['judul'] . '” diajukan. Serahkan bukunya ke meja sirkulasi — pengembalian selesai setelah petugas mengecek kondisi buku dan menyetujuinya.'
            . ($denda ? ' Denda keterlambatan ' . rupiah($denda) . ' tercatat.' : ''));
    }
    redirect('pinjaman.php');
}

$aktif = q('SELECT p.*, b.judul, b.penulis, b.no_panggil, b.rak, b.sampul, b.file_pdf, ' . SQL_DIANTRE . ' AS diantre FROM peminjaman p JOIN buku b ON b.id = p.buku_id
            WHERE p.user_id = ? AND p.tgl_kembali IS NULL ORDER BY p.tgl_ambil IS NOT NULL, p.tgl_ajuan IS NOT NULL, p.jatuh_tempo', [$u['id']])->fetchAll();
$riwayat = q('SELECT p.*, b.judul, b.penulis FROM peminjaman p JOIN buku b ON b.id = p.buku_id
              WHERE p.user_id = ? AND p.tgl_kembali IS NOT NULL ORDER BY p.tgl_kembali DESC LIMIT 30', [$u['id']])->fetchAll();
$tagihan = array_values(array_filter($riwayat, fn($r) => $r['denda'] + $r['denda_kondisi'] > 0 && !$r['denda_lunas']));
$totalTagihan = denda_belum_lunas((int) $u['id']);
$antrean = q('SELECT a.*, b.judul, b.penulis, b.sampul, b.no_panggil,
                (SELECT COUNT(*) FROM antrean x WHERE x.buku_id = a.buku_id AND x.tgl_tersedia IS NULL
                   AND (x.dibuat < a.dibuat OR (x.dibuat = a.dibuat AND x.id < a.id))) + 1 AS posisi
              FROM antrean a JOIN buku b ON b.id = a.buku_id WHERE a.user_id = ? ORDER BY a.tgl_tersedia IS NULL, a.dibuat', [$u['id']])->fetchAll();
$pengingat = pengingat($u);

$dendaBerjalan = array_sum(array_map('denda_pinjaman', $aktif));
$siapAmbil = count(array_filter($aktif, fn($p) => !$p['tgl_ambil']));
$menunggu = count(array_filter($aktif, fn($p) => $p['tgl_ajuan']));
$berjalan = array_values(array_filter($aktif, fn($p) => $p['tgl_ambil'] && !$p['tgl_ajuan']));
usort($berjalan, fn($a, $b) => strcmp($a['jatuh_tempo'], $b['jatuh_tempo']));
$terdekat = $berjalan[0]['jatuh_tempo'] ?? null;
$adaTelat = (bool) array_filter($berjalan, fn($p) => $p['format'] === 'fisik' && $p['jatuh_tempo'] < date('Y-m-d'));
$keteranganJumlah = array_filter([$siapAmbil ? "$siapAmbil siap diambil" : '', $menunggu ? "$menunggu menunggu dicek" : '']);

$judul = 'Pinjaman saya';
$menu = 'pinjaman';
require __DIR__ . '/partials/header.php';
?>

<div class="wrap account">
  <header class="admin__head">
    <div>
      <p class="eyebrow">Akun anggota · <?= e($u['no_anggota']) ?></p>
      <h1>Pinjaman saya</h1>
      <p class="muted">Buku yang sedang kamu pinjam, antrean, tagihan denda, dan riwayat peminjaman.</p>
    </div>
    <a class="btn" href="<?= url('katalog.php') ?>">Pinjam buku lain</a>
  </header>

  <?php if ($pengingat): ?>
    <ul class="reminders" aria-label="Pengingat">
      <?php foreach ($pengingat as [$kls, $pesan, $link]): ?>
        <li class="reminder reminder--<?= $kls ?>"><span><?= e($pesan) ?></span>
          <?php if (str_starts_with($link, 'pinjam.php')): ?><a class="btn btn--sm" href="<?= url($link) ?>">Pinjam sekarang</a><?php endif ?></li>
      <?php endforeach ?>
    </ul>
  <?php endif ?>

  <dl class="stats stats--3">
    <div><dt>Sedang dipinjam</dt><dd><?= count($aktif) ?><small> / <?= MAKS_PINJAM ?></small></dd>
      <span><?= $keteranganJumlah ? implode(' · ', $keteranganJumlah) : (count($aktif) >= MAKS_PINJAM ? 'Kuota penuh' : 'Masih bisa pinjam ' . (MAKS_PINJAM - count($aktif)) . ' buku') ?></span></div>
    <div><dt>Jatuh tempo terdekat</dt><dd class="stats__text"><?= $terdekat ? tgl($terdekat) : '—' ?></dd>
      <span><?= $terdekat ? e(status_pinjaman($berjalan[0])[1]) : 'Tidak ada pinjaman berjalan' ?></span></div>
    <div class="<?= $totalTagihan + $dendaBerjalan ? 'is-alert' : '' ?>"><dt>Denda</dt><dd class="stats__text"><?= rupiah($totalTagihan + $dendaBerjalan) ?></dd>
      <span><?= $totalTagihan ? rupiah($totalTagihan) . ' belum lunas' . ($dendaBerjalan ? ' · ' . rupiah($dendaBerjalan) . ' berjalan' : '') : ($dendaBerjalan ? 'Bertambah ' . rupiah(DENDA_PER_HARI) . '/hari per buku fisik' : 'Tidak ada denda') ?></span></div>
  </dl>

  <section class="section section--tight">
    <h2>Sedang dipinjam</h2>
    <?php if (!$aktif): ?>
      <div class="empty empty--inline">
        <p>Belum ada buku yang dipinjam. Cari buku di katalog, lalu klik “Pinjam buku ini”.</p>
        <a class="btn btn--ghost" href="<?= url('katalog.php') ?>">Buka katalog</a>
      </div>
    <?php else: ?>
      <ul class="loans">
        <?php foreach ($aktif as $p): [$kls, $lbl] = status_pinjaman($p); $denda = denda_pinjaman($p); $ebook = $p['format'] === 'ebook'; ?>
          <li class="loan loan--<?= $kls ?>">
            <?= sampul($p, 'sm') ?>
            <div class="loan__body">
              <h3><a href="<?= url('buku.php?id=' . $p['buku_id']) ?>"><?= e($p['judul']) ?></a></h3>
              <p class="muted"><span class="tag <?= $ebook ? 'tag--accent' : '' ?>"><?= LABEL_FORMAT[$p['format']] ?></span> <?= e($p['penulis']) ?></p>
              <?php if (!$p['tgl_ambil']): ?>
                <p class="loan__dates">Dipesan <?= tgl($p['tgl_pinjam']) ?> · lama pinjam <?= (int) $p['durasi'] ?> hari, dihitung sejak diambil</p>
                <p class="loan__note">Ambil di meja sirkulasi<?= $p['rak'] !== '' ? ' · Rak ' . e($p['rak']) : '' ?> paling lambat <?= tgl(batas_ambil($p), true) ?>. Lewat dari itu pesanan batal otomatis.</p>
              <?php else: ?>
                <p class="loan__dates">Dipinjam <?= tgl($p['tgl_ambil']) ?> (<?= (int) $p['durasi'] ?> hari) · Jatuh tempo <strong><?= tgl($p['jatuh_tempo']) ?></strong>
                  <?= $p['perpanjang'] ? ' · sudah diperpanjang' : '' ?></p>
                <?php if ($p['tgl_ajuan']): ?>
                  <p class="loan__note">Diserahkan <?= tgl($p['tgl_ajuan']) ?>. Petugas sedang mengecek kondisi buku; pengembalian selesai setelah disetujui.</p>
                <?php elseif (!$ebook): ?>
                  <p class="loan__note">Kembalikan di meja sirkulasi<?= $p['rak'] !== '' ? ' · Rak ' . e($p['rak']) : '' ?><?= $p['no_panggil'] !== '' ? ' · <span class="callno">' . e($p['no_panggil']) . '</span>' : '' ?></p>
                <?php endif ?>
              <?php endif ?>
            </div>
            <div class="loan__side">
              <span class="due due--<?= $kls ?>"><?= e($lbl) ?></span>
              <?php if ($denda): ?><span class="loan__fine">Denda <?= rupiah($denda) ?></span><?php endif ?>
              <?php if (!$p['tgl_ambil']): ?>
                <form method="post" data-confirm="Batalkan pesanan buku fisik “<?= e($p['judul']) ?>”?">
                  <?= csrf_field() ?>
                  <button class="btn-text loan__return" name="batal" value="<?= $p['id'] ?>">Batalkan pesanan</button>
                </form>
              <?php else: ?>
                <?php if ($ebook): ?><a class="btn btn--sm" href="<?= url('baca.php?id=' . $p['buku_id']) ?>">Baca isi buku</a><?php endif ?>
                <?php if (!$p['tgl_ajuan'] && $kls !== 'late' && $p['perpanjang'] < MAKS_PERPANJANG): ?>
                  <?php if (!$ebook && $p['diantre']): ?>
                    <span class="muted small">Tidak bisa diperpanjang, sedang diantre</span>
                  <?php else: ?>
                    <form method="post">
                      <?= csrf_field() ?>
                      <button class="btn btn--sm btn--ghost" name="perpanjang" value="<?= $p['id'] ?>">Perpanjang <?= (int) $p['durasi'] ?> hari</button>
                    </form>
                  <?php endif ?>
                <?php endif ?>
                <?php if ($ebook): ?>
                  <form method="post" data-confirm="Kembalikan e-book “<?= e($p['judul']) ?>” sekarang? Isinya tidak bisa dibaca lagi sampai kamu meminjamnya kembali.">
                    <?= csrf_field() ?>
                    <button class="btn-text loan__return" name="kembalikan" value="<?= $p['id'] ?>">Kembalikan sekarang</button>
                  </form>
                <?php elseif (!$p['tgl_ajuan']): ?>
                  <form method="post" data-confirm="Ajukan pengembalian “<?= e($p['judul']) ?>”? Serahkan bukunya ke meja sirkulasi — petugas akan mengecek kondisinya lalu menyetujui.">
                    <?= csrf_field() ?>
                    <button class="btn btn--sm btn--ghost" name="kembalikan" value="<?= $p['id'] ?>">Ajukan pengembalian</button>
                  </form>
                <?php endif ?>
              <?php endif ?>
            </div>
          </li>
        <?php endforeach ?>
      </ul>
    <?php endif ?>
  </section>

  <?php if ($antrean): ?>
    <section class="section section--tight">
      <h2>Antrean buku fisik</h2>
      <ul class="loans">
        <?php foreach ($antrean as $a): $sampai = date('Y-m-d', strtotime(($a['tgl_tersedia'] ?? 'today') . ' +' . BATAS_GILIRAN . ' days')); ?>
          <li class="loan loan--<?= $a['tgl_tersedia'] ? 'ok' : 'wait' ?>">
            <?= sampul($a, 'sm') ?>
            <div class="loan__body">
              <h3><a href="<?= url('buku.php?id=' . $a['buku_id']) ?>"><?= e($a['judul']) ?></a></h3>
              <p class="muted"><?= e($a['penulis']) ?></p>
              <p class="loan__dates">Masuk antrean <?= tgl(substr($a['dibuat'], 0, 10)) ?></p>
            </div>
            <div class="loan__side">
              <?php if ($a['tgl_tersedia']): ?>
                <span class="due due--ok">Giliranmu sampai <?= tgl($sampai) ?></span>
                <a class="btn btn--sm" href="<?= url('pinjam.php?id=' . $a['buku_id']) ?>">Pinjam sekarang</a>
              <?php else: ?>
                <span class="due due--wait">Antrean ke-<?= (int) $a['posisi'] ?></span>
              <?php endif ?>
              <form method="post" data-confirm="Keluar dari antrean “<?= e($a['judul']) ?>”?">
                <?= csrf_field() ?>
                <button class="btn-text loan__return" name="keluar_antrean" value="<?= $a['id'] ?>">Keluar dari antrean</button>
              </form>
            </div>
          </li>
        <?php endforeach ?>
      </ul>
    </section>
  <?php endif ?>

  <?php if ($tagihan): ?>
    <section class="section section--tight" id="tagihan">
      <h2>Tagihan denda <span class="tag tag--late"><?= rupiah($totalTagihan) ?></span></h2>
      <p class="muted">Bayar di meja sirkulasi. Selama masih ada denda yang belum lunas, kamu belum bisa meminjam buku baru.</p>
      <div class="table-wrap">
        <table class="table">
          <thead><tr><th>Buku</th><th>Selesai</th><th>Rincian</th><th class="num">Jumlah</th></tr></thead>
          <tbody>
          <?php foreach ($tagihan as $r): ?>
            <tr>
              <td><?= e($r['judul']) ?></td>
              <td><?= tgl($r['tgl_kembali']) ?></td>
              <td><?= e(implode(' + ', array_filter([$r['denda'] ? 'terlambat ' . rupiah((int) $r['denda']) : '', $r['denda_kondisi'] ? $r['kondisi'] . ' ' . rupiah((int) $r['denda_kondisi']) : '']))) ?></td>
              <td class="num"><?= rupiah((int) $r['denda'] + (int) $r['denda_kondisi']) ?></td>
            </tr>
          <?php endforeach ?>
          </tbody>
        </table>
      </div>
    </section>
  <?php endif ?>

  <section class="section section--tight">
    <h2>Riwayat</h2>
    <?php if (!$riwayat): ?>
      <p class="muted">Belum ada buku yang dikembalikan.</p>
    <?php else: ?>
      <div class="table-wrap">
        <table class="table">
          <thead><tr><th>Judul</th><th>Format</th><th>Dipinjam</th><th>Selesai</th><th class="num">Denda</th></tr></thead>
          <tbody>
          <?php foreach ($riwayat as $r): $t = (int) $r['denda'] + (int) $r['denda_kondisi']; ?>
            <tr>
              <td><a href="<?= url('buku.php?id=' . $r['buku_id']) ?>"><?= e($r['judul']) ?></a><span class="sub"><?= e($r['penulis']) ?></span></td>
              <td><?= LABEL_FORMAT[$r['format']] ?></td>
              <td><?= tgl($r['tgl_ambil'] ?: $r['tgl_pinjam']) ?></td>
              <td><?= tgl($r['tgl_kembali']) ?><?= $r['denda'] ? ' <span class="tag tag--late">terlambat</span>' : '' ?><?= in_array($r['kondisi'], ['rusak', 'hilang'], true) ? ' <span class="tag tag--late">' . $r['kondisi'] . '</span>' : '' ?></td>
              <td class="num"><?= $t ? rupiah($t) . ($r['denda_lunas'] ? '<span class="sub">lunas</span>' : '<span class="sub due-text--late">belum lunas</span>') : '—' ?></td>
            </tr>
          <?php endforeach ?>
          </tbody>
        </table>
      </div>
    <?php endif ?>
  </section>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
