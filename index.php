<?php
require __DIR__ . '/config.php';

$stat = q('SELECT COUNT(*) judul, COALESCE(SUM(stok),0) eksemplar FROM buku')->fetch();
$dipinjam = (int) q('SELECT COUNT(*) FROM peminjaman WHERE tgl_kembali IS NULL')->fetchColumn();
$terbaru = q('SELECT id, judul, penulis, no_panggil FROM buku ORDER BY dibuat DESC, id DESC LIMIT 8')->fetchAll();
$kelas = q('SELECT k.kode, k.nama, COUNT(b.id) jumlah FROM kategori k LEFT JOIN buku b ON b.kategori_id = k.id
            GROUP BY k.id ORDER BY k.kode')->fetchAll();
[$buka, $statusLayanan] = status_layanan();
$hariIni = (int) date('N');
$namaHari = [1 => 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];

$judul = '';
require __DIR__ . '/partials/header.php';
?>

<section class="hero">
  <div class="wrap hero__in">
    <?= logo('hero__logo') ?>
    <h1 class="hero__title">Perpustakaan</h1>
    <p class="hero__sub">Katalog Online · <?= number_format((int) $stat['judul'], 0, ',', '.') ?> Judul</p>
    <p class="hero__tagline">Cari bukunya di sini, ambil di raknya.</p>

    <form class="search search--hero" action="<?= url('katalog.php') ?>" role="search">
      <label class="sr-only" for="q">Kata kunci</label>
      <input id="q" name="q" type="search" placeholder="Judul, penulis, penerbit, atau ISBN" autocomplete="off">
      <button class="btn" type="submit">Cari</button>
    </form>
    <p class="hero__hint">Sering dicari:
      <a href="<?= url('katalog.php?q=metode+penelitian') ?>">metode penelitian</a>
      <a href="<?= url('katalog.php?kelas=000') ?>">pemrograman</a>
      <a href="<?= url('katalog.php?q=Pramoedya') ?>">Pramoedya</a>
      <a href="<?= url('katalog.php?kelas=100') ?>">psikologi</a>
    </p>
  </div>

  <div class="wrap">
    <dl class="today" aria-label="Status layanan hari ini">
      <div><dt>Hari ini</dt><dd class="today__status <?= $buka ? 'is-open' : '' ?>"><span class="dot"></span><?= e($statusLayanan) ?></dd></div>
      <div><dt>Eksemplar di rak</dt><dd><?= (int) $stat['eksemplar'] - $dipinjam ?></dd></div>
      <div><dt>Sedang dipinjam</dt><dd><?= $dipinjam ?></dd></div>
    </dl>
  </div>
</section>

<section class="section">
  <div class="wrap">
    <div class="section__head">
      <h2>Baru di rak</h2>
      <a class="link-arrow" href="<?= url('katalog.php?urut=terbaru') ?>">Lihat semua</a>
    </div>
    <ul class="shelf">
      <?php foreach ($terbaru as $b): ?>
        <li>
          <a class="shelf__item" href="<?= url('buku.php?id=' . $b['id']) ?>">
            <?= sampul($b) ?>
            <span class="shelf__title"><?= e($b['judul']) ?></span>
            <span class="shelf__author"><?= e($b['penulis']) ?></span>
          </a>
        </li>
      <?php endforeach ?>
    </ul>
  </div>
</section>

<section class="section section--alt">
  <div class="wrap split">
    <div>
      <h2>Jelajahi menurut kelas</h2>
      <p class="muted">Koleksi disusun dengan Klasifikasi Desimal Dewey — angka yang sama dengan label di punggung buku dan papan rak.</p>
    </div>
    <ol class="ddc">
      <?php foreach ($kelas as $k): ?>
        <li>
          <a href="<?= url('katalog.php?kelas=' . $k['kode']) ?>">
            <span class="ddc__code"><?= e($k['kode']) ?></span>
            <span class="ddc__name"><?= e($k['nama']) ?></span>
            <span class="ddc__count"><?= (int) $k['jumlah'] ?></span>
          </a>
        </li>
      <?php endforeach ?>
    </ol>
  </div>
</section>

<section class="section" id="cara-pinjam">
  <div class="wrap">
    <h2>Cara meminjam</h2>
    <ol class="steps">
      <li>
        <h3>Cari dan catat nomor panggil</h3>
        <p>Cek di katalog apakah buku tersedia. Nomor panggil (mis. <code class="callno">005.1 MAR c</code>) menunjukkan letak buku di rak.</p>
      </li>
      <li>
        <h3>Ambil, lalu ke meja sirkulasi</h3>
        <p>Bawa buku dan kartu anggota ke petugas. Belum punya kartu? <a href="<?= url('daftar.php') ?>">Daftar online</a>, aktif saat itu juga.</p>
      </li>
      <li>
        <h3>Kembalikan tepat waktu</h3>
        <p>Masa pinjam <?= LAMA_PINJAM ?> hari. Perpanjang sendiri <?= MAKS_PERPANJANG ?>× dari <a href="<?= url('akun.php') ?>">akun kamu</a> sebelum jatuh tempo.</p>
      </li>
    </ol>
  </div>
</section>

<section class="section section--alt" id="jam">
  <div class="wrap split">
    <div>
      <h2>Jam layanan</h2>
      <p class="muted">Sirkulasi tutup 30 menit sebelum jam layanan berakhir. Hari libur nasional tutup.</p>
    </div>
    <table class="hours">
      <caption class="sr-only">Jam layanan per hari</caption>
      <?php foreach (JAM_LAYANAN as $h => $jam): ?>
        <tr<?= $h === $hariIni ? ' class="is-today"' : '' ?>>
          <th scope="row"><?= $namaHari[$h] ?><?= $h === $hariIni ? ' <span class="tag">Hari ini</span>' : '' ?></th>
          <td><?= $jam ? str_replace(':', '.', $jam[0]) . ' – ' . str_replace(':', '.', $jam[1]) : 'Tutup' ?></td>
        </tr>
      <?php endforeach ?>
    </table>
  </div>
</section>

<?php require __DIR__ . '/partials/footer.php'; ?>
