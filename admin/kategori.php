<?php
require __DIR__ . '/../config.php';
wajib_admin();

$galat = '';
$in = ['kode' => '', 'nama' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_cek();

    if (isset($_POST['hapus'])) {
        $k = q('SELECT k.id, k.nama, (SELECT COUNT(*) FROM buku b WHERE b.kategori_id = k.id) jumlah FROM kategori k WHERE k.id = ?', [(int) $_POST['hapus']])->fetch();
        if ($k && $k['jumlah']) {
            flash('error', 'Kategori “' . $k['nama'] . '” masih dipakai ' . (int) $k['jumlah'] . ' buku. Pindahkan bukunya dulu di menu Koleksi.');
        } elseif ($k) {
            q('DELETE FROM kategori WHERE id = ?', [$k['id']]);
            flash('ok', 'Kategori “' . $k['nama'] . '” dihapus.');
        }
        redirect('admin/kategori.php');
    }

    $id = (int) ($_POST['simpan'] ?? 0);
    $d = ['kode' => trim($_POST['kode'] ?? ''), 'nama' => trim($_POST['nama'] ?? '')];
    $galat = match (true) {
        !preg_match('/^[0-9A-Za-z.\-]{1,10}$/', $d['kode']) => 'Kode 1–10 karakter: huruf, angka, titik, atau strip. Contoh: 615.7',
        mb_strlen($d['nama']) < 3 || mb_strlen($d['nama']) > 80 => 'Nama kategori 3–80 karakter.',
        (bool) q('SELECT 1 FROM kategori WHERE (kode = ? OR nama = ?) AND id <> ?', [$d['kode'], $d['nama'], $id])->fetch() => 'Kode atau nama itu sudah dipakai kategori lain.',
        default => '',
    };
    if (!$galat) {
        if ($id) q('UPDATE kategori SET kode = ?, nama = ? WHERE id = ?', [$d['kode'], $d['nama'], $id]);
        else q('INSERT INTO kategori (kode, nama) VALUES (?, ?)', [$d['kode'], $d['nama']]);
        flash('ok', 'Kategori “' . $d['nama'] . '” disimpan.');
        redirect('admin/kategori.php');
    }
    if (!$id) $in = $d;
}

$daftar = q('SELECT k.*, COUNT(b.id) jumlah FROM kategori k LEFT JOIN buku b ON b.kategori_id = k.id GROUP BY k.id ORDER BY k.kode')->fetchAll();

$judul = 'Kategori';
$admin = true;
$menu = 'kategori';
require __DIR__ . '/../partials/header.php';
?>

<div class="wrap admin">
  <div class="admin__head">
    <div><h1>Kategori</h1><p class="muted"><?= count($daftar) ?> kategori. Kategori baru langsung bisa dipilih di Koleksi, dan muncul di filter katalog setelah ada bukunya.</p></div>
  </div>

  <?php if ($galat): ?><div class="notice notice--error" role="alert"><?= e($galat) ?></div><?php endif ?>

  <div class="circ circ--reverse">
    <section class="panel circ__list">
      <div class="table-wrap">
        <table class="table">
          <thead><tr><th>Kode &amp; nama</th><th class="num">Buku</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($daftar as $k): ?>
            <tr>
              <td>
                <form class="row-form" method="post">
                  <?= csrf_field() ?>
                  <label class="sr-only" for="kode-<?= $k['id'] ?>">Kode</label>
                  <input class="row-form__kode" id="kode-<?= $k['id'] ?>" name="kode" value="<?= e($k['kode']) ?>" maxlength="10" required>
                  <label class="sr-only" for="nama-<?= $k['id'] ?>">Nama kategori</label>
                  <input id="nama-<?= $k['id'] ?>" name="nama" value="<?= e($k['nama']) ?>" maxlength="80" required>
                  <button class="btn btn--sm btn--ghost" name="simpan" value="<?= $k['id'] ?>">Simpan</button>
                </form>
              </td>
              <td class="num"><a href="<?= url('katalog.php?kelas=' . rawurlencode($k['kode'])) ?>"><?= (int) $k['jumlah'] ?></a></td>
              <td class="actions">
                <?php if ($k['jumlah']): ?>
                  <span class="muted small">Masih dipakai</span>
                <?php else: ?>
                  <form method="post" data-confirm="Hapus kategori “<?= e($k['nama']) ?>”?">
                    <?= csrf_field() ?>
                    <button class="btn btn--sm btn--ghost" name="hapus" value="<?= $k['id'] ?>">Hapus</button>
                  </form>
                <?php endif ?>
              </td>
            </tr>
          <?php endforeach ?>
          <?php if (!$daftar): ?><tr><td colspan="3" class="muted">Belum ada kategori.</td></tr><?php endif ?>
          </tbody>
        </table>
      </div>
    </section>

    <section class="panel circ__form">
      <h2 class="panel__title">Tambah kategori</h2>
      <form method="post" class="form" novalidate>
        <?= csrf_field() ?>
        <div class="field"><label for="kode">Kode</label><input id="kode" name="kode" value="<?= e($in['kode']) ?>" maxlength="10" required>
          <p class="field__hint">Kode klasifikasi, misalnya 615.7</p></div>
        <div class="field"><label for="nama">Nama kategori</label><input id="nama" name="nama" value="<?= e($in['nama']) ?>" maxlength="80" required></div>
        <button class="btn btn--block" name="simpan" value="0">Tambah kategori</button>
      </form>
    </section>
  </div>
</div>

<?php require __DIR__ . '/../partials/footer.php'; ?>
