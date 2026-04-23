<?php
// dashboard_admin.php - Admin Dashboard
session_start();

// Auth guard
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
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
    $action = $_POST['action'] ?? $_GET['action'] ?? '';

    // Get student list
    if ($action === 'get_students') {
        $stmt = $pdo->query("SELECT nis, nama FROM data_siswa ORDER BY nama");
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
        exit;
    }

    // Add student
    if ($action === 'add_student') {
        $nis  = trim((string) ($_POST['nis'] ?? ''));
        $nama = trim((string) ($_POST['nama'] ?? ''));
        if (
            empty($nis)
            || empty($nama)
            || !preg_match('/^[A-Za-z0-9\-]{3,20}$/', $nis)
            || mb_strlen($nama) > 100
        ) {
            echo json_encode(['success' => false, 'message' => 'NIS dan nama wajib diisi.']);
            exit;
        }
        try {
            $stmt = $pdo->prepare("INSERT INTO data_siswa (nis, nama) VALUES (?, ?)");
            $stmt->execute([$nis, $nama]);
            echo json_encode(['success' => true, 'message' => 'Siswa berhasil ditambahkan.']);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'NIS sudah ada di database.']);
        }
        exit;
    }

    // Delete student
    if ($action === 'delete_student') {
        $nis = trim((string) ($_POST['nis'] ?? ''));
        if (!preg_match('/^[A-Za-z0-9\-]{3,20}$/', $nis)) {
            echo json_encode(['success' => false, 'message' => 'NIS tidak valid.']);
            exit;
        }
        $stmt = $pdo->prepare("DELETE FROM data_siswa WHERE nis = ?");
        $stmt->execute([$nis]);
        echo json_encode(['success' => true, 'message' => 'Data siswa dihapus.']);
        exit;
    }

    // Edit student
    if ($action === 'edit_student') {
        $nis = trim((string) ($_POST['nis'] ?? ''));
        $nama = trim((string) ($_POST['nama'] ?? ''));
        if (
            empty($nis)
            || empty($nama)
            || !preg_match('/^[A-Za-z0-9\-]{3,20}$/', $nis)
            || mb_strlen($nama) > 100
        ) {
            echo json_encode(['success' => false, 'message' => 'NIS dan nama tidak valid.']);
            exit;
        }
        $stmt = $pdo->prepare("UPDATE data_siswa SET nama = ? WHERE nis = ?");
        $stmt->execute([$nama, $nis]);
        $pdo->prepare("UPDATE users SET nama = ? WHERE nis = ?")->execute([$nama, $nis]);
        echo json_encode(['success' => true, 'message' => 'Data siswa berhasil diperbarui.']);
        exit;
    }

    // Generate QR token
    if ($action === 'generate_qr') {
        // Invalidate old tokens
        $pdo->exec("DELETE FROM qr_sessions WHERE expired_at < NOW()");

        $token     = bin2hex(random_bytes(24)); // Secure 48-char token
        $expiredAt = date('Y-m-d H:i:s', time() + 600); // 10 minutes

        $stmt = $pdo->prepare("INSERT INTO qr_sessions (token, expired_at) VALUES (?, ?)");
        $stmt->execute([$token, $expiredAt]);

        echo json_encode([
            'success'    => true,
            'token'      => $token,
            'expired_at' => $expiredAt,
            'expires_in' => 600
        ]);
        exit;
    }

    // Get attendance records
    if ($action === 'get_absensi') {
        $stmt = $pdo->query("
            SELECT a.id, a.nis, a.tanggal, a.waktu, a.status, d.nama
            FROM absensi a
            LEFT JOIN data_siswa d ON a.nis = d.nis
            ORDER BY a.tanggal DESC, a.waktu DESC
            LIMIT 100
        ");
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
        exit;
    }

    if ($action === 'get_weekly_chart') {
        $labels = [];
        $hadir = [];
        $tugasDone = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} day"));
            $labels[] = date('d M', strtotime($date));

            $stmtHadir = $pdo->prepare("SELECT COUNT(*) FROM absensi WHERE tanggal = ?");
            $stmtHadir->execute([$date]);
            $hadir[] = (int) $stmtHadir->fetchColumn();

            $stmtDone = $pdo->prepare("SELECT COUNT(*) FROM tugas_progress WHERE DATE(updated_at) = ? AND status = 'done'");
            $stmtDone->execute([$date]);
            $tugasDone[] = (int) $stmtDone->fetchColumn();
        }

        echo json_encode([
            'success' => true,
            'labels' => $labels,
            'hadir' => $hadir,
            'tugas_done' => $tugasDone
        ]);
        exit;
    }

    if ($action === 'get_schedules') {
        $stmt = $pdo->query("
            SELECT id, judul, deskripsi, tanggal_materi, deadline_tugas
            FROM materi_jadwal
            ORDER BY tanggal_materi DESC, id DESC
            LIMIT 100
        ");
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
        exit;
    }

    if ($action === 'add_schedule') {
        $judul = trim((string) ($_POST['judul'] ?? ''));
        $deskripsi = trim((string) ($_POST['deskripsi'] ?? ''));
        $tanggalMateri = trim((string) ($_POST['tanggal_materi'] ?? ''));
        $deadline = trim((string) ($_POST['deadline_tugas'] ?? ''));

        if ($judul === '' || $tanggalMateri === '' || $deadline === '') {
            echo json_encode(['success' => false, 'message' => 'Judul, tanggal materi, dan deadline wajib diisi.']);
            exit;
        }

        $stmt = $pdo->prepare("
            INSERT INTO materi_jadwal (judul, deskripsi, tanggal_materi, deadline_tugas, created_by)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$judul, $deskripsi, $tanggalMateri, $deadline, (int) $_SESSION['user_id']]);
        echo json_encode(['success' => true, 'message' => 'Jadwal materi berhasil ditambahkan.']);
        exit;
    }

    if ($action === 'delete_schedule') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID jadwal tidak valid.']);
            exit;
        }
        $pdo->prepare("DELETE FROM tugas_progress WHERE jadwal_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM materi_jadwal WHERE id = ?")->execute([$id]);
        echo json_encode(['success' => true, 'message' => 'Jadwal materi dihapus.']);
        exit;
    }

    if ($action === 'get_task_monitor') {
        $stmt = $pdo->query("
            SELECT ds.nis, ds.nama,
                (SELECT COUNT(*) FROM absensi a WHERE a.nis = ds.nis AND a.tanggal = CURDATE()) AS hadir_hari_ini,
                (SELECT COUNT(*) FROM tugas_progress tp WHERE tp.nis = ds.nis AND tp.status = 'done') AS tugas_selesai
            FROM data_siswa ds
            ORDER BY ds.nama
        ");
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
        exit;
    }

    if ($action === 'add_account') {
        $nis = trim((string) ($_POST['nis'] ?? ''));
        $nama = trim((string) ($_POST['nama'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $role = (string) ($_POST['role'] ?? 'student');

        if ($nis === '' || $nama === '' || $password === '' || !in_array($role, ['admin', 'student'], true)) {
            echo json_encode(['success' => false, 'message' => 'Data akun tidak lengkap.']);
            exit;
        }

        $check = $pdo->prepare("SELECT id FROM users WHERE nis = ?");
        $check->execute([$nis]);
        if ($check->fetch()) {
            echo json_encode(['success' => false, 'message' => 'NIS sudah punya akun.']);
            exit;
        }

        $stmt = $pdo->prepare("INSERT INTO users (nis, nama, password, role) VALUES (?, ?, ?, ?)");
        $stmt->execute([$nis, $nama, password_hash($password, PASSWORD_BCRYPT), $role]);
        echo json_encode(['success' => true, 'message' => 'Akun berhasil dibuat.']);
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
        echo json_encode(['success' => true, 'message' => 'Profil berhasil diperbarui.', 'nama' => $nama]);
        exit;
    }

    // Get stats
    if ($action === 'get_stats') {
        $totalSiswa  = $pdo->query("SELECT COUNT(*) FROM data_siswa")->fetchColumn();
        $totalHadir  = $pdo->query("SELECT COUNT(*) FROM absensi WHERE tanggal = CURDATE()")->fetchColumn();
        $totalUsers  = $pdo->query("SELECT COUNT(*) FROM users WHERE role='student'")->fetchColumn();
        $activeToken = $pdo->query("SELECT COUNT(*) FROM qr_sessions WHERE expired_at > NOW()")->fetchColumn();
        echo json_encode([
            'success' => true,
            'total_siswa' => $totalSiswa,
            'hadir_hari_ini' => $totalHadir,
            'total_users' => $totalUsers,
            'active_token' => $activeToken
        ]);
        exit;
    }

    // Logout
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
  <title>Dashboard Admin | Multimedia JABUN</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css">
  <!-- QR Code generator library -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0"></script>
</head>
<body class="dashboard-page">

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
  <div class="sidebar-header">
    <div class="logo-icon">
      <svg width="28" height="28" viewBox="0 0 32 32" fill="none">
        <rect width="32" height="32" rx="8" fill="#6C5CE7"/>
        <path d="M8 8h6v6H8zM18 8h6v6h-6zM8 18h6v6H8z" fill="white" opacity="0.9"/>
        <rect x="20" y="20" width="4" height="4" fill="white"/>
        <rect x="18" y="18" width="2" height="2" fill="white" opacity="0.5"/>
      </svg>
    </div>
    <div class="logo-text">
      <span class="logo-name">JABUN</span>
      <span class="logo-sub">Admin Panel</span>
    </div>
  </div>

  <nav class="sidebar-nav">
    <a href="#" class="nav-item active" onclick="showTab('dashboard', this)">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
      Dashboard
    </a>
    <a href="#" class="nav-item" onclick="showTab('siswa', this)">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
      Data Siswa
    </a>
    <a href="#" class="nav-item" onclick="showTab('qr', this)">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="5" height="5"/><rect x="16" y="3" width="5" height="5"/><rect x="3" y="16" width="5" height="5"/><path d="M21 16h-3a2 2 0 0 0-2 2v3M21 21v.01M12 7v3a2 2 0 0 1-2 2H7M3 12h.01M12 3h.01M12 16v.01M16 12h1a2 2 0 0 1 2 2v1"/></svg>
      Generate QR
    </a>
    <a href="#" class="nav-item" onclick="showTab('absensi', this)">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
      Rekap Absensi
    </a>
    <a href="#" class="nav-item" onclick="showTab('jadwal', this)">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
      Jadwal Materi
    </a>
    <a href="#" class="nav-item" onclick="showTab('profil', this)">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
      Profil
    </a>
  </nav>

  <div class="sidebar-user">
    <div class="user-avatar"><?= strtoupper(substr($_SESSION['nama'], 0, 1)) ?></div>
    <div class="user-info">
      <span class="user-name"><?= htmlspecialchars($_SESSION['nama']) ?></span>
      <span class="user-role">Administrator</span>
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
    <h1 class="page-title" id="pageTitle">Dashboard</h1>
    <div class="topbar-right">
      <span class="date-badge" id="currentDate"></span>
    </div>
  </div>

  <!-- TAB: DASHBOARD -->
  <div id="tab-dashboard" class="tab-content active">
    <div class="hero-banner">
      <div>
        <h2>Halo <?= htmlspecialchars($_SESSION['nama']) ?>, selamat datang!</h2>
      <p>Pantau kehadiran siswa, buat QR sesi baru, dan lihat update absensi secara realtime dengan dashboard modern ini.</p>
      <div class="hero-badges">
        <span class="hero-badge">🎯 Real-time Monitoring</span>
        <span class="hero-badge">🔐 Token Aman 10 Menit</span>
        <span class="hero-badge">⚡ Tanpa Reload Halaman</span>
      </div>
    </div>
    <div class="hero-side">
      <div class="hero-circle">
        <svg width="60" height="60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
          <rect x="3" y="3" width="5" height="5"/><rect x="16" y="3" width="5" height="5"/><rect x="3" y="16" width="5" height="5"/><path d="M21 16h-3a2 2 0 0 0-2 2v3M21 21v.01M12 7v3a2 2 0 0 1-2 2H7M3 12h.01M12 3h.01M12 16v.01M16 12h1a2 2 0 0 1 2 2v1"/>
        </svg>
      </div>
    <div class="stats-grid">
      <div class="stat-card purple">
        <div class="stat-icon">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#6366F1" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        </div>
        <div class="stat-info">
          <span class="stat-num" id="s-totalSiswa">-</span>
          <span class="stat-label">Total Siswa</span>
        </div>
      </div>
      <div class="stat-card green">
        <div class="stat-icon">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#10B981" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
        </div>
        <div class="stat-info">
          <span class="stat-num" id="s-hadirHariIni">-</span>
          <span class="stat-label">Hadir Hari Ini</span>
        </div>
      </div>
      <div class="stat-card yellow">
        <div class="stat-icon">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#F59E0B" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        </div>
        <div class="stat-info">
          <span class="stat-num" id="s-totalUsers">-</span>
          <span class="stat-label">Akun Siswa</span>
        </div>
      </div>
      <div class="stat-card red">
        <div class="stat-icon">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#EF4444" stroke-width="2"><rect x="3" y="3" width="5" height="5"/><rect x="16" y="3" width="5" height="5"/><rect x="3" y="16" width="5" height="5"/></svg>
        </div>
        <div class="stat-info">
          <span class="stat-num" id="s-activeToken">-</span>
          <span class="stat-label">Token Aktif</span>
        </div>
      </div>
    </div>

    <div class="qr-grid">
      <div class="card">
        <div class="card-header">
          <h3>Grafik Mingguan</h3>
        </div>
        <p class="section-subtitle">Grafik valid berdasarkan data absensi dan tugas yang tersimpan.</p>
        <div class="chart-container">
          <canvas id="weeklyChartAdmin"></canvas>
        </div>
      </div>
      <div class="card">
        <div class="card-header">
          <h3>Monitoring Kehadiran & Tugas</h3>
        </div>
        <div class="calendar-container">
          <div id="miniCalendarAdmin"></div>
        </div>
        <div id="monitorWrap">
          <div class="loading-placeholder">Memuat monitoring...</div>
        </div>
      </div>
    </div>

    <div class="card mt-20">
      <div class="card-header">
        <h3>Absensi Terkini</h3>
        <button class="btn-refresh" onclick="loadAbsensi()">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
          Refresh
        </button>
      </div>
      <p class="section-subtitle">Data terbaru akan diperbarui otomatis setiap 15 detik.</p>
      <div id="absensiTableWrap">
        <div class="loading-placeholder">Memuat data...</div>
      </div>
    </div>
  </div>

  <!-- TAB: DATA SISWA -->
  <div id="tab-siswa" class="tab-content">
    <div class="card">
      <div class="card-header">
        <h3>Tambah Siswa</h3>
      </div>
      <p class="section-subtitle">Isi NIS dan nama lengkap siswa, lalu klik tombol tambah.</p>
      <form id="addSiswaForm" class="inline-form">
        <div class="input-wrap">
          <input type="text" id="newNis" placeholder="NIS Siswa" required/>
        </div>
        <div class="input-wrap">
          <input type="text" id="newNama" placeholder="Nama Lengkap" required/>
        </div>
        <button type="submit" class="btn-primary">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          Tambah
        </button>
      </form>
    </div>

    <div class="card mt-20">
      <div class="card-header">
        <h3>Buat Akun dari Dashboard</h3>
      </div>
      <p class="section-subtitle">Admin bisa langsung membuat akun admin/student tanpa keluar dashboard.</p>
      <form id="addAccountForm" class="inline-form">
        <div class="input-wrap">
          <input type="text" id="accNis" placeholder="NIS/ID akun" required/>
        </div>
        <div class="input-wrap">
          <input type="text" id="accNama" placeholder="Nama akun" required/>
        </div>
        <div class="input-wrap">
          <input type="text" id="accPassword" placeholder="Password awal" required/>
        </div>
        <div class="input-wrap">
          <select id="accRole" class="select-transparent">
            <option value="student">Student</option>
            <option value="admin">Admin</option>
          </select>
        </div>
        <button type="submit" class="btn-primary">Buat Akun</button>
      </form>
    </div>

    <div class="card mt-20">
      <div class="card-header">
        <h3>Daftar Siswa</h3>
        <div class="search-mini">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
          <input type="text" id="searchSiswa" placeholder="Cari siswa..." oninput="filterSiswa()"/>
        </div>
      </div>
      <p class="section-subtitle">Gunakan kolom cari untuk menemukan siswa lebih cepat.</p>
      <div id="siswaTableWrap">
        <div class="loading-placeholder">Memuat data...</div>
      </div>
    </div>
  </div>

  <!-- TAB: GENERATE QR -->
  <div id="tab-qr" class="tab-content">
    <div class="qr-grid">
      <div class="card qr-gen-card">
        <div class="card-header">
          <h3>Generate QR Absensi</h3>
        </div>
        <p class="qr-hint">Token berlaku selama <strong>10 menit</strong>. Tampilkan ke layar kelas agar siswa bisa scan lebih cepat.</p>
        <button class="btn-generate" onclick="generateQR()">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="5" height="5"/><rect x="16" y="3" width="5" height="5"/><rect x="3" y="16" width="5" height="5"/><path d="M21 16h-3a2 2 0 0 0-2 2v3M21 21v.01M12 7v3a2 2 0 0 1-2 2H7M3 12h.01M12 3h.01M12 16v.01M16 12h1a2 2 0 0 1 2 2v1"/></svg>
          Generate QR Code Baru
        </button>

        <div id="qrResult" class="qr-result hidden">
          <div id="qrCanvas" class="qr-canvas"></div>
          <div class="qr-meta">
            <div class="qr-token-display">
              <span class="mono" id="qrTokenText"></span>
            </div>
            <div class="qr-timer">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
              Berakhir dalam: <strong id="qrCountdown">10:00</strong>
            </div>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><h3>Panduan Penggunaan</h3></div>
        <div class="guide-list">
          <div class="guide-item">
            <span class="guide-num">1</span>
            <div>
              <strong>Generate QR</strong>
              <p>Klik tombol "Generate QR Code Baru" untuk membuat token absensi unik</p>
            </div>
          </div>
          <div class="guide-item">
            <span class="guide-num">2</span>
            <div>
              <strong>Tampilkan ke Kelas</strong>
              <p>Tampilkan QR Code di layar atau proyektor kelas</p>
            </div>
          </div>
          <div class="guide-item">
            <span class="guide-num">3</span>
            <div>
              <strong>Siswa Scan</strong>
              <p>Siswa membuka halaman scan di perangkat masing-masing</p>
            </div>
          </div>
          <div class="guide-item">
            <span class="guide-num">4</span>
            <div>
              <strong>Otomatis Tercatat</strong>
              <p>Absensi langsung tersimpan, tidak bisa duplikat per sesi</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- TAB: ABSENSI -->
  <div id="tab-absensi" class="tab-content">
    <div class="card">
      <div class="card-header">
        <h3>Rekap Absensi</h3>
        <button class="btn-refresh" onclick="loadAbsensi()">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
          Refresh
        </button>
      </div>
      <div id="absensiFullTableWrap">
        <div class="loading-placeholder">Memuat data...</div>
      </div>
    </div>
  </div>

  <div id="tab-jadwal" class="tab-content">
    <div class="card">
      <div class="card-header">
        <h3>Atur Jadwal Materi</h3>
      </div>
      <p class="section-subtitle">Jadwal ini otomatis tampil di dashboard siswa beserta progres tugasnya.</p>
      <form id="scheduleForm" class="inline-form">
        <div class="input-wrap"><input type="text" id="jadwalJudul" placeholder="Judul materi" required/></div>
        <div class="input-wrap"><input type="date" id="jadwalTanggal" required/></div>
        <div class="input-wrap"><input type="date" id="jadwalDeadline" required/></div>
        <button class="btn-primary" type="submit">Tambah Jadwal</button>
      </form>
      <div class="mt-20 input-wrap">
        <input type="text" id="jadwalDesk" placeholder="Deskripsi / catatan materi (opsional)"/>
      </div>
    </div>
    <div class="card mt-20">
      <div class="card-header"><h3>Daftar Jadwal Materi</h3></div>
      <div id="scheduleWrap"><div class="loading-placeholder">Memuat jadwal...</div></div>
    </div>
  </div>

  <div id="tab-profil" class="tab-content">
    <div class="card">
      <div class="card-header"><h3>Edit Profil Admin</h3></div>
      <p class="section-subtitle">Ubah nama tampilan dan password akun admin kamu.</p>
      <form id="profileForm" class="form-grid">
        <div class="input-wrap">
          <input type="text" id="profileNama" value="<?= htmlspecialchars($_SESSION['nama']) ?>" placeholder="Nama lengkap" required/>
        </div>
        <div class="input-wrap">
          <input type="password" id="profilePass" placeholder="Password baru (kosongkan jika tidak diubah)"/>
        </div>
        <button class="btn-primary" type="submit">Simpan Profil</button>
      </form>
    </div>
  </div>

</main>

<!-- OVERLAY for mobile -->
<div class="sidebar-overlay" id="overlay" onclick="toggleSidebar()"></div>

<div id="toast" class="toast"></div>

<script src="assets/js/script.js"></script>
<script>
// ============================================================
// Dashboard Admin Script
// ============================================================
let siswaData = [];
let qrTimer = null;
let weeklyChartAdmin = null;

// Init
document.addEventListener('DOMContentLoaded', () => {
  document.getElementById('currentDate').textContent = new Date().toLocaleDateString('id-ID', {weekday:'long',year:'numeric',month:'long',day:'numeric'});
  loadStats();
  loadSiswa();
  loadAbsensi();
  loadWeeklyChart();
  loadTaskMonitor();
  loadSchedules();
  renderMiniCalendarAdmin();
  // Auto-refresh absensi every 15s
  setInterval(loadAbsensi, 15000);
  setInterval(loadStats, 15000);
  setInterval(loadTaskMonitor, 20000);
});

// Tab switching
function showTab(tab, el) {
  document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
  document.querySelectorAll('.nav-item').forEach(el => el.classList.remove('active'));
  document.getElementById('tab-' + tab).classList.add('active');
  if (el) el.classList.add('active');
  const titles = {dashboard:'Dashboard',siswa:'Data Siswa',qr:'Generate QR',absensi:'Rekap Absensi',jadwal:'Jadwal Materi',profil:'Profil Saya'};
  document.getElementById('pageTitle').textContent = titles[tab];
  if (window.innerWidth < 768) toggleSidebar();
}

function renderMiniCalendarAdmin() {
  const target = document.getElementById('miniCalendarAdmin');
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

// Load stats
async function loadStats() {
  try {
    const res = await fetch('dashboard_admin.php?action=get_stats', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
    const data = await res.json();
    if (data.success) {
      document.getElementById('s-totalSiswa').textContent = data.total_siswa || 0;
      document.getElementById('s-hadirHariIni').textContent = data.hadir_hari_ini || 0;
      document.getElementById('s-totalUsers').textContent = data.total_users || 0;
      document.getElementById('s-activeToken').textContent = data.active_token || 0;
    } else {
      console.error('Failed to load stats:', data);
    }
  } catch (err) {
    console.error('Error loading stats:', err);
    // Fallback values
    document.getElementById('s-totalSiswa').textContent = '0';
    document.getElementById('s-hadirHariIni').textContent = '0';
    document.getElementById('s-totalUsers').textContent = '0';
    document.getElementById('s-activeToken').textContent = '0';
  }
}

// Load siswa
async function loadSiswa() {
  try {
    const res = await fetch('dashboard_admin.php?action=get_students', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
    const data = await res.json();
    if (data.success) {
      siswaData = data.data;
      renderSiswaTable(siswaData);
    }
  } catch (err) {
    document.getElementById('siswaTableWrap').innerHTML = '<div class="empty-state">Gagal memuat data siswa. Coba refresh.</div>';
  }
}

function renderSiswaTable(list) {
  const wrap = document.getElementById('siswaTableWrap');
  if (!list.length) { wrap.innerHTML = '<div class="empty-state">Belum ada data siswa</div>'; return; }
  wrap.innerHTML = `
    <table class="data-table">
      <thead><tr><th>#</th><th>NIS</th><th>Nama</th><th>Aksi</th></tr></thead>
      <tbody>
        ${list.map((s,i) => `
          <tr>
            <td>${i+1}</td>
            <td><span class="badge-nis">${escapeHTML(s.nis)}</span></td>
            <td>${escapeHTML(s.nama)}</td>
            <td class="action-cell">
              <button class="btn-secondary-sm" onclick="editSiswa('${encodeURIComponent(s.nis)}','${encodeURIComponent(s.nama)}')">Edit</button>
              <button class="btn-danger-sm" onclick="deleteSiswa('${encodeURIComponent(s.nis)}','${encodeURIComponent(s.nama)}')">Hapus</button>
            </td>
          </tr>`).join('')}
      </tbody>
    </table>`;
}

function editSiswa(nis, nama) {
  nis = decodeURIComponent(nis);
  nama = decodeURIComponent(nama);
  const newName = prompt('Ubah nama siswa:', nama);
  if (newName === null || newName.trim() === '') return;
  const fd = new FormData();
  fd.append('action', 'edit_student');
  fd.append('nis', nis);
  fd.append('nama', newName.trim());
  fetch('dashboard_admin.php', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd })
    .then(res => res.json())
    .then(data => {
      showToast(data.message, data.success ? 'success' : 'error');
      if (data.success) loadSiswa();
    })
    .catch(err => {
      console.error(err);
      showToast('Gagal mengubah data siswa', 'error');
    });
}

function filterSiswa() {
  const q = document.getElementById('searchSiswa').value.toLowerCase();
  renderSiswaTable(siswaData.filter(s => s.nama.toLowerCase().includes(q) || s.nis.toLowerCase().includes(q)));
}

// Add siswa
document.getElementById('addSiswaForm').addEventListener('submit', async function(e) {
  e.preventDefault();
  const nisInput = document.getElementById('newNis');
  const namaInput = document.getElementById('newNama');
  if (!nisInput.value.trim() || !namaInput.value.trim()) {
    showToast('Isi NIS dan nama siswa terlebih dahulu.', 'warning');
    return;
  }
  const fd = new FormData();
  fd.append('action','add_student');
  fd.append('nis', nisInput.value.trim());
  fd.append('nama', namaInput.value.trim());
  const res = await fetch('dashboard_admin.php', { method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'}, body:fd });
  const data = await res.json();
  showToast(data.message, data.success ? 'success' : 'error');
  if (data.success) { this.reset(); loadSiswa(); loadStats(); }
});

// Delete siswa
async function deleteSiswa(nis, nama) {
  nis = decodeURIComponent(nis);
  nama = decodeURIComponent(nama);
  if (!confirm(`Hapus siswa ${nama} (${nis})?`)) return;
  const fd = new FormData();
  fd.append('action','delete_student');
  fd.append('nis', nis);
  const res = await fetch('dashboard_admin.php', { method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'}, body:fd });
  const data = await res.json();
  showToast(data.message, data.success ? 'success' : 'error');
  if (data.success) { loadSiswa(); loadStats(); }
}

// Load absensi
async function loadAbsensi() {
  try {
    const res = await fetch('dashboard_admin.php?action=get_absensi', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
    const data = await res.json();

    const makeTable = (id) => {
      const wrap = document.getElementById(id);
      if (!wrap) return;
      if (!data.data || !data.data.length) {
        wrap.innerHTML = '<div class="empty-state">Belum ada data absensi</div>';
        return;
      }
      wrap.innerHTML = `
        <table class="data-table">
          <thead><tr><th>#</th><th>NIS</th><th>Nama</th><th>Tanggal</th><th>Waktu</th><th>Status</th></tr></thead>
          <tbody>
            ${data.data.map((a,i) => `
              <tr>
                <td>${i+1}</td>
                <td><span class="badge-nis">${escapeHTML(a.nis || '')}</span></td>
                <td>${escapeHTML(a.nama || a.nis || '')}</td>
                <td>${escapeHTML(a.tanggal || '')}</td>
                <td class="mono">${escapeHTML(a.waktu || '')}</td>
                <td><span class="badge-status">${escapeHTML(a.status || 'Hadir')}</span></td>
              </tr>`).join('')}
          </tbody>
        </table>`;
    };
    makeTable('absensiTableWrap');
    makeTable('absensiFullTableWrap');
  } catch (err) {
    console.error('Error loading absensi:', err);
    const wrap = document.getElementById('absensiTableWrap');
    if (wrap) wrap.innerHTML = '<div class="empty-state">Gagal memuat data absensi</div>';
  }
}

async function loadWeeklyChart() {
  try {
    const res = await fetch('dashboard_admin.php?action=get_weekly_chart', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
    const data = await res.json();
    if (!data.success) {
      console.error('Failed to load chart data:', data);
      return;
    }
    const ctx = document.getElementById('weeklyChartAdmin');
    if (!ctx) {
      console.error('Chart canvas not found');
      return;
    }
    if (weeklyChartAdmin) weeklyChartAdmin.destroy();

    // Grafik sederhana dengan Chart.js
    weeklyChartAdmin = new Chart(ctx, {
      type: 'bar',
      data: {
        labels: data.labels,
        datasets: [{
          label: 'Hadir',
          data: data.hadir,
          backgroundColor: 'rgba(99, 102, 241, 0.8)',
          borderColor: '#6366F1',
          borderWidth: 1,
          borderRadius: 4,
          borderSkipped: false,
        }, {
          label: 'Tugas Selesai',
          data: data.tugas_done,
          backgroundColor: 'rgba(16, 185, 129, 0.8)',
          borderColor: '#10B981',
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
          legend: {
            position: 'top',
            labels: {
              boxWidth: 12,
              padding: 16,
            }
          },
          title: {
            display: false
          },
          tooltip: {
            backgroundColor: 'rgba(0,0,0,0.8)',
            titleColor: '#fff',
            bodyColor: '#fff',
            cornerRadius: 6,
            displayColors: true
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
            grid: { color: 'rgba(148,163,184,0.18)' }
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
    // Fallback: tampilkan pesan error
    const ctx = document.getElementById('weeklyChartAdmin');
    if (ctx) {
      ctx.style.display = 'none';
      const parent = ctx.parentNode;
      if (!parent.querySelector('.chart-error')) {
        parent.innerHTML += '<div class="chart-error">Grafik tidak dapat dimuat. Periksa koneksi internet.</div>';
      }
    }
  }
}

async function loadTaskMonitor() {
  try {
    const res = await fetch('dashboard_admin.php?action=get_task_monitor', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
    const data = await res.json();
    const wrap = document.getElementById('monitorWrap');
    if (!wrap) return;
    if (!data.success || !data.data.length) {
      wrap.innerHTML = '<div class="empty-state">Belum ada data monitoring siswa.</div>';
      return;
    }
    wrap.innerHTML = `
      <table class="data-table">
        <thead><tr><th>NIS</th><th>Nama</th><th>Hadir Hari Ini</th><th>Tugas Selesai</th></tr></thead>
        <tbody>
          ${data.data.map(row => `
            <tr>
              <td><span class="badge-nis">${escapeHTML(row.nis)}</span></td>
              <td>${escapeHTML(row.nama)}</td>
              <td>${Number(row.hadir_hari_ini) > 0 ? 'Hadir' : 'Belum'}</td>
              <td>${escapeHTML(String(row.tugas_selesai))}</td>
            </tr>
          `).join('')}
        </tbody>
      </table>
    `;
  } catch (err) {
    console.error(err);
  }
}

async function loadSchedules() {
  const wrap = document.getElementById('scheduleWrap');
  try {
    const res = await fetch('dashboard_admin.php?action=get_schedules', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
    const data = await res.json();
    if (!data.success || !data.data.length) {
      wrap.innerHTML = '<div class="empty-state">Belum ada jadwal materi.</div>';
      return;
    }
    wrap.innerHTML = `
      <table class="data-table">
        <thead><tr><th>Judul</th><th>Tanggal Materi</th><th>Deadline</th><th>Aksi</th></tr></thead>
        <tbody>
          ${data.data.map(j => `
            <tr>
              <td>
                <strong>${escapeHTML(j.judul)}</strong><br/>
                <span class="section-subtitle">${escapeHTML(j.deskripsi || '-')}</span>
              </td>
              <td>${escapeHTML(j.tanggal_materi)}</td>
              <td>${escapeHTML(j.deadline_tugas)}</td>
              <td><button class="btn-danger-sm" onclick="deleteSchedule(${Number(j.id)})">Hapus</button></td>
            </tr>
          `).join('')}
        </tbody>
      </table>
    `;
  } catch (err) {
    wrap.innerHTML = '<div class="empty-state">Gagal memuat jadwal.</div>';
  }
}

// Generate QR
async function generateQR() {
  const res = await fetch('dashboard_admin.php?action=generate_qr', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
  const data = await res.json();
  if (!data.success) { showToast('Gagal generate QR', 'error'); return; }

  // Render QR Code
  document.getElementById('qrCanvas').innerHTML = '';
  new QRCode(document.getElementById('qrCanvas'), {
    text: data.token,
    width: 220, height: 220,
    colorDark: '#1a1a2e', colorLight: '#ffffff',
    correctLevel: QRCode.CorrectLevel.H
  });

  document.getElementById('qrTokenText').textContent = data.token.substring(0, 20) + '...';
  document.getElementById('qrResult').style.display = 'block';

  // Countdown timer
  if (qrTimer) clearInterval(qrTimer);
  let seconds = data.expires_in;
  function updateTimer() {
    const m = Math.floor(seconds / 60).toString().padStart(2,'0');
    const s = (seconds % 60).toString().padStart(2,'0');
    document.getElementById('qrCountdown').textContent = `${m}:${s}`;
    if (seconds <= 0) {
      clearInterval(qrTimer);
      document.getElementById('qrCountdown').textContent = 'EXPIRED';
      showToast('Token QR telah kedaluwarsa!', 'warning');
    }
    seconds--;
  }
  updateTimer();
  qrTimer = setInterval(updateTimer, 1000);
  showToast('QR sesi baru berhasil dibuat.', 'success');
  loadStats();
}

// Logout
async function doLogout() {
  if (!confirm('Yakin ingin logout?')) return;
  const fd = new FormData(); fd.append('action','logout');
  const res = await fetch('dashboard_admin.php', { method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'}, body:fd });
  const data = await res.json();
  if (data.success) window.location.href = data.redirect;
}

document.getElementById('addAccountForm').addEventListener('submit', async function(e) {
  e.preventDefault();
  const fd = new FormData();
  fd.append('action', 'add_account');
  fd.append('nis', document.getElementById('accNis').value.trim());
  fd.append('nama', document.getElementById('accNama').value.trim());
  fd.append('password', document.getElementById('accPassword').value.trim());
  fd.append('role', document.getElementById('accRole').value);
  const res = await fetch('dashboard_admin.php', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd });
  const data = await res.json();
  showToast(data.message, data.success ? 'success' : 'error');
  if (data.success) this.reset();
});

document.getElementById('scheduleForm').addEventListener('submit', async function(e) {
  e.preventDefault();
  const fd = new FormData();
  fd.append('action', 'add_schedule');
  fd.append('judul', document.getElementById('jadwalJudul').value.trim());
  fd.append('deskripsi', document.getElementById('jadwalDesk').value.trim());
  fd.append('tanggal_materi', document.getElementById('jadwalTanggal').value);
  fd.append('deadline_tugas', document.getElementById('jadwalDeadline').value);
  const res = await fetch('dashboard_admin.php', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd });
  const data = await res.json();
  showToast(data.message, data.success ? 'success' : 'error');
  if (data.success) { this.reset(); document.getElementById('jadwalDesk').value = ''; loadSchedules(); }
});

async function deleteSchedule(id) {
  if (!confirm('Hapus jadwal materi ini?')) return;
  const fd = new FormData();
  fd.append('action', 'delete_schedule');
  fd.append('id', String(id));
  const res = await fetch('dashboard_admin.php', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd });
  const data = await res.json();
  showToast(data.message, data.success ? 'success' : 'error');
  if (data.success) loadSchedules();
}

document.getElementById('profileForm').addEventListener('submit', async function(e) {
  e.preventDefault();
  const fd = new FormData();
  fd.append('action', 'save_profile');
  fd.append('nama', document.getElementById('profileNama').value.trim());
  fd.append('password_baru', document.getElementById('profilePass').value);
  const res = await fetch('dashboard_admin.php', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd });
  const data = await res.json();
  showToast(data.message, data.success ? 'success' : 'error');
  if (data.success) {
    document.querySelectorAll('.user-name').forEach(el => el.textContent = data.nama);
    document.getElementById('profilePass').value = '';
  }
});

// Sidebar toggle
function toggleSidebar() {
  document.getElementById('sidebar').classList.toggle('open');
  document.getElementById('overlay').classList.toggle('show');
}

// Utility
function escapeHTML(str) {
  if (str == null) return '';
  const div = document.createElement('div');
  div.textContent = str;
  return div.innerHTML;
}
</script>
</body>
</html>
