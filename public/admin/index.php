<?php
session_start();
require_once "../../app/middleware/auth.php";
require_once "../../app/middleware/role.php";
require_once "../../app/config/db.php"; // Pastikan koneksi DB ada untuk ambil data real-time

require_role('admin');
$u = $_SESSION['user'];

// --- LOGIKA AMBIL DATA REAL-TIME ---
$today = date('Y-m-d');

// 1. Total Transaksi Hari Ini
$qTrans = mysqli_query($conn, "SELECT COUNT(*) as total FROM transaksi WHERE DATE(waktu_masuk) = '$today'");
$data_transaksi = mysqli_fetch_assoc($qTrans);

// 2. Pendapatan Hari Ini
$qDuit = mysqli_query($conn, "SELECT SUM(total_bayar) as total FROM transaksi WHERE DATE(waktu_keluar) = '$today' AND status='OUT'");
$data_pendapatan = mysqli_fetch_assoc($qDuit);

// 3. Kendaraan Sedang Parkir
$qParkir = mysqli_query($conn, "SELECT COUNT(*) as total FROM transaksi WHERE status='IN'");
$data_parkir = mysqli_fetch_assoc($qParkir);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin | Sistem Parkir</title>
    <link rel="stylesheet" href="../assets/css/owner.css"> 
    <style>
        /* Penyesuaian agar Sidebar Admin terlihat kokoh */
        .app-layout { display: flex; min-height: 100vh; background: #f4f7f6; }
        .sidebar { width: 260px; background: white; border-right: 1px solid #eef2f6; padding: 30px 20px; }
        .sidebar-brand { font-weight: 800; color: #1e3c72; font-size: 20px; margin-bottom: 40px; }
        .nav-menu a { 
            display: flex; align-items: center; gap: 12px; padding: 14px 18px; 
            text-decoration: none; color: #64748b; border-radius: 14px; margin-bottom: 8px; transition: 0.3s;
        }
        .nav-menu a:hover { background: #f8fafc; color: #1e3c72; }
        .nav-menu a.active { background: #1e3c72; color: white; font-weight: 600; box-shadow: 0 4px 12px rgba(30, 60, 114, 0.15); }
        .main-content { flex: 1; padding: 40px; }
    </style>
</head>
<body>

<div class="app-layout">
    <aside class="sidebar">
        <div class="sidebar-brand">ADMIN PARKIR</div>
        <nav class="nav-menu">
            <a href="index.php" class="active">📊 Dashboard</a>
            <a href="user.php">👥 Kelola User</a>
            <a href="tarif.php">💰 Kelola Tarif</a>
            <a href="area.php">📍 Kelola Area</a>
            <hr style="border:0; border-top:1px solid #f1f5f9; margin: 20px 0;">
            <a href="../logout.php" style="color: #ef4444;">🚪 Logout</a>
        </nav>
    </aside>

    <main class="main-content">
        <header style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 40px;">
            <div>
                <h1 style="font-size: 28px; font-weight: 800; margin: 0;">Dashboard Admin 🛠️</h1>
                <p style="color: #8e8e93; margin-top: 5px;">Selamat datang kembali, <b><?= htmlspecialchars($u['nama']) ?></b>.</p>
            </div>
            <div style="background: white; padding: 10px 20px; border-radius: 12px; box-shadow: 0 4px 10px rgba(0,0,0,0.03);">
                <span style="font-size: 14px; font-weight: 600; color: #1e3c72;"><?= date('d M Y') ?></span>
            </div>
        </header>

        <div class="cards">
            <div class="card highlight">
                <div class="label">Pendapatan Hari Ini</div>
                <div class="value">Rp <?= number_format($data_pendapatan['total'] ?? 0, 0, ',', '.') ?></div>
            </div>
            <div class="card">
                <div class="label">Total Transaksi</div>
                <div class="value"><?= $data_transaksi['total'] ?? 0 ?></div>
            </div>
            <div class="card">
                <div class="label">Sedang Parkir</div>
                <div class="value"><?= $data_parkir['total'] ?? 0 ?></div>
            </div>
        </div>

        <div style="margin-top: 40px;">
            <h3 style="margin-bottom: 20px;">Aksi Cepat</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                <a href="area.php" style="text-decoration:none; background:white; padding:25px; border-radius:20px; text-align:center; box-shadow:0 10px 30px rgba(0,0,0,0.05);">
                    <div style="font-size: 30px; margin-bottom:10px;">🚧</div>
                    <div style="font-weight:700; color:#1e3c72;">Cek Area</div>
                </a>
                <a href="tarif.php" style="text-decoration:none; background:white; padding:25px; border-radius:20px; text-align:center; box-shadow:0 10px 30px rgba(0,0,0,0.05);">
                    <div style="font-size: 30px; margin-bottom:10px;">💸</div>
                    <div style="font-weight:700; color:#1e3c72;">Update Tarif</div>
                </a>
                <a href="user.php" style="text-decoration:none; background:white; padding:25px; border-radius:20px; text-align:center; box-shadow:0 10px 30px rgba(0,0,0,0.05);">
                    <div style="font-size: 30px; margin-bottom:10px;">👤</div>
                    <div style="font-weight:700; color:#1e3c72;">Kelola Petugas</div>
                </a>
            </div>
        </div>
    </main>
</div>

</body>
</html>