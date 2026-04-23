<?php
// login.php - Login Page
session_start();

if (isset($_SESSION['user_id'])) {
    header('Location: ' . ($_SESSION['role'] === 'admin' ? 'dashboard_admin.php' : 'dashboard_student.php'));
    exit;
}

// Handle AJAX login
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_SERVER['HTTP_X_REQUESTED_WITH'])
    && strtolower((string) $_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
) {
    require 'koneksi.php';
    header('Content-Type: application/json');

    $nis      = trim((string) ($_POST['nis'] ?? ''));
    $password = $_POST['password'] ?? '';

    if (empty($nis) || empty($password) || !preg_match('/^[A-Za-z0-9\-]{3,20}$/', $nis)) {
        echo json_encode(['success' => false, 'message' => 'Input login tidak valid.']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT * FROM users WHERE nis = ? LIMIT 1");
    $stmt->execute([$nis]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['nis']     = $user['nis'];
        $_SESSION['nama']    = $user['nama'];
        $_SESSION['role']    = $user['role'];

        $redirect = $user['role'] === 'admin' ? 'dashboard_admin.php' : 'dashboard_student.php';
        echo json_encode(['success' => true, 'redirect' => $redirect]);
    } else {
        echo json_encode(['success' => false, 'message' => 'NIS atau password salah.']);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Login | Multimedia JABUN</title>
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

    <h2 class="auth-title">Selamat Datang</h2>
    <p class="auth-desc">Masuk ke sistem absensi QR Code</p>

    <form id="loginForm" autocomplete="off">
      <div class="form-group">
        <label>NIS / ID</label>
        <div class="input-wrap">
          <span class="input-icon">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          </span>
          <input type="text" id="nis" name="nis" placeholder="Masukkan NIS kamu" maxlength="20" pattern="[A-Za-z0-9\-]{3,20}" required/>
        </div>
        <p class="field-note">Gunakan NIS yang sudah terdaftar di data sekolah.</p>
      </div>
      <div class="form-group">
        <label>Password</label>
        <div class="input-wrap">
          <span class="input-icon">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
          </span>
          <input type="password" id="password" name="password" placeholder="Masukkan password" required/>
          <button type="button" class="toggle-pass" onclick="togglePass()">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" id="eyeIcon"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
          </button>
        </div>
        <p class="field-note">Klik ikon mata untuk menampilkan atau menyembunyikan password.</p>
      </div>
      <button type="submit" class="btn-auth" id="loginBtn">
        <span>Masuk</span>
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
      </button>
    </form>

    <div class="auth-footer">
      <p>Belum punya akun? <a href="register.php">Daftar di sini</a></p>
    </div>

    <div class="auth-hint">
      <span class="hint-badge">Admin</span>
      <span>NIS: ADMIN001 | Pass: admin123</span>
    </div>
  </div>
</div>

<div id="toast" class="toast"></div>

<script src="assets/js/script.js"></script>
<script>
document.getElementById('loginForm').addEventListener('submit', async function(e) {
  e.preventDefault();
  const btn = document.getElementById('loginBtn');
  btn.classList.add('loading');
  btn.innerHTML = '<span class="spinner"></span>';

  const formData = new FormData(this);
  try {
    const res = await fetch('login.php', {
      method: 'POST',
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      body: formData
    });
    const data = await res.json();
    if (data.success) {
      showToast('Login berhasil! Mengalihkan...', 'success');
      setTimeout(() => window.location.href = data.redirect, 800);
    } else {
      showToast(data.message, 'error');
      btn.classList.remove('loading');
      btn.innerHTML = '<span>Masuk</span><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M12 5l7 7-7 7"/></svg>';
    }
  } catch(err) {
    showToast('Terjadi kesalahan. Coba lagi.', 'error');
    btn.classList.remove('loading');
  }
});

function togglePass() {
  const inp = document.getElementById('password');
  inp.type = inp.type === 'password' ? 'text' : 'password';
}
</script>
</body>
</html>
