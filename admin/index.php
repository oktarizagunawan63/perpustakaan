<?php
require __DIR__ . '/../config.php';
wajib_admin();

$s = q('SELECT
          (SELECT COUNT(*) FROM buku) judul,
          (SELECT COALESCE(SUM(stok),0) FROM buku) eksemplar,
          (SELECT COUNT(*) FROM peminjaman WHERE tgl_kembali IS NULL) aktif,
          (SELECT COUNT(*) FROM peminjaman WHERE tgl_kembali IS NULL AND format = "fisik") aktif_fisik,
          (SELECT COUNT(*) FROM peminjaman WHERE tgl_kembali IS NULL AND tgl_ajuan IS NOT NULL) menunggu,
          (SELECT COUNT(*) FROM peminjaman WHERE tgl_kembali IS NULL AND tgl_ambil IS NULL) pesanan,
          (SELECT COUNT(*) FROM peminjaman WHERE tgl_kembali IS NULL AND tgl_ajuan IS NULL AND format = "fisik" AND jatuh_tempo < CURDATE()) terlambat,
          (SELECT COUNT(*) FROM users WHERE role = "anggota" AND aktif = 1) anggota,
          (SELECT COUNT(*) FROM peminjaman WHERE tgl_pinjam = CURDATE()) pinjam_hari_ini,
          (SELECT COUNT(*) FROM peminjaman WHERE tgl_kembali = CURDATE()) kembali_hari_ini')->fetch();

$sqlAktif = 'SELECT p.*, b.judul, u.nama, u.no_anggota, u.telepon FROM peminjaman p
             JOIN buku b ON b.id = p.buku_id JOIN users u ON u.id = p.user_id WHERE p.tgl_kembali IS NULL';
// Yang perlu ditagih/diingatkan hanya buku fisik yang belum diserahkan (e-book berakhir otomatis)
$sqlFisik = $sqlAktif . ' AND p.format = "fisik" AND p.tgl_ambil IS NOT NULL AND p.tgl_ajuan IS NULL';
$terlambat = q("$sqlFisik AND p.jatuh_tempo < CURDATE() ORDER BY p.jatuh_tempo")->fetchAll();
$segera = q("$sqlFisik AND p.jatuh_tempo BETWEEN CURDATE() AND CURDATE() + INTERVAL 2 DAY ORDER BY p.jatuh_tempo")->fetchAll();
$dendaBerjalan = array_sum(array_map('denda_pinjaman', $terlambat));
$terakhir = q('SELECT p.*, b.judul, u.nama, COALESCE(p.tgl_kembali, p.tgl_pinjam) tgl_aksi FROM peminjaman p
               JOIN buku b ON b.id = p.buku_id JOIN users u ON u.id = p.user_id
               ORDER BY tgl_aksi DESC, p.id DESC LIMIT 8')->fetchAll();

$judul = 'Ringkasan';
$admin = true;
$menu = 'dash';
require __DIR__ . '/../partials/header.php';
?>

<div class="wrap admin">
  <div class="admin__head">
    <div>
      <h1>Ringkasan hari ini</h1>
      <p class="muted"><?= tgl(date('Y-m-d'), true) ?> · <?= (int) $s['pinjam_hari_ini'] ?> peminjaman dan <?= (int) $s['kembali_hari_ini'] ?> pengembalian tercatat</p>
    </div>
    <a class="btn" href="<?= url('admin/sirkulasi.php') ?>">Buka sirkulasi</a>
  </div>

  <?php if ($s['pesanan']): ?>
    <div class="notice notice--info"><strong><?= (int) $s['pesanan'] ?> pesanan buku fisik</strong> menunggu diambil anggota. <a href="<?= url('admin/sirkulasi.php') ?>">Lihat di Sirkulasi</a></div>
  <?php endif ?>
  <?php if ($s['menunggu']): ?>
    <div class="notice notice--info"><strong><?= (int) $s['menunggu'] ?> buku fisik</strong> sudah diserahkan anggota dan menunggu dicek kondisinya. <a href="<?= url('admin/sirkulasi.php') ?>">Cek &amp; setujui di Sirkulasi</a></div>
  <?php endif ?>

  <dl class="stats">
    <div><dt>Judul koleksi</dt><dd><?= (int) $s['judul'] ?></dd><span><?= (int) $s['eksemplar'] ?> eksemplar fisik</span></div>
    <div><dt>Sedang dipinjam</dt><dd><?= (int) $s['aktif'] ?></dd><span><?= (int) $s['aktif_fisik'] ?> fisik · <?= (int) $s['aktif'] - (int) $s['aktif_fisik'] ?> e-book · <?= (int) $s['eksemplar'] - (int) $s['aktif_fisik'] ?> masih di rak</span></div>
    <div class="<?= $s['terlambat'] ? 'is-alert' : '' ?>"><dt>Terlambat</dt><dd><?= (int) $s['terlambat'] ?></dd><span>Denda berjalan <?= rupiah($dendaBerjalan) ?></span></div>
    <div><dt>Anggota aktif</dt><dd><?= (int) $s['anggota'] ?></dd><span><a href="<?= url('admin/anggota.php') ?>">Kelola</a></span></div>
  </dl>

  <div class="admin__cols">
    <section class="panel">
      <h2 class="panel__title">Perlu ditagih <span class="count"><?= count($terlambat) ?></span></h2>
      <?php if (!$terlambat): ?>
        <p class="muted">Tidak ada pinjaman terlambat. Rapi.</p>
      <?php else: ?>
        <ul class="mini-list">
          <?php foreach ($terlambat as $p): ?>
            <li>
              <div><strong><?= e($p['nama']) ?></strong> <span class="callno"><?= e($p['no_anggota']) ?></span>
                <span class="sub"><?= e($p['judul']) ?></span></div>
              <div class="mini-list__end"><span class="due due--late"><?= selisih_hari($p['jatuh_tempo']) ?> hari</span>
                <span class="sub"><?= rupiah(denda_pinjaman($p)) ?></span></div>
            </li>
          <?php endforeach ?>
        </ul>
      <?php endif ?>
    </section>

    <section class="panel">
      <h2 class="panel__title">Jatuh tempo ≤ 2 hari <span class="count"><?= count($segera) ?></span></h2>
      <?php if (!$segera): ?>
        <p class="muted">Tidak ada yang jatuh tempo dalam dua hari ke depan.</p>
      <?php else: ?>
        <ul class="mini-list">
          <?php foreach ($segera as $p): [$kls, $lbl] = status_pinjaman($p); ?>
            <li>
              <div><strong><?= e($p['nama']) ?></strong><span class="sub"><?= e($p['judul']) ?></span></div>
              <div class="mini-list__end"><span class="due due--<?= $kls ?>"><?= tgl($p['jatuh_tempo']) ?></span>
                <?php if ($p['telepon']): ?><span class="sub mono"><?= e($p['telepon']) ?></span><?php endif ?></div>
            </li>
          <?php endforeach ?>
        </ul>
      <?php endif ?>
    </section>
  </div>

  <section class="panel">
    <h2 class="panel__title">Transaksi terakhir</h2>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Tanggal</th><th>Jenis</th><th>Anggota</th><th>Buku</th><th class="num">Denda</th></tr></thead>
        <tbody>
        <?php foreach ($terakhir as $t): ?>
          <tr>
            <td><?= tgl($t['tgl_aksi']) ?></td>
            <td><?= $t['tgl_kembali'] ? '<span class="tag">Kembali</span>' : '<span class="tag tag--accent">Pinjam</span>' ?><?= in_array($t['kondisi'], ['rusak', 'hilang'], true) ? ' <span class="tag tag--late">' . $t['kondisi'] . '</span>' : '' ?></td>
            <td><?= e($t['nama']) ?></td>
            <td><?= e($t['judul']) ?></td>
            <td class="num"><?= ($total = (int) $t['denda'] + (int) $t['denda_kondisi']) ? rupiah($total) : '—' ?></td>
          </tr>
        <?php endforeach ?>
        </tbody>
      </table>
    </div>
  </section>
</div>

<?php require __DIR__ . '/../partials/footer.php'; ?>
