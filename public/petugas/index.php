<?php
session_start();

// 1. Load File Pendukung
require_once "../../app/middleware/auth.php";
require_once "../../app/middleware/role.php";
require_once "../../app/config/db.php";

// 2. Proteksi Role
require_role('petugas');

// 3. Ambil Data Session
$u = $_SESSION['user'];

// 4. Pengaturan Waktu & Ringkasan Hari Ini
$today = date("Y-m-d");
$ts = $today . " 00:00:00";
$te = $today . " 23:59:59";

// Query Ringkasan (Menghitung Total Masuk, Keluar, dan Pendapatan dalam satu blok)
// Menggunakan mysqli_real_escape_string demi keamanan
$safe_ts = mysqli_real_escape_string($conn, $ts);
$safe_te = mysqli_real_escape_string($conn, $te);

// Count Masuk Hari Ini
$qMasuk = mysqli_query($conn, "SELECT COUNT(*) AS total FROM transaksi WHERE waktu_masuk BETWEEN '$safe_ts' AND '$safe_te'");
$masuk  = mysqli_fetch_assoc($qMasuk)['total'] ?? 0;

// Count Keluar Hari Ini
$qKeluar = mysqli_query($conn, "SELECT COUNT(*) AS total FROM transaksi WHERE status='OUT' AND waktu_keluar BETWEEN '$safe_ts' AND '$safe_te'");
$keluar  = mysqli_fetch_assoc($qKeluar)['total'] ?? 0;

// Sum Pendapatan Hari Ini
$qPendapatan = mysqli_query($conn, "SELECT SUM(total_bayar) AS total FROM transaksi WHERE status='OUT' AND waktu_keluar BETWEEN '$safe_ts' AND '$safe_te'");
$pendapatan  = mysqli_fetch_assoc($qPendapatan)['total'] ?? 0;

// Count Sedang Parkir (Status IN)
$qSedang = mysqli_query($conn, "SELECT COUNT(*) AS total FROM transaksi WHERE status='IN'");
$sedang  = mysqli_fetch_assoc($qSedang)['total'] ?? 0;

// 5. Ambil 10 Transaksi Terbaru dengan Nama Area
$qLast = mysqli_query($conn, "
    SELECT t.*, a.nama_area 
    FROM transaksi t 
    LEFT JOIN area_parkir a ON t.area_id = a.id 
    ORDER BY t.id DESC 
    LIMIT 10
");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Dashboard Petugas - Sistem Parkir</title>
    <link rel="stylesheet" href="../assets/css/petugas.css">
</head>
<body>

<header class="topbar">
    <div class="brand">
        <div class="brand-title">Sistem Parkir</div>
        <div class="brand-sub">Petugas: <?= htmlspecialchars($u['nama']) ?></div>
    </div>

    <div class="topbar-right">
        <div class="user-info">
            <span class="user-role badge">Petugas</span>
        </div>
        <a class="btn-logout" href="../logout.php" onclick="return confirm('Yakin ingin logout?')">Logout</a>
    </div>
</header>

<main class="page">
    <section class="hero">
        <div class="hero-text">
            <h1>Halo, <?= htmlspecialchars($u['nama']) ?> 👋</h1>
            <p>Kelola transaksi parkir kendaraan dengan cepat dan akurat.</p>
        </div>

        <div class="hero-actions">
            <a class="action-card" href="masuk.php">
                <div class="action-icon">📥</div>
                <div class="action-content">
                    <div class="action-title">Kendaraan Masuk</div>
                    <p>Input plat nomor & cetak tiket</p>
                </div>
            </a>

            <a class="action-card card-keluar" href="keluar.php">
                <div class="action-icon">📤</div>
                <div class="action-content">
                    <div class="action-title">Kendaraan Keluar</div>
                    <p>Scan tiket & hitung biaya</p>
                </div>
            </a>
        </div>
    </section>

    <section class="cards">
        <div class="card">
            <div class="label">Masuk (Hari Ini)</div>
            <div class="value"><?= number_format($masuk) ?></div>
        </div>
        <div class="card">
            <div class="label">Keluar (Hari Ini)</div>
            <div class="value"><?= number_format($keluar) ?></div>
        </div>
        <div class="card highlight">
            <div class="label">Pendapatan (Hari Ini)</div>
            <div class="value">Rp <?= number_format($pendapatan, 0, ',', '.') ?></div>
        </div>
        <div class="card active">
            <div class="label">Sedang Parkir</div>
            <div class="value"><?= number_format($sedang) ?></div>
        </div>
    </section>

    <section class="tablebox">
        <div class="tablehead">
            <h3>Transaksi Terbaru</h3>
            <span class="muted">Update terakhir: <?= date("H:i") ?></span>
        </div>

        <div class="tablewrap">
            <table width="100%">
                <thead>
                    <tr>
                        <th>Waktu</th>
                        <th>Kode Tiket</th>
                        <th>Plat Nomor</th>
                        <th>Area</th>
                        <th>Status</th>
                        <th style="text-align:right;">Biaya</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (mysqli_num_rows($qLast) > 0): ?>
                        <?php while($r = mysqli_fetch_assoc($qLast)): ?>
                            <tr>
                                <td>
                                    <small class="muted"><?= $r['status'] == 'IN' ? 'Masuk:' : 'Keluar:' ?></small><br>
                                    <strong><?= date("H:i", strtotime($r['status'] == 'IN' ? $r['waktu_masuk'] : $r['waktu_keluar'])) ?></strong>
                                </td>
                                <td><code><?= htmlspecialchars($r['kode_tiket']) ?></code></td>
                                <td><strong><?= strtoupper(htmlspecialchars($r['plat_nomor'])) ?></strong></td>
                                <td><?= htmlspecialchars($r['nama_area'] ?? 'Umum') ?></td>
                                <td>
                                    <span class="pill <?= ($r['status'] == 'IN') ? 'pill-in' : 'pill-out' ?>">
                                        <?= $r['status'] ?>
                                    </span>
                                </td>
                                <td style="text-align:right; font-weight:bold;">
                                    <?= $r['status'] == 'OUT' ? 'Rp '.number_format($r['total_bayar'], 0, ',', '.') : '-' ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" style="text-align:center; padding: 20px;" class="muted">
                                Belum ada data transaksi untuk ditampilkan.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>

</body>
</html>