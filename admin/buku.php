<?php
require __DIR__ . '/../config.php';
wajib_admin();

$kategori = q('SELECT id, kode, nama FROM kategori ORDER BY kode')->fetchAll();
$kolom = ['judul', 'penulis', 'penerbit', 'tahun', 'isbn', 'kategori_id', 'no_panggil', 'rak', 'halaman', 'bahasa', 'stok', 'sinopsis'];
$kosong = ['id' => 0, 'bahasa' => 'Indonesia', 'stok' => 1] + array_fill_keys($kolom, '');

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$modeForm = $id > 0 || isset($_GET['baru']);
$b = $kosong;
$galat = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_cek();

    if (isset($_POST['hapus'])) {
        try {
            q('DELETE FROM buku WHERE id = ?', [$id]);
            flash('ok', 'Buku dihapus dari katalog.');
        } catch (PDOException) {
            flash('error', 'Buku ini punya riwayat peminjaman, jadi tidak dihapus agar riwayat tetap utuh. Kurangi stok bila eksemplarnya hilang atau rusak.');
            redirect('admin/buku.php?id=' . $id);
        }
        redirect('admin/buku.php');
    }

    $b = array_map('trim', array_intersect_key($_POST, $kosong)) + $kosong;
    $b['id'] = $id;
    $dipinjam = $id ? (int) q('SELECT COUNT(*) FROM peminjaman WHERE buku_id = ? AND tgl_kembali IS NULL', [$id])->fetchColumn() : 0;

    foreach (['judul' => 'Judul', 'penulis' => 'Penulis', 'penerbit' => 'Penerbit', 'no_panggil' => 'Nomor panggil', 'rak' => 'Rak'] as $f => $label) {
        if ($b[$f] === '') $galat[$f] = "$label wajib diisi.";
    }
    if (!ctype_digit($b['tahun']) || $b['tahun'] < 1000 || $b['tahun'] > date('Y') + 1) $galat['tahun'] = 'Tahun terbit 4 digit, maksimal ' . (date('Y') + 1) . '.';
    if ($b['isbn'] !== '' && !preg_match('/^(97[89])?\d{9}[\dX]$/', str_replace('-', '', $b['isbn']))) $galat['isbn'] = 'ISBN 10 atau 13 digit.';
    if ($b['halaman'] !== '' && (!ctype_digit($b['halaman']) || $b['halaman'] > 30000)) $galat['halaman'] = 'Isi angka saja.';
    if (!ctype_digit((string) $b['stok']) || $b['stok'] > 255) $galat['stok'] = 'Stok 0–255.';
    elseif ($b['stok'] < $dipinjam) $galat['stok'] = "Ada $dipinjam eksemplar sedang dipinjam; stok tidak boleh lebih kecil.";
    if (!in_array((int) $b['kategori_id'], array_column($kategori, 'id'))) $galat['kategori_id'] = 'Pilih kelas.';

    if (!$galat) {
        $nilai = [$b['judul'], $b['penulis'], $b['penerbit'], (int) $b['tahun'], str_replace('-', '', $b['isbn']) ?: null, (int) $b['kategori_id'],
                  $b['no_panggil'], strtoupper($b['rak']), $b['halaman'] !== '' ? (int) $b['halaman'] : null, $b['bahasa'] ?: 'Indonesia', (int) $b['stok'], $b['sinopsis'] ?: null];
        if ($id) {
            q('UPDATE buku SET ' . implode(' = ?, ', $kolom) . ' = ? WHERE id = ?', [...$nilai, $id]);
            flash('ok', 'Perubahan “' . $b['judul'] . '” disimpan.');
        } else {
            q('INSERT INTO buku (' . implode(', ', $kolom) . ') VALUES (' . rtrim(str_repeat('?, ', count($kolom)), ', ') . ')', $nilai);
            flash('ok', '“' . $b['judul'] . '” ditambahkan ke katalog.');
        }
        redirect('admin/buku.php');
    }
    $modeForm = true;
} elseif ($id) {
    $b = q('SELECT * FROM buku WHERE id = ?', [$id])->fetch() ?: redirect('admin/buku.php');
}

if (!$modeForm) {
    $cari = trim($_GET['q'] ?? '');
    $args = $cari !== '' ? array_fill(0, 4, "%$cari%") : [];
    $daftar = q('SELECT b.*, k.kode, ' . SQL_DIPINJAM . ' AS dipinjam FROM buku b JOIN kategori k ON k.id = b.kategori_id '
        . ($cari !== '' ? 'WHERE b.judul LIKE ? OR b.penulis LIKE ? OR b.isbn LIKE ? OR b.no_panggil LIKE ? ' : '') . 'ORDER BY b.judul', $args)->fetchAll();
}

$err = fn(string $f) => isset($galat[$f]) ? '<p class="field__error">' . e($galat[$f]) . '</p>' : '';
$inv = fn(string $f) => isset($galat[$f]) ? ' aria-invalid="true"' : '';

$judul = $modeForm ? ($id ? 'Ubah buku' : 'Tambah buku') : 'Koleksi';
$admin = true;
$menu = 'buku';
require __DIR__ . '/../partials/header.php';
?>

<div class="wrap admin">
<?php if ($modeForm): ?>
  <nav class="crumbs"><a href="<?= url('admin/buku.php') ?>">Koleksi</a> <span aria-hidden="true">/</span> <?= $id ? e($b['judul']) : 'Buku baru' ?></nav>
  <div class="admin__head"><h1><?= $id ? 'Ubah data buku' : 'Tambah buku' ?></h1></div>

  <?php if ($galat): ?><div class="notice notice--error" role="alert">Periksa lagi <?= count($galat) ?> isian yang ditandai.</div><?php endif ?>

  <form method="post" class="form panel book-form">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= $id ?>">

    <fieldset>
      <legend>Bibliografi</legend>
      <div class="field"><label for="judul">Judul</label><input id="judul" name="judul" value="<?= e($b['judul']) ?>" required<?= $inv('judul') ?>><?= $err('judul') ?></div>
      <div class="field-row">
        <div class="field"><label for="penulis">Penulis</label><input id="penulis" name="penulis" value="<?= e($b['penulis']) ?>" required<?= $inv('penulis') ?>><?= $err('penulis') ?></div>
        <div class="field"><label for="penerbit">Penerbit</label><input id="penerbit" name="penerbit" value="<?= e($b['penerbit']) ?>" required<?= $inv('penerbit') ?>><?= $err('penerbit') ?></div>
      </div>
      <div class="field-row field-row--4">
        <div class="field"><label for="tahun">Tahun</label><input id="tahun" name="tahun" inputmode="numeric" maxlength="4" value="<?= e((string) $b['tahun']) ?>" required<?= $inv('tahun') ?>><?= $err('tahun') ?></div>
        <div class="field"><label for="isbn">ISBN <span class="opt">opsional</span></label><input id="isbn" name="isbn" inputmode="numeric" value="<?= e($b['isbn']) ?>"<?= $inv('isbn') ?>><?= $err('isbn') ?></div>
        <div class="field"><label for="halaman">Halaman</label><input id="halaman" name="halaman" inputmode="numeric" value="<?= e((string) $b['halaman']) ?>"<?= $inv('halaman') ?>><?= $err('halaman') ?></div>
        <div class="field"><label for="bahasa">Bahasa</label><input id="bahasa" name="bahasa" list="dl-bahasa" value="<?= e($b['bahasa']) ?>">
          <datalist id="dl-bahasa"><option value="Indonesia"><option value="Inggris"><option value="Arab"><option value="Jawa"></datalist></div>
      </div>
      <div class="field"><label for="sinopsis">Sinopsis <span class="opt">opsional</span></label><textarea id="sinopsis" name="sinopsis" rows="4"><?= e($b['sinopsis']) ?></textarea></div>
    </fieldset>

    <fieldset>
      <legend>Penempatan &amp; stok</legend>
      <div class="field-row field-row--4">
        <div class="field">
          <label for="kategori_id">Kelas DDC</label>
          <select id="kategori_id" name="kategori_id" required<?= $inv('kategori_id') ?>>
            <option value="">Pilih…</option>
            <?php foreach ($kategori as $k): ?>
              <option value="<?= $k['id'] ?>"<?= (int) $b['kategori_id'] === (int) $k['id'] ? ' selected' : '' ?>><?= e($k['kode'] . ' — ' . $k['nama']) ?></option>
            <?php endforeach ?>
          </select><?= $err('kategori_id') ?>
        </div>
        <div class="field"><label for="no_panggil">Nomor panggil</label><input id="no_panggil" name="no_panggil" class="mono" placeholder="005.1 MAR c" value="<?= e($b['no_panggil']) ?>" required<?= $inv('no_panggil') ?>><?= $err('no_panggil') ?></div>
        <div class="field"><label for="rak">Rak</label><input id="rak" name="rak" placeholder="B-01" value="<?= e($b['rak']) ?>" required<?= $inv('rak') ?>><?= $err('rak') ?></div>
        <div class="field"><label for="stok">Jumlah eksemplar</label><input id="stok" name="stok" type="number" min="0" max="255" value="<?= e((string) $b['stok']) ?>" required<?= $inv('stok') ?>><?= $err('stok') ?></div>
      </div>
      <p class="field__hint">Nomor panggil = kelas DDC + 3 huruf nama belakang penulis + huruf awal judul.</p>
    </fieldset>

    <div class="form__actions">
      <button class="btn" type="submit"><?= $id ? 'Simpan perubahan' : 'Tambahkan ke katalog' ?></button>
      <a class="btn btn--ghost" href="<?= url('admin/buku.php') ?>">Batal</a>
      <?php if ($id): ?>
        <button class="btn btn--danger" name="hapus" value="1" formnovalidate data-confirm-btn="Hapus “<?= e($b['judul']) ?>” dari katalog? Tindakan ini tidak bisa dibatalkan.">Hapus buku</button>
      <?php endif ?>
    </div>
  </form>

<?php else: ?>
  <div class="admin__head">
    <div><h1>Koleksi</h1><p class="muted"><?= count($daftar) ?> judul<?= $cari !== '' ? ' cocok dengan “' . e($cari) . '”' : '' ?></p></div>
    <a class="btn" href="<?= url('admin/buku.php?baru') ?>">Tambah buku</a>
  </div>

  <section class="panel">
    <form class="search search--sm panel__search" role="search">
      <label class="sr-only" for="q">Cari koleksi</label>
      <input id="q" name="q" type="search" value="<?= e($cari) ?>" placeholder="Judul, penulis, ISBN, nomor panggil">
      <button class="btn btn--sm btn--ghost">Cari</button>
    </form>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Judul</th><th>Nomor panggil</th><th>Rak</th><th class="num">Stok</th><th class="num">Dipinjam</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($daftar as $r): ?>
          <tr>
            <td><a href="<?= url('buku.php?id=' . $r['id']) ?>"><?= e($r['judul']) ?></a><span class="sub"><?= e($r['penulis']) ?> · <?= (int) $r['tahun'] ?></span></td>
            <td class="mono"><?= e($r['no_panggil']) ?></td>
            <td><?= e($r['rak']) ?></td>
            <td class="num"><?= (int) $r['stok'] ?></td>
            <td class="num"><?= (int) $r['dipinjam'] ?: '—' ?></td>
            <td class="actions"><a class="btn btn--sm btn--ghost" href="<?= url('admin/buku.php?id=' . $r['id']) ?>">Ubah</a></td>
          </tr>
        <?php endforeach ?>
        <?php if (!$daftar): ?><tr><td colspan="6" class="muted">Tidak ada judul yang cocok.</td></tr><?php endif ?>
        </tbody>
      </table>
    </div>
  </section>
<?php endif ?>
</div>

<?php require __DIR__ . '/../partials/footer.php'; ?>
