<?php
require __DIR__ . '/config.php';

$stat = q('SELECT COUNT(*) judul, COALESCE(SUM(stok),0) eksemplar FROM buku')->fetch();
$dipinjam = (int) q('SELECT COUNT(*) FROM peminjaman WHERE tgl_kembali IS NULL')->fetchColumn();
$terbaru = q('SELECT id, judul, penulis, no_panggil, sampul FROM buku ORDER BY dibuat DESC, id DESC LIMIT 8')->fetchAll();
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
      <a href="<?= url('katalog.php?kelas=615.7') ?>">farmakologi</a>
      <a href="<?= url('katalog.php?q=farmakope') ?>">farmakope</a>
      <a href="<?= url('katalog.php?q=herbal') ?>">herbal</a>
      <a href="<?= url('katalog.php?q=pediatri') ?>">dosis anak</a>
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
        <h3>Cari bukunya</h3>
        <p>Cek di katalog apakah buku tersedia. Nomor panggil (mis. <code class="callno">615.7 KAT b</code>) menunjukkan letak buku di rak. Belum punya akun? <a href="<?= url('daftar.php') ?>">Daftar online</a>, aktif saat itu juga.</p>
      </li>
      <li>
        <h3>Klik “Pinjam buku ini”</h3>
        <p>Pilih <strong>e-book</strong> (langsung dibaca di website) atau <strong>buku fisik</strong> (tunggu disetujui petugas, lalu ambil di meja sirkulasi paling lambat <?= BATAS_AMBIL ?> hari), lalu lama pinjam <?= implode(', ', array_slice(PILIHAN_LAMA, 0, -1)) ?>, atau <?= max(PILIHAN_LAMA) ?> hari.</p>
      </li>
      <li>
        <h3>Kembalikan tepat waktu</h3>
        <p>E-book berakhir otomatis. Buku fisik diserahkan ke meja sirkulasi lalu dicek petugas; telat didenda <?= rupiah(DENDA_PER_HARI) ?>/hari, rusak <?= rupiah(DENDA_KONDISI['rusak']) ?>, hilang <?= rupiah(DENDA_KONDISI['hilang']) ?>. Cek semuanya di <a href="<?= url('pinjaman.php') ?>">Pinjaman saya</a>.</p>
      </li>
    </ol>
    <div class="how-extra">
      <div>
        <h3>Buku fisik sedang habis?</h3>
        <p>Klik “Antre buku fisik” di halaman buku. Saat ada eksemplar kembali, giliranmu muncul di <a href="<?= url('pinjaman.php') ?>">Pinjaman saya</a> dan bukunya disimpan <?= BATAS_GILIRAN ?> hari. Kalau tidak dipakai, giliran pindah ke antrean berikutnya.</p>
      </div>
      <div>
        <h3>Kena denda?</h3>
        <p>Bayar di meja sirkulasi, lalu petugas menandainya lunas. Selama masih ada denda yang belum lunas, kamu belum bisa meminjam buku baru. Rinciannya ada di <a href="<?= url('pinjaman.php#tagihan') ?>">Pinjaman saya</a>.</p>
      </div>
    </div>
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
