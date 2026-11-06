<?php
require __DIR__ . '/../config.php';
wajib_admin();

$tanggal = function (string $kunci, string $bawaan): string {
    $v = is_string($_GET[$kunci] ?? null) ? $_GET[$kunci] : '';
    $d = DateTime::createFromFormat('!Y-m-d', $v);
    return $d && $d->format('Y-m-d') === $v ? $v : $bawaan;
};
$dari = $tanggal('dari', date('Y-01-01'));
$sampai = $tanggal('sampai', date('Y-m-d'));
if ($dari > $sampai) [$dari, $sampai] = [$sampai, $dari];
$periode = [$dari, $sampai];

if (isset($_GET['unduh'])) {
    $baris = q('SELECT p.tgl_ambil, u.no_anggota, u.nama, b.judul, p.format, p.jatuh_tempo, p.tgl_kembali, p.kondisi, p.denda, p.denda_kondisi, p.denda_lunas
                FROM peminjaman p JOIN buku b ON b.id = p.buku_id JOIN users u ON u.id = p.user_id
                WHERE p.tgl_ambil BETWEEN ? AND ? ORDER BY p.tgl_ambil, p.id', $periode)->fetchAll();
    // Nama diisi anggota sendiri: awalan rumus dinetralkan supaya tidak dijalankan Excel
    $aman = fn(string $s) => preg_match('/^[=+\-@\t\r]/', $s) ? "'" . $s : $s;
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="laporan-perpustakaan-' . $dari . '-sd-' . $sampai . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // BOM + pemisah titik koma: terbaca rapi di Excel berbahasa Indonesia
    fputcsv($out, ['Tanggal pinjam', 'No. anggota', 'Nama', 'Judul', 'Format', 'Jatuh tempo', 'Tanggal kembali', 'Kondisi', 'Denda terlambat', 'Denda kondisi', 'Status denda'], ';', '"', '');
    foreach ($baris as $r) {
        $total = $r['denda'] + $r['denda_kondisi'];
        fputcsv($out, [$r['tgl_ambil'], $r['no_anggota'], $aman($r['nama']), $aman($r['judul']), LABEL_FORMAT[$r['format']], $r['jatuh_tempo'], $r['tgl_kembali'] ?? '',
            $r['kondisi'] ?? '', $r['denda'], $r['denda_kondisi'], !$total ? '' : ($r['denda_lunas'] ? 'Lunas' : 'Belum lunas')], ';', '"', '');
    }
    exit;
}

$s = q('SELECT COUNT(*) pinjam, COALESCE(SUM(format = "fisik"), 0) fisik, COUNT(DISTINCT user_id) peminjam
        FROM peminjaman WHERE tgl_ambil BETWEEN ? AND ?', $periode)->fetch();
// Denda dihitung pada periode pinjaman selesai, karena nominalnya baru pasti saat itu
$d = q('SELECT COALESCE(SUM(denda), 0) telat, COALESCE(SUM(denda_kondisi), 0) kondisi,
          COALESCE(SUM(IF(denda_lunas IS NULL, 0, denda + denda_kondisi)), 0) lunas
        FROM peminjaman WHERE tgl_kembali BETWEEN ? AND ?', $periode)->fetch();
$totalDenda = (int) $d['telat'] + (int) $d['kondisi'];

$data = q('SELECT DATE_FORMAT(tgl_ambil, "%Y-%m") bulan, COUNT(*) jumlah, SUM(format = "fisik") fisik
           FROM peminjaman WHERE tgl_ambil BETWEEN ? AND ? GROUP BY bulan ORDER BY bulan', $periode)->fetchAll();
$perBulan = [];
if ($data) {
    // Bulan tanpa peminjaman tetap tampil sebagai 0 supaya polanya tidak menipu
    $isi = array_column($data, null, 'bulan');
    for ($t = strtotime($data[0]['bulan'] . '-01'), $akhir = strtotime(end($data)['bulan'] . '-01'); $t <= $akhir; $t = strtotime('+1 month', $t)) {
        $k = date('Y-m', $t);
        $perBulan[$k] = $isi[$k] ?? ['jumlah' => 0, 'fisik' => 0];
    }
}
$puncak = max([1, ...array_map('intval', array_column($perBulan, 'jumlah'))]);

$bukuTeratas = q('SELECT b.judul, b.penulis, COUNT(*) jumlah, SUM(p.format = "fisik") fisik FROM peminjaman p JOIN buku b ON b.id = p.buku_id
                  WHERE p.tgl_ambil BETWEEN ? AND ? GROUP BY b.id ORDER BY jumlah DESC, b.judul LIMIT 10', $periode)->fetchAll();
$anggotaTeratas = q('SELECT u.nama, u.no_anggota, COUNT(*) jumlah, SUM(p.tgl_kembali IS NOT NULL AND p.jatuh_tempo < COALESCE(p.tgl_ajuan, p.tgl_kembali) AND p.format = "fisik") telat
                     FROM peminjaman p JOIN users u ON u.id = p.user_id
                     WHERE p.tgl_ambil BETWEEN ? AND ? GROUP BY u.id ORDER BY jumlah DESC, u.nama LIMIT 10', $periode)->fetchAll();

$judul = 'Laporan';
$admin = true;
$menu = 'laporan';
require __DIR__ . '/../partials/header.php';
?>

<div class="wrap admin">
  <div class="admin__head">
    <div>
      <h1>Laporan</h1>
      <p class="muted">Periode <?= tgl($dari, true) ?> – <?= tgl($sampai, true) ?></p>
    </div>
    <div class="report-actions">
      <a class="btn btn--ghost" href="<?= url('admin/laporan.php?' . http_build_query(['dari' => $dari, 'sampai' => $sampai, 'unduh' => 'csv'])) ?>">Unduh CSV</a>
      <button class="btn" type="button" data-print>Cetak</button>
    </div>
  </div>

  <form class="form panel report-filter">
    <div class="field"><label for="dari">Dari tanggal</label><input id="dari" name="dari" type="date" value="<?= e($dari) ?>"></div>
    <div class="field"><label for="sampai">Sampai tanggal</label><input id="sampai" name="sampai" type="date" value="<?= e($sampai) ?>"></div>
    <button class="btn btn--sm">Tampilkan</button>
  </form>

  <dl class="stats">
    <div><dt>Peminjaman</dt><dd><?= (int) $s['pinjam'] ?></dd><span><?= (int) $s['fisik'] ?> fisik · <?= (int) $s['pinjam'] - (int) $s['fisik'] ?> e-book</span></div>
    <div><dt>Anggota meminjam</dt><dd><?= (int) $s['peminjam'] ?></dd><span>Dalam periode ini</span></div>
    <div><dt>Denda tercatat</dt><dd class="stats__text"><?= rupiah($totalDenda) ?></dd><span>Terlambat <?= rupiah((int) $d['telat']) ?> · kondisi <?= rupiah((int) $d['kondisi']) ?></span></div>
    <div class="<?= $totalDenda - (int) $d['lunas'] ? 'is-alert' : '' ?>"><dt>Denda belum lunas</dt><dd class="stats__text"><?= rupiah($totalDenda - (int) $d['lunas']) ?></dd><span>Sudah lunas <?= rupiah((int) $d['lunas']) ?></span></div>
  </dl>

  <section class="panel">
    <h2 class="panel__title">Peminjaman per bulan</h2>
    <?php if (!$perBulan): ?>
      <p class="muted">Tidak ada peminjaman pada periode ini.</p>
    <?php else: ?>
      <div class="table-wrap">
        <table class="table">
          <thead><tr><th>Bulan</th><th class="report-bar-col"><span class="sr-only">Grafik jumlah</span></th><th class="num">Total</th><th class="num">Fisik</th><th class="num">E-book</th></tr></thead>
          <tbody>
          <?php foreach ($perBulan as $bulan => $r): ?>
            <tr>
              <td><?= substr(tgl($bulan . '-01', true), 2) ?></td>
              <td class="report-bar-col"><span class="report-bar" style="width:<?= round($r['jumlah'] / $puncak * 100) ?>%" aria-hidden="true"></span></td>
              <td class="num"><?= (int) $r['jumlah'] ?></td>
              <td class="num"><?= (int) $r['fisik'] ?></td>
              <td class="num"><?= (int) $r['jumlah'] - (int) $r['fisik'] ?></td>
            </tr>
          <?php endforeach ?>
          </tbody>
        </table>
      </div>
    <?php endif ?>
  </section>

  <div class="admin__cols">
    <section class="panel">
      <h2 class="panel__title">Buku paling sering dipinjam</h2>
      <?php if (!$bukuTeratas): ?>
        <p class="muted">Belum ada data.</p>
      <?php else: ?>
        <div class="table-wrap">
          <table class="table">
            <thead><tr><th>Judul</th><th class="num">Dipinjam</th></tr></thead>
            <tbody>
            <?php foreach ($bukuTeratas as $b): ?>
              <tr>
                <td><?= e($b['judul']) ?><span class="sub"><?= e($b['penulis']) ?></span></td>
                <td class="num"><?= (int) $b['jumlah'] ?>×<span class="sub"><?= (int) $b['fisik'] ?> fisik</span></td>
              </tr>
            <?php endforeach ?>
            </tbody>
          </table>
        </div>
      <?php endif ?>
    </section>

    <section class="panel">
      <h2 class="panel__title">Anggota paling aktif</h2>
      <?php if (!$anggotaTeratas): ?>
        <p class="muted">Belum ada data.</p>
      <?php else: ?>
        <div class="table-wrap">
          <table class="table">
            <thead><tr><th>Anggota</th><th class="num">Meminjam</th></tr></thead>
            <tbody>
            <?php foreach ($anggotaTeratas as $a): ?>
              <tr>
                <td><?= e($a['nama']) ?><span class="sub mono"><?= e($a['no_anggota']) ?></span></td>
                <td class="num"><?= (int) $a['jumlah'] ?>×<?php if ($a['telat']): ?><span class="sub"><?= (int) $a['telat'] ?> terlambat</span><?php endif ?></td>
              </tr>
            <?php endforeach ?>
            </tbody>
          </table>
        </div>
      <?php endif ?>
    </section>
  </div>
</div>

<?php require __DIR__ . '/../partials/footer.php'; ?>
