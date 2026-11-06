<?php
require __DIR__ . '/../config.php';
wajib_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_cek();
    $p = q('SELECT p.id, p.denda, p.denda_kondisi, b.judul, u.nama FROM peminjaman p
            JOIN buku b ON b.id = p.buku_id JOIN users u ON u.id = p.user_id
            WHERE p.id = ? AND p.tgl_kembali IS NOT NULL AND p.denda_lunas IS NULL AND p.denda + p.denda_kondisi > 0', [(int) ($_POST['lunas'] ?? 0)])->fetch();
    if ($p) {
        q('UPDATE peminjaman SET denda_lunas = CURDATE() WHERE id = ?', [$p['id']]);
        flash('ok', 'Denda ' . rupiah($p['denda'] + $p['denda_kondisi']) . ' dari ' . $p['nama'] . ' untuk “' . $p['judul'] . '” lunas.');
    }
    redirect('admin/denda.php');
}

$cari = trim($_GET['q'] ?? '');
$filter = $cari !== '' ? 'AND (u.nama LIKE ? OR u.no_anggota LIKE ? OR b.judul LIKE ?)' : '';
$args = $cari !== '' ? array_fill(0, 3, "%$cari%") : [];
$sql = 'SELECT p.*, b.judul, u.nama, u.no_anggota FROM peminjaman p
        JOIN buku b ON b.id = p.buku_id JOIN users u ON u.id = p.user_id
        WHERE p.tgl_kembali IS NOT NULL AND p.denda + p.denda_kondisi > 0 ';
$belum = q($sql . "AND p.denda_lunas IS NULL $filter ORDER BY u.nama, p.tgl_kembali", $args)->fetchAll();
$lunas = q($sql . "AND p.denda_lunas IS NOT NULL $filter ORDER BY p.denda_lunas DESC, p.id DESC LIMIT 10", $args)->fetchAll();
$s = q('SELECT
          COALESCE(SUM(IF(denda_lunas IS NULL, denda + denda_kondisi, 0)), 0) belum,
          COUNT(DISTINCT IF(denda_lunas IS NULL AND denda + denda_kondisi > 0, user_id, NULL)) orang,
          COALESCE(SUM(IF(denda_lunas >= DATE_FORMAT(CURDATE(), "%Y-%m-01"), denda + denda_kondisi, 0)), 0) bulan_ini
        FROM peminjaman WHERE tgl_kembali IS NOT NULL')->fetch();

$rincian = fn(array $p) => implode(' + ', array_filter([
    $p['denda'] ? 'terlambat ' . rupiah((int) $p['denda']) : '',
    $p['denda_kondisi'] ? $p['kondisi'] . ' ' . rupiah((int) $p['denda_kondisi']) : '',
]));

$judul = 'Denda';
$admin = true;
$menu = 'denda';
require __DIR__ . '/../partials/header.php';
?>

<div class="wrap admin">
  <div class="admin__head">
    <div>
      <h1>Denda</h1>
      <p class="muted">Terima pembayaran di meja sirkulasi, lalu tandai lunas. Anggota yang masih punya tunggakan tidak bisa meminjam.</p>
    </div>
  </div>

  <dl class="stats stats--3">
    <div><dt>Belum lunas</dt><dd class="stats__text"><?= rupiah((int) $s['belum']) ?></dd><span><?= count($belum) ?> tagihan<?= $cari !== '' ? ' cocok' : '' ?></span></div>
    <div class="<?= $s['orang'] ? 'is-alert' : '' ?>"><dt>Anggota tertunggak</dt><dd><?= (int) $s['orang'] ?></dd><span>Tidak bisa meminjam</span></div>
    <div><dt>Lunas bulan ini</dt><dd class="stats__text"><?= rupiah((int) $s['bulan_ini']) ?></dd><span><?= tgl(date('Y-m-01')) ?> sampai hari ini</span></div>
  </dl>

  <section class="panel">
    <div class="panel__bar">
      <h2 class="panel__title">Belum lunas <span class="count"><?= count($belum) ?></span></h2>
      <form class="search search--sm" role="search">
        <label class="sr-only" for="q">Cari denda</label>
        <input id="q" name="q" type="search" value="<?= e($cari) ?>" placeholder="Nama, no. anggota, judul">
      </form>
    </div>
    <?php if (!$belum): ?>
      <p class="muted"><?= $cari !== '' ? 'Tidak ada tagihan yang cocok.' : 'Tidak ada denda yang belum lunas.' ?></p>
    <?php else: ?>
      <div class="table-wrap">
        <table class="table">
          <thead><tr><th>Anggota</th><th>Buku</th><th>Rincian</th><th class="num">Jumlah</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($belum as $p): $total = (int) $p['denda'] + (int) $p['denda_kondisi']; ?>
            <tr>
              <td><?= e($p['nama']) ?><span class="sub mono"><?= e($p['no_anggota']) ?></span></td>
              <td><?= e($p['judul']) ?><span class="sub">Selesai <?= tgl($p['tgl_kembali']) ?></span></td>
              <td><?= e($rincian($p)) ?></td>
              <td class="num"><?= rupiah($total) ?></td>
              <td class="actions">
                <form method="post" data-confirm="Tandai lunas denda <?= rupiah($total) ?> dari <?= e($p['nama']) ?>?">
                  <?= csrf_field() ?>
                  <button class="btn btn--sm" name="lunas" value="<?= $p['id'] ?>">Tandai lunas</button>
                </form>
              </td>
            </tr>
          <?php endforeach ?>
          </tbody>
        </table>
      </div>
    <?php endif ?>
  </section>

  <?php if ($lunas): ?>
    <section class="panel admin__next">
      <h2 class="panel__title">Baru dilunasi</h2>
      <div class="table-wrap">
        <table class="table">
          <thead><tr><th>Anggota</th><th>Buku</th><th>Dilunasi</th><th class="num">Jumlah</th></tr></thead>
          <tbody>
          <?php foreach ($lunas as $p): ?>
            <tr>
              <td><?= e($p['nama']) ?><span class="sub mono"><?= e($p['no_anggota']) ?></span></td>
              <td><?= e($p['judul']) ?><span class="sub"><?= e($rincian($p)) ?></span></td>
              <td><?= tgl($p['denda_lunas']) ?></td>
              <td class="num"><?= rupiah((int) $p['denda'] + (int) $p['denda_kondisi']) ?></td>
            </tr>
          <?php endforeach ?>
          </tbody>
        </table>
      </div>
    </section>
  <?php endif ?>
</div>

<?php require __DIR__ . '/../partials/footer.php'; ?>
