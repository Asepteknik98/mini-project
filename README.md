# Sistem Absensi Siswa Multimedia JABUN

Dashboard admin modern untuk mengelola absensi siswa menggunakan QR Code.

## 🚀 Fitur Utama

- ✅ **Dashboard Real-time** - Pantau kehadiran siswa secara langsung
- ✅ **Generate QR Code** - Token absensi yang aman dengan batas waktu 10 menit
- ✅ **Manajemen Siswa** - Tambah, edit, dan hapus data siswa
- ✅ **Rekap Absensi** - Lihat riwayat kehadiran lengkap
- ✅ **Jadwal Materi** - Atur materi dan deadline tugas
- ✅ **Monitoring Tugas** - Lacak progress tugas siswa
- ✅ **Grafik Mingguan** - Visualisasi data absensi dan tugas
- ✅ **Multi-user** - Sistem login untuk admin dan siswa

## 🎨 Desain Modern

- Interface yang berwarna dan responsif
- Gradient background dan efek hover
- Avatar profil bulat di sidebar
- Animasi smooth dan transisi
- Mobile-friendly design

## 📊 SQL Database

Copy dan jalankan SQL berikut di phpMyAdmin atau MySQL client:

```sql
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
```

## 🛠️ Instalasi

1. **Setup Database**: Jalankan SQL di atas di MySQL
2. **Konfigurasi Web Server**: Pastikan XAMPP atau server PHP berjalan
4. **Akses Dashboard**: Buka `http://localhost/mini-project/`
5. **Login Admin**:
   - NIS: `ADMIN001`
   - Password: `admin123`
6. **Login Siswa Test**:
   - NIS: `2024001`, `2024002`, atau `2024003`
   - Password: `siswa123`

## 📱 Cara Penggunaan

### Untuk Admin:
1. **Login** sebagai admin di `login.php`
2. **Dashboard Admin** (`dashboard_admin.php`):
   - Lihat statistik real-time siswa
   - Generate QR code untuk absensi
   - Kelola data siswa dan akun
   - Monitor absensi dan progress tugas
   - Atur jadwal materi

### Untuk Siswa:
1. **Login** sebagai siswa di `login.php`
2. **Dashboard Siswa** (`dashboard_student.php`):
   - Lihat statistik kehadiran pribadi
   - Pantau progress tugas
   - Lihat jadwal materi
   - Edit profil
3. **Scan QR** (`scan.php`):
   - Gunakan kamera untuk scan QR absensi
   - Lihat riwayat absensi
   - Mini kalender dan grafik

## 🎯 Fitur Lengkap

### Dashboard Admin
- Statistik real-time (total siswa, hadir hari ini, dll)
- Grafik mingguan absensi dan tugas
- Kalender mini dengan tanggal hari ini
- Monitoring kehadiran dan progress tugas

### Manajemen Siswa
- Tambah siswa baru
- Buat akun login untuk siswa
- Cari dan filter siswa
- Hapus data siswa

### QR Code Generator
- Generate token unik dengan timer 10 menit
- QR Code yang bisa discan siswa
- Token expired otomatis

### Rekap Absensi
- Tabel lengkap data absensi
- Filter berdasarkan tanggal
- Export data (bisa dikembangkan)

### Jadwal Materi
- Atur materi pembelajaran
- Set deadline tugas
- Siswa bisa lihat di dashboard mereka

### Profil Admin
- Edit nama dan password
- Avatar otomatis dari inisial nama

## 🔧 Teknologi

- **Backend**: PHP 7+ dengan PDO
- **Database**: MySQL/MariaDB
- **Frontend**: HTML5, CSS3, JavaScript ES6
- **Libraries**: Chart.js, QRCode.js
- **Fonts**: Outfit, JetBrains Mono

## 📞 Dukungan

Untuk pertanyaan atau masalah, silakan periksa:
- File `koneksi.php` untuk konfigurasi database
- Console browser untuk error JavaScript
- Log PHP untuk error server

---

**Dibuat untuk Multimedia JABUN** 🎓







<nav class="bottom-nav" id="bottomNav">
  <a href="#" class="bottom-nav-item active" data-tab="dashboard" onclick="showTab('dashboard', this)">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
    <span>Dashboard</span>
  </a>
  <a href="#" class="bottom-nav-item" data-tab="jadwal" onclick="showTab('jadwal', this)">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
    <span>Jadwal</span>
  </a>
  <a href="#" class="bottom-nav-item" data-tab="scan" onclick="showTab('scan', this)">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="5" height="5"/><rect x="16" y="3" width="5" height="5"/><rect x="3" y="16" width="5" height="5"/><path d="M21 16h-3a2 2 0 0 0-2 2v3M21 21v.01M12 7v3a2 2 0 0 1-2 2H7M3 12h.01M12 3h.01M12 16v.01M16 12h1a2 2 0 0 1 2 2v1"/></svg>
    <span>Scan</span>
  </a>
  <a href="#" class="bottom-nav-item" data-tab="profil" onclick="showTab('profil', this)">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
    <span>Profil</span>
  </a>
</nav>