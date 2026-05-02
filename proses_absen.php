<?php
session_start();
header('Content-Type: application/json');

// AJAX only
if (
    !isset($_SERVER['HTTP_X_REQUESTED_WITH']) ||
    strtolower((string) $_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest'
) {
    echo json_encode(['success' => false, 'message' => 'Akses tidak diizinkan.']);
    exit;
}

// login check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    echo json_encode(['success' => false, 'message' => 'Sesi tidak valid.']);
    exit;
}

require 'koneksi.php';

$token = trim($_POST['token'] ?? '');
$lat   = $_POST['lat'] ?? null;
$lng   = $_POST['lng'] ?? null;
$acc   = $_POST['acc'] ?? 999;

$nis   = $_SESSION['nis'];

// 🔴 VALIDASI BASIC
if ($token === '' || $lat === null || $lng === null) {
    echo json_encode(['success' => false, 'message' => 'GPS tidak aktif']);
    exit;
}

if (!is_numeric($lat) || !is_numeric($lng)) {
    echo json_encode(['success' => false, 'message' => 'Format lokasi tidak valid']);
    exit;
}

if ($acc > 50) {
    echo json_encode(['success' => false, 'message' => 'GPS tidak akurat']);
    exit;
}

if (!preg_match('/^[a-f0-9]{48}$/', $token)) {
    echo json_encode(['success' => false, 'message' => 'Token tidak valid']);
    exit;
}

// 🔴 CEK QR
$stmt = $pdo->prepare("SELECT id, expired_at, type FROM qr_sessions WHERE token = ? LIMIT 1");
$stmt->execute([$token]);
$session = $stmt->fetch();

if (!$session) {
    echo json_encode(['success' => false, 'message' => 'QR tidak valid']);
    exit;
}

if (strtotime($session['expired_at']) < time()) {
    echo json_encode(['success' => false, 'message' => 'QR expired']);
    exit;
}

// 🔴 DUPLICATE
$dup = $pdo->prepare("SELECT id FROM absensi WHERE nis = ? AND token = ? LIMIT 1");
$dup->execute([$nis, $token]);
if ($dup->fetch()) {
    echo json_encode(['success' => false, 'message' => 'Sudah absen']);
    exit;
}

// 🔴 VALIDASI SISWA
$siswa = $pdo->prepare("SELECT nama FROM data_siswa WHERE nis = ?");
$siswa->execute([$nis]);
if (!$siswa->fetch()) {
    echo json_encode(['success' => false, 'message' => 'NIS tidak ditemukan']);
    exit;
}

// 📍 LOKASI SEKOLAH (WAJIB LU GANTI)
$office_lat = -6.200000;
$office_lng = 106.816666;

// 🔥 HITUNG JARAK
function distance($lat1, $lon1, $lat2, $lon2) {
    $earth = 6371000;
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);

    $a = sin($dLat/2) * sin($dLat/2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($dLon/2) * sin($dLon/2);

    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    return $earth * $c;
}

$jarak = distance($lat, $lng, $office_lat, $office_lng);

// 🔴 VALIDASI RADIUS
if ($jarak > 100) {
    echo json_encode([
        'success' => false,
        'message' => 'Diluar area (' . round($jarak) . 'm)'
    ]);
    exit;
}

// 🔴 SIMPAN
$tanggal = date('Y-m-d');
$waktu   = date('H:i:s');
$status  = $session['type'] === 'end' ? 'Selesai Jam' : 'Hadir';

$ins = $pdo->prepare("
    INSERT INTO absensi 
    (nis, token, tanggal, waktu, status, lat, lng, accuracy) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
");

$ins->execute([
    $nis, $token, $tanggal, $waktu, $status, $lat, $lng, $acc
]);

echo json_encode([
    'success' => true,
    'message' => 'Absensi berhasil 📍 (' . round($jarak) . 'm)',
    'status' => $status
]);