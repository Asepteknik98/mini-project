<?php
session_start();
header('Content-Type: application/json');

if (
    !isset($_SERVER['HTTP_X_REQUESTED_WITH']) ||
    strtolower((string) $_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest'
) {
    echo json_encode(['success' => false]); exit;
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    echo json_encode(['success' => false]); exit;
}

require 'koneksi.php';

$token = trim($_POST['token'] ?? '');
$lat   = $_POST['lat'] ?? null;
$lng   = $_POST['lng'] ?? null;
$acc   = $_POST['acc'] ?? 999;
$nis   = $_SESSION['nis'];

// 🔥 JANGAN FAIL KALO GPS GA ADA (BIAR TESTING AMAN)
if ($token === '') {
    echo json_encode(['success' => false, 'message' => 'Token kosong']); exit;
}

// 🔥 QR VALIDASI
$stmt = $pdo->prepare("SELECT * FROM qr_sessions WHERE token=? LIMIT 1");
$stmt->execute([$token]);
$session = $stmt->fetch();

if (!$session || strtotime($session['expired_at']) < time()) {
    echo json_encode(['success' => false, 'message' => 'QR invalid']); exit;
}

// 🔥 DUPLICATE
$dup = $pdo->prepare("SELECT id FROM absensi WHERE nis=? AND token=?");
$dup->execute([$nis,$token]);
if ($dup->fetch()) {
    echo json_encode(['success' => false, 'message' => 'Sudah absen']); exit;
}

// ============================
// 🔥 MODE TEST (NO GPS CHECK)
// ============================

$mode_test = true; // 🔥 ganti false kalo udah production

if (!$mode_test) {

    if ($lat === null || $lng === null) {
        echo json_encode(['success' => false, 'message' => 'GPS wajib']); exit;
    }

    if ($acc > 200) {
        echo json_encode(['success' => false, 'message' => 'GPS tidak akurat']); exit;
    }

    // lokasi sekolah
    $office_lat = -6.200000;
    $office_lng = 106.816666;

    function distance($lat1,$lon1,$lat2,$lon2){
        $earth=6371000;
        $dLat=deg2rad($lat2-$lat1);
        $dLon=deg2rad($lon2-$lon1);
        $a=sin($dLat/2)**2 + cos(deg2rad($lat1))*cos(deg2rad($lat2))*sin($dLon/2)**2;
        return $earth*(2*atan2(sqrt($a),sqrt(1-$a)));
    }

    $jarak = distance($lat,$lng,$office_lat,$office_lng);

    if ($jarak > 300) {
        echo json_encode(['success'=>false,'message'=>'Diluar area']); exit;
    }

} else {
    $jarak = 0; // dummy
}

// 🔥 SIMPAN
$pdo->prepare("INSERT INTO absensi 
(nis,token,tanggal,waktu,status,lat,lng,accuracy)
VALUES (?,?,CURDATE(),CURTIME(),'Hadir',?,?,?)")
->execute([$nis,$token,$lat,$lng,$acc]);

echo json_encode([
  'success'=>true,
  'message'=>'Absensi berhasil 🔥'
]);