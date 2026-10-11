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
    f.addEventListener('submit', e => { if (!confirm(f.dataset.confirm)) e.preventDefault(); }));

  document.querySelectorAll('[data-confirm-btn]').forEach(b =>
    b.addEventListener('click', e => { if (!confirm(b.dataset.confirmBtn)) e.preventDefault(); }));

  document.querySelectorAll('[data-copy]').forEach(b =>
    b.addEventListener('click', async () => {
      try {
        await navigator.clipboard.writeText(b.dataset.copy);
        b.textContent = 'Tersalin';
        setTimeout(() => (b.textContent = 'Salin'), 1600);
      } catch { b.textContent = 'Gagal menyalin'; }
    }));

  document.querySelectorAll('[data-toggle-pw]').forEach(b =>
    b.addEventListener('click', () => {
      const input = document.getElementById(b.dataset.togglePw);
      const tampil = input.type === 'password';
      input.type = tampil ? 'text' : 'password';
      b.textContent = tampil ? 'Sembunyikan' : 'Tampilkan';
    }));
});
