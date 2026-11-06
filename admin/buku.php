<?php
require __DIR__ . '/../config.php';
wajib_admin();

const DIR_PDF = __DIR__ . '/../berkas/';
const DIR_SAMPUL = __DIR__ . '/../assets/sampul/';

/** Nama file aman dari judul + akhiran acak, mis. "farmakologi-dasar-3fa91c.pdf". */
function nama_berkas(string $judul, string $ext): string
{
    $slug = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($judul)), '-') ?: 'buku';
    return substr($slug, 0, 60) . '-' . bin2hex(random_bytes(3)) . '.' . $ext;
}

/** @return array{0: ?string, 1: ?string} [nama file tersimpan, galat] */
function simpan_pdf(array $f, string $judul): array
{
    if ($f['error'] === UPLOAD_ERR_NO_FILE) return [null, null];
    if ($f['error'] !== UPLOAD_ERR_OK) return [null, 'Upload PDF gagal (kode ' . $f['error'] . '). Coba lagi.'];
    // Cek isi file, bukan nama/ekstensinya
    if ((new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']) !== 'application/pdf') return [null, 'File isi buku harus berformat PDF.'];
    $nama = nama_berkas($judul, 'pdf');
    if (!move_uploaded_file($f['tmp_name'], DIR_PDF . $nama)) return [null, 'PDF gagal disimpan ke folder berkas/.'];
    return [$nama, null];
}

/** Sampul dibuat ulang jadi JPEG lebar 480px: ukuran seragam dan isi selain gambar ikut terbuang. */
function simpan_sampul(array $f, string $judul): array
{
    if ($f['error'] === UPLOAD_ERR_NO_FILE) return [null, null];
    if ($f['error'] !== UPLOAD_ERR_OK) return [null, 'Upload sampul gagal (kode ' . $f['error'] . '). Coba lagi.'];
    if ($f['size'] > 10 * 1024 * 1024) return [null, 'Gambar sampul maksimal 10 MB.'];
    $info = @getimagesize($f['tmp_name']);
    if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) return [null, 'Sampul harus gambar JPG, PNG, atau WebP.'];
    $src = @imagecreatefromstring(file_get_contents($f['tmp_name']));
    if (!$src) return [null, 'Gambar sampul tidak bisa dibaca.'];
    $w = min(480, imagesx($src));
    $h = (int) round(imagesy($src) * $w / imagesx($src));
    $dst = imagecreatetruecolor($w, $h);
    imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255)); // latar putih untuk PNG transparan
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $w, $h, imagesx($src), imagesy($src));
    $nama = nama_berkas($judul, 'jpg');
    $ok = imagejpeg($dst, DIR_SAMPUL . $nama, 85);
    imagedestroy($src);
    imagedestroy($dst);
    return $ok ? [$nama, null] : [null, 'Sampul gagal disimpan.'];
}

$kategori = q('SELECT id, kode, nama FROM kategori ORDER BY nama')->fetchAll();
$kolom = ['judul', 'penulis', 'penerbit', 'tahun', 'isbn', 'kategori_id', 'no_panggil', 'rak', 'halaman', 'bahasa', 'stok', 'sinopsis', 'daftar_isi', 'file_pdf', 'sampul'];
$filePdf = array_map('basename', glob(DIR_PDF . '*.pdf') ?: []);
$kosong = ['id' => 0, 'bahasa' => 'Indonesia', 'stok' => 1, 'jenis' => 'ebook'] + array_fill_keys($kolom, '');

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$modeForm = $id > 0 || isset($_GET['baru']);
$b = $kosong;
$galat = [];
$fisikDipinjam = fn(int $bukuId) => (int) q('SELECT COUNT(*) FROM peminjaman WHERE buku_id = ? AND format = "fisik" AND tgl_kembali IS NULL', [$bukuId])->fetchColumn();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_cek();

    // Ubah stok cepat dari daftar koleksi
    if (isset($_POST['stok_cepat'])) {
        $bukuId = (int) $_POST['stok_cepat'];
        $stok = trim($_POST['stok'] ?? '');
        $buku = q('SELECT judul FROM buku WHERE id = ?', [$bukuId])->fetch();
        $dipinjam = $fisikDipinjam($bukuId);
        if (!$buku) {
            flash('error', 'Buku tidak ditemukan.');
        } elseif (!ctype_digit($stok) || $stok > 255) {
            flash('error', 'Stok harus angka 0–255.');
        } elseif ($stok < $dipinjam) {
            flash('error', "Stok “{$buku['judul']}” tidak bisa di bawah $dipinjam — sebanyak itu sedang dipinjam.");
        } else {
            q('UPDATE buku SET stok = ? WHERE id = ?', [(int) $stok, $bukuId]);
            flash('ok', 'Stok fisik “' . $buku['judul'] . '” sekarang ' . (int) $stok . ' eksemplar.');
        }
        redirect('admin/buku.php' . ($_POST['q'] ?? '' ? '?q=' . urlencode($_POST['q']) : ''));
    }

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

    $lama = $id ? q('SELECT * FROM buku WHERE id = ?', [$id])->fetch() : null;
    $b = array_map(fn($v) => is_string($v) ? trim($v) : '', array_intersect_key($_POST, $kosong)) + $kosong;
    $b['id'] = $id;
    $b['sampul'] = $lama['sampul'] ?? '';
    $baru = !$id;
    $jenis = $baru && ($_POST['jenis'] ?? '') === 'fisik' ? 'fisik' : 'ebook';
    // E-book baru tidak punya eksemplar fisik; tambahkan lewat "stok fisik" di daftar koleksi bila ada versi cetaknya
    if ($baru && $jenis === 'ebook') $b['stok'] = '0';
    $adaPdfBaru = ($_FILES['pdf']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    $adaSampulBaru = ($_FILES['sampul_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;

    if ($b['judul'] === '') $galat['judul'] = 'Judul wajib diisi.';
    // Batas panjang mengikuti kolom database
    foreach (['judul' => 200, 'penulis' => 150, 'penerbit' => 120, 'no_panggil' => 40, 'rak' => 20, 'bahasa' => 30] as $f => $maks) {
        if (mb_strlen($b[$f]) > $maks) $galat[$f] = "Maksimal $maks karakter.";
    }
    if (!in_array((int) $b['kategori_id'], array_column($kategori, 'id'))) $galat['kategori_id'] = 'Pilih kategori.';
    if ($b['tahun'] !== '' && (!ctype_digit($b['tahun']) || $b['tahun'] < 1000 || $b['tahun'] > date('Y') + 1)) $galat['tahun'] = 'Tahun terbit 4 digit, maksimal ' . (date('Y') + 1) . '.';
    if ($b['isbn'] !== '' && !preg_match('/^(97[89])?\d{9}[\dX]$/', str_replace('-', '', $b['isbn']))) $galat['isbn'] = 'ISBN 10 atau 13 digit.';
    if ($b['halaman'] !== '' && (!ctype_digit($b['halaman']) || $b['halaman'] > 30000)) $galat['halaman'] = 'Isi angka saja.';
    if (!ctype_digit((string) $b['stok']) || $b['stok'] > 255) $galat['stok'] = 'Jumlah eksemplar 0–255.';
    elseif ($id && $b['stok'] < ($d = $fisikDipinjam($id))) $galat['stok'] = "Ada $d eksemplar sedang dipinjam; stok tidak boleh lebih kecil.";
    elseif ($baru && $jenis === 'fisik' && $b['stok'] < 1) $galat['stok'] = 'Buku fisik minimal 1 eksemplar.';
    if ($b['file_pdf'] !== '' && !in_array($b['file_pdf'], $filePdf, true)) $galat['pdf'] = 'Pilih file dari folder berkas/.';
    if ($baru && $jenis === 'ebook' && !$adaPdfBaru && $b['file_pdf'] === '') $galat['pdf'] = 'Upload file PDF isi buku.';
    if ($baru && $jenis === 'fisik' && !$adaSampulBaru) $galat['sampul'] = 'Upload foto sampul buku.';

    // File baru disimpan hanya bila isian lain sudah benar, supaya tidak ada file yatim
    if (!$galat) {
        [$pdf, $galat['pdf']] = simpan_pdf($_FILES['pdf'] ?? ['error' => UPLOAD_ERR_NO_FILE], $b['judul']);
        [$sampul, $galat['sampul']] = simpan_sampul($_FILES['sampul_file'] ?? ['error' => UPLOAD_ERR_NO_FILE], $b['judul']);
        $galat = array_filter($galat);
        if ($galat) {
            foreach ([DIR_PDF . $pdf, DIR_SAMPUL . $sampul] as $f) if (is_file($f)) unlink($f);
        } else {
            if ($pdf) $b['file_pdf'] = $pdf;
            if ($sampul) $b['sampul'] = $sampul;
        }
    }

    if (!$galat) {
        $nilai = [$b['judul'], $b['penulis'], $b['penerbit'], $b['tahun'] !== '' ? (int) $b['tahun'] : null, str_replace('-', '', $b['isbn']) ?: null, (int) $b['kategori_id'],
                  $b['no_panggil'], strtoupper($b['rak']), $b['halaman'] !== '' ? (int) $b['halaman'] : null, $b['bahasa'] ?: 'Indonesia', (int) $b['stok'], $b['sinopsis'] ?: null,
                  $b['daftar_isi'] ?: null, $b['file_pdf'] ?: null, $b['sampul'] ?: null];
        if ($id) {
            q('UPDATE buku SET ' . implode(' = ?, ', $kolom) . ' = ? WHERE id = ?', [...$nilai, $id]);
            flash('ok', 'Perubahan “' . $b['judul'] . '” disimpan.');
        } else {
            q('INSERT INTO buku (' . implode(', ', $kolom) . ') VALUES (' . rtrim(str_repeat('?, ', count($kolom)), ', ') . ')', $nilai);
            flash('ok', '“' . $b['judul'] . '” ditambahkan ke katalog sebagai ' . strtolower(LABEL_FORMAT[$jenis]) . '.');
        }
        redirect('admin/buku.php');
    }
    $b['jenis'] = $jenis;
    $modeForm = true;
} elseif ($id) {
    $b = (q('SELECT * FROM buku WHERE id = ?', [$id])->fetch() ?: redirect('admin/buku.php')) + ['jenis' => ''];
}

if (!$modeForm) {
    $cari = trim($_GET['q'] ?? '');
    $args = $cari !== '' ? array_fill(0, 4, "%$cari%") : [];
    $daftar = q('SELECT b.*, k.nama kategori, ' . SQL_DIPINJAM . ' AS dipinjam FROM buku b JOIN kategori k ON k.id = b.kategori_id '
        . ($cari !== '' ? 'WHERE b.judul LIKE ? OR b.penulis LIKE ? OR b.isbn LIKE ? OR b.no_panggil LIKE ? ' : '') . 'ORDER BY b.judul', $args)->fetchAll();
}

$err = fn(string $f) => isset($galat[$f]) ? '<p class="field__error">' . e($galat[$f]) . '</p>' : '';
$inv = fn(string $f) => isset($galat[$f]) ? ' aria-invalid="true"' : '';
$opsionalTerisi = $id || array_filter(array_intersect_key($b, array_flip(['penulis', 'penerbit', 'tahun', 'isbn', 'halaman', 'no_panggil', 'rak', 'sinopsis', 'daftar_isi'])))
    || array_intersect_key($galat, array_flip(['penulis', 'penerbit', 'tahun', 'isbn', 'halaman', 'bahasa', 'no_panggil', 'rak']));

$judul = $modeForm ? ($id ? 'Ubah buku' : 'Tambah buku') : 'Koleksi';
$admin = true;
$menu = 'buku';
require __DIR__ . '/../partials/header.php';
?>

<div class="wrap admin">
<?php if ($modeForm): ?>
  <nav class="crumbs"><a href="<?= url('admin/buku.php') ?>">Koleksi</a> <span aria-hidden="true">/</span> <?= $id ? e($b['judul']) : 'Buku baru' ?></nav>
  <div class="admin__head"><h1><?= $id ? 'Ubah data buku' : 'Tambah buku baru' ?></h1></div>

  <?php if ($galat): ?><div class="notice notice--error" role="alert">Periksa lagi <?= count($galat) ?> isian yang ditandai.<?= $_FILES ? ' File yang tadi dipilih perlu dipilih ulang.' : '' ?></div><?php endif ?>

  <form method="post" enctype="multipart/form-data" class="form panel book-form<?= $id ? ' is-edit' : '' ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= $id ?>">
    <?= keterangan_wajib() ?>

    <?php if (!$id): ?>
      <fieldset>
        <legend>Jenis buku <?= wajib() ?></legend>
        <div class="choices">
          <?php foreach (['ebook' => 'Upload file PDF isi buku', 'fisik' => 'Upload foto sampul & isi jumlah eksemplar'] as $f => $ket): ?>
            <label class="choice">
              <input type="radio" name="jenis" value="<?= $f ?>"<?= $b['jenis'] === $f ? ' checked' : '' ?>>
              <span class="choice__body"><strong><?= LABEL_FORMAT[$f] ?></strong><span><?= $ket ?></span></span>
            </label>
          <?php endforeach ?>
        </div>
      </fieldset>
    <?php endif ?>

    <fieldset>
      <legend>Buku</legend>
      <div class="field-row">
        <div class="field"><label for="judul">Judul <?= wajib() ?></label><input id="judul" name="judul" value="<?= e($b['judul']) ?>" required<?= $inv('judul') ?>><?= $err('judul') ?></div>
        <div class="field">
          <label for="kategori_id">Kategori <?= wajib() ?></label>
          <select id="kategori_id" name="kategori_id" required<?= $inv('kategori_id') ?>>
            <option value="">Pilih…</option>
            <?php foreach ($kategori as $k): ?>
              <option value="<?= $k['id'] ?>"<?= (int) $b['kategori_id'] === (int) $k['id'] ? ' selected' : '' ?>><?= e($k['nama'] . ' (' . $k['kode'] . ')') ?></option>
            <?php endforeach ?>
          </select><?= $err('kategori_id') ?>
        </div>
      </div>

      <div class="upload-row">
        <div class="field only-ebook">
          <label for="pdf">File PDF isi buku <?= $id ? opsional('ganti bila perlu') : wajib() ?></label>
          <?php if ($b['file_pdf']): ?><p class="field__hint">Sekarang: <span class="mono"><?= e($b['file_pdf']) ?></span></p><?php endif ?>
          <input id="pdf" name="pdf" type="file" accept="application/pdf,.pdf" class="file"<?= $inv('pdf') ?>>
          <?= $err('pdf') ?>
          <?php if ($filePdf): ?>
            <label class="field__hint" for="file_pdf">atau pakai file yang sudah ada di folder <span class="mono">berkas/</span>:</label>
            <select id="file_pdf" name="file_pdf" class="select-sm">
              <option value=""><?= $id ? '— Tanpa isi digital —' : '— Tidak —' ?></option>
              <?php foreach ($filePdf as $pdf): ?>
                <option value="<?= e($pdf) ?>"<?= $b['file_pdf'] === $pdf ? ' selected' : '' ?>><?= e($pdf) ?></option>
              <?php endforeach ?>
            </select>
          <?php endif ?>
        </div>

        <div class="field">
          <label for="sampul_file">Foto sampul <?= $id ? opsional('ganti bila perlu') : '<span class="only-fisik">' . wajib() . '</span><span class="only-ebook">' . opsional() . '</span>' ?></label>
          <div class="cover-input">
            <?= $b['sampul'] ? sampul($b, 'sm') : '<span class="cover-input__empty" aria-hidden="true">Belum ada</span>' ?>
            <div>
              <input id="sampul_file" name="sampul_file" type="file" accept="image/jpeg,image/png,image/webp" class="file"<?= $inv('sampul') ?>>
              <p class="field__hint">JPG, PNG, atau WebP, maks. 10 MB. Otomatis diperkecil.</p>
            </div>
          </div>
          <?= $err('sampul') ?>
        </div>

        <div class="field only-fisik">
          <label for="stok">Jumlah eksemplar fisik <?= wajib() ?></label>
          <input id="stok" name="stok" type="number" min="0" max="255" value="<?= e((string) $b['stok']) ?>"<?= $inv('stok') ?>><?= $err('stok') ?>
        </div>
      </div>
    </fieldset>

    <details class="more"<?= $opsionalTerisi ? ' open' : '' ?>>
      <summary>Detail lainnya <span class="opt">(opsional) penulis, penerbit, tahun, rak, sinopsis, daftar isi</span></summary>
      <div class="field-row">
        <div class="field"><label for="penulis">Penulis <?= opsional() ?></label><input id="penulis" name="penulis" value="<?= e($b['penulis']) ?>"<?= $inv('penulis') ?>><?= $err('penulis') ?></div>
        <div class="field"><label for="penerbit">Penerbit <?= opsional() ?></label><input id="penerbit" name="penerbit" value="<?= e($b['penerbit']) ?>"<?= $inv('penerbit') ?>><?= $err('penerbit') ?></div>
      </div>
      <div class="field-row field-row--4">
        <div class="field"><label for="tahun">Tahun terbit <?= opsional() ?></label><input id="tahun" name="tahun" inputmode="numeric" maxlength="4" value="<?= e((string) $b['tahun']) ?>"<?= $inv('tahun') ?>><?= $err('tahun') ?></div>
        <div class="field"><label for="isbn">ISBN <?= opsional() ?></label><input id="isbn" name="isbn" inputmode="numeric" value="<?= e((string) $b['isbn']) ?>"<?= $inv('isbn') ?>><?= $err('isbn') ?></div>
        <div class="field"><label for="halaman">Halaman <?= opsional() ?></label><input id="halaman" name="halaman" inputmode="numeric" value="<?= e((string) $b['halaman']) ?>"<?= $inv('halaman') ?>><?= $err('halaman') ?></div>
        <div class="field"><label for="bahasa">Bahasa <?= opsional() ?></label><input id="bahasa" name="bahasa" list="dl-bahasa" value="<?= e($b['bahasa']) ?>"<?= $inv('bahasa') ?>><?= $err('bahasa') ?>
          <datalist id="dl-bahasa"><option value="Indonesia"><option value="Inggris"><option value="Arab"><option value="Jawa"></datalist></div>
      </div>
      <div class="field-row">
        <div class="field"><label for="no_panggil">Nomor panggil <?= opsional() ?></label><input id="no_panggil" name="no_panggil" class="mono" placeholder="615.7 KAT b" value="<?= e($b['no_panggil']) ?>"<?= $inv('no_panggil') ?>><?= $err('no_panggil') ?>
          <p class="field__hint">Kode kategori + 3 huruf nama belakang penulis + huruf awal judul.</p></div>
        <div class="field"><label for="rak">Rak <?= opsional() ?></label><input id="rak" name="rak" placeholder="F-01" value="<?= e($b['rak']) ?>"<?= $inv('rak') ?>><?= $err('rak') ?></div>
      </div>
      <div class="field"><label for="sinopsis">Sinopsis <?= opsional() ?></label><textarea id="sinopsis" name="sinopsis" rows="4"><?= e($b['sinopsis']) ?></textarea></div>
      <div class="field"><label for="daftar_isi">Daftar isi <?= opsional() ?></label><textarea id="daftar_isi" name="daftar_isi" rows="6" placeholder="Bab 1 · Konsep Dasar Farmakologi"><?= e($b['daftar_isi']) ?></textarea>
        <p class="field__hint">Satu bagian per baris. Pisahkan nomor dan judul dengan “ · ”, misalnya <span class="mono">Bab 1 · Konsep Dasar Farmakologi</span>.</p></div>
    </details>

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
    <div><h1>Koleksi</h1><p class="muted"><?= count($daftar) ?> judul<?= $cari !== '' ? ' cocok dengan “' . e($cari) . '”' : '' ?>. Ubah stok fisik langsung di tabel.</p></div>
    <a class="btn" href="<?= url('admin/buku.php?baru') ?>">Tambah buku</a>
  </div>

  <section class="panel">
    <form class="search search--sm panel__search" role="search">
      <label class="sr-only" for="q">Cari koleksi</label>
      <input id="q" name="q" type="search" value="<?= e($cari) ?>" placeholder="Judul, penulis, ISBN, nomor panggil">
      <button class="btn btn--sm btn--ghost">Cari</button>
    </form>
    <div class="table-wrap">
      <table class="table table--stack">
        <thead><tr><th>Judul</th><th>Tersedia sebagai</th><th class="num">Dipinjam fisik</th><th>Stok fisik</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($daftar as $r): ?>
          <tr>
            <td class="koleksi__judul">
              <?= sampul($r, 'sm') ?>
              <div><a href="<?= url('buku.php?id=' . $r['id']) ?>"><?= e($r['judul']) ?></a>
                <span class="sub"><?= e(implode(' · ', array_filter([$r['penulis'], $r['tahun'], $r['kategori']]))) ?></span></div>
            </td>
            <td>
              <?php if ($r['file_pdf']): ?><span class="tag tag--accent">E-book</span><?php endif ?>
              <?php if ($r['stok'] > 0): ?><span class="tag">Fisik</span><?php endif ?>
            </td>
            <td class="num" data-label="Dipinjam"><?= (int) $r['dipinjam'] ?: '—' ?></td>
            <td>
              <form method="post" class="stock-form">
                <?= csrf_field() ?>
                <input type="hidden" name="q" value="<?= e($cari) ?>">
                <label class="sr-only" for="stok-<?= $r['id'] ?>">Stok fisik <?= e($r['judul']) ?></label>
                <input id="stok-<?= $r['id'] ?>" name="stok" type="number" min="<?= (int) $r['dipinjam'] ?>" max="255" value="<?= (int) $r['stok'] ?>">
                <button class="btn btn--sm btn--ghost" name="stok_cepat" value="<?= $r['id'] ?>">Simpan</button>
              </form>
            </td>
            <td class="actions"><a class="btn btn--sm btn--ghost" href="<?= url('admin/buku.php?id=' . $r['id']) ?>">Ubah</a></td>
          </tr>
        <?php endforeach ?>
        <?php if (!$daftar): ?><tr><td colspan="5" class="muted">Tidak ada judul yang cocok.</td></tr><?php endif ?>
        </tbody>
      </table>
    </div>
  </section>
<?php endif ?>
</div>

<?php require __DIR__ . '/../partials/footer.php'; ?>
