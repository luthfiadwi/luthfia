<?php
session_start();
require_once "../../app/middleware/auth.php";
require_once "../../app/middleware/role.php";
require_once "../../app/config/db.php";

require_role('admin');

$u = $_SESSION['user'];
$msg = $_GET['msg'] ?? "";
$err = "";

// helper log aktivitas
function log_admin($conn, $user_id, $aksi, $keterangan, $ref_id = null) {
    $aksiEsc = mysqli_real_escape_string($conn, $aksi);
    $ketEsc  = mysqli_real_escape_string($conn, $keterangan);
    $refTbl  = "tarif";
    $ref_id  = $ref_id ? (int)$ref_id : "NULL";
    mysqli_query($conn, "INSERT INTO log_aktivitas (user_id, aksi, keterangan, referensi_tabel, referensi_id) VALUES ($user_id, '$aksiEsc', '$ketEsc', '$refTbl', $ref_id)");
}

// ambil area untuk dropdown
$areas = [];
$qAreas = mysqli_query($conn, "SELECT id, nama_area FROM area_parkir ORDER BY nama_area ASC");
while ($r = mysqli_fetch_assoc($qAreas)) $areas[] = $r;

// HAPUS
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $del = mysqli_query($conn, "DELETE FROM tarif WHERE id=$id");
    if ($del) {
        log_admin($conn, (int)$u['id'], "TARIF_DELETE", "Hapus tarif id=$id", $id);
        header("Location: tarif.php?msg=Tarif berhasil dihapus"); exit;
    } else {
        $err = "Gagal hapus tarif: " . mysqli_error($conn);
    }
}

// MODE EDIT
$edit = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $edit = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM tarif WHERE id=$id LIMIT 1"));
}

// SIMPAN
if (isset($_POST['save'])) {
    $id = (int)($_POST['id'] ?? 0);
    $area_id = (int)$_POST['area_id'];
    $jenis   = $_POST['jenis_kendaraan'];
    $jam1    = (int)$_POST['jam_pertama'];
    $jamN    = (int)$_POST['jam_berikutnya'];
    $bulat   = (int)$_POST['pembulatan_menit'];
    $denda   = (int)$_POST['denda_tiket_hilang'];

    if ($id > 0) {
        $upd = mysqli_query($conn, "UPDATE tarif SET area_id=$area_id, jenis_kendaraan='$jenis', jam_pertama=$jam1, jam_berikutnya=$jamN, pembulatan_menit=$bulat, denda_tiket_hilang=$denda WHERE id=$id");
        if ($upd) {
            log_admin($conn, (int)$u['id'], "TARIF_UPDATE", "Update tarif $jenis di area ID $area_id", $id);
            header("Location: tarif.php?msg=Tarif berhasil diupdate"); exit;
        }
    } else {
        $ins = mysqli_query($conn, "INSERT INTO tarif (area_id, jenis_kendaraan, jam_pertama, jam_berikutnya, pembulatan_menit, denda_tiket_hilang) VALUES ($area_id, '$jenis', $jam1, $jamN, $bulat, $denda)");
        if ($ins) {
            header("Location: tarif.php?msg=Tarif berhasil ditambahkan"); exit;
        } else { $err = "Gagal simpan. Mungkin tarif untuk jenis ini sudah ada di area tersebut."; }
    }
}

$qList = mysqli_query($conn, "SELECT t.*, a.nama_area FROM tarif t JOIN area_parkir a ON a.id = t.area_id ORDER BY a.nama_area ASC");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Tarif | Admin Parkir</title>
    <link rel="stylesheet" href="../assets/css/owner.css">
    <style>
        .app-layout { display: flex; min-height: 100vh; background: #f4f7f6; }
        .sidebar { width: 260px; background: white; border-right: 1px solid #eef2f6; padding: 30px 20px; }
        .sidebar-brand { font-weight: 800; color: #1e3c72; font-size: 20px; margin-bottom: 40px; }
        .nav-menu a { display: flex; align-items: center; gap: 12px; padding: 14px 18px; text-decoration: none; color: #64748b; border-radius: 14px; margin-bottom: 8px; transition: 0.3s; }
        .nav-menu a.active { background: #1e3c72; color: white; font-weight: 600; box-shadow: 0 4px 12px rgba(30, 60, 114, 0.15); }
        .main-content { flex: 1; padding: 40px; }

        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; margin-bottom: 20px; }
        .input-group label { display: block; font-size: 11px; font-weight: 700; color: #8e8e93; text-transform: uppercase; margin-bottom: 8px; }
        .input-group input, .input-group select { width: 100%; padding: 12px; border: 1px solid #e2e8f0; border-radius: 12px; font-family: inherit; box-sizing: border-box; }
        
        .modern-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .modern-table th { text-align: left; padding: 15px; color: #8e8e93; font-size: 11px; text-transform: uppercase; border-bottom: 2px solid #f4f7f6; }
        .modern-table td { padding: 15px; border-bottom: 1px solid #f4f7f6; font-size: 14px; }
        
        .btn-action { padding: 8px 12px; border-radius: 10px; text-decoration: none; font-size: 12px; font-weight: 600; }
        .btn-edit { background: #f0f7ff; color: #007aff; margin-right: 5px; }
        .btn-delete { background: #fff5f5; color: #ff3b30; }
        .btn-save { background: #1e3c72; color: white; border: none; padding: 12px 25px; border-radius: 12px; cursor: pointer; font-weight: 600; }
        
        .alert { padding: 15px; border-radius: 12px; margin-bottom: 25px; font-weight: 600; font-size: 14px; }
        .alert-ok { background: #dcfce7; color: #166534; }
        .alert-bad { background: #fee2e2; color: #991b1b; }
        .badge { padding: 4px 8px; border-radius: 6px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
        .badge-motor { background: #e0f2fe; color: #0369a1; }
        .badge-mobil { background: #fef3c7; color: #92400e; }
    </style>
</head>
<body>

<div class="app-layout">
    <aside class="sidebar">
        <div class="sidebar-brand">ADMIN PARKIR</div>
        <nav class="nav-menu">
            <a href="index.php">📊 Dashboard</a>
            <a href="user.php">👥 Kelola User</a>
            <a href="tarif.php" class="active">💰 Kelola Tarif</a>
            <a href="area.php">📍 Kelola Area</a>
            <hr style="border:0; border-top:1px solid #f1f5f9; margin: 20px 0;">
            <a href="../logout.php" style="color: #ef4444;">🚪 Logout</a>
        </nav>
    </aside>

    <main class="main-content">
        <header style="margin-bottom: 40px;">
            <h1 style="font-size: 28px; font-weight: 800; margin: 0;">  Kelola Tarif 💰</h1>
            <p style="color: #8e8e93; margin-top: 5px;">Atur biaya parkir berdasarkan area dan jenis kendaraan.</p>
        </header>

        <?php if($msg): ?> <div class="alert alert-ok">✅ <?= htmlspecialchars($msg) ?></div> <?php endif; ?>
        <?php if($err): ?> <div class="alert alert-bad">❌ <?= htmlspecialchars($err) ?></div> <?php endif; ?>

        <div class="card" style="margin-bottom: 30px; padding: 30px;">
            <h3 style="margin-top: 0; margin-bottom: 25px;"><?= $edit ? "✍️ Edit Tarif" : "➕ Tambah Tarif Baru" ?></h3>
            <form method="POST">
                <input type="hidden" name="id" value="<?= $edit['id'] ?? 0 ?>">
                <div class="form-grid">
                    <div class="input-group">
                        <label>Pilih Area</label>
                        <select name="area_id" required>
                            <option value="">-- Pilih --</option>
                            <?php foreach ($areas as $a): ?>
                                <option value="<?= $a['id'] ?>" <?= ($edit && $edit['area_id'] == $a['id']) ? 'selected' : '' ?>><?= $a['nama_area'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="input-group">
                        <label>Jenis Kendaraan</label>
                        <select name="jenis_kendaraan" required>
                            <option value="motor" <?= ($edit && $edit['jenis_kendaraan'] == 'motor') ? 'selected' : '' ?>>Motor</option>
                            <option value="mobil" <?= ($edit && $edit['jenis_kendaraan'] == 'mobil') ? 'selected' : '' ?>>Mobil</option>
                        </select>
                    </div>
                    <div class="input-group">
                        <label>Jam Pertama (Rp)</label>
                        <input type="number" name="jam_pertama" value="<?= $edit['jam_pertama'] ?? '' ?>" required>
                    </div>
                    <div class="input-group">
                        <label>Jam Berikutnya (Rp)</label>
                        <input type="number" name="jam_berikutnya" value="<?= $edit['jam_berikutnya'] ?? '' ?>" required>
                    </div>
                    <div class="input-group">
                        <label>Pembulatan (Menit)</label>
                        <input type="number" name="pembulatan_menit" value="<?= $edit['pembulatan_menit'] ?? 60 ?>" required>
                    </div>
                    <div class="input-group">
                        <label>Denda Hilang (Rp)</label>
                        <input type="number" name="denda_tiket_hilang" value="<?= $edit['denda_tiket_hilang'] ?? 0 ?>">
                    </div>
                </div>
                <button type="submit" name="save" class="btn-save"><?= $edit ? "Simpan Perubahan" : "Simpan Tarif" ?></button>
                <?php if($edit): ?> <a href="tarif.php" style="margin-left:15px; color:#8e8e93; font-size:14px; text-decoration:none;">Batal</a> <?php endif; ?>
            </form>
        </div>

        <div class="card" style="padding: 20px;">
            <table class="modern-table">
                <thead>
                    <tr>
                        <th>Area</th>
                        <th>Jenis</th>
                        <th>Jam 1</th>
                        <th>Berikutnya</th>
                        <th>Penyelarasan</th>
                        <th>Denda</th>
                        <th style="text-align: right;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($t = mysqli_fetch_assoc($qList)): ?>
                    <tr>
                        <td><b><?= htmlspecialchars($t['nama_area']) ?></b></td>
                        <td><span class="badge badge-<?= $t['jenis_kendaraan'] ?>"><?= $t['jenis_kendaraan'] ?></span></td>
                        <td>Rp <?= number_format($t['jam_pertama'], 0, ',', '.') ?></td>
                        <td>Rp <?= number_format($t['jam_berikutnya'], 0, ',', '.') ?></td>
                        <td style="color:#8e8e93;"><?= $t['pembulatan_menit'] ?> mnt</td>
                        <td>Rp <?= number_format($t['denda_tiket_hilang'], 0, ',', '.') ?></td>
                        <td style="text-align: right;">
                            <a href="tarif.php?edit=<?= $t['id'] ?>" class="btn-action btn-edit">Edit</a>
                            <a href="tarif.php?delete=<?= $a['id'] ?>" class="btn-action btn-delete" onclick="return confirm('Hapus tarif ini?')">Hapus</a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>

</body>
</html>