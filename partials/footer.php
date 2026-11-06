</main>

<?php if (empty($admin) && empty($polos)): ?>
<footer class="footer">
  <div class="wrap footer__in">
    <div>
      <div class="footer__brand"><?= logo() ?><p class="footer__name">Perpustakaan</p></div>
      <p>Gedung Perpustakaan Lt. 1–3<br>Jl. Kampus Raya No. 1</p>
      <p><a href="mailto:layanan@perpus.test">layanan@perpus.test</a></p>
    </div>
    <div>
      <p class="footer__label">Aturan singkat</p>
      <ul class="footer__list">
        <li>Maksimal <?= MAKS_PINJAM ?> buku sekaligus</li>
        <li>Lama pinjam <?= implode('/', PILIHAN_LAMA) ?> hari, bisa diperpanjang <?= MAKS_PERPANJANG ?>×</li>
        <li>E-book berakhir otomatis, tanpa denda</li>
        <li>Buku fisik telat <?= rupiah(DENDA_PER_HARI) ?>/hari · rusak <?= rupiah(DENDA_KONDISI['rusak']) ?> · hilang <?= rupiah(DENDA_KONDISI['hilang']) ?> atau ganti buku</li>
      </ul>
    </div>
    <div>
      <p class="footer__label">Pintasan</p>
      <ul class="footer__list">
        <li><a href="<?= url('katalog.php') ?>">Katalog online</a></li>
        <li><a href="<?= url('daftar.php') ?>">Daftar anggota</a></li>
        <li><a href="<?= url('pinjaman.php') ?>">Cek pinjaman saya</a></li>
      </ul>
    </div>
  </div>
  <div class="wrap footer__base">© <?= date('Y') ?> <?= e(NAMA_PERPUS) ?></div>
</footer>
<?php endif ?>
</body>
</html>
