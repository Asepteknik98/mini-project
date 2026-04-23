<?php
// proses_absen.php - Process QR Attendance
// Only accessible via AJAX POST

session_start();
header('Content-Type: application/json');

// Must be AJAX
if (
    !isset($_SERVER['HTTP_X_REQUESTED_WITH'])
    || strtolower((string) $_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest'
) {
    echo json_encode(['success' => false, 'message' => 'Akses tidak diizinkan.']);
    exit;
}

// Must be logged in as student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    echo json_encode(['success' => false, 'message' => 'Sesi tidak valid. Silakan login ulang.']);
    exit;
}

require 'koneksi.php';

$token = trim($_POST['token'] ?? '');
$nis   = $_SESSION['nis']; // Use session NIS, not POST (security)

if (empty($token)) {
    echo json_encode(['success' => false, 'message' => 'Token tidak valid.']);
    exit;
}

// Sanitize token (allow alphanumeric only)
if (!preg_match('/^[a-f0-9]{48}$/', $token)) {
    echo json_encode(['success' => false, 'message' => 'Format token tidak valid.']);
    exit;
}

// 1. Validate token exists and not expired
$stmt = $pdo->prepare("SELECT id, expired_at FROM qr_sessions WHERE token = ? LIMIT 1");
$stmt->execute([$token]);
$session = $stmt->fetch();

if (!$session) {
    echo json_encode(['success' => false, 'message' => 'QR Code tidak dikenali atau sudah tidak berlaku.']);
    exit;
}

if (strtotime($session['expired_at']) < time()) {
    echo json_encode(['success' => false, 'message' => 'QR Code sudah kedaluwarsa. Minta guru untuk generate ulang.']);
    exit;
}

// 2. Prevent duplicate: same NIS + same token session
$dup = $pdo->prepare("SELECT id FROM absensi WHERE nis = ? AND token = ? LIMIT 1");
$dup->execute([$nis, $token]);
if ($dup->fetch()) {
    echo json_encode(['success' => false, 'message' => 'Kamu sudah melakukan absensi untuk sesi ini.']);
    exit;
}

// 3. Validate NIS exists in data_siswa
$siswa = $pdo->prepare("SELECT nama FROM data_siswa WHERE nis = ?");
$siswa->execute([$nis]);
if (!$siswa->fetch()) {
    echo json_encode(['success' => false, 'message' => 'Data NIS tidak ditemukan.']);
    exit;
}

// 4. Record attendance
$tanggal = date('Y-m-d');
$waktu   = date('H:i:s');

$ins = $pdo->prepare("INSERT INTO absensi (nis, token, tanggal, waktu, status) VALUES (?, ?, ?, ?, 'Hadir')");
$ins->execute([$nis, $token, $tanggal, $waktu]);

echo json_encode([
    'success' => true,
    'message' => 'Absensi berhasil! Selamat datang, ' . $_SESSION['nama'] . '.',
    'waktu'   => $waktu,
    'tanggal' => $tanggal
]);
