<?php
// scan.php - Student QR Scanner Page
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: login.php');
    exit;
}

require 'koneksi.php';
$profilePhone = '';
$profilePhoto = '';
$stmtProfile = $pdo->prepare("SELECT phone, profile_photo FROM users WHERE id = ? LIMIT 1");
$stmtProfile->execute([(int) $_SESSION['user_id']]);
$userProfile = $stmtProfile->fetch();
if ($userProfile) {
    $profilePhone = $userProfile['phone'] ?? '';
    $profilePhoto = $userProfile['profile_photo'] ?? '';
}

// Get student history via AJAX
if (
    isset($_SERVER['HTTP_X_REQUESTED_WITH'])
    && strtolower((string) $_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
) {
    header('Content-Type: application/json');
    $action = $_GET['action'] ?? $_POST['action'] ?? '';

    if ($action === 'get_history') {
        $nis = $_SESSION['nis'];
        $stmt = $pdo->prepare("SELECT tanggal, waktu, status FROM absensi WHERE nis = ? ORDER BY tanggal DESC, waktu DESC LIMIT 20");
        $stmt->execute([$nis]);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
        exit;
    }

    if ($action === 'get_dashboard_data') {
        $nis = $_SESSION['nis'];
        $labels = [];
        $hadir = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} day"));
            $labels[] = date('d M', strtotime($date));
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM absensi WHERE nis = ? AND tanggal = ?");
            $stmt->execute([$nis, $date]);
            $hadir[] = (int) $stmt->fetchColumn();
        }

        $jadwal = $pdo->prepare("
            SELECT mj.id, mj.judul, mj.deskripsi, mj.tanggal_materi, mj.deadline_tugas,
                COALESCE(tp.status, 'pending') AS tugas_status
            FROM materi_jadwal mj
            LEFT JOIN tugas_progress tp ON tp.jadwal_id = mj.id AND tp.nis = ?
            ORDER BY mj.tanggal_materi DESC
            LIMIT 20
        ");
        $jadwal->execute([$nis]);

        echo json_encode([
            'success' => true,
            'labels' => $labels,
            'hadir' => $hadir,
            'jadwal' => $jadwal->fetchAll()
        ]);
        exit;
    }

    if ($action === 'mark_task_done') {
        $jadwalId = (int) ($_POST['jadwal_id'] ?? 0);
        if ($jadwalId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Jadwal tidak valid.']);
            exit;
        }
        $checkJadwal = $pdo->prepare("SELECT id FROM materi_jadwal WHERE id = ?");
        $checkJadwal->execute([$jadwalId]);
        if (!$checkJadwal->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Jadwal tidak ditemukan.']);
            exit;
        }
        $stmt = $pdo->prepare("
            INSERT INTO tugas_progress (jadwal_id, nis, status)
            VALUES (?, ?, 'done')
            ON DUPLICATE KEY UPDATE status = 'done', updated_at = CURRENT_TIMESTAMP
        ");
        $stmt->execute([$jadwalId, $_SESSION['nis']]);
        echo json_encode(['success' => true, 'message' => 'Tugas ditandai selesai.']);
        exit;
    }

    if ($action === 'save_profile') {
        $nama        = trim((string) ($_POST['nama'] ?? ''));
        $passwordBaru = (string) ($_POST['password_baru'] ?? '');
        $phone       = trim((string) ($_POST['phone'] ?? ''));
        if ($nama === '') {
            echo json_encode(['success' => false, 'message' => 'Nama tidak boleh kosong.']);
            exit;
        }
        if ($phone !== '' && !preg_match('/^[0-9\+\-\s]{6,20}$/', $phone)) {
            echo json_encode(['success' => false, 'message' => 'Nomor HP tidak valid.']);
            exit;
        }

        $fields = ['nama = ?', 'phone = ?'];
        $params = [$nama, $phone];
        $photoPath = null;

        if (!empty($_FILES['photo']['name']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $allowed = ['jpg', 'jpeg', 'png', 'webp'];
            $fileInfo = pathinfo($_FILES['photo']['name']);
            $ext = strtolower($fileInfo['extension'] ?? '');
            if (!in_array($ext, $allowed, true)) {
                echo json_encode(['success' => false, 'message' => 'Format foto hanya JPG, PNG, atau WEBP.']);
                exit;
            }
            $uploadDir = __DIR__ . '/uploads/profiles';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            $fileName = 'user_' . (int) $_SESSION['user_id'] . '_' . time() . '.' . $ext;
            $dest = $uploadDir . '/' . $fileName;
            if (!move_uploaded_file($_FILES['photo']['tmp_name'], $dest)) {
                echo json_encode(['success' => false, 'message' => 'Gagal mengunggah foto profil.']);
                exit;
            }
            $photoPath = 'uploads/profiles/' . $fileName;
            $fields[] = 'profile_photo = ?';
            $params[] = $photoPath;

            $oldPhoto = $_SESSION['profile_photo'] ?? '';
            if ($oldPhoto && strpos($oldPhoto, 'uploads/profiles/') === 0 && file_exists(__DIR__ . '/' . $oldPhoto)) {
                @unlink(__DIR__ . '/' . $oldPhoto);
            }
        }

        $params[] = (int) $_SESSION['user_id'];
        $pdo->prepare('UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = ?')->execute($params);
        $_SESSION['nama'] = $nama;
        $_SESSION['phone'] = $phone;
        if ($photoPath !== null) {
            $_SESSION['profile_photo'] = $photoPath;
        }
        if ($passwordBaru !== '') {
            $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([password_hash($passwordBaru, PASSWORD_BCRYPT), (int) $_SESSION['user_id']]);
        }
        echo json_encode(['success' => true, 'message' => 'Profil berhasil diperbarui.', 'nama' => $nama, 'phone' => $phone, 'photo' => $photoPath]);
        exit;
    }

    if ($action === 'logout') {
        session_destroy();
        echo json_encode(['success' => true, 'redirect' => 'login.php']);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Scan Absensi | Multimedia JABUN</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css"/>
  <!-- html5-qrcode library -->
  <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="scan-page">

<div class="scan-wrapper">

  <!-- HEADER -->
  <div class="scan-header">
    <div class="logo-icon-sm">
      <svg width="22" height="22" viewBox="0 0 32 32" fill="none">
        <rect width="32" height="32" rx="8" fill="#6C5CE7"/>
        <path d="M8 8h6v6H8zM18 8h6v6h-6zM8 18h6v6H8z" fill="white" opacity="0.9"/>
        <rect x="20" y="20" width="4" height="4" fill="white"/>
      </svg>
    </div>
    <div class="scan-header-info">
      <span class="scan-app-name">JABUN Attendance</span>
      <span class="scan-user-name"><?= htmlspecialchars($_SESSION['nama']) ?></span>
    </div>
    <button class="btn-logout-sm" onclick="doLogout()" title="Logout">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
    </button>
  </div>

  <!-- STUDENT INFO CARD -->
  <div class="student-card fade-in">
    <div class="student-avatar">
      <?php if (!empty($profilePhoto)): ?>
        <img src="<?= htmlspecialchars($profilePhoto) ?>" alt="Foto Profil" />
      <?php else: ?>
        <?= strtoupper(substr($_SESSION['nama'], 0, 1)) ?>
      <?php endif; ?>
    </div>
    <div class="student-details">
      <span class="student-name"><?= htmlspecialchars($_SESSION['nama']) ?></span>
      <span class="student-nis">NIS: <?= htmlspecialchars($_SESSION['nis']) ?></span>
      <?php if (!empty($profilePhone)): ?>
        <span class="student-phone">HP: <?= htmlspecialchars($profilePhone) ?></span>
      <?php endif; ?>
    </div>
    <div class="student-status-indicator" id="statusIndicator">
      <span class="status-dot"></span>
      <span>Siap Absen</span>
    </div>
  </div>

  <!-- SCANNER SECTION -->
  <div class="scanner-card fade-in">
    <div class="scanner-label">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="5" height="5"/><rect x="16" y="3" width="5" height="5"/><rect x="3" y="16" width="5" height="5"/><path d="M21 16h-3a2 2 0 0 0-2 2v3M21 21v.01M12 7v3a2 2 0 0 1-2 2H7"/></svg>
      Scan QR Code Absensi
    </div>
    <p class="scanner-helper">Izinkan akses kamera, lalu arahkan QR agar otomatis terbaca.</p>

    <div id="qr-reader" class="qr-reader-box"></div>

    <div class="scanner-actions">
      <button class="btn-scan-start" id="startBtn" onclick="startScanner()">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 7l-7 5 7 5V7z"/><rect x="1" y="5" width="15" height="14" rx="2" ry="2"/></svg>
        Mulai Kamera
      </button>
      <button class="btn-scan-stop hidden" id="stopBtn" onclick="stopScanner()">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="6" y="6" width="12" height="12"/></svg>
        Stop Kamera
      </button>
    </div>

    <div id="scanResult" class="scan-result-box hidden"></div>
  </div>

  <div class="qr-grid">
    <div class="card fade-in">
      <div class="card-header"><h3>Grafik Absensi Mingguan</h3></div>
      <p class="section-subtitle">Grafik ini diambil dari riwayat absensi kamu selama 7 hari terakhir.</p>
      <canvas id="weeklyChartStudent" height="120"></canvas>
    </div>
    <div class="card fade-in">
      <div class="card-header"><h3>Mini Kalender</h3></div>
      <div id="miniCalendar"></div>
    </div>
  </div>

  <div class="card fade-in">
    <div class="card-header"><h3>Jadwal Materi & Status Tugas</h3></div>
    <div id="jadwalWrap"><div class="loading-placeholder">Memuat jadwal materi...</div></div>
  </div>

  <div class="card fade-in">
    <div class="card-header"><h3>Edit Profil Saya</h3></div>
    <form id="profileFormStudent" class="form-grid" enctype="multipart/form-data">
      <div class="profile-card">
        <div class="profile-photo-box">
          <?php if (!empty($profilePhoto)): ?>
            <img id="profilePreview" src="<?= htmlspecialchars($profilePhoto) ?>" alt="Profil" />
          <?php else: ?>
            <div id="profilePreview" class="profile-fallback"><?= strtoupper(substr($_SESSION['nama'], 0, 1)) ?></div>
          <?php endif; ?>
        </div>
        <div class="profile-photo-label">Foto Profil</div>
      </div>
      <div class="input-wrap">
        <input type="text" id="studentNama" name="nama" value="<?= htmlspecialchars($_SESSION['nama']) ?>" placeholder="Nama lengkap" required/>
      </div>
      <div class="input-wrap">
        <input type="text" id="studentPhone" name="phone" value="<?= htmlspecialchars($profilePhone) ?>" placeholder="No HP (misal +6281234567890)"/>
      </div>
      <div class="input-wrap">
        <input type="file" id="studentPhoto" name="photo" accept="image/png,image/jpeg,image/webp"/>
      </div>
      <div class="input-wrap">
        <input type="password" id="studentPassBaru" name="password_baru" placeholder="Password baru (opsional)"/>
      </div>
      <button class="btn-primary" type="submit">Simpan Perubahan Profil</button>
    </form>
  </div>

  <!-- HISTORY -->
  <div class="card fade-in">
    <div class="card-header">
      <h3>Riwayat Absensi</h3>
      <button class="btn-refresh" onclick="loadHistory()">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
        Refresh
      </button>
    </div>
    <div id="historyWrap">
      <div class="loading-placeholder">Memuat riwayat...</div>
    </div>
  </div>

</div>

<div id="toast" class="toast"></div>

<script src="assets/js/script.js"></script>
<script>
let html5QrCode = null;
let scanning = false;
let lastScanned = '';
let weeklyChartStudent = null;

// Start QR scanner
async function startScanner() {
  if (scanning) return;
  if (!navigator.mediaDevices || !navigator.mediaDevices.enumerateDevices) {
    showToast('Browser Anda tidak mendukung akses kamera. Gunakan browser terbaru.', 'error');
    return;
  }

  try {
    const cameras = await Html5Qrcode.getCameras();
    if (!cameras || !cameras.length) {
      showToast('Tidak ada kamera yang tersedia. Pastikan kamera aktif.', 'error');
      return;
    }

    const selectedCamera = cameras.find(cam => /back|rear|belakang/i.test(cam.label)) || cameras[0];
    html5QrCode = new Html5Qrcode('qr-reader');
    const config = { fps: 10, qrbox: { width: 220, height: 220 }, aspectRatio: 1.0 };

    await html5QrCode.start(selectedCamera.id, config, onScanSuccess, onScanError);
    scanning = true;
    document.getElementById('startBtn').style.display = 'none';
    document.getElementById('stopBtn').style.display = 'flex';
    showToast('Kamera aktif. Arahkan ke QR Code.', 'success');
  } catch (err) {
    const message = err?.message || String(err);
    const errorMessage = /permission|denied|not allowed/i.test(message)
      ? 'Izin kamera ditolak. Coba izinkan akses kamera di pengaturan browser.'
      : /notfound|NotFound|No cameras/i.test(message)
        ? 'Tidak ditemukan kamera yang dapat digunakan.'
        : message;
    showToast('Gagal mengaktifkan kamera: ' + errorMessage, 'error');
    html5QrCode = null;
  }
}

// Stop scanner
async function stopScanner() {
  if (!scanning || !html5QrCode) return;
  await html5QrCode.stop();
  scanning = false;
  document.getElementById('startBtn').style.display = 'flex';
  document.getElementById('stopBtn').style.display = 'none';
}

// On scan success
async function onScanSuccess(token) {
  if (token === lastScanned) return; // Debounce
  lastScanned = token;

  await stopScanner();
  showScanResult('loading', 'Memproses absensi...');

  const fd = new FormData();
  fd.append('token', token);
  const res = await fetch('proses_absen.php', {
    method: 'POST',
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
    body: fd
  });
  const data = await res.json();

  if (data.success) {
    showScanResult('success', data.message);
    showToast(data.message, 'success');
    document.getElementById('statusIndicator').innerHTML = `<span class="status-dot active"></span><span>Sudah Absen ✓</span>`;
    document.getElementById('startBtn').innerHTML = `
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 4v6h-6"/><path d="M1 20v-6h6"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10"/><path d="M20.49 15a9 9 0 0 1-14.85 3.36L1 14"/></svg>
      Scan Lagi
    `;
    loadHistory();
  } else {
    showScanResult('error', data.message);
    showToast(data.message, 'error');
    setTimeout(() => { lastScanned = ''; }, 3000);
  }
}

function onScanError(err) {
  // Silently ignore scan errors (camera searching)
}

function showScanResult(type, msg) {
  const el = document.getElementById('scanResult');
  el.style.display = 'block';
  el.className = 'scan-result-box ' + type;
  const icons = {
    loading: '⏳',
    success: '✅',
    error: '❌'
  };
  el.innerHTML = `<span>${icons[type]}</span> ${msg}`;
}

// Load history
async function loadHistory() {
  const res = await fetch('scan.php?action=get_history', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
  const data = await res.json();
  const wrap = document.getElementById('historyWrap');
  if (!data.data || !data.data.length) {
    wrap.innerHTML = '<div class="empty-state">Belum ada riwayat absensi</div>';
    return;
  }
  wrap.innerHTML = `
    <table class="data-table">
      <thead><tr><th>Tanggal</th><th>Waktu</th><th>Status</th></tr></thead>
      <tbody>
        ${data.data.map(a => `
          <tr>
            <td>${escapeHTML(a.tanggal)}</td>
            <td class="mono">${escapeHTML(a.waktu)}</td>
            <td><span class="badge-status">${escapeHTML(a.status)}</span></td>
          </tr>`).join('')}
      </tbody>
    </table>`;
}

function renderMiniCalendar() {
  const target = document.getElementById('miniCalendar');
  if (!target) return;
  const now = new Date();
  const monthName = now.toLocaleDateString('id-ID', { month: 'long', year: 'numeric' });
  const firstDay = new Date(now.getFullYear(), now.getMonth(), 1).getDay();
  const totalDay = new Date(now.getFullYear(), now.getMonth() + 1, 0).getDate();
  const dayNames = ['Mg', 'Sn', 'Sl', 'Rb', 'Km', 'Jm', 'Sb'];
  const cells = [];
  for (let i = 0; i < firstDay; i++) cells.push('<div></div>');
  for (let d = 1; d <= totalDay; d++) {
    const cls = d === now.getDate() ? 'mini-cal-date today' : 'mini-cal-date';
    cells.push(`<div class="${cls}">${d}</div>`);
  }
  target.innerHTML = `
    <div class="mini-cal-header"><span>${monthName}</span></div>
    <div class="mini-cal-grid">${dayNames.map(d => `<div class="mini-cal-day">${d}</div>`).join('')}</div>
    <div class="mini-cal-grid">${cells.join('')}</div>
  `;
}

async function loadStudentDashboardData() {
  const res = await fetch('scan.php?action=get_dashboard_data', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
  const data = await res.json();
  if (!data.success) return;

  const chartEl = document.getElementById('weeklyChartStudent');
  if (chartEl) {
    if (weeklyChartStudent) weeklyChartStudent.destroy();
    weeklyChartStudent = new Chart(chartEl, {
      type: 'bar',
      data: {
        labels: data.labels,
        datasets: [{ label: 'Kehadiran Kamu', data: data.hadir, backgroundColor: 'rgba(108,92,231,.65)', borderRadius: 8 }]
      },
      options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
    });
  }

  const jadwalWrap = document.getElementById('jadwalWrap');
  if (jadwalWrap) {
    if (!data.jadwal || !data.jadwal.length) {
      jadwalWrap.innerHTML = '<div class="empty-state">Belum ada jadwal materi dari admin.</div>';
    } else {
      jadwalWrap.innerHTML = `
        <table class="data-table">
          <thead><tr><th>Materi</th><th>Tanggal</th><th>Deadline</th><th>Status Tugas</th></tr></thead>
          <tbody>
            ${data.jadwal.map(j => `
              <tr>
                <td>
                  <strong>${escapeHTML(j.judul)}</strong><br/>
                  <span class="section-subtitle">${escapeHTML(j.deskripsi || '-')}</span>
                </td>
                <td>${escapeHTML(j.tanggal_materi)}</td>
                <td>${escapeHTML(j.deadline_tugas)}</td>
                <td>
                  ${j.tugas_status === 'done'
                    ? '<span class="badge-task done">Selesai</span>'
                    : `<button class="btn-primary btn-task" onclick="markTaskDone(${Number(j.id)})">Tandai Selesai</button>`}
                </td>
              </tr>
            `).join('')}
          </tbody>
        </table>
      `;
    }
  }
}

async function markTaskDone(jadwalId) {
  const fd = new FormData();
  fd.append('action', 'mark_task_done');
  fd.append('jadwal_id', String(jadwalId));
  const res = await fetch('scan.php', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd });
  const data = await res.json();
  showToast(data.message, data.success ? 'success' : 'error');
  if (data.success) loadStudentDashboardData();
}

async function doLogout() {
  const fd = new FormData(); fd.append('action','logout');
  const res = await fetch('scan.php', { method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'}, body:fd });
  const data = await res.json();
  if (data.success) window.location.href = data.redirect;
}

const scanProfileForm = document.getElementById('profileFormStudent');
if (scanProfileForm) {
  scanProfileForm.addEventListener('submit', async function(e) {
    e.preventDefault();
    const fd = new FormData(this);
    fd.append('action', 'save_profile');

    const res = await fetch('scan.php', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd });
    const data = await res.json();
    showToast(data.message, data.success ? 'success' : 'error');
    if (data.success) {
      document.querySelector('.scan-user-name').textContent = data.nama;
      document.querySelector('.student-name').textContent = data.nama;
      document.getElementById('studentPassBaru').value = '';
      if (data.photo) {
        const avatar = document.querySelector('.student-avatar');
        if (avatar) {
          avatar.innerHTML = `<img src="${escapeHTML(data.photo)}" alt="Foto Profil" />`;
        }
      }
    }
  });
}

const scanPhotoInput = document.getElementById('studentPhoto');
if (scanPhotoInput) {
  scanPhotoInput.addEventListener('change', function() {
    const preview = document.getElementById('profilePreview');
    if (!preview || !this.files || !this.files[0]) return;
    const file = this.files[0];
    if (!file.type.startsWith('image/')) return;
    const reader = new FileReader();
    reader.onload = () => {
      if (preview.tagName === 'IMG') {
        preview.src = reader.result;
      } else {
        const img = document.createElement('img');
        img.id = 'profilePreview';
        img.src = reader.result;
        img.alt = 'Preview Profil';
        img.className = 'profile-photo-img';
        preview.replaceWith(img);
      }
    };
    reader.readAsDataURL(file);
  });
}

// Init
document.addEventListener('DOMContentLoaded', () => {
  loadHistory();
  loadStudentDashboardData();
  renderMiniCalendar();
});
</script>
</body>
</html>
