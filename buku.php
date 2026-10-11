<?php
require __DIR__ . '/config.php';

$b = q('SELECT b.*, k.kode, k.nama kategori, b.stok - ' . SQL_DIPINJAM . ' AS tersedia
        FROM buku b JOIN kategori k ON k.id = b.kategori_id WHERE b.id = ?', [(int) ($_GET['id'] ?? 0)])->fetch();
if (!$b) {
    http_response_code(404);
    $judul = 'Buku tidak ditemukan';
    require __DIR__ . '/partials/header.php';
    echo '<div class="wrap empty"><h1>Buku tidak ditemukan.</h1><p>Mungkin tautannya keliru atau buku sudah ditarik dari koleksi.</p><a class="btn" href="' . url('katalog.php') . '">Kembali ke katalog</a></div>';
    require __DIR__ . '/partials/footer.php';
    exit;
}

$u = user();
$pinjamanSaya = $u ? q('SELECT jatuh_tempo FROM peminjaman WHERE user_id = ? AND buku_id = ? AND tgl_kembali IS NULL', [$u['id'], $b['id']])->fetch() : null;
$kembaliTerdekat = $b['tersedia'] <= 0 ? q('SELECT MIN(jatuh_tempo) FROM peminjaman WHERE buku_id = ? AND tgl_kembali IS NULL', [$b['id']])->fetchColumn() : null;
$serupa = q('SELECT id, judul, penulis, no_panggil FROM buku WHERE kategori_id = ? AND id <> ? ORDER BY RAND() LIMIT 6', [$b['kategori_id'], $b['id']])->fetchAll();

$judul = $b['judul'];
$menu = 'katalog';
require __DIR__ . '/partials/header.php';
?>

<div class="wrap">
  <nav class="crumbs" aria-label="Jejak halaman">
    <a href="<?= url('katalog.php') ?>">Katalog</a> <span aria-hidden="true">/</span>
    <a href="<?= url('katalog.php?kelas=' . $b['kode']) ?>"><?= e($b['kode'] . ' ' . $b['kategori']) ?></a>
  </nav>

  <article class="book">
    <div class="book__cover"><?= sampul($b, 'lg') ?></div>

    <div class="book__main">
      <h1 class="book__title"><?= e($b['judul']) ?></h1>
      <p class="book__by"><?= e($b['penulis']) ?></p>

      <div class="locator">
        <?php if ($pinjamanSaya): ?>
          <?php [$kls, $lbl] = status_tempo($pinjamanSaya['jatuh_tempo']) ?>
          <p class="locator__status"><span class="avail avail--<?= $kls === 'late' ? 'no' : 'yes' ?>">Sedang kamu pinjam</span>
            <?= e($lbl) ?> — <a href="<?= url('akun.php') ?>">kelola di akun</a></p>
        <?php elseif ($b['tersedia'] > 0): ?>
          <p class="locator__status"><span class="avail avail--yes">Tersedia <?= (int) $b['tersedia'] ?> dari <?= (int) $b['stok'] ?> eksemplar</span></p>
        <?php else: ?>
          <p class="locator__status"><span class="avail avail--no">Semua sedang dipinjam</span>
            <?= $kembaliTerdekat ? 'Paling cepat kembali ' . tgl($kembaliTerdekat) : '' ?></p>
        <?php endif ?>

        <div class="locator__grid">
          <div>
            <span class="locator__label">Nomor panggil</span>
            <span class="callno callno--lg"><?= e($b['no_panggil']) ?></span>
            <button class="btn-text" type="button" data-copy="<?= e($b['no_panggil']) ?>">Salin</button>
          </div>
          <div>
            <span class="locator__label">Letak</span>
            <span class="locator__value">Rak <?= e($b['rak']) ?></span>
          </div>
        </div>
        <p class="locator__help">Ambil di rak, lalu bawa ke meja sirkulasi bersama kartu anggota.
          <?php if (!$u): ?>Belum anggota? <a href="<?= url('daftar.php') ?>">Daftar dulu</a>.<?php endif ?></p>
      </div>

      <?php if ($b['sinopsis']): ?>
        <h2 class="h-small">Tentang buku ini</h2>
        <p class="book__desc"><?= e($b['sinopsis']) ?></p>
      <?php endif ?>

      <h2 class="h-small">Detail bibliografi</h2>
      <dl class="card-catalog">
        <div><dt>Penerbit</dt><dd><?= e($b['penerbit']) ?></dd></div>
        <div><dt>Tahun terbit</dt><dd><?= (int) $b['tahun'] ?></dd></div>
        <div><dt>ISBN</dt><dd class="mono"><?= e($b['isbn'] ?: '—') ?></dd></div>
        <div><dt>Halaman</dt><dd><?= $b['halaman'] ? (int) $b['halaman'] . ' hlm.' : '—' ?></dd></div>
        <div><dt>Bahasa</dt><dd><?= e($b['bahasa']) ?></dd></div>
        <div><dt>Kelas</dt><dd><a href="<?= url('katalog.php?kelas=' . $b['kode']) ?>"><?= e($b['kode'] . ' — ' . $b['kategori']) ?></a></dd></div>
      </dl>
    </div>
  </article>

  <?php if ($serupa): ?>
    <section class="section section--tight">
      <div class="section__head">
        <h2>Satu rak dengan buku ini</h2>
        <a class="link-arrow" href="<?= url('katalog.php?kelas=' . $b['kode']) ?>">Semua di kelas <?= e($b['kode']) ?></a>
      </div>
      <ul class="shelf">
        <?php foreach ($serupa as $s): ?>
          <li><a class="shelf__item" href="<?= url('buku.php?id=' . $s['id']) ?>"><?= sampul($s) ?>
            <span class="shelf__title"><?= e($s['judul']) ?></span><span class="shelf__author"><?= e($s['penulis']) ?></span></a></li>
        <?php endforeach ?>
      </ul>
    </section>
  <?php endif ?>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
