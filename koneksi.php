<?php
// koneksi.php - PDO connection helper
declare(strict_types=1);

define('DB_HOST', 'localhost');
define('DB_NAME', 'jabun_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

$dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);

    // Optional migration: add profile fields if missing
    $hasUsersTable = $pdo->query("SHOW TABLES LIKE 'users'")->rowCount() > 0;
    if ($hasUsersTable) {
        $columns = [
            'phone' => 'VARCHAR(20) NULL AFTER password',
            'profile_photo' => 'VARCHAR(255) NULL AFTER phone'
        ];
        foreach ($columns as $column => $definition) {
            $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE '$column'");
            if ($stmt && $stmt->rowCount() === 0) {
                $pdo->exec("ALTER TABLE users ADD COLUMN $column $definition");
            }
        }
    }
} catch (PDOException $e) {
    http_response_code(500);
    $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']);
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Koneksi database gagal.']);
    } else {
        echo 'Koneksi database gagal. Periksa konfigurasi di koneksi.php.';
    }
    exit;
}
