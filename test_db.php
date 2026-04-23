<?php
// test_db.php - Test database connection and tables
require 'koneksi.php';

echo "<h1>Database Connection Test</h1>";

try {
    // Test connection
    echo "<p>✅ Database connection successful</p>";

    // Test tables
    $tables = ['data_siswa', 'users', 'absensi', 'qr_sessions', 'materi_jadwal', 'tugas_progress'];
    foreach ($tables as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            echo "<p>✅ Table '$table' exists</p>";
        } else {
            echo "<p>❌ Table '$table' missing</p>";
        }
    }

    // Test data
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM data_siswa");
    $count = $stmt->fetch()['count'];
    echo "<p>📊 Data siswa: $count records</p>";

    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    $count = $stmt->fetch()['count'];
    echo "<p>👥 Users: $count records</p>";

    echo "<p><a href='login.php'>Go to Login</a></p>";

} catch (Exception $e) {
    echo "<h2>Error:</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
}
?>