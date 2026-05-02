// ============================================================
// TOAST NOTIFICATION
// ============================================================
function showToast(message, type = 'info') {
  const toast = document.getElementById('toast');
  if (!toast) return;

  const icons = {
    success: '✅',
    error: '❌',
    warning: '⚠️',
    info: 'ℹ️'
  };

  toast.className = `toast toast-${type} show`;
  toast.innerHTML = `<span class="toast-icon">${icons[type] || '💬'}</span> <span>${message}</span>`;

  clearTimeout(toast._timeout);
  toast._timeout = setTimeout(() => {
    toast.classList.remove('show');
  }, 3500);
}


// ============================================================
// 📍 GET LOCATION (GPS)
// ============================================================
function getLocation() {
  return new Promise((resolve, reject) => {
    if (!navigator.geolocation) {
      reject('Browser tidak support GPS');
      return;
    }

    navigator.geolocation.getCurrentPosition(
      (pos) => {
        resolve({
          lat: pos.coords.latitude,
          lng: pos.coords.longitude,
          acc: pos.coords.accuracy
        });
      },
      (err) => {
        reject('Gagal ambil lokasi');
      },
      {
        enableHighAccuracy: true,
        timeout: 10000,
        maximumAge: 0
      }
    );
  });
}


// ============================================================
// 🚀 KIRIM ABSEN (PAKE GPS)
// ============================================================
async function kirimAbsen(token) {
  try {
    showToast('Mengambil lokasi...', 'info');

    const loc = await getLocation();

    // validasi akurasi
    if (loc.acc > 50) {
      showToast('GPS kurang akurat, coba di luar ruangan', 'warning');
      return;
    }

    showToast('Mengirim absensi...', 'info');

    const fd = new FormData();
    fd.append('token', token);
    fd.append('lat', loc.lat);
    fd.append('lng', loc.lng);
    fd.append('acc', loc.acc);

    const res = await fetch('proses_absen.php', {
      method: 'POST',
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      body: fd
    });

    const data = await res.json();

    if (data.success) {
      showToast(data.message, 'success');
    } else {
      showToast(data.message, 'error');
    }

  } catch (err) {
    showToast('Gagal ambil lokasi, aktifkan GPS', 'error');
  }
}