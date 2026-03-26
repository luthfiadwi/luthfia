<?php
session_start();

// 1. Load File & Middleware
require_once "../../app/middleware/auth.php";
require_once "../../app/middleware/role.php";
require_once "../../app/config/db.php";

require_role('petugas');

// 2. Inisialisasi Variabel
$u = $_SESSION['user'];
$error = "";
$info = null; 
$hasil = null;
$perhitungan = null;

// 3. Fungsi Hitung Biaya
function hitung_biaya($waktu_masuk, $waktu_keluar, $jam_pertama, $jam_berikutnya, $pembulatan_menit) {
    $t_in  = strtotime($waktu_masuk);
    $t_out = strtotime($waktu_keluar);
    $diff_seconds = max(0, $t_out - $t_in);

    $durasi_menit = (int)ceil($diff_seconds / 60);
    $unit = max(1, (int)$pembulatan_menit);
    $menit_terbulat = (int)(ceil($durasi_menit / $unit) * $unit);

    $jam = (int)ceil($menit_terbulat / 60);
    if ($jam < 1) $jam = 1;

    $total = $jam_pertama + (($jam - 1) * $jam_berikutnya);

    return [
        'durasi_menit' => $durasi_menit,
        'menit_terbulat' => $menit_terbulat,
        'jam_terhitung' => $jam,
        'total' => $total
    ];
}

// 4. LOGIKA: CARI TIKET
if (isset($_POST['cari'])) {
    $kode = mysqli_real_escape_string($conn, trim($_POST['kode_tiket']));
    $q = mysqli_query($conn, "
        SELECT t.*, k.jenis_kendaraan, a.nama_area
        FROM transaksi t
        LEFT JOIN kendaraan k ON k.id = t.kendaraan_id
        LEFT JOIN area_parkir a ON a.id = t.area_id
        WHERE t.kode_tiket='$kode' AND t.status='IN'
        LIMIT 1
    ");
    $info = mysqli_fetch_assoc($q);

    if (!$info) {
        $error = "Tiket tidak ditemukan atau sudah keluar.";
    } else {
        $jenis = $info['jenis_kendaraan'] ?? 'motor';
        $area_id = (int)$info['area_id'];
        $qTarif = mysqli_query($conn, "SELECT * FROM tarif WHERE area_id=$area_id AND jenis_kendaraan='$jenis' LIMIT 1");
        $tarif = mysqli_fetch_assoc($qTarif);

        if (!$tarif) {
            $error = "Tarif belum diatur untuk area & jenis ini.";
            $info = null;
        } else {
            $waktu_sekarang = date("Y-m-d H:i:s");
            $perhitungan = hitung_biaya($info['waktu_masuk'], $waktu_sekarang, $tarif['jam_pertama'], $tarif['jam_berikutnya'], $tarif['pembulatan_menit']);
            $perhitungan['waktu_keluar'] = $waktu_sekarang;
        }
    }
}

// 5. LOGIKA: PROSES CHECKOUT
if (isset($_POST['checkout'])) {
    $id_trans = (int)$_POST['transaksi_id'];
    $metode = mysqli_real_escape_string($conn, $_POST['metode_bayar']);
    $waktu_out = date("Y-m-d H:i:s");
    
    $qTrans = mysqli_query($conn, "SELECT t.*, k.jenis_kendaraan FROM transaksi t LEFT JOIN kendaraan k ON k.id = t.kendaraan_id WHERE t.id=$id_trans AND t.status='IN'");
    $data = mysqli_fetch_assoc($qTrans);

    if ($data) {
        $jenis = $data['jenis_kendaraan'] ?? 'motor';
        $qTarif = mysqli_query($conn, "SELECT * FROM tarif WHERE area_id={$data['area_id']} AND jenis_kendaraan='$jenis' LIMIT 1");
        $t = mysqli_fetch_assoc($qTarif);
        $calc = hitung_biaya($data['waktu_masuk'], $waktu_out, $t['jam_pertama'], $t['jam_berikutnya'], $t['pembulatan_menit']);

        $total = $calc['total'];
        $durasi = $calc['durasi_menit'];
        $petugas_id = $u['id'];

        $upd = mysqli_query($conn, "UPDATE transaksi SET status='OUT', waktu_keluar='$waktu_out', total_bayar=$total, durasi_menit=$durasi, metode_bayar='$metode', petugas_keluar_id=$petugas_id WHERE id=$id_trans");

        if ($upd) {
            $hasil = [
                'kode' => $data['kode_tiket'],
                'plat' => $data['plat_nomor'],
                'masuk' => $data['waktu_masuk'],
                'keluar' => $waktu_out,
                'total' => $total,
                'metode' => strtoupper($metode)
            ];
            mysqli_query($conn, "INSERT INTO log_aktivitas (user_id, aksi, keterangan) VALUES ($petugas_id, 'CHECKOUT', 'Tiket: {$data['kode_tiket']}')");
        } else {
            $error = "Terjadi kesalahan saat memproses data.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Kendaraan Keluar - Sistem Parkir</title>
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
            <h2 class="card-title">Kendaraan Keluar</h2>
            <p class="card-subtitle">Scan barcode atau masukkan kode tiket secara manual.</p>

            <?php if ($error): ?>
                <div style="background:#fee2e2; color:#b91c1c; padding:15px; border-radius:12px; margin-bottom:20px; font-size:14px; font-weight:600;">
                    ⚠️ <?= $error ?>
                </div>
            <?php endif; ?>

            <?php if (!$info && !$hasil): ?>
            <form method="POST">
                <div class="form-group">
                    <label>Kode Tiket</label>
                    <input type="text" name="kode_tiket" placeholder="TKT-XXXXXXXX" required class="modern-input" autofocus autocomplete="off">
                </div>
                <div class="form-actions">
                    <button type="submit" name="cari" class="btn-next">Cari Data Tiket</button>
                </div>
            </form>
            <?php elseif ($info && !$hasil): ?>
            <form method="POST">
                <input type="hidden" name="transaksi_id" value="<?= $info['id'] ?>">
                
                <div class="form-group">
                    <label>Metode Pembayaran</label>
                    <div class="radio-toggle-group">
                        <label class="radio-item">
                            <input type="radio" name="metode_bayar" value="cash" checked>
                            <span class="radio-button">💵 Tunai</span>
                        </label>
                        <label class="radio-item">
                            <input type="radio" name="metode_bayar" value="qris">
                            <span class="radio-button">📱 QRIS</span>
                        </label>
                    </div>
                </div>

                <div class="form-actions" style="display:flex; gap:10px;">
                    <button type="submit" name="checkout" class="btn-next" style="flex:2;">Bayar & Selesai</button>
                    <a href="keluar.php" class="btn-next" style="flex:1; background:#e5e5ea; color:#1a1a1a; text-align:center; text-decoration:none;">Batal</a>
                </div>
            </form>
            <?php else: ?>
                <div style="text-align:center; padding: 20px;">
                    <p style="color:var(--text-muted);">Transaksi telah selesai diproses.</p>
                    <a href="keluar.php" class="btn-next" style="margin-top:20px; text-decoration:none; display:block;">Transaksi Baru</a>
                </div>
            <?php endif; ?>
        </div>

        <div class="glass-card">
            <h2 class="card-title">Rincian Biaya</h2>
            
            <?php if ($hasil): ?>
                <div style="text-align: center;">
                    <div style="background:#f0fdf4; color:#166534; padding:12px; border-radius:10px; margin-bottom:20px; font-weight:700;">
                        ✅ Checkout Berhasil
                    </div>
                    
                    <div class="ticket-box" style="border: 2px dashed #e5e5ea; padding: 25px; border-radius: 20px; background: #fafafa;">
                        <h3 style="margin:0 0 15px 0; font-size:18px;">STRUK PARKIR</h3>
                        <div style="display:flex; justify-content: space-between; font-size: 14px; margin-bottom: 8px;">
                            <span>Kode:</span> <strong><?= $hasil['kode'] ?></strong>
                        </div>
                        <div style="display:flex; justify-content: space-between; font-size: 14px; margin-bottom: 8px;">
                            <span>Plat:</span> <strong><?= $hasil['plat'] ?></strong>
                        </div>
                        <hr style="border:none; border-top: 1px dashed #ccc; margin:10px 0;">
                        <div style="display:flex; justify-content: space-between; font-size: 14px; margin-bottom: 8px;">
                            <span>Metode:</span> <strong><?= $hasil['metode'] ?></strong>
                        </div>
                        <div style="display:flex; justify-content: space-between; font-size: 18px; margin-top: 10px;">
                            <span>Total:</span> <strong style="color:var(--primary-blue);">Rp <?= number_format($hasil['total'], 0, ',', '.') ?></strong>
                        </div>
                    </div>
                    
                    <button onclick="window.print()" style="margin-top:20px; width:100%; padding:15px; border-radius:15px; border:none; background:#1a1a1a; color:white; font-weight:700; cursor:pointer;">
                        🖨️ Cetak Struk
                    </button>
                </div>

            <?php elseif ($info && $perhitungan): ?>
                <div class="ticket-box" style="border: 1px solid var(--border-color); padding: 20px; border-radius: 20px;">
                    <div style="margin-bottom:15px;">
                        <label style="font-size:11px; color:var(--text-muted); text-transform:uppercase; font-weight:700;">Data Kendaraan</label>
                        <p style="margin:5px 0; font-size:18px; font-weight:800;"><?= strtoupper($info['plat_nomor']) ?> (<?= ucfirst($info['jenis_kendaraan']) ?>)</p>
                    </div>
                    
                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px; margin-bottom:15px;">
                        <div>
                            <label style="font-size:11px; color:var(--text-muted);">Masuk</label>
                            <p style="font-weight:600; font-size:13px;"><?= date("H:i", strtotime($info['waktu_masuk'])) ?></p>
                        </div>
                        <div>
                            <label style="font-size:11px; color:var(--text-muted);">Durasi</label>
                            <p style="font-weight:600; font-size:13px;"><?= $perhitungan['jam_terhitung'] ?> Jam</p>
                        </div>
                    </div>

                    <div style="background:var(--bg-app); padding:15px; border-radius:15px; text-align:center;">
                        <label style="font-size:12px; color:var(--text-muted);">Total Tagihan</label>
                        <p style="font-size:24px; font-weight:900; color:var(--primary-blue); margin:5px 0;">Rp <?= number_format($perhitungan['total'], 0, ',', '.') ?></p>
                    </div>
                </div>

            <?php else: ?>
                <div class="ticket-empty-state" style="text-align:center; padding:40px 0; opacity:0.5;">
                    <div style="font-size:50px;">🔍</div>
                    <p style="font-size:14px;">Belum ada data tiket.<br>Masukkan kode di sebelah kiri.</p>
                </div>
            <?php endif; ?>
        </div>

    </div>
</main>

</body>
</html>