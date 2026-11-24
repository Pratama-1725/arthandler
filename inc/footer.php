</main>
</div>
<script>
// Tabel dibungkus wadah geser sendiri supaya di HP hanya tabel yang digeser,
// sedangkan judul panel, form cari, dan tombol tetap di tempat.
document.querySelectorAll('.panel table').forEach(function (t) {
  if (t.closest('.inv-head') || t.parentElement.classList.contains('tabel-geser')) return;
  var w = document.createElement('div');
  w.className = 'tabel-geser';
  var kolom = t.querySelectorAll('thead th').length || (t.rows[0] ? t.rows[0].cells.length : 0);
  if (kolom <= 4) w.classList.add('kecil');
  t.parentNode.insertBefore(w, t);
  w.appendChild(t);
});
function tandaiGeser() {
  document.querySelectorAll('.tabel-geser').forEach(function (w) {
    w.classList.toggle('bisa-geser', w.scrollWidth > w.clientWidth + 2);
  });
}
tandaiGeser();
window.addEventListener('resize', tandaiGeser);
</script>
</body>
</html>
