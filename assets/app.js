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

  // Dasbor memperbarui diri saat data berubah, kecuali sedang ada isian yang belum dikirim
  const { live, versi } = document.body.dataset;
  const adaIsian = () => document.querySelector('details.reject[open]') || [...document.querySelectorAll('input, textarea, select')].some(el => {
    if (el.tagName === 'SELECT') return el.selectedIndex !== Math.max([...el.options].findIndex(o => o.defaultSelected), 0);
    if (['checkbox', 'radio'].includes(el.type)) return el.checked !== el.defaultChecked;
    return el.value !== el.defaultValue;
  });
  if (live) setInterval(async () => {
    if (document.hidden) return;
    try {
      const res = await fetch(live, { cache: 'no-store' });
      if (res.ok && (await res.json()).versi !== versi && !adaIsian()) location.reload();
    } catch { /* server sedang mati: coba lagi di putaran berikutnya */ }
  }, 3000);

  // Jam di Laporan mengikuti waktu server, bukan jam perangkat
  const jam = document.querySelector('[data-jam]');
  if (jam) {
    const selisih = jam.dataset.jam - Date.now();
    const format = new Intl.DateTimeFormat('id-ID', { dateStyle: 'long', timeStyle: 'medium', timeZone: 'Asia/Jakarta' });
    setInterval(() => { jam.textContent = format.format(Date.now() + selisih) + ' WIB'; }, 1000);
  }

  // Pratinjau foto profil sebelum disimpan
  document.querySelectorAll('.foto-input input[type=file]').forEach(input =>
    input.addEventListener('change', () => {
      const file = input.files[0];
      if (!file || !file.type.startsWith('image/')) return;
      const img = document.createElement('img');
      img.className = 'avatar avatar--md';
      img.alt = '';
      img.src = URL.createObjectURL(file);
      input.closest('.foto-input').firstElementChild.replaceWith(img);
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
