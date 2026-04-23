<?php
// dashboard_student.php - Student Dashboard
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: login.php');
    exit;
}

require 'koneksi.php';

// ---- AJAX HANDLERS ----
if (
    isset($_SERVER['HTTP_X_REQUESTED_WITH'])
    && strtolower((string) $_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
) {
    header('Content-Type: application/json');
    $action = $_GET['action'] ?? $_POST['action'] ?? '';

    if ($action === 'get_dashboard_data') {
        $nis = $_SESSION['nis'];

        // Data absensi mingguan
        $labels = [];
        $hadir = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} day"));
            $labels[] = date('d M', strtotime($date));
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM absensi WHERE nis = ? AND tanggal = ?");
            $stmt->execute([$nis, $date]);
            $hadir[] = (int) $stmt->fetchColumn();
        }

        // Statistik siswa
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM absensi WHERE nis = ?");
        $stmt->execute([$nis]);
        $totalHadir = (int) $stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM absensi WHERE nis = ? AND tanggal = CURDATE()");
        $stmt->execute([$nis]);
        $hadirHariIni = (int) $stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM tugas_progress WHERE nis = ? AND status = 'done'");
        $stmt->execute([$nis]);
        $totalTugas = (int) $stmt->fetchColumn();

        // Jadwal materi
        $jadwal = $pdo->prepare("
            SELECT mj.id, mj.judul, mj.deskripsi, mj.tanggal_materi, mj.deadline_tugas,
                COALESCE(tp.status, 'pending') AS tugas_status
            FROM materi_jadwal mj
            LEFT JOIN tugas_progress tp ON tp.jadwal_id = mj.id AND tp.nis = ?
            ORDER BY mj.tanggal_materi DESC
            LIMIT 10
        ");
        $jadwal->execute([$nis]);

        echo json_encode([
            'success' => true,
            'labels' => $labels,
            'hadir' => $hadir,
            'stats' => [
                'total_hadir' => $totalHadir,
                'hadir_hari_ini' => $hadirHariIni,
                'total_tugas' => $totalTugas
            ],
            'jadwal' => $jadwal->fetchAll()
        ]);
        exit;
    }

    // Get student history
    if ($action === 'get_history') {
        $nis = $_SESSION['nis'];
        $stmt = $pdo->prepare("SELECT tanggal, waktu, status FROM absensi WHERE nis = ? ORDER BY tanggal DESC, waktu DESC LIMIT 20");
        $stmt->execute([$nis]);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
        exit;
    }

    if ($action === 'mark_task_done') {
        $jadwalId = (int) ($_POST['jadwal_id'] ?? 0);
        if ($jadwalId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Jadwal tidak valid.']);
            exit;
        }
        $stmt = $pdo->prepare("
            INSERT INTO tugas_progress (jadwal_id, nis, status)
            VALUES (?, ?, 'done')
            ON DUPLICATE KEY UPDATE status = 'done', updated_at = CURRENT_TIMESTAMP
        ");
        $stmt->execute([$jadwalId, $_SESSION['nis']]);
        echo json_encode(['success' => true, 'message' => 'Tugas ditandai selesai! 🎉']);
        exit;
    }

    if ($action === 'save_profile') {
        $nama = trim((string) ($_POST['nama'] ?? ''));
        $passwordBaru = (string) ($_POST['password_baru'] ?? '');
        if ($nama === '') {
            echo json_encode(['success' => false, 'message' => 'Nama tidak boleh kosong.']);
            exit;
        }
        $pdo->prepare("UPDATE users SET nama = ? WHERE id = ?")->execute([$nama, (int) $_SESSION['user_id']]);
        $_SESSION['nama'] = $nama;
        if ($passwordBaru !== '') {
            $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([password_hash($passwordBaru, PASSWORD_BCRYPT), (int) $_SESSION['user_id']]);
        }
        echo json_encode(['success' => true, 'message' => 'Profil berhasil diperbarui!', 'nama' => $nama]);
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
  <title>Dashboard Siswa | Multimedia JABUN</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css"/>
  <!-- html5-qrcode library -->
  <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0"></script>
</head>
<body class="dashboard-page">

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
  <div class="sidebar-header">
    <div class="logo-icon">
      <svg width="28" height="28" viewBox="0 0 32 32" fill="none">
        <rect width="32" height="32" rx="8" fill="#6366F1"/>
        <path d="M8 8h6v6H8zM18 8h6v6h-6zM8 18h6v6H8z" fill="white" opacity="0.9"/>
        <rect x="20" y="20" width="4" height="4" fill="white"/>
      </svg>
    </div>
    <div class="logo-text">
      <span class="logo-name">JABUN</span>
      <span class="logo-sub">Student Panel</span>
    </div>
  </div>

  <nav class="sidebar-nav">
    <a href="#" class="nav-item active" onclick="showTab('dashboard', this)">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
      Dashboard
    </a>
    <a href="#" class="nav-item" onclick="showTab('scan', this)">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="5" height="5"/><rect x="16" y="3" width="5" height="5"/><rect x="3" y="16" width="5" height="5"/><path d="M21 16h-3a2 2 0 0 0-2 2v3M21 21v.01M12 7v3a2 2 0 0 1-2 2H7M3 12h.01M12 3h.01M12 16v.01M16 12h1a2 2 0 0 1 2 2v1"/></svg>
      Scan QR
    </a>
    <a href="#" class="nav-item" onclick="showTab('jadwal', this)">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
      Jadwal Materi
    </a>
    <a href="#" class="nav-item" onclick="showTab('profil', this)">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
      Profil Saya
    </a>
  </nav>

  <div class="sidebar-user">
    <div class="user-avatar"><?= strtoupper(substr($_SESSION['nama'], 0, 1)) ?></div>
    <div class="user-info">
      <span class="user-name"><?= htmlspecialchars($_SESSION['nama']) ?></span>
      <span class="user-role">Siswa</span>
    </div>
    <button class="btn-logout" onclick="doLogout()" title="Logout">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
    </button>
  </div>
</aside>

<!-- MAIN CONTENT -->
<main class="main-content">
  <div class="topbar">
    <button class="menu-toggle" onclick="toggleSidebar()">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <h1 class="page-title" id="pageTitle">Dashboard Siswa</h1>
    <div class="topbar-right">
      <span class="date-badge" id="currentDate"></span>
    </div>
  </div>

  <!-- TAB: DASHBOARD -->
  <div id="tab-dashboard" class="tab-content active">
    <div class="hero-banner">
      <div>
        <h2>Halo <?= htmlspecialchars($_SESSION['nama']) ?>! 👋</h2>
        <p>Selamat datang di dashboard siswa. Lihat statistik absensi, jadwal materi, dan progress tugas kamu.</p>
        <div class="hero-badges">
          <span class="hero-badge">📊 Statistik Absensi</span>
          <span class="hero-badge">📅 Jadwal Materi</span>
          <span class="hero-badge">✅ Progress Tugas</span>
        </div>
      </div>
      <div class="hero-side">
        <div class="hero-circle">
          <svg width="60" height="60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
            <path d="M9 12l2 2 4-4M21 12c0 4.97-4.03 9-9 9s-9-4.03-9-9 4.03-9 9-9 9 4.03 9 9z"/>
          </svg>
        </div>
      </div>
    </div>

    <div class="stats-grid">
      <div class="stat-card purple">
        <div class="stat-icon">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#6366F1" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
        </div>
        <div class="stat-info">
          <span class="stat-num" id="s-totalHadir">-</span>
          <span class="stat-label">Total Hadir</span>
        </div>
      </div>
      <div class="stat-card green">
        <div class="stat-icon">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#10B981" stroke-width="2"><path d="M9 12l2 2 4-4M21 12c0 4.97-4.03 9-9 9s-9-4.03-9-9 4.03-9 9-9 9 4.03 9 9z"/></svg>
        </div>
        <div class="stat-info">
          <span class="stat-num" id="s-hadirHariIni">-</span>
          <span class="stat-label">Hadir Hari Ini</span>
        </div>
      </div>
      <div class="stat-card yellow">
        <div class="stat-icon">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#F59E0B" stroke-width="2"><path d="M9 12h6l-6 6h6"/><path d="M21 12c0 4.97-4.03 9-9 9s-9-4.03-9-9 4.03-9 9-9 9 4.03 9 9z"/></svg>
        </div>
        <div class="stat-info">
          <span class="stat-num" id="s-totalTugas">-</span>
          <span class="stat-label">Tugas Selesai</span>
        </div>
      </div>
    </div>

    <div class="qr-grid">
      <div class="card">
        <div class="card-header">
          <h3>Grafik Absensi Mingguan</h3>
        </div>
        <p class="section-subtitle">Pantau kehadiran kamu selama 7 hari terakhir.</p>
        <div class="chart-container">
          <canvas id="weeklyChartStudent"></canvas>
        </div>
      </div>
      <div class="card">
        <div class="card-header">
          <h3>Status Hari Ini</h3>
        </div>
        <div class="status-today" id="statusToday">
          <div class="loading-placeholder">Memuat status...</div>
        </div>
        <div class="mt-20">
          <button onclick="showTab('scan')" class="btn-primary btn-full-width">
            📱 Buka Scanner QR
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- TAB: JADWAL -->
  <div id="tab-jadwal" class="tab-content">
    <div class="card">
      <div class="card-header">
        <h3>Jadwal Materi & Progress Tugas</h3>
      </div>
      <p class="section-subtitle">Klik tombol "Selesai" untuk menandai tugas yang sudah dikerjakan.</p>
      <div id="jadwalWrap">
        <div class="loading-placeholder">Memuat jadwal materi...</div>
      </div>
    </div>
  </div>

  <!-- TAB: SCAN -->
  <div id="tab-scan" class="tab-content">
    <!-- STUDENT INFO CARD -->
    <div class="student-card fade-in">
      <div class="student-avatar"><?= strtoupper(substr($_SESSION['nama'], 0, 1)) ?></div>
      <div class="student-details">
        <span class="student-name"><?= htmlspecialchars($_SESSION['nama']) ?></span>
        <span class="student-nis">NIS: <?= htmlspecialchars($_SESSION['nis']) ?></span>
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
      <div class="card">
        <div class="card-header"><h3>Grafik Absensi Mingguan</h3></div>
        <p class="section-subtitle">Pantau kehadiran kamu selama 7 hari terakhir.</p>
        <div class="chart-container">
          <canvas id="weeklyChartStudentScan"></canvas>
        </div>
      </div>
      <div class="card">
        <div class="card-header"><h3>Mini Kalender</h3></div>
        <div class="calendar-container">
          <div id="miniCalendar"></div>
        </div>
      </div>
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

  <!-- TAB: PROFIL -->
  <div id="tab-profil" class="tab-content">
    <div class="card">
      <div class="card-header">
        <h3>Edit Profil Siswa</h3>
      </div>
      <p class="section-subtitle">Ubah nama tampilan dan password akun kamu.</p>
      <form id="profileFormStudent" class="form-grid">
        <div class="input-wrap">
          <input type="text" id="studentNama" value="<?= htmlspecialchars($_SESSION['nama']) ?>" placeholder="Nama lengkap" required/>
        </div>
        <div class="input-wrap">
          <input type="password" id="studentPassBaru" placeholder="Password baru (kosongkan jika tidak diubah)"/>
        </div>
        <button class="btn-primary" type="submit">Simpan Perubahan Profil</button>
      </form>
    </div>
  </div>

</main>

<!-- BOTTOM NAVIGATION (Mobile) -->
<nav class="bottom-nav" id="bottomNav">
  <a href="#" class="bottom-nav-item active" data-tab="dashboard" onclick="showTab('dashboard', this)">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
    <span>Dashboard</span>
  </a>
  <a href="#" class="bottom-nav-item" data-tab="jadwal" onclick="showTab('jadwal', this)">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
    <span>Jadwal</span>
  </a>
  <a href="#" class="bottom-nav-item" data-tab="scan" onclick="showTab('scan', this)">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="5" height="5"/><rect x="16" y="3" width="5" height="5"/><rect x="3" y="16" width="5" height="5"/><path d="M21 16h-3a2 2 0 0 0-2 2v3M21 21v.01M12 7v3a2 2 0 0 1-2 2H7M3 12h.01M12 3h.01M12 16v.01M16 12h1a2 2 0 0 1 2 2v1"/></svg>
    <span>Scan</span>
  </a>
  <a href="#" class="bottom-nav-item" data-tab="profil" onclick="showTab('profil', this)">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
    <span>Profil</span>
  </a>
</nav>

<!-- OVERLAY for mobile -->
<div class="sidebar-overlay" id="overlay" onclick="toggleSidebar()"></div>

<div id="toast" class="toast"></div>

<script>
// ============================================================
// Dashboard Student Script
// ============================================================
let weeklyChartStudent = null;
let html5QrCode = null;
let scanning = false;
let lastScanned = '';
let weeklyChartStudentScan = null;

// Init
document.addEventListener('DOMContentLoaded', () => {
  document.getElementById('currentDate').textContent = new Date().toLocaleDateString('id-ID', {weekday:'long',year:'numeric',month:'long',day:'numeric'});
  // Set active tab for bottom nav
  document.querySelector('.bottom-nav-item[data-tab="dashboard"]').classList.add('active');
  loadDashboardData();
});

// Tab switching
function showTab(tab, el) {
  if (tab === 'dashboard') {
    window.location.href = 'dashboard_student.php';
    return;
  }
  document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
  document.querySelectorAll('.nav-item').forEach(el => el.classList.remove('active'));
  document.querySelectorAll('.bottom-nav-item').forEach(el => el.classList.remove('active'));
  document.getElementById('tab-' + tab).classList.add('active');
  if (el) el.classList.add('active');
  const titles = {jadwal:'Jadwal Materi', scan:'Scan QR', profil:'Profil Saya'};
  document.getElementById('pageTitle').textContent = titles[tab] || 'Dashboard Siswa';
  if (window.innerWidth < 768) toggleSidebar();

  // Load tab-specific data
  if (tab === 'scan') {
    loadHistory();
    loadMiniCalendar();
    loadWeeklyChartScan();
  }
}

// Load dashboard data
async function loadDashboardData() {
  try {
    const res = await fetch('dashboard_student.php?action=get_dashboard_data', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
    const data = await res.json();
    if (data.success) {
      // Update stats
      document.getElementById('s-totalHadir').textContent = data.stats.total_hadir;
      document.getElementById('s-hadirHariIni').textContent = data.stats.hadir_hari_ini;
      document.getElementById('s-totalTugas').textContent = data.stats.total_tugas;

      // Load chart
      loadWeeklyChart(data.labels, data.hadir);

      // Load jadwal
      renderJadwal(data.jadwal);

      // Status hari ini
      renderStatusToday(data.stats.hadir_hari_ini > 0);
    }
  } catch (err) {
    console.error('Error loading dashboard:', err);
    showToast('Gagal memuat data dashboard', 'error');
  }
}

// Load weekly chart
function loadWeeklyChart(labels, hadirData) {
  const ctx = document.getElementById('weeklyChartStudent');
  if (!ctx) return;
  if (weeklyChartStudent) weeklyChartStudent.destroy();

  weeklyChartStudent = new Chart(ctx, {
    type: 'bar',
    data: {
      labels: labels,
      datasets: [{
        label: 'Kehadiran',
        data: hadirData,
        backgroundColor: 'rgba(99, 102, 241, 0.7)',
        borderColor: '#6366F1',
        borderWidth: 1,
        borderRadius: 4,
        borderSkipped: false,
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      aspectRatio: 1,
      animation: false,
      plugins: {
        legend: { display: false },
        tooltip: {
          backgroundColor: 'rgba(0,0,0,0.8)',
          titleColor: '#fff',
          bodyColor: '#fff',
          cornerRadius: 6,
          displayColors: false
        }
      },
      scales: {
        x: {
          grid: { display: false },
          ticks: { font: { size: 12 } }
        },
        y: {
          beginAtZero: true,
          ticks: { precision: 0, font: { size: 12 } },
          grid: { color: 'rgba(148,163,184,0.2)' }
        }
      },
      interaction: {
        intersect: false,
        mode: 'index'
      }
    }
  });
}

// Load weekly chart for scan tab
async function loadWeeklyChartScan() {
  try {
    const res = await fetch('dashboard_student.php?action=get_dashboard_data', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
    const data = await res.json();
    if (!data.success) return;
    const ctx = document.getElementById('weeklyChartStudentScan');
    if (!ctx) return;
    if (weeklyChartStudentScan) weeklyChartStudentScan.destroy();

    weeklyChartStudentScan = new Chart(ctx, {
      type: 'bar',
      data: {
        labels: data.labels,
        datasets: [{
          label: 'Kehadiran',
          data: data.hadir,
          backgroundColor: 'rgba(99, 102, 241, 0.7)',
          borderColor: '#6366F1',
          borderWidth: 1,
          borderRadius: 4,
          borderSkipped: false,
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        aspectRatio: 1,
        animation: false,
        plugins: {
          legend: { display: false },
          tooltip: {
            backgroundColor: 'rgba(0,0,0,0.8)',
            titleColor: '#fff',
            bodyColor: '#fff',
            cornerRadius: 6,
            displayColors: false
          }
        },
        scales: {
          x: {
            grid: { display: false },
            ticks: { font: { size: 12 } }
          },
          y: {
            beginAtZero: true,
            ticks: { precision: 0, font: { size: 12 } },
            grid: { color: 'rgba(148,163,184,0.2)' }
          }
        },
        interaction: {
          intersect: false,
          mode: 'index'
        }
      }
    });
  } catch (err) {
    console.error('Error loading chart:', err);
  }
}

// Render jadwal
function renderJadwal(jadwal) {
  const wrap = document.getElementById('jadwalWrap');
  if (!jadwal || !jadwal.length) {
    wrap.innerHTML = '<div class="empty-state">Belum ada jadwal materi</div>';
    return;
  }

  wrap.innerHTML = jadwal.map(j => `
    <div class="jadwal-item ${j.tugas_status === 'done' ? 'completed' : ''}">
      <div class="jadwal-header">
        <h4>${escapeHTML(j.judul)}</h4>
        <span class="jadwal-date">${escapeHTML(j.tanggal_materi)}</span>
      </div>
      <p class="jadwal-desc">${escapeHTML(j.deskripsi || 'Tidak ada deskripsi')}</p>
      <div class="jadwal-footer">
        <span class="deadline">Deadline: ${escapeHTML(j.deadline_tugas)}</span>
        ${j.tugas_status === 'done'
          ? '<span class="status-done">✅ Selesai</span>'
          : `<button class="btn-primary" onclick="markTaskDone(${j.id})">Tandai Selesai</button>`
        }
      </div>
    </div>
  `).join('');
}

// Render status hari ini
function renderStatusToday(hasAttended) {
  const wrap = document.getElementById('statusToday');
  wrap.innerHTML = hasAttended ? `
    <div class="status-attended">
      <div class="status-icon">✅</div>
      <div>
        <h4>Kamu sudah absen hari ini!</h4>
        <p>Selamat, kehadiran tercatat dengan baik.</p>
      </div>
    </div>
  ` : `
    <div class="status-missing">
      <div class="status-icon">⏰</div>
      <div>
        <h4>Belum absen hari ini</h4>
        <p>Jangan lupa untuk scan QR code absensi.</p>
      </div>
    </div>
  `;
}

// Mark task as done
async function markTaskDone(jadwalId) {
  const fd = new FormData();
  fd.append('action', 'mark_task_done');
  fd.append('jadwal_id', jadwalId);

  try {
    const res = await fetch('dashboard_student.php', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd });
    const data = await res.json();
    showToast(data.message, data.success ? 'success' : 'error');
    if (data.success) {
      loadDashboardData(); // Reload data
    }
  } catch (err) {
    showToast('Gagal menyimpan perubahan', 'error');
  }
}

// Profile form
document.getElementById('profileFormStudent').addEventListener('submit', async function(e) {
  e.preventDefault();
  const fd = new FormData();
  fd.append('action', 'save_profile');
  fd.append('nama', document.getElementById('studentNama').value.trim());
  fd.append('password_baru', document.getElementById('studentPassBaru').value);

  try {
    const res = await fetch('dashboard_student.php', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd });
    const data = await res.json();
    showToast(data.message, data.success ? 'success' : 'error');
    if (data.success) {
      document.querySelectorAll('.user-name').forEach(el => el.textContent = data.nama);
      document.getElementById('studentPassBaru').value = '';
    }
  } catch (err) {
    showToast('Gagal menyimpan profil', 'error');
  }
});

// Logout
async function doLogout() {
  if (!confirm('Yakin ingin logout?')) return;
  const fd = new FormData();
  fd.append('action', 'logout');
  try {
    const res = await fetch('dashboard_student.php', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd });
    const data = await res.json();
    if (data.success) window.location.href = data.redirect;
  } catch (err) {
    showToast('Gagal logout', 'error');
  }
}

// Start QR scanner
async function startScanner() {
  if (scanning) return;

  try {
    html5QrCode = new Html5Qrcode("qr-reader");
    const config = { fps: 10, qrbox: { width: 220, height: 220 }, aspectRatio: 1.0 };

    await html5QrCode.start(
      { facingMode: "environment" },
      config,
      onScanSuccess,
      onScanError
    );

    scanning = true;
    document.getElementById('startBtn').style.display = 'none';
    document.getElementById('stopBtn').style.display = 'flex';
    showToast('Kamera aktif. Arahkan ke QR Code.', 'info');
  } catch(err) {
    showToast('Gagal akses kamera: ' + err, 'error');
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
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 4v6h-6"/><path d="M20.49 15a9 9 0 0 1-14.85 3.36L1 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10"/></svg>
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
  const resultDiv = document.getElementById('scanResult');
  resultDiv.style.display = 'block';
  resultDiv.className = 'scan-result-box ' + type;
  resultDiv.innerHTML = msg;
}

// Load history
async function loadHistory() {
  try {
    const res = await fetch('dashboard_student.php?action=get_history', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
    const data = await res.json();
    if (!data.success) return;
    renderHistory(data.data);
  } catch (err) {
    console.error('Error loading history:', err);
  }
}

// Render history
function renderHistory(history) {
  const wrap = document.getElementById('historyWrap');
  if (!history || !history.length) {
    wrap.innerHTML = '<div class="empty-state">Belum ada riwayat absensi</div>';
    return;
  }

  wrap.innerHTML = history.map(h => `
    <div class="history-item">
      <div class="history-date">${escapeHTML(h.tanggal)}</div>
      <div class="history-time">${escapeHTML(h.waktu)}</div>
      <div class="history-status ${h.status === 'hadir' ? 'success' : 'error'}">${escapeHTML(h.status)}</div>
    </div>
  `).join('');
}

// Load mini calendar
function loadMiniCalendar() {
  const calendarDiv = document.getElementById('miniCalendar');
  const now = new Date();
  const month = now.getMonth();
  const year = now.getFullYear();
  const firstDay = new Date(year, month, 1).getDay();
  const daysInMonth = new Date(year, month + 1, 0).getDate();

  let html = `<div class="mini-calendar">
    <div class="calendar-header">${now.toLocaleDateString('id-ID', { month: 'long', year: 'numeric' })}</div>
    <div class="calendar-grid">
      <div class="day-label">M</div><div class="day-label">S</div><div class="day-label">S</div><div class="day-label">R</div><div class="day-label">K</div><div class="day-label">J</div><div class="day-label">S</div>`;

  for (let i = 0; i < firstDay; i++) {
    html += '<div class="day empty"></div>';
  }

  for (let day = 1; day <= daysInMonth; day++) {
    const isToday = day === now.getDate();
    html += `<div class="day ${isToday ? 'today' : ''}">${day}</div>`;
  }

  html += '</div></div>';
  calendarDiv.innerHTML = html;
}

// Sidebar toggle
function toggleSidebar() {
  document.getElementById('sidebar').classList.toggle('open');
  document.getElementById('overlay').classList.toggle('show');
}

// Utility
function escapeHTML(str) {
  const div = document.createElement('div');
  div.textContent = str;
  return div.innerHTML;
}
</script>
</body>
</html>