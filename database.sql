-- ============================================================
-- Multimedia JABUN - QR Attendance System
-- Database Setup SQL
-- ============================================================

CREATE DATABASE IF NOT EXISTS jabun_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE jabun_db;

-- Tabel data siswa (master data)
CREATE TABLE IF NOT EXISTS data_siswa (
    nis VARCHAR(20) PRIMARY KEY,
    nama VARCHAR(100) NOT NULL
);

-- Tabel users (login accounts)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nis VARCHAR(20) UNIQUE NOT NULL,
    nama VARCHAR(100) NOT NULL,
    password TEXT NOT NULL,
    role ENUM('admin','student') DEFAULT 'student'
);

-- Tabel absensi
CREATE TABLE IF NOT EXISTS absensi (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nis VARCHAR(20) NOT NULL,
    token VARCHAR(100) NOT NULL,
    tanggal DATE NOT NULL,
    waktu TIME NOT NULL,
    status VARCHAR(20) DEFAULT 'Hadir'
);

-- Tabel QR session tokens
CREATE TABLE IF NOT EXISTS qr_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    token VARCHAR(100) NOT NULL UNIQUE,
    expired_at DATETIME NOT NULL
);

-- Jadwal materi yang diatur admin
CREATE TABLE IF NOT EXISTS materi_jadwal (
    id INT AUTO_INCREMENT PRIMARY KEY,
    judul VARCHAR(120) NOT NULL,
    deskripsi TEXT NULL,
    tanggal_materi DATE NOT NULL,
    deadline_tugas DATE NOT NULL,
    created_by INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Progress tugas siswa per materi
CREATE TABLE IF NOT EXISTS tugas_progress (
    id INT AUTO_INCREMENT PRIMARY KEY,
    jadwal_id INT NOT NULL,
    nis VARCHAR(20) NOT NULL,
    status ENUM('pending','done') NOT NULL DEFAULT 'pending',
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_jadwal_nis (jadwal_id, nis)
);

-- ============================================================
-- Data awal: Admin account
-- Password: admin123
-- ============================================================
INSERT IGNORE INTO users (nis, nama, password, role) VALUES
('ADMIN001', 'Administrator', '$2y$10$UGywvFrg9.kTcZM9rqMPZO2ToKeXNWISnJaY/qQ/OCd52NS.WShlO', 'admin');

-- Data siswa contoh
INSERT IGNORE INTO data_siswa (nis, nama) VALUES
('2024001', 'Andi Pratama'),
('2024002', 'Siti Rahayu'),
('2024003', 'Budi Santoso'),
('2024004', 'Dewi Kusuma'),
('2024005', 'Rizki Maulana'),
('2024006', 'Ahmad Fauzi'),
('2024007', 'Maya Sari'),
('2024008', 'Dika Ramadhan'),
('2024009', 'Nina Amelia'),
('2024010', 'Fajar Nugroho');

-- Data absensi contoh untuk grafik
INSERT IGNORE INTO absensi (nis, token, tanggal, waktu, status) VALUES
('2024001', 'sample_token_1', CURDATE(), '07:30:00', 'Hadir'),
('2024002', 'sample_token_1', CURDATE(), '07:35:00', 'Hadir'),
('2024003', 'sample_token_1', CURDATE(), '07:40:00', 'Hadir'),
('2024004', 'sample_token_1', CURDATE(), '07:45:00', 'Hadir'),
('2024005', 'sample_token_1', CURDATE(), '07:50:00', 'Hadir'),
('2024001', 'sample_token_2', DATE_SUB(CURDATE(), INTERVAL 1 DAY), '08:00:00', 'Hadir'),
('2024002', 'sample_token_2', DATE_SUB(CURDATE(), INTERVAL 1 DAY), '08:05:00', 'Hadir'),
('2024003', 'sample_token_2', DATE_SUB(CURDATE(), INTERVAL 1 DAY), '08:10:00', 'Hadir'),
('2024001', 'sample_token_3', DATE_SUB(CURDATE(), INTERVAL 2 DAY), '07:55:00', 'Hadir'),
('2024002', 'sample_token_3', DATE_SUB(CURDATE(), INTERVAL 2 DAY), '08:00:00', 'Hadir'),
('2024001', 'sample_token_4', DATE_SUB(CURDATE(), INTERVAL 3 DAY), '07:45:00', 'Hadir'),
('2024002', 'sample_token_4', DATE_SUB(CURDATE(), INTERVAL 3 DAY), '07:50:00', 'Hadir'),
('2024003', 'sample_token_4', DATE_SUB(CURDATE(), INTERVAL 3 DAY), '07:55:00', 'Hadir'),
('2024001', 'sample_token_5', DATE_SUB(CURDATE(), INTERVAL 4 DAY), '08:10:00', 'Hadir'),
('2024002', 'sample_token_5', DATE_SUB(CURDATE(), INTERVAL 4 DAY), '08:15:00', 'Hadir'),
('2024001', 'sample_token_6', DATE_SUB(CURDATE(), INTERVAL 5 DAY), '07:40:00', 'Hadir'),
('2024002', 'sample_token_6', DATE_SUB(CURDATE(), INTERVAL 5 DAY), '07:45:00', 'Hadir'),
('2024003', 'sample_token_6', DATE_SUB(CURDATE(), INTERVAL 5 DAY), '07:50:00', 'Hadir'),
('2024001', 'sample_token_7', DATE_SUB(CURDATE(), INTERVAL 6 DAY), '08:05:00', 'Hadir'),
('2024002', 'sample_token_7', DATE_SUB(CURDATE(), INTERVAL 6 DAY), '08:10:00', 'Hadir');

-- Data tugas progress contoh
INSERT IGNORE INTO tugas_progress (jadwal_id, nis, status, updated_at) VALUES
(1, '2024001', 'done', NOW()),
(1, '2024002', 'done', NOW()),
(1, '2024003', 'pending', NOW()),
(2, '2024001', 'done', NOW()),
(2, '2024002', 'done', NOW());

-- Data jadwal materi contoh
INSERT IGNORE INTO materi_jadwal (judul, deskripsi, tanggal_materi, deadline_tugas, created_by) VALUES
('Pengenalan Multimedia', 'Dasar-dasar multimedia dan tools yang digunakan', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 7 DAY), 1),
('Editing Video dengan Adobe Premiere', 'Teknik editing video profesional', DATE_ADD(CURDATE(), INTERVAL 3 DAY), DATE_ADD(CURDATE(), INTERVAL 10 DAY), 1),
('Desain Grafis dengan Photoshop', 'Membuat poster dan banner menarik', DATE_ADD(CURDATE(), INTERVAL 7 DAY), DATE_ADD(CURDATE(), INTERVAL 14 DAY), 1);

CREATE INDEX idx_absensi_nis ON absensi(nis);
CREATE INDEX idx_absensi_token ON absensi(token);
CREATE INDEX idx_qr_expired_at ON qr_sessions(expired_at);
CREATE INDEX idx_materi_tanggal ON materi_jadwal(tanggal_materi);
CREATE INDEX idx_tugas_nis ON tugas_progress(nis);
