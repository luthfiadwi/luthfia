<?php
session_start();
require_once "../../app/middleware/auth.php";
require_once "../../app/middleware/role.php";
require_once "../../app/config/db.php";

require_role('owner');

$u = $_SESSION['user'];

// --- LOGIKA FILTER TANGGAL ---
$from = $_GET['from'] ?? date('Y-m-d');
$to   = $_GET['to']   ?? date('Y-m-d');

// Validasi sederhana format tanggal
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))   $to   = date('Y-m-d');

$fromDT = $from . " 00:00:00";
$toDT   = $to   . " 23:59:59";
$fromEsc = mysqli_real_escape_string($conn, $fromDT);
$toEsc   = mysqli_real_escape_string($conn, $toDT);

// --- 1. DATA UNTUK CARDS (RINGKASAN) ---
$qSum = mysqli_query($conn, "
    SELECT 
        COUNT(*) AS total_transaksi,
        COALESCE(SUM(total_bayar),0) AS total_pendapatan
    FROM transaksi
    WHERE status='OUT' AND waktu_keluar BETWEEN '$fromEsc' AND '$toEsc'
");
$sum = mysqli_fetch_assoc($qSum);

// Kendaraan sedang parkir (status IN)
$qIn = mysqli_query($conn, "SELECT COUNT(*) AS sedang_parkir FROM transaksi WHERE status='IN'");
$in = mysqli_fetch_assoc($qIn);

// Pendapatan bulan ini
$mStart = date('Y-m-01 00:00:00');
$mEnd   = date('Y-m-t 23:59:59');
$qMonth = mysqli_query($conn, "
    SELECT COALESCE(SUM(total_bayar),0) AS pendapatan_bulan_ini
    FROM transaksi
    WHERE status='OUT' AND waktu_keluar BETWEEN '$mStart' AND '$mEnd'
");
$month = mysqli_fetch_assoc($qMonth);

// --- 2. DATA UNTUK TABEL ---
// Breakdown per area
$qArea = mysqli_query($conn, "
    SELECT a.nama_area,
           COUNT(t.id) AS total_transaksi,
           COALESCE(SUM(t.total_bayar),0) AS total_pendapatan
    FROM transaksi t
    JOIN area_parkir a ON a.id = t.area_id
    WHERE t.status='OUT' AND t.waktu_keluar BETWEEN '$fromEsc' AND '$toEsc'
    GROUP BY t.area_id
    ORDER BY total_pendapatan DESC
");

// Transaksi terbaru
$qLast = mysqli_query($conn, "
    SELECT t.kode_tiket, t.plat_nomor, t.total_bayar, t.waktu_keluar, a.nama_area
    FROM transaksi t
    JOIN area_parkir a ON a.id = t.area_id
    WHERE t.status='OUT' AND t.waktu_keluar BETWEEN '$fromEsc' AND '$toEsc'
    ORDER BY t.waktu_keluar DESC LIMIT 10
");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Owner Dashboard | Sistem Parkir</title>
    <link rel="stylesheet" href="../assets/css/owner.css">
    <style>
        .filter-box { background: white; padding: 20px; border-radius: 20px; margin-bottom: 25px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); }
        .modern-table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        .modern-table th { text-align: left; padding: 12px; border-bottom: 2px solid #f0f0f0; color: var(--text-muted); font-size: 13px; text-transform: uppercase; }
        .modern-table td { padding: 15px 12px; border-bottom: 1px solid #f9f9f9; font-size: 14px; }
        .input-date { padding: 10px; border: 1px solid #ddd; border-radius: 8px; font-family: inherit; }
        .btn-filter { padding: 10px 20px; border: none; border-radius: 10px; background: var(--primary-blue); color: white; cursor: pointer; font-weight: 600; }
        .section-title { margin: 30px 0 15px 0; font-size: 18px; font-weight: 800; display: flex; align-items: center; gap: 10px; }
    </style>
</head>
<body>

<header class="topbar">
    <div class="brand">
        <span class="brand-title">SISTEM PARKIR</span><br>
        <span class="brand-sub">Owner Panel</span>
    </div>
    <div class="topbar-right">
        <span style="font-weight:600; font-size:14px; color:white; margin-right:20px;">Halo, <?= htmlspecialchars($u['nama']) ?> 👋</span>
        <a href="../logout.php" class="btn-logout">Logout</a>
    </div>
</header>

<main class="page-container">
    <div class="hero-section">
        <h1 style="font-size: 26px; font-weight: 800; margin-bottom: 5px;">Dashboard Ringkasan</h1>
        <p style="color: var(--text-muted); margin-bottom: 25px;">Laporan pendapatan dan aktivitas kendaraan.</p>
    </div>

    <div class="filter-box">
        <form method="GET" style="display:flex; gap:15px; align-items: flex-end; flex-wrap: wrap;">
            <div>
                <label style="font-size: 12px; font-weight: 700; color: var(--text-muted); display: block; margin-bottom: 5px;">DARI TANGGAL</label>
                <input type="date" name="from" value="<?= $from ?>" class="input-date">
            </div>
            <div>
                <label style="font-size: 12px; font-weight: 700; color: var(--text-muted); display: block; margin-bottom: 5px;">SAMPAI TANGGAL</label>
                <input type="date" name="to" value="<?= $to ?>" class="input-date">
            </div>
            <button type="submit" class="btn-filter">Update Laporan</button>
        </form>
    </div>

    <div class="cards">
        <div class="card highlight">
            <div class="label">Pendapatan Periode Ini</div>
            <div class="value">Rp <?= number_format($sum['total_pendapatan'], 0, ',', '.') ?></div>
        </div>
        <div class="card">
            <div class="label">Pendapatan Bulan Ini</div>
            <div class="value">Rp <?= number_format($month['pendapatan_bulan_ini'], 0, ',', '.') ?></div>
        </div>
        <div class="card">
            <div class="label">Total Transaksi Selesai</div>
            <div class="value"><?= $sum['total_transaksi'] ?></div>
        </div>
        <div class="card">
            <div class="label">Sedang Parkir (Saat Ini)</div>
            <div class="value"><?= $in['sedang_parkir'] ?></div>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 25px; margin-top: 30px;">
        <div class="card" style="height: fit-content;">
            <h3 style="margin-top:0; font-size: 16px;">📍 Pendapatan per Area</h3>
            <table class="modern-table">
                <thead>
                    <tr><th>Area</th><th style="text-align:right;">Total</th></tr>
                </thead>
                <tbody>
                    <?php while ($r = mysqli_fetch_assoc($qArea)): ?>
                    <tr>
                        <td><?= htmlspecialchars($r['nama_area']) ?></td>
                        <td align="right"><b>Rp <?= number_format($r['total_pendapatan'], 0, ',', '.') ?></b></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <div class="card" style="height: fit-content;">
            <h3 style="margin-top:0; font-size: 16px;">⏱️ 10 Transaksi Terakhir</h3>
            <table class="modern-table">
                <thead>
                    <tr>
                        <th>Waktu Keluar</th>
                        <th>Plat</th>
                        <th>Area</th>
                        <th style="text-align:right;">Biaya</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($r = mysqli_fetch_assoc($qLast)): ?>
                    <tr>
                        <td style="font-size: 12px;"><?= date('d/m H:i', strtotime($r['waktu_keluar'])) ?></td>
                        <td><b><?= htmlspecialchars($r['plat_nomor']) ?></b></td>
                        <td><?= htmlspecialchars($r['nama_area']) ?></td>
                        <td align="right">Rp <?= number_format($r['total_bayar'], 0, ',', '.') ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

</body>
</html>