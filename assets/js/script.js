// ================= FULL REPLACE (dashboard_student.php <script>) =================

// TOAST
function showToast(message, type = 'info') {
  const toast = document.getElementById('toast');
  if (!toast) return;

  const icons = { success:'✅', error:'❌', warning:'⚠️', info:'ℹ️' };

  toast.className = `toast toast-${type} show`;
  toast.innerHTML = `<span>${icons[type]}</span> ${message}`;

  clearTimeout(toast._timeout);
  toast._timeout = setTimeout(() => toast.classList.remove('show'), 3000);
}

// GPS
function getLocation() {
  return new Promise((resolve, reject) => {
    if (!navigator.geolocation) return reject('Browser tidak support GPS');

    navigator.geolocation.getCurrentPosition(
      pos => resolve({
        lat: pos.coords.latitude,
        lng: pos.coords.longitude,
        acc: pos.coords.accuracy
      }),
      err => {
        if (err.code === 1) reject('Izin lokasi ditolak');
        else if (err.code === 2) reject('Lokasi tidak tersedia');
        else reject('Timeout GPS');
      },
      { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
    );
  });
}

// QR
let html5QrCode = null;
let scanning = false;
let lastScanned = '';

async function startScanner() {
  if (scanning) return;

  try {
    const cameras = await Html5Qrcode.getCameras();
    const cam = cameras.find(c => /back|rear|belakang/i.test(c.label)) || cameras[0];

    html5QrCode = new Html5Qrcode('qr-reader');
    await html5QrCode.start(cam.id, { fps:10, qrbox:220 }, onScanSuccess);

    scanning = true;
    document.getElementById('startBtn').style.display = 'none';
    document.getElementById('stopBtn').style.display = 'flex';

    showToast('Kamera aktif', 'success');

  } catch (err) {
    showToast('Gagal buka kamera', 'error');
  }
}

async function stopScanner() {
  if (!scanning) return;
  await html5QrCode.stop();
  scanning = false;
  document.getElementById('startBtn').style.display = 'flex';
  document.getElementById('stopBtn').style.display = 'none';
}

// SCAN + GPS
async function onScanSuccess(token) {
  if (token === lastScanned) return;
  lastScanned = token;

  await stopScanner();
  showScanResult('loading', 'Mengambil lokasi...');

  try {
    const loc = await getLocation();

    if (loc.acc > 100) {
      showScanResult('error', 'GPS kurang akurat');
      showToast('GPS lemah', 'warning');
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

      document.getElementById('statusIndicator').innerHTML =
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

// RESULT UI
function showScanResult(type, msg) {
  const el = document.getElementById('scanResult');
  el.style.display = 'block';
  el.className = 'scan-result-box ' + type;

  const icons = { loading:'⏳', success:'✅', error:'❌' };
  el.innerHTML = `<span>${icons[type]}</span> ${msg}`;
}

// HISTORY
async function loadHistory() {
  try {
    const res = await fetch('dashboard_student.php?action=get_history', {
      headers:{ 'X-Requested-With':'XMLHttpRequest' }
    });

    const data = await res.json();
    const wrap = document.getElementById('historyWrap');

    if (!data.data?.length) {
      wrap.innerHTML = '<div class="empty-state">Belum ada</div>';
      return;
    }

    wrap.innerHTML = data.data.map(a => `
      <div class="history-item">
        <div>${a.tanggal}</div>
        <div>${a.waktu}</div>
        <div>${a.status}</div>
      </div>
    `).join('');

  } catch (err) {
    console.error(err);
  }
}