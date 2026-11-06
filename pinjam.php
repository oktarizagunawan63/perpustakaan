<?php
require __DIR__ . '/config.php';

$u = wajib_login();
if ($u['role'] === 'admin') {
    flash('info', 'Petugas mencatat peminjaman lewat menu Sirkulasi.');
    redirect('admin/sirkulasi.php');
}

$b = q('SELECT b.*, k.kode, k.nama kategori, b.stok - ' . SQL_DIPINJAM . ' AS tersedia
        FROM buku b JOIN kategori k ON k.id = b.kategori_id WHERE b.id = ?', [(int) ($_GET['id'] ?? 0)])->fetch();
if (!$b) {
    flash('error', 'Buku tidak ditemukan.');
    redirect('katalog.php');
}
if (q('SELECT 1 FROM peminjaman WHERE user_id = ? AND buku_id = ? AND tgl_kembali IS NULL', [$u['id'], $b['id']])->fetch()) {
    flash('info', 'Kamu sedang meminjam buku ini.');
    redirect('buku.php?id=' . $b['id']);
}

// Anggota yang mendapat giliran antrean memakai eksemplar yang sudah disimpan untuknya
$giliran = q('SELECT tgl_tersedia FROM antrean WHERE user_id = ? AND buku_id = ? AND tgl_tersedia IS NOT NULL', [$u['id'], $b['id']])->fetchColumn();
$bisaEbook = (bool) $b['file_pdf'];
$bisaFisik = $b['tersedia'] > 0 || $giliran;
$hari = (int) ($_POST['lama'] ?? LAMA_PINJAM);
$format = $_POST['format'] ?? ($bisaEbook && !$giliran ? 'ebook' : 'fisik');
$galat = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_cek();
    if (empty($_POST['setuju'])) {
        $galat = 'Centang persetujuan ketentuan peminjaman dulu.';
    } else {
        [$ok, $pesan] = catat_pinjaman($u, (int) $b['id'], $hari, $format);
        if ($ok) {
            flash('ok', $pesan);
            redirect($format === 'ebook' ? 'baca.php?id=' . $b['id'] : 'pinjaman.php');
        }
        $galat = $pesan;
    }
}

$jumlahPinjam = (int) q('SELECT COUNT(*) FROM peminjaman WHERE user_id = ? AND tgl_kembali IS NULL', [$u['id']])->fetchColumn();
$tunggakan = denda_belum_lunas((int) $u['id']);
$bisa = $jumlahPinjam < MAKS_PINJAM && !$tunggakan && ($bisaEbook || $bisaFisik);
$pilihanFormat = [
    'ebook' => [$bisaEbook, 'Baca langsung di website', $bisaEbook ? 'Selalu tersedia' : 'Belum ada versi e-book'],
    'fisik' => [$bisaFisik, 'Ambil di meja sirkulasi' . ($b['rak'] !== '' ? ' · Rak ' . $b['rak'] : ''),
        $giliran ? 'Disimpan untukmu' : ($bisaFisik ? 'Tersedia ' . (int) $b['tersedia'] . ' dari ' . (int) $b['stok'] : 'Sedang habis dipinjam')],
];

$judul = 'Pinjam ' . $b['judul'];
$menu = 'katalog';
require __DIR__ . '/partials/header.php';
?>

<div class="wrap">
  <nav class="crumbs" aria-label="Jejak halaman">
    <a href="<?= url('katalog.php') ?>">Katalog</a> <span aria-hidden="true">/</span>
    <a href="<?= url('buku.php?id=' . $b['id']) ?>"><?= e($b['judul']) ?></a>
  </nav>

  <div class="loan-form">
    <aside class="loan-form__book">
      <?= sampul($b, 'md') ?>
      <div>
        <h2 class="loan-form__title"><?= e($b['judul']) ?></h2>
        <p class="muted small"><?= e($b['penulis']) ?></p>
        <?php if ($b['no_panggil'] !== '' || $b['rak'] !== ''): ?>
          <p class="small"><?= $b['no_panggil'] !== '' ? '<span class="callno">' . e($b['no_panggil']) . '</span>' : '' ?><?= $b['rak'] !== '' ? ' · Rak ' . e($b['rak']) : '' ?></p>
        <?php endif ?>
      </div>
    </aside>

    <form class="form panel loan-form__main" method="post" novalidate>
      <?= csrf_field() ?>
      <h1>Pinjam buku</h1>
      <p class="muted">Kamu sedang meminjam <strong><?= $jumlahPinjam ?></strong> dari maksimal <?= MAKS_PINJAM ?> buku.</p>

      <?php if ($galat): ?><div class="notice notice--error" role="alert"><?= e($galat) ?></div><?php endif ?>
      <?php if (!$bisa): ?>
        <div class="notice notice--error" role="alert">
          <?php if ($tunggakan): ?>Kamu masih punya denda <?= rupiah($tunggakan) ?> yang belum lunas. <a href="<?= url('pinjaman.php#tagihan') ?>">Lihat tagihan</a>
          <?php elseif ($jumlahPinjam >= MAKS_PINJAM): ?>Kuota pinjamanmu sudah penuh. Kembalikan salah satu buku dulu.
          <?php else: ?>Buku fisiknya sedang habis dipinjam dan belum ada versi e-book. <a href="<?= url('buku.php?id=' . $b['id']) ?>">Ikut antrean</a><?php endif ?>
        </div>
      <?php elseif (!$bisaFisik && $b['stok'] > 0): ?>
        <p class="muted small">Mau buku fisiknya? <a href="<?= url('buku.php?id=' . $b['id']) ?>">Ikut antrean</a> di halaman buku.</p>
      <?php endif ?>

      <fieldset>
        <legend>Mau pinjam yang mana?</legend>
        <div class="choices">
          <?php foreach ($pilihanFormat as $f => [$ada, $cara, $stok]): ?>
            <label class="choice">
              <input type="radio" name="format" value="<?= $f ?>"<?= $format === $f && $ada ? ' checked' : '' ?><?= $ada && $bisa ? '' : ' disabled' ?>>
              <span class="choice__body">
                <strong><?= LABEL_FORMAT[$f] ?></strong>
                <span><?= e($cara) ?></span>
                <span class="tag <?= $ada ? 'tag--accent' : 'tag--late' ?>"><?= e($stok) ?></span>
              </span>
            </label>
          <?php endforeach ?>
        </div>
      </fieldset>

      <fieldset>
        <legend>Lama pinjam</legend>
        <div class="choices">
          <?php foreach (PILIHAN_LAMA as $n): ?>
            <label class="choice">
              <input type="radio" name="lama" value="<?= $n ?>"<?= $hari === $n ? ' checked' : '' ?><?= $bisa ? '' : ' disabled' ?>>
              <span class="choice__body">
                <strong><?= $n ?> hari</strong>
                <span>Sampai <?= tgl(date('Y-m-d', strtotime("+$n days"))) ?> bila mulai hari ini</span>
                <?php if ($n === LAMA_PINJAM): ?><span class="tag tag--accent">Disarankan</span><?php endif ?>
              </span>
            </label>
          <?php endforeach ?>
        </div>
      </fieldset>

      <fieldset>
        <legend>Ketentuan peminjaman</legend>
        <ul class="rules">
          <li><strong>Semua format:</strong> maksimal <?= MAKS_PINJAM ?> buku sekaligus, bisa diperpanjang <?= MAKS_PERPANJANG ?>× selama belum lewat jatuh tempo dan buku fisiknya tidak sedang diantre anggota lain.</li>
          <li><strong>E-book:</strong> dibaca di website selama masa pinjam, hanya untuk pribadi. Bisa dikembalikan kapan saja, dan berakhir otomatis di tanggal jatuh tempo tanpa denda.</li>
          <li><strong>Buku fisik — pengambilan:</strong> ambil di meja sirkulasi paling lambat <?= BATAS_AMBIL ?> hari setelah dipesan; lewat dari itu pesanan batal otomatis. Masa pinjam dihitung sejak buku diambil.</li>
          <li><strong>Buku fisik — terlambat:</strong> denda <?= rupiah(DENDA_PER_HARI) ?> per hari. Selama ada buku fisik yang terlambat atau denda yang belum lunas, kamu belum bisa meminjam buku lain.</li>
          <li><strong>Buku fisik — rusak</strong> (sobek, basah, dicoret, halaman lepas): denda <?= rupiah(DENDA_KONDISI['rusak']) ?>. <strong>Hilang:</strong> ganti dengan buku yang sama, atau bayar <?= rupiah(DENDA_KONDISI['hilang']) ?>.</li>
          <li><strong>Buku fisik — pengembalian:</strong> serahkan buku ke meja sirkulasi dan ajukan pengembalian dari menu Pinjaman saya. Pengembalian selesai setelah petugas mengecek kondisi buku dan menyetujuinya.</li>
        </ul>
      </fieldset>

      <label class="check agree">
        <input type="checkbox" name="setuju" value="1"<?= !empty($_POST['setuju']) ? ' checked' : '' ?><?= $bisa ? '' : ' disabled' ?>>
        Saya sudah membaca dan menyetujui ketentuan peminjaman di atas.
      </label>

      <div class="form__actions">
        <button class="btn" type="submit"<?= $bisa ? '' : ' disabled' ?>>Konfirmasi pinjam</button>
        <a class="btn btn--ghost" href="<?= url('buku.php?id=' . $b['id']) ?>">Batal</a>
      </div>
    </form>
  </div>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
