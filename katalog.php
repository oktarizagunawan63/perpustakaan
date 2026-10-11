<?php
require __DIR__ . '/config.php';

$cari     = trim($_GET['q'] ?? '');
$kelas    = $_GET['kelas'] ?? '';
$tersedia = !empty($_GET['tersedia']);
$urut     = $_GET['urut'] ?? 'relevan';
$hal      = max(1, (int) ($_GET['hal'] ?? 1));
$perHal   = 10;

$where = [];
$args = [];
if ($cari !== '') {
    $where[] = '(b.judul LIKE ? OR b.penulis LIKE ? OR b.penerbit LIKE ? OR b.isbn LIKE ? OR b.no_panggil LIKE ?)';
    array_push($args, ...array_fill(0, 5, '%' . $cari . '%'));
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
                  GROUP BY k.id ORDER BY k.kode')->fetchAll();
$namaKelas = array_column($daftarKelas, 'nama', 'kode');

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
    <h1>Katalog koleksi</h1>
    <form class="search" action="<?= url('katalog.php') ?>" role="search">
      <label class="sr-only" for="q">Kata kunci</label>
      <input id="q" name="q" type="search" value="<?= e($cari) ?>" placeholder="Judul, penulis, penerbit, ISBN, atau nomor panggil">
      <?php if ($kelas !== ''): ?><input type="hidden" name="kelas" value="<?= e($kelas) ?>"><?php endif ?>
      <button class="btn" type="submit">Cari</button>
    </form>
  </div>
</div>

<div class="wrap catalog">
  <form class="filters" action="<?= url('katalog.php') ?>" data-autosubmit>
    <input type="hidden" name="q" value="<?= e($cari) ?>">
    <fieldset>
      <legend>Kelas</legend>
      <label class="radio"><input type="radio" name="kelas" value=""<?= $kelas === '' ? ' checked' : '' ?>> Semua kelas</label>
      <?php foreach ($daftarKelas as $k): ?>
        <label class="radio<?= $k['jumlah'] ? '' : ' is-empty' ?>">
          <input type="radio" name="kelas" value="<?= e($k['kode']) ?>"<?= $kelas === $k['kode'] ? ' checked' : '' ?>>
          <span class="callno"><?= e($k['kode']) ?></span> <?= e($k['nama']) ?>
          <span class="count"><?= (int) $k['jumlah'] ?></span>
        </label>
      <?php endforeach ?>
    </fieldset>
    <fieldset>
      <legend>Ketersediaan</legend>
      <label class="check"><input type="checkbox" name="tersedia" value="1"<?= $tersedia ? ' checked' : '' ?>> Hanya yang ada di rak</label>
    </fieldset>
    <fieldset>
      <legend><label for="urut">Urutkan</label></legend>
      <select id="urut" name="urut">
        <?php foreach (['relevan' => 'Paling sesuai', 'judul' => 'Judul A–Z', 'tahun' => 'Tahun terbit terbaru', 'terbaru' => 'Baru ditambahkan'] as $v => $l): ?>
          <option value="<?= $v ?>"<?= $urut === $v ? ' selected' : '' ?>><?= $l ?></option>
        <?php endforeach ?>
      </select>
    </fieldset>
    <noscript><button class="btn btn--sm" type="submit">Terapkan</button></noscript>
  </form>

  <section class="results" aria-live="polite">
    <p class="results__meta">
      <strong><?= $total ?></strong> judul<?= $cari !== '' ? ' untuk “' . e($cari) . '”' : '' ?><?= $kelas !== '' && isset($namaKelas[$kelas]) ? ' di kelas ' . e($kelas . ' ' . $namaKelas[$kelas]) : '' ?>
      <?php if ($cari !== '' || $kelas !== '' || $tersedia): ?>
        · <a href="<?= url('katalog.php') ?>">Hapus semua filter</a>
      <?php endif ?>
    </p>

    <?php if (!$hasil): ?>
      <div class="empty">
        <h2>Belum ketemu.</h2>
        <p>Coba kata kunci yang lebih pendek (misalnya nama belakang penulis saja), periksa ejaan, atau hapus filter kelas.</p>
        <a class="btn btn--ghost" href="<?= url('katalog.php') ?>">Lihat seluruh koleksi</a>
      </div>
    <?php else: ?>
      <ol class="result-list" start="<?= $offset + 1 ?>">
        <?php foreach ($hasil as $b): ?>
          <li class="result">
            <a href="<?= url('buku.php?id=' . $b['id']) ?>" tabindex="-1"><?= sampul($b, 'sm') ?></a>
            <div class="result__body">
              <h2 class="result__title"><a href="<?= url('buku.php?id=' . $b['id']) ?>"><?= $sorot($b['judul']) ?></a></h2>
              <p class="result__by"><?= $sorot($b['penulis']) ?></p>
              <p class="result__meta"><?= $sorot($b['penerbit']) ?>, <?= (int) $b['tahun'] ?> · <?= e($b['bahasa']) ?></p>
            </div>
            <div class="result__loc">
              <?php if ($b['tersedia'] > 0): ?>
                <span class="avail avail--yes">Tersedia <?= (int) $b['tersedia'] ?> dari <?= (int) $b['stok'] ?></span>
              <?php else: ?>
                <span class="avail avail--no">Semua sedang dipinjam</span>
              <?php endif ?>
              <span class="callno"><?= e($b['no_panggil']) ?></span>
              <span class="muted small">Rak <?= e($b['rak']) ?></span>
            </div>
          </li>
        <?php endforeach ?>
      </ol>

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
