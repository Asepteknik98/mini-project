<?php
// index.php - Entry point, redirect based on session
session_start();

if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'admin') {
        header('Location: dashboard_admin.php');
    } else {
        header('Location: dashboard_student.php');
    }
    exit;
}

header('Location: login.php');
exit;
