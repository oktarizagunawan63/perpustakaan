<?php
require __DIR__ . '/../config.php';
wajib_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_cek();

    if (isset($_POST['ambil']) || isset($_POST['batal'])) {
        $p = q('SELECT p.*, b.judul, u.nama FROM peminjaman p JOIN buku b ON b.id = p.buku_id JOIN users u ON u.id = p.user_id
                WHERE p.id = ? AND p.tgl_ambil IS NULL AND p.tgl_kembali IS NULL', [(int) ($_POST['ambil'] ?? $_POST['batal'])])->fetch();
        if ($p && isset($_POST['ambil'])) {
            // Masa pinjam mulai dihitung saat buku berpindah tangan, bukan saat dipesan
            $tempo = date('Y-m-d', strtotime('+' . (int) $p['durasi'] . ' days'));
            q('UPDATE peminjaman SET tgl_ambil = CURDATE(), jatuh_tempo = ? WHERE id = ?', [$tempo, $p['id']]);
            flash('ok', '“' . $p['judul'] . '” sudah diambil ' . $p['nama'] . '. Kembali paling lambat ' . tgl($tempo, true) . '.');
        } elseif ($p) {
            q('DELETE FROM peminjaman WHERE id = ?', [$p['id']]);
            flash('ok', 'Pesanan “' . $p['judul'] . '” milik ' . $p['nama'] . ' dibatalkan.');
        }
        redirect('admin/sirkulasi.php');
    }

    if (isset($_POST['kembali'])) {
        $kondisi = array_key_exists($_POST['kondisi'] ?? '', DENDA_KONDISI) ? $_POST['kondisi'] : 'baik';
        $p = q('SELECT p.*, b.judul, u.nama FROM peminjaman p JOIN buku b ON b.id = p.buku_id JOIN users u ON u.id = p.user_id
                WHERE p.id = ? AND p.tgl_ambil IS NOT NULL AND p.tgl_kembali IS NULL', [(int) $_POST['kembali']])->fetch();
        if ($p && $p['format'] === 'ebook') {
            q('UPDATE peminjaman SET tgl_kembali = CURDATE(), kondisi = "baik" WHERE id = ?', [$p['id']]);
            flash('ok', 'Pinjaman e-book “' . $p['judul'] . '” milik ' . $p['nama'] . ' diakhiri.');
        } elseif ($p) {
            // Denda telat dihitung sampai buku diserahkan (tgl_ajuan), bukan sampai petugas selesai mengecek
            $diserahkan = $p['tgl_ajuan'] ?: date('Y-m-d');
            $denda = hitung_denda($p['jatuh_tempo'], $diserahkan);
            $dendaKondisi = DENDA_KONDISI[$kondisi];
            q('UPDATE peminjaman SET tgl_ajuan = ?, tgl_kembali = CURDATE(), denda = ?, kondisi = ?, denda_kondisi = ? WHERE id = ?',
                [$diserahkan, $denda, $kondisi, $dendaKondisi, $p['id']]);
            // Eksemplar yang hilang keluar dari koleksi; naikkan lagi stoknya di menu Koleksi bila diganti
            if ($kondisi === 'hilang') q('UPDATE buku SET stok = stok - 1 WHERE id = ? AND stok > 0', [$p['buku_id']]);
            $rincian = array_filter(['terlambat ' . rupiah($denda) => $denda, $kondisi . ' ' . rupiah($dendaKondisi) => $dendaKondisi]);
            flash('ok', 'Pengembalian “' . $p['judul'] . '” dari ' . $p['nama'] . ' disetujui (kondisi ' . $kondisi . ').'
                . ($rincian ? ' Tagih denda ' . rupiah($denda + $dendaKondisi) . ': ' . implode(' + ', array_keys($rincian)) . '.' : ' Tanpa denda.'));
        }
        redirect('admin/sirkulasi.php');
    }

    // Peminjaman baru. Nilai input buku = "Judul — Penulis" dari datalist.
    $noAnggota = strtoupper(trim(explode(' ', trim($_POST['anggota'] ?? ''))[0]));
    $agt = q('SELECT * FROM users WHERE no_anggota = ? AND role = "anggota"', [$noAnggota])->fetch();
    $buku = q('SELECT id FROM buku WHERE CONCAT(judul, " — ", penulis) = ? LIMIT 1', [trim($_POST['buku'] ?? '')])->fetch();
    [$ok, $pesan] = match (true) {
        !$agt => [false, "Nomor anggota “{$noAnggota}” tidak ditemukan."],
        !$buku => [false, 'Pilih buku dari daftar saran.'],
        default => catat_pinjaman($agt, (int) $buku['id'], (int) ($_POST['lama'] ?? LAMA_PINJAM), $_POST['format'] ?? 'fisik', true),
    };
    flash($ok ? 'ok' : 'error', $ok ? $agt['nama'] . ': ' . $pesan : $pesan);
    if (!$ok) $_SESSION['form_pinjam'] = $_POST;
    redirect('admin/sirkulasi.php');
}

$form = ($_SESSION['form_pinjam'] ?? []) + ['anggota' => '', 'buku' => '', 'lama' => LAMA_PINJAM, 'format' => 'fisik'];
unset($_SESSION['form_pinjam']);

$anggota = q('SELECT no_anggota, nama FROM users WHERE role = "anggota" AND aktif = 1 ORDER BY nama')->fetchAll();
$buku = q('SELECT b.id, b.judul, b.penulis, b.file_pdf, b.stok - ' . SQL_DIPINJAM . ' AS tersedia FROM buku b ORDER BY b.judul')->fetchAll();

$cari = trim($_GET['q'] ?? '');
$args = [];
$filter = '';
if ($cari !== '') {
    $filter = 'AND (u.nama LIKE ? OR u.no_anggota LIKE ? OR b.judul LIKE ?)';
    $args = array_fill(0, 3, "%$cari%");
}
$semua = q("SELECT p.*, b.judul, u.nama, u.no_anggota FROM peminjaman p
            JOIN buku b ON b.id = p.buku_id JOIN users u ON u.id = p.user_id
            WHERE p.tgl_kembali IS NULL $filter ORDER BY p.tgl_ajuan, p.jatuh_tempo", $args)->fetchAll();
$pesanan = array_filter($semua, fn($p) => !$p['tgl_ambil']);
$menunggu = array_filter($semua, fn($p) => $p['tgl_ajuan']);
$aktif = array_filter($semua, fn($p) => $p['tgl_ambil'] && !$p['tgl_ajuan']);

$pilihKondisi = function (int $id): string {
    $h = '<label class="sr-only" for="kondisi-' . $id . '">Kondisi buku</label><select id="kondisi-' . $id . '" name="kondisi">';
    foreach (DENDA_KONDISI as $k => $nominal) $h .= '<option value="' . $k . '">' . ucfirst($k) . ($nominal ? ' +' . rupiah($nominal) : '') . '</option>';
    return $h . '</select>';
};

$judul = 'Sirkulasi';
$admin = true;
$menu = 'sirkulasi';
require __DIR__ . '/../partials/header.php';
?>

<div class="wrap admin">
  <div class="admin__head">
    <div>
      <h1>Sirkulasi</h1>
      <p class="muted">Catat peminjaman di kiri. Di kanan: serahkan pesanan, cek &amp; setujui buku yang dikembalikan, atau terima pengembalian langsung.</p>
    </div>
  </div>

  <div class="circ">
    <section class="panel circ__form">
      <h2 class="panel__title">Pinjamkan buku</h2>
      <form method="post" class="form">
        <?= csrf_field() ?>
        <div class="field">
          <label for="anggota">Anggota</label>
          <input id="anggota" name="anggota" list="dl-anggota" value="<?= e($form['anggota']) ?>" placeholder="Ketik nama atau nomor anggota" required autocomplete="off" autofocus>
          <datalist id="dl-anggota">
            <?php foreach ($anggota as $a): ?><option value="<?= e($a['no_anggota'] . ' — ' . $a['nama']) ?>"><?php endforeach ?>
          </datalist>
        </div>
        <div class="field">
          <label for="buku">Buku</label>
          <input id="buku" name="buku" list="dl-buku" value="<?= e($form['buku']) ?>" placeholder="Ketik judul atau penulis" required autocomplete="off">
          <datalist id="dl-buku">
            <?php foreach ($buku as $b): ?>
              <option value="<?= e($b['judul'] . ' — ' . $b['penulis']) ?>">fisik: <?= $b['tersedia'] < 1 ? 'habis' : (int) $b['tersedia'] . ' tersedia' ?><?= $b['file_pdf'] ? ' · ada e-book' : '' ?></option>
            <?php endforeach ?>
          </datalist>
        </div>
        <div class="field-row">
          <div class="field">
            <label for="format">Format</label>
            <select id="format" name="format">
              <?php foreach (LABEL_FORMAT as $f => $label): ?><option value="<?= $f ?>"<?= $form['format'] === $f ? ' selected' : '' ?>><?= $label ?></option><?php endforeach ?>
            </select>
          </div>
          <div class="field">
            <label for="lama">Lama pinjam</label>
            <select id="lama" name="lama">
              <?php foreach (PILIHAN_LAMA as $n): ?>
                <option value="<?= $n ?>"<?= (int) $form['lama'] === $n ? ' selected' : '' ?>><?= $n ?> hari</option>
              <?php endforeach ?>
            </select>
          </div>
        </div>
        <button class="btn btn--block" type="submit">Catat peminjaman</button>
      </form>
    </section>

    <div class="circ__list">
      <section class="panel panel--wait">
        <div class="panel__bar">
          <h2 class="panel__title">Menunggu diambil <span class="count"><?= count($pesanan) ?></span></h2>
          <form class="search search--sm" role="search">
            <label class="sr-only" for="q">Cari pinjaman</label>
            <input id="q" name="q" type="search" value="<?= e($cari) ?>" placeholder="Nama, no. anggota, judul">
          </form>
        </div>
        <?php if (!$pesanan): ?>
          <p class="muted">Tidak ada pesanan buku fisik yang menunggu diambil.</p>
        <?php else: ?>
          <p class="muted small">Klik “Sudah diambil” saat buku diserahkan — masa pinjam mulai dihitung saat itu.</p>
          <div class="table-wrap">
            <table class="table">
              <thead><tr><th>Anggota</th><th>Buku</th><th>Batas ambil</th><th></th></tr></thead>
              <tbody>
              <?php foreach ($pesanan as $p): ?>
                <tr>
                  <td><?= e($p['nama']) ?><span class="sub mono"><?= e($p['no_anggota']) ?></span></td>
                  <td><?= e($p['judul']) ?><span class="sub">Lama pinjam <?= (int) $p['durasi'] ?> hari</span></td>
                  <td><?= tgl(batas_ambil($p)) ?><span class="sub">Dipesan <?= tgl($p['tgl_pinjam']) ?></span></td>
                  <td class="actions">
                    <form method="post">
                      <?= csrf_field() ?>
                      <button class="btn btn--sm" name="ambil" value="<?= $p['id'] ?>">Sudah diambil</button>
                    </form>
                    <form method="post" data-confirm="Batalkan pesanan “<?= e($p['judul']) ?>” milik <?= e($p['nama']) ?>?">
                      <?= csrf_field() ?>
                      <button class="btn btn--sm btn--ghost" name="batal" value="<?= $p['id'] ?>">Batalkan</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach ?>
              </tbody>
            </table>
          </div>
        <?php endif ?>
      </section>

      <section class="panel panel--wait">
        <h2 class="panel__title">Menunggu pengecekan <span class="count"><?= count($menunggu) ?></span></h2>
        <?php if (!$menunggu): ?>
          <p class="muted">Tidak ada buku fisik yang menunggu dicek.</p>
        <?php else: ?>
          <p class="muted small">Anggota sudah menyerahkan buku fisik ini. Cek kondisinya, pilih hasilnya, lalu setujui.</p>
          <div class="table-wrap">
            <table class="table">
              <thead><tr><th>Anggota</th><th>Buku</th><th>Diserahkan</th><th class="num">Denda telat</th><th></th></tr></thead>
              <tbody>
              <?php foreach ($menunggu as $p): $denda = denda_pinjaman($p); ?>
                <tr>
                  <td><?= e($p['nama']) ?><span class="sub mono"><?= e($p['no_anggota']) ?></span></td>
                  <td><?= e($p['judul']) ?></td>
                  <td><?= tgl($p['tgl_ajuan']) ?><span class="sub">Jatuh tempo <?= tgl($p['jatuh_tempo']) ?></span></td>
                  <td class="num"><?= $denda ? rupiah($denda) : '—' ?></td>
                  <td class="actions">
                    <form method="post" class="return-form" data-confirm="Setujui pengembalian “<?= e($p['judul']) ?>” dari <?= e($p['nama']) ?>?<?= $denda ? ' Denda keterlambatan: ' . rupiah($denda) . '.' : '' ?>">
                      <?= csrf_field() ?>
                      <?= $pilihKondisi((int) $p['id']) ?>
                      <button class="btn btn--sm" name="kembali" value="<?= $p['id'] ?>">Setujui</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach ?>
              </tbody>
            </table>
          </div>
        <?php endif ?>
      </section>

      <section class="panel">
        <h2 class="panel__title">Sedang dipinjam <span class="count"><?= count($aktif) ?></span></h2>
        <?php if (!$aktif): ?>
          <p class="muted"><?= $cari !== '' ? 'Tidak ada pinjaman yang cocok.' : 'Belum ada pinjaman aktif.' ?></p>
        <?php else: ?>
          <div class="table-wrap">
            <table class="table">
              <thead><tr><th>Anggota</th><th>Buku</th><th>Jatuh tempo</th><th class="num">Denda</th><th></th></tr></thead>
              <tbody>
              <?php foreach ($aktif as $p): [$kls, $lbl] = status_pinjaman($p); $denda = denda_pinjaman($p); $ebook = $p['format'] === 'ebook'; ?>
                <tr>
                  <td><?= e($p['nama']) ?><span class="sub mono"><?= e($p['no_anggota']) ?></span></td>
                  <td><?= e($p['judul']) ?><span class="sub"><span class="tag <?= $ebook ? 'tag--accent' : '' ?>"><?= LABEL_FORMAT[$p['format']] ?></span></span></td>
                  <td><?= tgl($p['jatuh_tempo']) ?><span class="sub due-text due-text--<?= $kls ?>"><?= e($lbl) ?></span></td>
                  <td class="num"><?= $denda ? rupiah($denda) : '—' ?></td>
                  <td class="actions">
                    <?php if ($ebook): ?>
                      <form method="post" data-confirm="Akhiri pinjaman e-book “<?= e($p['judul']) ?>” milik <?= e($p['nama']) ?>?">
                        <?= csrf_field() ?>
                        <button class="btn btn--sm btn--ghost" name="kembali" value="<?= $p['id'] ?>">Akhiri</button>
                      </form>
                    <?php else: ?>
                      <form method="post" class="return-form" data-confirm="Terima “<?= e($p['judul']) ?>” dari <?= e($p['nama']) ?>?<?= $denda ? ' Denda keterlambatan: ' . rupiah($denda) . '.' : '' ?>">
                        <?= csrf_field() ?>
                        <?= $pilihKondisi((int) $p['id']) ?>
                        <button class="btn btn--sm btn--ghost" name="kembali" value="<?= $p['id'] ?>">Terima kembali</button>
                      </form>
                    <?php endif ?>
                  </td>
                </tr>
              <?php endforeach ?>
              </tbody>
            </table>
          </div>
        <?php endif ?>
      </section>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../partials/footer.php'; ?>
