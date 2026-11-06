<?php
require __DIR__ . '/../config.php';
wajib_admin();

const STATUS_USULAN = ['baru' => 'Baru', 'diproses' => 'Diproses', 'ditambahkan' => 'Ditambahkan', 'ditolak' => 'Ditolak'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_cek();
    $status = $_POST['status'] ?? '';
    $catatan = trim($_POST['catatan'] ?? '');
    if (!isset(STATUS_USULAN[$status]) || mb_strlen($catatan) > 255) {
        flash('error', 'Pilih status yang tersedia; catatan maksimal 255 karakter.');
    } else {
        q('UPDATE usulan SET status = ?, catatan = ? WHERE id = ?', [$status, $catatan ?: null, (int) ($_POST['simpan'] ?? 0)]);
        flash('ok', 'Usulan diperbarui. Anggota bisa melihat statusnya di Akun saya.');
    }
    redirect('admin/usulan.php');
}

$daftar = q('SELECT s.*, u.nama, u.no_anggota FROM usulan s JOIN users u ON u.id = s.user_id
             ORDER BY FIELD(s.status, "baru", "diproses", "ditambahkan", "ditolak"), s.dibuat DESC, s.id DESC')->fetchAll();

$judul = 'Usulan buku';
$admin = true;
$menu = 'usulan';
$live = true;
require __DIR__ . '/../partials/header.php';
?>

<div class="wrap admin">
  <div class="admin__head">
    <div>
      <h1>Usulan buku</h1>
      <p class="muted">Permintaan buku baru dari anggota. Bila disetujui, tambahkan bukunya di menu Koleksi lalu ubah statusnya.</p>
    </div>
    <a class="btn" href="<?= url('admin/buku.php') ?>">Buka koleksi</a>
  </div>

  <section class="panel">
    <?php if (!$daftar): ?>
      <p class="muted">Belum ada usulan dari anggota.</p>
    <?php else: ?>
      <div class="table-wrap">
        <table class="table table--stack">
          <thead><tr><th>Usulan</th><th>Dari</th><th>Status &amp; catatan</th></tr></thead>
          <tbody>
          <?php foreach ($daftar as $s): ?>
            <tr>
              <td><strong><?= e($s['judul']) ?></strong><span class="sub"><?= e($s['penulis'] ?: 'Penulis tidak diisi') ?></span>
                <?php if ($s['alasan']): ?><span class="sub">“<?= e($s['alasan']) ?>”</span><?php endif ?></td>
              <td><?= e($s['nama']) ?><span class="sub mono"><?= e($s['no_anggota']) ?></span><span class="sub"><?= tgl($s['dibuat']) ?></span></td>
              <td>
                <form class="row-form" method="post">
                  <?= csrf_field() ?>
                  <label class="sr-only" for="status-<?= $s['id'] ?>">Status</label>
                  <select id="status-<?= $s['id'] ?>" name="status">
                    <?php foreach (STATUS_USULAN as $k => $label): ?><option value="<?= $k ?>"<?= $s['status'] === $k ? ' selected' : '' ?>><?= $label ?></option><?php endforeach ?>
                  </select>
                  <label class="sr-only" for="catatan-<?= $s['id'] ?>">Catatan untuk anggota (opsional)</label>
                  <input id="catatan-<?= $s['id'] ?>" name="catatan" value="<?= e($s['catatan']) ?>" maxlength="255" placeholder="Catatan untuk anggota (opsional)">
                  <button class="btn btn--sm btn--ghost" name="simpan" value="<?= $s['id'] ?>">Simpan</button>
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

<?php require __DIR__ . '/../partials/footer.php'; ?>
