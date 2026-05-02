<script src="assets/js/script.js"></script>
<script>
let html5QrCode = null;
let scanning = false;
let lastScanned = '';
let weeklyChartStudent = null;

// START SCANNER
async function startScanner() {
  if (scanning) return;

  try {
    const cameras = await Html5Qrcode.getCameras();
    const cam = cameras.find(c => /back|rear|belakang/i.test(c.label)) || cameras[0];

    html5QrCode = new Html5Qrcode('qr-reader');
    await html5QrCode.start(
      cam.id,
      { fps: 10, qrbox: 220 },
      onScanSuccess
    );

    scanning = true;
    startBtn.style.display = 'none';
    stopBtn.style.display = 'flex';

    showToast('Kamera aktif', 'success');

  } catch (err) {
    showToast('Gagal buka kamera', 'error');
  }
}

// STOP
async function stopScanner() {
  if (!scanning) return;
  await html5QrCode.stop();
  scanning = false;
  startBtn.style.display = 'flex';
  stopBtn.style.display = 'none';
}

// 🔥 SCAN + GPS (REVISI)
async function onScanSuccess(token) {
  if (token === lastScanned) return;
  lastScanned = token;

  await stopScanner();
  showScanResult('loading', 'Mengambil lokasi...');

  try {
    const loc = await getLocation();

    // 🔥 lebih longgar biar ga gagal mulu
    if (loc.acc > 100) {
      showScanResult('error', 'GPS kurang akurat');
      showToast('Coba pindah ke luar ruangan', 'warning');
      lastScanned = '';
      return;
    }

    const fd = new FormData();
    fd.append('token', token);
    fd.append('lat', loc.lat);
    fd.append('lng', loc.lng);
    fd.append('acc', loc.acc);

    showScanResult('loading', 'Mengirim absensi...');

    const res = await fetch('proses_absen.php', {
      method: 'POST',
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      body: fd
    });

    const data = await res.json();

    if (data.success) {
      showScanResult('success', data.message);
      showToast(data.message, 'success');

      statusIndicator.innerHTML =
        `<span class="status-dot active"></span><span>Sudah Absen ✓</span>`;

      loadHistory();

    } else {
      showScanResult('error', data.message);
      showToast(data.message, 'error');
      setTimeout(() => lastScanned = '', 3000);
    }

  } catch (err) {
    showScanResult('error', err);
    showToast(err, 'error');
    lastScanned = '';
  }
}

// UI RESULT
function showScanResult(type, msg) {
  const el = document.getElementById('scanResult');
  el.style.display = 'block';
  el.className = 'scan-result-box ' + type;

  const icons = { loading:'⏳', success:'✅', error:'❌' };
  el.innerHTML = `<span>${icons[type]}</span> ${msg}`;
}

// HISTORY
async function loadHistory() {
  const res = await fetch('scan.php?action=get_history', {
    headers: { 'X-Requested-With': 'XMLHttpRequest' }
  });
  const data = await res.json();

  const wrap = document.getElementById('historyWrap');

  if (!data.data?.length) {
    wrap.innerHTML = '<div class="empty-state">Belum ada</div>';
    return;
  }

  wrap.innerHTML = `
    <table class="data-table">
      <thead><tr><th>Tanggal</th><th>Waktu</th><th>Status</th></tr></thead>
      <tbody>
        ${data.data.map(a => `
          <tr>
            <td>${a.tanggal}</td>
            <td>${a.waktu}</td>
            <td>${a.status}</td>
          </tr>
        `).join('')}
      </tbody>
    </table>`;
}

// INIT
document.addEventListener('DOMContentLoaded', () => {
  loadHistory();
});
</script>