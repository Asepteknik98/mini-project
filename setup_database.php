<?php
// setup_database.php - Setup database and tables
require 'koneksi.php';

try {
    // Read SQL file
    $sql = file_get_contents('database.sql');

    // Split into individual statements
    $statements = array_filter(array_map('trim', explode(';', $sql)));

    foreach ($statements as $statement) {
        if (!empty($statement) && !preg_match('/^(CREATE DATABASE|USE|--)/i', $statement)) {
            $pdo->exec($statement);
        }
    }

    echo "<h2>Database setup completed successfully!</h2>";
    echo "<p>Tables created and sample data inserted.</p>";
    echo "<p><a href='login.php'>Go to Login</a></p>";

} catch (Exception $e) {
    echo "<h2>Error setting up database:</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
}
?>