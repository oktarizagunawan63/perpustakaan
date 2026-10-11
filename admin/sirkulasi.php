<?php
require __DIR__ . '/../config.php';
wajib_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_cek();

    if (isset($_POST['kembali'])) {
        $p = q('SELECT p.*, b.judul, u.nama FROM peminjaman p JOIN buku b ON b.id = p.buku_id JOIN users u ON u.id = p.user_id
                WHERE p.id = ? AND p.tgl_kembali IS NULL', [(int) $_POST['kembali']])->fetch();
        if ($p) {
            $denda = hitung_denda($p['jatuh_tempo']);
            q('UPDATE peminjaman SET tgl_kembali = CURDATE(), denda = ? WHERE id = ?', [$denda, $p['id']]);
            flash('ok', '“' . $p['judul'] . '” dari ' . $p['nama'] . ' diterima kembali' . ($denda ? '. Tagih denda ' . rupiah($denda) . '.' : ', tanpa denda.'));
        }
        redirect('admin/sirkulasi.php');
    }

    // Peminjaman baru
    $noAnggota = strtoupper(trim(explode(' ', trim($_POST['anggota'] ?? ''))[0]));
    $db = db();
    $db->beginTransaction();
    try {
        $agt = q('SELECT * FROM users WHERE no_anggota = ? AND role = "anggota"', [$noAnggota])->fetch();
        // Nilai input = "Judul — Penulis" dari datalist. FOR UPDATE: dua petugas tak bisa meminjamkan eksemplar terakhir bersamaan
        $buku = q('SELECT b.*, b.stok - ' . SQL_DIPINJAM . ' AS tersedia FROM buku b WHERE CONCAT(b.judul, " — ", b.penulis) = ? LIMIT 1 FOR UPDATE',
            [trim($_POST['buku'] ?? '')])->fetch();
        $bukuId = (int) ($buku['id'] ?? 0);
        $galat = match (true) {
            !$agt => "Nomor anggota “{$noAnggota}” tidak ditemukan.",
            !$agt['aktif'] => $agt['nama'] . ' sedang dinonaktifkan.',
            !$buku => 'Pilih buku dari daftar saran.',
            $buku['tersedia'] < 1 => '“' . $buku['judul'] . '” sedang habis dipinjam.',
            default => null,
        };
        if (!$galat) {
            $pinjam = q('SELECT buku_id, jatuh_tempo FROM peminjaman WHERE user_id = ? AND tgl_kembali IS NULL', [$agt['id']])->fetchAll();
            $galat = match (true) {
                count($pinjam) >= MAKS_PINJAM => $agt['nama'] . ' sudah meminjam ' . MAKS_PINJAM . ' buku (batas maksimal).',
                in_array($bukuId, array_map('intval', array_column($pinjam, 'buku_id')), true) => $agt['nama'] . ' masih meminjam judul yang sama.',
                (bool) array_filter($pinjam, fn($x) => $x['jatuh_tempo'] < date('Y-m-d')) => $agt['nama'] . ' punya pinjaman terlambat. Selesaikan pengembalian dulu.',
                default => null,
            };
        }
        if ($galat) {
            $db->rollBack();
            flash('error', $galat);
            $_SESSION['form_pinjam'] = $_POST;
        } else {
            $tempo = date('Y-m-d', strtotime('+' . LAMA_PINJAM . ' days'));
            q('INSERT INTO peminjaman (user_id, buku_id, tgl_pinjam, jatuh_tempo) VALUES (?, ?, CURDATE(), ?)', [$agt['id'], $bukuId, $tempo]);
            $db->commit();
            flash('ok', '“' . $buku['judul'] . '” dipinjam ' . $agt['nama'] . '. Kembali paling lambat ' . tgl($tempo, true) . '.');
        }
    } catch (Throwable $t) {
        $db->rollBack();
        throw $t;
    }
    redirect('admin/sirkulasi.php');
}

$form = $_SESSION['form_pinjam'] ?? ['anggota' => '', 'buku' => ''];
unset($_SESSION['form_pinjam']);

$anggota = q('SELECT no_anggota, nama FROM users WHERE role = "anggota" AND aktif = 1 ORDER BY nama')->fetchAll();
$buku = q('SELECT b.id, b.judul, b.penulis, b.stok - ' . SQL_DIPINJAM . ' AS tersedia FROM buku b ORDER BY b.judul')->fetchAll();

$cari = trim($_GET['q'] ?? '');
$args = [];
$filter = '';
if ($cari !== '') {
    $filter = 'AND (u.nama LIKE ? OR u.no_anggota LIKE ? OR b.judul LIKE ?)';
    $args = array_fill(0, 3, "%$cari%");
}
$aktif = q("SELECT p.*, b.judul, u.nama, u.no_anggota FROM peminjaman p
            JOIN buku b ON b.id = p.buku_id JOIN users u ON u.id = p.user_id
            WHERE p.tgl_kembali IS NULL $filter ORDER BY p.jatuh_tempo", $args)->fetchAll();

$judul = 'Sirkulasi';
$admin = true;
$menu = 'sirkulasi';
require __DIR__ . '/../partials/header.php';
?>

<div class="wrap admin">
  <div class="admin__head">
    <div>
      <h1>Sirkulasi</h1>
      <p class="muted">Catat peminjaman di kiri, terima pengembalian dari daftar di kanan.</p>
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
              <option value="<?= e($b['judul'] . ' — ' . $b['penulis']) ?>"><?= $b['tersedia'] < 1 ? 'habis dipinjam' : 'tersedia ' . (int) $b['tersedia'] ?></option>
            <?php endforeach ?>
          </datalist>
          <p class="field__hint">Jatuh tempo otomatis <?= tgl(date('Y-m-d', strtotime('+' . LAMA_PINJAM . ' days')), true) ?>.</p>
        </div>
        <button class="btn btn--block" type="submit">Catat peminjaman</button>
      </form>
    </section>

    <section class="panel circ__list">
      <div class="panel__bar">
        <h2 class="panel__title">Sedang dipinjam <span class="count"><?= count($aktif) ?></span></h2>
        <form class="search search--sm" role="search">
          <label class="sr-only" for="q">Cari pinjaman</label>
          <input id="q" name="q" type="search" value="<?= e($cari) ?>" placeholder="Nama, no. anggota, judul">
        </form>
      </div>
      <?php if (!$aktif): ?>
        <p class="muted"><?= $cari !== '' ? 'Tidak ada pinjaman yang cocok.' : 'Belum ada pinjaman aktif.' ?></p>
      <?php else: ?>
        <div class="table-wrap">
          <table class="table">
            <thead><tr><th>Anggota</th><th>Buku</th><th>Jatuh tempo</th><th class="num">Denda</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($aktif as $p): [$kls, $lbl] = status_tempo($p['jatuh_tempo']); $denda = hitung_denda($p['jatuh_tempo']); ?>
              <tr>
                <td><?= e($p['nama']) ?><span class="sub mono"><?= e($p['no_anggota']) ?></span></td>
                <td><?= e($p['judul']) ?></td>
                <td><?= tgl($p['jatuh_tempo']) ?><span class="sub due-text due-text--<?= $kls ?>"><?= e($lbl) ?></span></td>
                <td class="num"><?= $denda ? rupiah($denda) : '—' ?></td>
                <td class="actions">
                  <form method="post" data-confirm="Terima “<?= e($p['judul']) ?>” dari <?= e($p['nama']) ?>?<?= $denda ? ' Denda yang harus dibayar: ' . rupiah($denda) . '.' : '' ?>">
                    <?= csrf_field() ?>
                    <button class="btn btn--sm btn--ghost" name="kembali" value="<?= $p['id'] ?>">Kembalikan</button>
                  </form>
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

<?php require __DIR__ . '/../partials/footer.php'; ?>
