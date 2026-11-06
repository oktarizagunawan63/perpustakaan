// Interaksi kecil; semua halaman tetap berfungsi tanpa JavaScript.
document.addEventListener('DOMContentLoaded', () => {
  const toggle = document.querySelector('.nav-toggle');
  toggle?.addEventListener('click', () => {
    const buka = toggle.getAttribute('aria-expanded') !== 'true';
    toggle.setAttribute('aria-expanded', buka);
    document.getElementById('nav').classList.toggle('is-open', buka);
  });

  document.querySelectorAll('form[data-autosubmit]').forEach(f =>
    f.addEventListener('change', () => f.submit()));

  document.querySelectorAll('form[data-confirm]').forEach(f =>
    f.addEventListener('submit', e => {
      const kondisi = f.querySelector('select[name=kondisi]');
      const pesan = f.dataset.confirm + (kondisi ? ' Kondisi buku: ' + kondisi.selectedOptions[0].text + '.' : '');
      if (!confirm(pesan)) e.preventDefault();
    }));

  document.querySelectorAll('[data-confirm-btn]').forEach(b =>
    b.addEventListener('click', e => { if (!confirm(b.dataset.confirmBtn)) e.preventDefault(); }));

  document.querySelectorAll('[data-print]').forEach(b =>
    b.addEventListener('click', () => print()));

  document.querySelectorAll('[data-copy]').forEach(b =>
    b.addEventListener('click', async () => {
      try {
        await navigator.clipboard.writeText(b.dataset.copy);
        b.textContent = 'Tersalin';
        setTimeout(() => (b.textContent = 'Salin'), 1600);
      } catch { b.textContent = 'Gagal menyalin'; }
    }));

  // Pratinjau sampul sebelum disimpan
  document.querySelectorAll('.cover-input input[type=file]').forEach(input =>
    input.addEventListener('change', () => {
      const file = input.files[0];
      const wadah = input.closest('.cover-input');
      if (!file || !file.type.startsWith('image/')) return;
      const img = document.createElement('img');
      img.className = 'cover cover--sm cover--foto';
      img.alt = '';
      img.src = URL.createObjectURL(file);
      wadah.firstElementChild.replaceWith(img);
    }));

  document.querySelectorAll('[data-toggle-pw]').forEach(b =>
    b.addEventListener('click', () => {
      const input = document.getElementById(b.dataset.togglePw);
      const tampil = input.type === 'password';
      input.type = tampil ? 'text' : 'password';
      b.textContent = tampil ? 'Sembunyikan' : 'Tampilkan';
    }));
});
