<?php
// register.php - Student Registration
session_start();

if (isset($_SESSION['user_id'])) {
    header('Location: ' . ($_SESSION['role'] === 'admin' ? 'dashboard_admin.php' : 'scan.php'));
    exit;
}

// Handle AJAX: Check NIS exists
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_SERVER['HTTP_X_REQUESTED_WITH'])
    && strtolower((string) $_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
) {
    require 'koneksi.php';
    header('Content-Type: application/json');

    $action = $_POST['action'] ?? '';

    // Check NIS and fetch name
    if ($action === 'check_nis') {
        $nis = trim((string) ($_POST['nis'] ?? ''));
        if (!preg_match('/^[A-Za-z0-9\-]{3,20}$/', $nis)) {
            echo json_encode(['success' => false, 'message' => 'Format NIS tidak valid.']);
            exit;
        }
        $stmt = $pdo->prepare("SELECT nama FROM data_siswa WHERE nis = ?");
        $stmt->execute([$nis]);
        $siswa = $stmt->fetch();
        if ($siswa) {
            // Check not already registered
            $check = $pdo->prepare("SELECT id FROM users WHERE nis = ?");
            $check->execute([$nis]);
            if ($check->fetch()) {
                echo json_encode(['success' => false, 'message' => 'NIS ini sudah terdaftar.']);
            } else {
                echo json_encode(['success' => true, 'nama' => $siswa['nama']]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'NIS tidak ditemukan dalam data siswa.']);
        }
        exit;
    }

    // Register
    if ($action === 'register') {
        $nis      = trim((string) ($_POST['nis'] ?? ''));
        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['confirm'] ?? '';

        if (empty($nis) || empty($password) || empty($confirm) || !preg_match('/^[A-Za-z0-9\-]{3,20}$/', $nis)) {
            echo json_encode(['success' => false, 'message' => 'Semua field wajib diisi.']);
            exit;
        }
        if (strlen($password) < 6) {
            echo json_encode(['success' => false, 'message' => 'Password minimal 6 karakter.']);
            exit;
        }
        if ($password !== $confirm) {
            echo json_encode(['success' => false, 'message' => 'Konfirmasi password tidak cocok.']);
            exit;
        }

        // Validate NIS in data_siswa
        $stmt = $pdo->prepare("SELECT nama FROM data_siswa WHERE nis = ?");
        $stmt->execute([$nis]);
        $siswa = $stmt->fetch();
        if (!$siswa) {
            echo json_encode(['success' => false, 'message' => 'NIS tidak valid.']);
            exit;
        }

        // Check duplicate
        $dup = $pdo->prepare("SELECT id FROM users WHERE nis = ?");
        $dup->execute([$nis]);
        if ($dup->fetch()) {
            echo json_encode(['success' => false, 'message' => 'NIS sudah terdaftar.']);
            exit;
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);
        $ins = $pdo->prepare("INSERT INTO users (nis, nama, password, role) VALUES (?, ?, ?, 'student')");
        $ins->execute([$nis, $siswa['nama'], $hash]);

        echo json_encode(['success' => true, 'message' => 'Akun berhasil dibuat! Silakan login.']);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Daftar | Multimedia JABUN</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css"/>
</head>
<body class="auth-page">

<div class="auth-bg">
  <div class="auth-orb orb1"></div>
  <div class="auth-orb orb2"></div>
  <div class="auth-orb orb3"></div>
</div>

<div class="auth-container">
  <div class="auth-card fade-in">
    <div class="auth-logo">
      <div class="logo-icon">
        <svg width="32" height="32" viewBox="0 0 32 32" fill="none">
          <rect width="32" height="32" rx="8" fill="#6C5CE7"/>
          <path d="M8 8h6v6H8zM18 8h6v6h-6zM8 18h6v6H8z" fill="white" opacity="0.9"/>
          <rect x="20" y="20" width="4" height="4" fill="white"/>
          <rect x="18" y="18" width="2" height="2" fill="white" opacity="0.5"/>
        </svg>
      </div>
      <div class="logo-text">
        <span class="logo-name">JABUN</span>
        <span class="logo-sub">Multimedia Attendance</span>
      </div>
    </div>

    <h2 class="auth-title">Buat Akun</h2>
    <p class="auth-desc">Daftar menggunakan NIS kamu</p>

    <form id="registerForm" autocomplete="off">
      <div class="form-group">
        <label>NIS (Nomor Induk Siswa)</label>
        <div class="input-wrap">
          <span class="input-icon">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/></svg>
          </span>
          <input type="text" id="nis" name="nis" placeholder="Contoh: 2024001" maxlength="20" pattern="[A-Za-z0-9\-]{3,20}" required/>
          <button type="button" class="btn-check-nis" onclick="checkNIS()">Cek</button>
        </div>
        <p class="field-note">Klik tombol Cek supaya nama siswa terisi otomatis.</p>
      </div>

      <div class="form-group hidden" id="namaGroup">
        <label>Nama Siswa</label>
        <div class="input-wrap">
          <input type="text" id="namaDisplay" readonly class="readonly-input"/>
        </div>
      </div>

      <div class="form-group">
        <label>Password</label>
        <div class="input-wrap">
          <span class="input-icon">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
          </span>
          <input type="password" id="password" name="password" placeholder="Minimal 6 karakter" required/>
        </div>
        <p class="field-note">Gunakan kombinasi huruf dan angka agar akun lebih aman.</p>
      </div>

      <div class="form-group">
        <label>Konfirmasi Password</label>
        <div class="input-wrap">
          <span class="input-icon">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
          </span>
          <input type="password" id="confirm" name="confirm" placeholder="Ulangi password" required/>
        </div>
      </div>

      <input type="hidden" name="action" value="register"/>
      <button type="submit" class="btn-auth" id="regBtn">
        <span>Buat Akun</span>
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
      </button>
    </form>

    <div class="auth-footer">
      <p>Sudah punya akun? <a href="login.php">Login di sini</a></p>
    </div>
  </div>
</div>

<div id="toast" class="toast"></div>

<script src="assets/js/script.js"></script>
<script>
let nisValid = false;
const nisInput = document.getElementById('nis');

nisInput.addEventListener('input', () => {
  nisValid = false;
  document.getElementById('namaGroup').style.display = 'none';
});

nisInput.addEventListener('blur', () => {
  if (nisInput.value.trim()) checkNIS();
});

async function checkNIS() {
  const nis = document.getElementById('nis').value.trim();
  if (!nis) { showToast('Masukkan NIS terlebih dahulu', 'warning'); return; }

  const fd = new FormData();
  fd.append('action', 'check_nis');
  fd.append('nis', nis);

  const res = await fetch('register.php', {
    method: 'POST',
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
    body: fd
  });
  const data = await res.json();

  if (data.success) {
    document.getElementById('namaDisplay').value = data.nama;
    document.getElementById('namaGroup').style.display = 'block';
    showToast('NIS valid: ' + data.nama, 'success');
    nisValid = true;
  } else {
    document.getElementById('namaGroup').style.display = 'none';
    showToast(data.message, 'error');
    nisValid = false;
  }
}

document.getElementById('registerForm').addEventListener('submit', async function(e) {
  e.preventDefault();
  if (!nisValid) { showToast('Validasi NIS terlebih dahulu', 'warning'); return; }

  const fd = new FormData(this);
  const res = await fetch('register.php', {
    method: 'POST',
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
    body: fd
  });
  const data = await res.json();
  if (data.success) {
    showToast(data.message, 'success');
    setTimeout(() => window.location.href = 'login.php', 1500);
  } else {
    showToast(data.message, 'error');
  }
});
</script>
</body>
</html>
