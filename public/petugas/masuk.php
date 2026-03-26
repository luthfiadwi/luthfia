<?php
session_start();
require_once "../../app/middleware/auth.php";
require_once "../../app/middleware/role.php";
require_once "../../app/config/db.php";

require_role('petugas');

$u = $_SESSION['user'];
$success = "";
$error = "";
$ticket_info = null;

function generate_ticket_code($conn, $prefix = "TKT") {
    $date = date("Ymd-His");
    $rand = substr(str_shuffle("ABCDEFGHJKLMNPQRSTUVWXYZ23456789"), 0, 4);
    $kode = "{$prefix}-{$date}-{$rand}";
    $kodeEsc = mysqli_real_escape_string($conn, $kode);
    $cek = mysqli_query($conn, "SELECT id FROM transaksi WHERE kode_tiket='$kodeEsc' LIMIT 1");
    if (mysqli_fetch_assoc($cek)) { return generate_ticket_code($conn, "TKTX"); }
    return $kode;
}

// Ambil data area untuk dropdown
$areas = [];
$qAreas = mysqli_query($conn, "SELECT id, nama_area FROM area_parkir ORDER BY nama_area ASC");
while ($row = mysqli_fetch_assoc($qAreas)) { $areas[] = $row; }

if (isset($_POST['submit'])) {
    $plat = strtoupper(trim($_POST['plat_nomor'] ?? ""));
    $jenis = $_POST['jenis_kendaraan'] ?? "";
    $area_id = (int)($_POST['area_id'] ?? 0);

    if ($plat === "" || !in_array($jenis, ['motor', 'mobil'], true) || $area_id <= 0) {
        $error = "Lengkapi semua data dengan benar.";
    } else {
        $platEsc = mysqli_real_escape_string($conn, $plat);
        $jenisEsc = mysqli_real_escape_string($conn, $jenis);
        $petugas_id = (int)$u['id'];
        $waktu_masuk = date("Y-m-d H:i:s");

        $cekK = mysqli_query($conn, "SELECT id FROM kendaraan WHERE plat_nomor='$platEsc' LIMIT 1");
        $kendaraan = mysqli_fetch_assoc($cekK);

        if ($kendaraan) {
            $kendaraan_id = (int)$kendaraan['id'];
            mysqli_query($conn, "UPDATE kendaraan SET jenis_kendaraan='$jenisEsc' WHERE id=$kendaraan_id");
        } else {
            mysqli_query($conn, "INSERT INTO kendaraan (plat_nomor, jenis_kendaraan) VALUES ('$platEsc', '$jenisEsc')");
            $kendaraan_id = (int)mysqli_insert_id($conn);
        }

        $kode = generate_ticket_code($conn);
        $kodeEsc = mysqli_real_escape_string($conn, $kode);

        $insT = mysqli_query($conn, "
            INSERT INTO transaksi (kode_tiket, kendaraan_id, plat_nomor, area_id, petugas_masuk_id, waktu_masuk, status)
            VALUES ('$kodeEsc', $kendaraan_id, '$platEsc', $area_id, $petugas_id, '$waktu_masuk', 'IN')
        ");

        if ($insT) {
            $success = "Tiket berhasil dicetak!";
            $ticket_info = [
                'kode' => $kode,
                'plat' => $plat,
                'jenis' => ucfirst($jenis),
                'waktu' => $waktu_masuk
            ];
        } else {
            $error = "Sistem gagal: " . mysqli_error($conn);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Input Kendaraan Masuk</title>
    <link rel="stylesheet" href="../assets/css/petugas.css">
</head>
<body>

<header class="topbar">
    <div class="brand">SISTEM PARKIR</div>
    <div class="topbar-right">
        <span style="font-weight:600; font-size:14px; opacity:0.9; color:white; margin-right:15px;">Petugas: <?= htmlspecialchars($u['nama']) ?></span>
        <a href="index.php" class="btn-back">Kembali</a>
    </div>
</header>
<main class="page-container">
    <div class="modern-grid">
        
        <div class="glass-card">
            <h2 class="card-title">Input Kendaraan</h2>
            <p class="card-subtitle">Isi data kendaraan untuk mencetak tiket.</p>

            <?php if ($error): ?>
                <div style="background:#fee2e2; color:#b91c1c; padding:15px; border-radius:12px; margin-bottom:20px; font-size:14px; font-weight:600;">
                    ⚠️ <?= $error ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="modern-form">
                <div class="form-group">
                    <label>Jenis Kendaraan</label>
                    <div class="radio-toggle-group">
                        <label class="radio-item">
                            <input type="radio" name="jenis_kendaraan" value="motor" checked>
                            <span class="radio-button">🛵 Motor</span>
                        </label>
                        <label class="radio-item">
                            <input type="radio" name="jenis_kendaraan" value="mobil">
                            <span class="radio-button">🚗 Mobil</span>
                        </label>
                    </div>
                </div>

                <div class="form-group">
                    <label>Area Parkir</label>
                    <select name="area_id" class="modern-input" required>
                        <option value="" disabled selected>Pilih lokasi parkir...</option>
                        <?php foreach ($areas as $a): ?>
                            <option value="<?= $a['id'] ?>"><?= htmlspecialchars($a['nama_area']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Plat Nomor</label>
                    <input type="text" name="plat_nomor" placeholder="Contoh: B 1234 ABC" required class="modern-input" autocomplete="off">
                </div>

                <div class="form-actions">
                    <button type="submit" name="submit" class="btn-next">Buat Tiket Masuk</button>
                </div>
            </form>
        </div>

        <div class="glass-card">
            <h2 class="card-title">Preview Karcis</h2>
            
            <?php if ($ticket_info): ?>
                <div style="animation: fadeIn 0.5s ease;">
                    <div style="background:#dcfce7; color:#166534; padding:12px; border-radius:10px; margin-bottom:20px; font-weight:700; text-align:center;">
                        ✅ <?= $success ?>
                    </div>
                    
                    <div class="ticket-box" style="border: 2px dashed var(--border-color); padding: 30px; border-radius: 24px; background: #fcfcfc; position: relative;">
                        <div style="text-align: center; margin-bottom: 20px;">
                            <h4 style="margin:0; color:var(--text-muted); font-size:11px; text-transform:uppercase; letter-spacing: 1px;">Kode Tiket</h4>
                            <span style="font-family: 'Courier New', monospace; font-size: 24px; font-weight: 900; color: var(--primary-blue);"><?= $ticket_info['kode'] ?></span>
                        </div>
                        
                        <div style="border-top: 1px dashed var(--border-color); margin: 20px 0; padding-top: 20px;">
                            <div style="display:flex; justify-content: space-between; margin-bottom: 12px;">
                                <span style="color:var(--text-muted); font-size:14px;">Plat Nomor</span>
                                <strong style="font-size:16px;"><?= $ticket_info['plat'] ?></strong>
                            </div>
                            <div style="display:flex; justify-content: space-between; margin-bottom: 12px;">
                                <span style="color:var(--text-muted); font-size:14px;">Jenis</span>
                                <strong><?= $ticket_info['jenis'] ?></strong>
                            </div>
                            <div style="display:flex; justify-content: space-between;">
                                <span style="color:var(--text-muted); font-size:14px;">Waktu Masuk</span>
                                <strong style="font-size:14px;"><?= date("d/m/Y H:i", strtotime($ticket_info['waktu'])) ?></strong>
                            </div>
                        </div>
                    </div>
                    
                    <button onclick="window.print()" style="margin-top:20px; width:100%; padding:18px; border-radius:18px; border:none; background:var(--primary-blue); color:white; font-weight:700; cursor:pointer; transition: 0.3s;">
                        🖨️ Cetak Karcis Sekarang
                    </button>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 60px 20px; border: 2px dashed var(--border-color); border-radius: 24px; opacity: 0.5;">
                    <div style="font-size: 50px; margin-bottom: 15px;">🎫</div>
                    <p style="color:var(--text-muted); font-size: 14px; font-weight: 600;">Belum ada tiket dibuat.<br>Silakan isi data di sebelah kiri.</p>
                </div>
            <?php endif; ?>
        </div>

    </div>
</main>

<style>
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}


</style>

</body>
</html>