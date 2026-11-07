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
$pinjamanSaya = $u ? q('SELECT * FROM peminjaman WHERE user_id = ? AND buku_id = ? AND tgl_kembali IS NULL', [$u['id'], $b['id']])->fetch() : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_cek();
    $u = wajib_login();
    if ($u['role'] === 'anggota') {
        $kunci = [$u['id'], $b['id']];
        if (isset($_POST['simpan'])) {
            $ada = q('DELETE FROM favorit WHERE user_id = ? AND buku_id = ?', $kunci)->rowCount();
            if (!$ada) q('INSERT INTO favorit (user_id, buku_id) VALUES (?, ?)', $kunci);
            flash('ok', $ada ? 'Dihapus dari daftar baca.' : 'Disimpan ke daftar baca di Akun saya.');
        } elseif (isset($_POST['keluar_antrean'])) {
            q('DELETE FROM antrean WHERE user_id = ? AND buku_id = ?', $kunci);
            flash('ok', 'Kamu sudah keluar dari antrean.');
        } elseif (isset($_POST['antre'])) {
            if ($pinjamanSaya) {
                flash('error', 'Kamu sedang meminjam judul ini.');
            } elseif ($b['stok'] < 1 || $b['tersedia'] > 0) {
                flash('info', 'Buku ini tidak perlu antre.');
            } else {
                q('INSERT IGNORE INTO antrean (user_id, buku_id) VALUES (?, ?)', $kunci);
                flash('ok', 'Kamu masuk antrean. Saat bukunya kembali, eksemplarnya disimpan untukmu selama ' . BATAS_GILIRAN . ' hari.');
            }
        }
    }
    redirect('buku.php?id=' . $b['id']);
}

$anggota = $u && $u['role'] === 'anggota';
$tersimpan = $anggota && q('SELECT 1 FROM favorit WHERE user_id = ? AND buku_id = ?', [$u['id'], $b['id']])->fetch();
$antreSaya = $anggota ? q('SELECT a.tgl_tersedia, ' . SQL_POSISI_ANTREAN . ' AS posisi FROM antrean a WHERE a.user_id = ? AND a.buku_id = ?', [$u['id'], $b['id']])->fetch() : null;
$giliran = $antreSaya && $antreSaya['tgl_tersedia'];
$kembaliTerdekat = $b['tersedia'] <= 0 ? q('SELECT MIN(jatuh_tempo) FROM peminjaman WHERE buku_id = ? AND format = "fisik" AND tgl_kembali IS NULL', [$b['id']])->fetchColumn() : null;
$bisaPinjam = $b['tersedia'] > 0 || $b['file_pdf'] || $giliran;
$bisaAntre = $b['stok'] > 0 && $b['tersedia'] <= 0 && !$pinjamanSaya && !$antreSaya;
$bisaBaca = akses_isi($u, $b) !== null;
$serupa = q('SELECT id, judul, penulis, no_panggil, sampul FROM buku WHERE kategori_id = ? AND id <> ? ORDER BY RAND() LIMIT 6', [$b['kategori_id'], $b['id']])->fetchAll();

$judul = $b['judul'];
$menu = 'katalog';
$live = true;
require __DIR__ . '/partials/header.php';
?>

<div class="wrap">
  <nav class="crumbs" aria-label="Jejak halaman">
    <a href="<?= url('katalog.php') ?>">Katalog</a> <span aria-hidden="true">/</span>
    <a href="<?= url('katalog.php?kelas=' . $b['kode']) ?>"><?= e($b['kategori']) ?></a>
  </nav>

  <article class="book">
    <div class="book__cover"><?= sampul($b, 'lg') ?></div>

    <div class="book__main">
      <h1 class="book__title"><?= e($b['judul']) ?></h1>
      <p class="book__by"><?= e($b['penulis']) ?></p>
      <?php if ($anggota): ?>
        <form class="book__save" method="post">
          <?= csrf_field() ?>
          <button class="btn btn--sm btn--ghost" name="simpan" value="1" aria-pressed="<?= $tersimpan ? 'true' : 'false' ?>"><?= $tersimpan ? 'Tersimpan · hapus dari daftar baca' : 'Simpan ke daftar baca' ?></button>
        </form>
      <?php endif ?>

      <div class="locator">
        <?php if ($pinjamanSaya): ?>
          <?php [$kls, $lbl] = status_pinjaman($pinjamanSaya) ?>
          <p class="locator__status"><span class="avail avail--<?= $kls === 'late' ? 'no' : 'yes' ?>">Sedang kamu pinjam (<?= strtolower(LABEL_FORMAT[$pinjamanSaya['format']]) ?>)</span>
            <?= e($lbl) ?> — <a href="<?= url('pinjaman.php') ?>">lihat di Pinjaman saya</a></p>
        <?php else: ?>
          <p class="locator__status">
            <?php if ($giliran): ?>
              <span class="avail avail--yes">Giliranmu! Buku fisik disimpan sampai <?= tgl(batas_giliran($antreSaya['tgl_tersedia'])) ?></span>
            <?php elseif ($b['tersedia'] > 0): ?>
              <span class="avail avail--yes">Buku fisik: <?= (int) $b['tersedia'] ?> dari <?= (int) $b['stok'] ?> tersedia</span>
            <?php elseif ($b['stok'] > 0): ?>
              <span class="avail avail--no">Buku fisik sedang dipinjam semua</span><?= $kembaliTerdekat ? ' Paling cepat kembali ' . tgl($kembaliTerdekat) . '.' : '' ?>
            <?php endif ?>
            <?php if ($b['file_pdf']): ?><span class="avail avail--yes">E-book tersedia</span><?php endif ?>
          </p>
        <?php endif ?>

        <?php if ($b['no_panggil'] !== '' || $b['rak'] !== ''): ?>
        <div class="locator__grid">
          <div>
            <?php if ($b['no_panggil'] !== ''): ?>
              <span class="locator__label">Nomor panggil</span>
              <span class="callno callno--lg"><?= e($b['no_panggil']) ?></span>
              <button class="btn-text" type="button" data-copy="<?= e($b['no_panggil']) ?>">Salin</button>
            <?php endif ?>
          </div>
          <div>
            <?php if ($b['rak'] !== ''): ?>
              <span class="locator__label">Letak</span>
              <span class="locator__value">Rak <?= e($b['rak']) ?></span>
            <?php endif ?>
          </div>
        </div>
        <?php endif ?>
        <p class="locator__help">Pinjam lewat tombol di bawah, atau bawa buku dari rak ke meja sirkulasi bersama kartu anggota.
          <?php if (!$u): ?>Belum anggota? <a href="<?= url('daftar.php') ?>">Daftar dulu</a>.<?php endif ?></p>
        <?php if (!$pinjamanSaya && $bisaPinjam && (!$u || $u['role'] !== 'admin')): ?>
          <a class="btn btn--light locator__cta" href="<?= url($u ? 'pinjam.php?id=' . $b['id'] : 'login.php?lanjut=' . urlencode(rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/pinjam.php?id=' . $b['id'])) ?>"><?= $u ? 'Pinjam buku ini' : 'Masuk untuk meminjam' ?></a>
        <?php endif ?>
        <?php if ($antreSaya && !$giliran): ?>
          <form class="locator__queue" method="post" data-confirm="Keluar dari antrean buku ini?">
            <?= csrf_field() ?>
            <span>Kamu antrean ke-<?= (int) $antreSaya['posisi'] ?> untuk buku fisik.</span>
            <button class="btn btn--sm btn--light" name="keluar_antrean" value="1">Keluar dari antrean</button>
          </form>
        <?php elseif ($anggota && $bisaAntre): ?>
          <form class="locator__queue" method="post">
            <?= csrf_field() ?>
            <button class="btn btn--sm btn--light" name="antre" value="1">Antre buku fisik</button>
          </form>
        <?php elseif (!$u && $bisaAntre): ?>
          <a class="btn btn--light locator__cta" href="<?= url('login.php?lanjut=' . urlencode($_SERVER['REQUEST_URI'])) ?>">Masuk untuk ikut antrean</a>
        <?php endif ?>
      </div>

      <?php if ($b['file_pdf']): ?>
        <div class="ebook<?= $bisaBaca ? ' is-open' : '' ?>">
          <svg class="ebook__icon" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
            <?php if ($bisaBaca): ?><path d="M2 5.5C4.5 4 8 4 12 6c4-2 7.5-2 10-.5V19c-2.5-1.5-6-1.5-10 .5-4-2-7.5-2-10-.5Z"/><path d="M12 6v13.5"/>
            <?php else: ?><rect x="4.5" y="10.5" width="15" height="10" rx="2"/><path d="M8 10.5V7.5a4 4 0 0 1 8 0v3"/><?php endif ?>
          </svg>
          <div class="ebook__text">
            <span class="ebook__label">Isi buku digital<?= $b['halaman'] ? ' · ' . number_format((int) $b['halaman'], 0, ',', '.') . ' halaman' : '' ?></span>
            <?php if ($bisaBaca): ?>
              <p><?= $u['role'] === 'admin' ? 'Akses petugas: seluruh isi buku bisa dibuka.' : 'Kamu sedang meminjam buku ini — seluruh isinya bisa dibaca sampai ' . tgl($pinjamanSaya['jatuh_tempo'], true) . '.' ?></p>
            <?php elseif ($pinjamanSaya): ?>
              <p>Kamu sedang meminjam buku fisiknya. Isi digital hanya terbuka untuk peminjaman versi e-book.</p>
            <?php elseif ($u): ?>
              <p>Isi lengkap terbuka setelah kamu meminjam versi e-book-nya.</p>
            <?php else: ?>
              <p><a href="<?= url('login.php?lanjut=' . urlencode($_SERVER['REQUEST_URI'])) ?>">Masuk</a> dan pinjam buku ini untuk membaca seluruh isinya.</p>
            <?php endif ?>
          </div>
          <?php if ($bisaBaca): ?><a class="btn" href="<?= url('baca.php?id=' . $b['id']) ?>">Baca isi buku</a><?php endif ?>
        </div>
      <?php endif ?>

      <?php if ($b['sinopsis']): ?>
        <h2 class="h-small">Tentang buku ini</h2>
        <p class="book__desc"><?= e($b['sinopsis']) ?></p>
      <?php endif ?>

      <?php if ($b['daftar_isi']): ?>
        <h2 class="h-small">Daftar isi</h2>
        <ol class="toc">
          <?php foreach (preg_split('/\R/', trim($b['daftar_isi'])) as $baris): $bagian = explode(' · ', $baris, 2); ?>
            <li><?= isset($bagian[1]) ? '<span class="toc__no">' . e($bagian[0]) . '</span>' . e($bagian[1]) : e($baris) ?></li>
          <?php endforeach ?>
        </ol>
      <?php endif ?>

      <h2 class="h-small">Detail bibliografi</h2>
      <dl class="card-catalog">
        <div><dt>Penerbit</dt><dd><?= e($b['penerbit'] ?: '—') ?></dd></div>
        <div><dt>Tahun terbit</dt><dd><?= $b['tahun'] ? (int) $b['tahun'] : '—' ?></dd></div>
        <div><dt>ISBN</dt><dd class="mono"><?= e($b['isbn'] ?: '—') ?></dd></div>
        <div><dt>Halaman</dt><dd><?= $b['halaman'] ? number_format((int) $b['halaman'], 0, ',', '.') . ' hlm.' : '—' ?></dd></div>
        <div><dt>Bahasa</dt><dd><?= e($b['bahasa']) ?></dd></div>
        <div><dt>Kategori</dt><dd><a href="<?= url('katalog.php?kelas=' . $b['kode']) ?>"><?= e($b['kategori']) ?></a></dd></div>
      </dl>
    </div>
  </article>

  <?php if ($serupa): ?>
    <section class="section section--tight">
      <div class="section__head">
        <h2>Satu rak dengan buku ini</h2>
        <a class="link-arrow" href="<?= url('katalog.php?kelas=' . $b['kode']) ?>">Semua di kategori ini</a>
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
