</main>

<button class="scroll-top" id="scrollTop" aria-label="Kembali ke atas" title="Kembali ke atas">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
    <polyline points="18 15 12 9 6 15"></polyline>
  </svg>
</button>

<?php if (!empty($admin)): ?>
<footer class="admin-footer">
  <div class="admin-footer__in">
    <p>&copy; <?= date('Y') ?> <?= e(NAMA_PERPUS) ?> · Panel Admin</p>
    <p><a href="<?= url('index.php') ?>">Lihat Situs Publik</a></p>
  </div>
</footer>
<?php elseif (empty($polos)): ?>
<footer class="footer">
  <div class="wrap footer__in">
    <div>
      <div class="footer__brand"><?= logo() ?><p class="footer__name">Perpustakaan</p></div>
      <p>Cari, pinjam, dan baca koleksi perpustakaan secara online.</p>
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
