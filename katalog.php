<?php
require __DIR__ . '/config.php';

$cari     = trim($_GET['q'] ?? '');
$kelas    = $_GET['kelas'] ?? '';
$tersedia = !empty($_GET['tersedia']);
$urut     = $_GET['urut'] ?? 'relevan';
$hal      = max(1, (int) ($_GET['hal'] ?? 1));
$perHal   = 12;

$where = [];
$args = [];
if ($cari !== '') {
    $where[] = '(b.judul LIKE ? OR b.penulis LIKE ? OR b.penerbit LIKE ? OR b.isbn LIKE ? OR b.no_panggil LIKE ? OR b.daftar_isi LIKE ? OR b.sinopsis LIKE ?)';
    array_push($args, ...array_fill(0, 7, '%' . $cari . '%'));
}
if ($kelas !== '') { $where[] = 'k.kode = ?'; $args[] = $kelas; }
if ($tersedia)     { $where[] = 'b.stok > ' . SQL_DIPINJAM; }
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$orderSql = match ($urut) {
    'judul'   => 'b.judul',
    'terbaru' => 'b.dibuat DESC, b.id DESC',
    'tahun'   => 'b.tahun DESC',
    default   => $cari !== '' ? 'b.judul LIKE ' . db()->quote($cari . '%') . ' DESC, b.judul' : 'b.judul',
};

$total = (int) q("SELECT COUNT(*) FROM buku b JOIN kategori k ON k.id = b.kategori_id $whereSql", $args)->fetchColumn();
$jumlahHal = max(1, (int) ceil($total / $perHal));
$hal = min($hal, $jumlahHal);
$offset = ($hal - 1) * $perHal;

$hasil = q("SELECT b.*, k.kode, k.nama kategori, b.stok - " . SQL_DIPINJAM . " AS tersedia
            FROM buku b JOIN kategori k ON k.id = b.kategori_id
            $whereSql ORDER BY $orderSql LIMIT $perHal OFFSET $offset", $args)->fetchAll();

$daftarKelas = q('SELECT k.kode, k.nama, COUNT(b.id) jumlah FROM kategori k LEFT JOIN buku b ON b.kategori_id = k.id
                  GROUP BY k.id HAVING jumlah > 0 ORDER BY k.nama')->fetchAll();
$namaKelas = array_column($daftarKelas, 'nama', 'kode');
$semua = array_sum(array_column($daftarKelas, 'jumlah'));

// Tandai kata kunci di hasil (input sudah di-escape lebih dulu)
$sorot = function (string $teks) use ($cari): string {
    $teks = e($teks);
    return $cari === '' ? $teks : preg_replace('/' . preg_quote(e($cari), '/') . '/iu', '<mark>$0</mark>', $teks);
};
$linkHal = fn(int $n) => url('katalog.php?' . http_build_query(array_filter(['q' => $cari, 'kelas' => $kelas, 'tersedia' => $tersedia ? 1 : null, 'urut' => $urut !== 'relevan' ? $urut : null, 'hal' => $n > 1 ? $n : null])));

$judul = $cari !== '' ? "Cari “{$cari}”" : 'Katalog';
$menu = 'katalog';
require __DIR__ . '/partials/header.php';
?>

<div class="page-head">
  <div class="wrap">
    <h1>Katalog buku</h1>
    <form class="search" action="<?= url('katalog.php') ?>" role="search">
      <label class="sr-only" for="q">Kata kunci</label>
      <input id="q" name="q" type="search" value="<?= e($cari) ?>" placeholder="Cari judul, penulis, atau topik (mis. antibiotik)">
      <?php if ($kelas !== ''): ?><input type="hidden" name="kelas" value="<?= e($kelas) ?>"><?php endif ?>
      <button class="btn" type="submit">Cari</button>
    </form>
  </div>
</div>

<div class="wrap catalog2">
  <form class="catalog-bar" action="<?= url('katalog.php') ?>" data-autosubmit>
    <input type="hidden" name="q" value="<?= e($cari) ?>">
    <fieldset class="chips">
      <legend class="sr-only">Kategori</legend>
      <label class="chip"><input type="radio" name="kelas" value=""<?= $kelas === '' ? ' checked' : '' ?>><span>Semua <small><?= $semua ?></small></span></label>
      <?php foreach ($daftarKelas as $k): ?>
        <label class="chip"><input type="radio" name="kelas" value="<?= e($k['kode']) ?>"<?= $kelas === $k['kode'] ? ' checked' : '' ?>><span><?= e($k['nama']) ?> <small><?= (int) $k['jumlah'] ?></small></span></label>
      <?php endforeach ?>
    </fieldset>
    <div class="catalog-bar__tools">
      <label class="switch"><input type="checkbox" name="tersedia" value="1"<?= $tersedia ? ' checked' : '' ?>><span>Buku fisik tersedia</span></label>
      <label class="sr-only" for="urut">Urutkan</label>
      <select id="urut" name="urut">
        <?php foreach (['relevan' => 'Urutkan: paling sesuai', 'judul' => 'Urutkan: judul A–Z', 'tahun' => 'Urutkan: terbit terbaru', 'terbaru' => 'Urutkan: baru ditambahkan'] as $v => $l): ?>
          <option value="<?= $v ?>"<?= $urut === $v ? ' selected' : '' ?>><?= $l ?></option>
        <?php endforeach ?>
      </select>
      <noscript><button class="btn btn--sm" type="submit">Terapkan</button></noscript>
    </div>
  </form>

  <section aria-live="polite">
    <p class="results__meta">
      <strong><?= $total ?> buku</strong><?= $cari !== '' ? ' untuk “' . e($cari) . '”' : '' ?><?= isset($namaKelas[$kelas]) ? ' di ' . e($namaKelas[$kelas]) : '' ?><?= $tersedia ? ', buku fisiknya tersedia' : '' ?>
      <?php if ($cari !== '' || $kelas !== '' || $tersedia): ?>
        · <a href="<?= url('katalog.php') ?>">Hapus filter</a>
      <?php endif ?>
    </p>

    <?php if (!$hasil): ?>
      <div class="empty">
        <h2>Belum ketemu.</h2>
        <p>Coba kata kunci yang lebih pendek (misalnya nama belakang penulis saja), periksa ejaan, atau pilih kategori “Semua”.</p>
        <a class="btn btn--ghost" href="<?= url('katalog.php') ?>">Lihat semua buku</a>
      </div>
    <?php else: ?>
      <ul class="book-grid">
        <?php foreach ($hasil as $b): $link = url('buku.php?id=' . $b['id']); ?>
          <li class="book-card">
            <a class="book-card__cover" href="<?= $link ?>" tabindex="-1" aria-hidden="true"><?= sampul($b) ?>
              <?php if ($b['file_pdf']): ?><span class="book-card__flag">E-book</span><?php endif ?></a>
            <div class="book-card__body">
              <span class="book-card__cat"><?= e($b['kategori']) ?></span>
              <h2 class="book-card__title"><a href="<?= $link ?>"><?= $sorot($b['judul']) ?></a></h2>
              <p class="book-card__by"><?= implode(' · ', array_filter([$sorot($b['penulis']), $b['tahun'] ? (int) $b['tahun'] : ''])) ?></p>
              <p class="book-card__status <?= $b['tersedia'] > 0 || $b['file_pdf'] ? 'is-yes' : 'is-no' ?>">
                <?= $b['tersedia'] > 0 ? 'Fisik ' . (int) $b['tersedia'] . ' dari ' . (int) $b['stok'] . ' tersedia' : ($b['file_pdf'] ? 'Fisik habis · e-book tersedia' : 'Sedang dipinjam semua') ?>
              </p>
              <a class="btn btn--sm btn--block book-card__cta" href="<?= $link ?>"><?= $b['tersedia'] > 0 || $b['file_pdf'] ? 'Lihat & pinjam' : 'Lihat detail' ?></a>
            </div>
          </li>
        <?php endforeach ?>
      </ul>

      <?php if ($jumlahHal > 1): ?>
        <nav class="pager" aria-label="Halaman hasil">
          <?php if ($hal > 1): ?><a href="<?= $linkHal($hal - 1) ?>" rel="prev">← Sebelumnya</a><?php endif ?>
          <span>Halaman <?= $hal ?> dari <?= $jumlahHal ?></span>
          <?php if ($hal < $jumlahHal): ?><a href="<?= $linkHal($hal + 1) ?>" rel="next">Berikutnya →</a><?php endif ?>
        </nav>
      <?php endif ?>
    <?php endif ?>
  </section>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
