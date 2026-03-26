<?php
session_start();
require_once "../../app/middleware/auth.php";
require_once "../../app/middleware/role.php";
require_once "../../app/config/db.php";

require_role('admin');

$u = $_SESSION['user'];
$msg = $_GET['msg'] ?? "";
$err = "";

// helper log aktivitas admin
function log_admin_area($conn, $user_id, $aksi, $keterangan, $ref_id = null) {
    $aksiEsc = mysqli_real_escape_string($conn, $aksi);
    $ketEsc  = mysqli_real_escape_string($conn, $keterangan);
    $refTbl  = "area_parkir";
    $ref_id  = $ref_id ? (int)$ref_id : "NULL";
    mysqli_query($conn, "INSERT INTO log_aktivitas (user_id, aksi, keterangan, referensi_tabel, referensi_id) VALUES ($user_id, '$aksiEsc', '$ketEsc', '$refTbl', $ref_id)");
}

// TOGGLE STATUS (Ganti dari fitur Hapus)
if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $current = (int)$_GET['status'];
    $newStatus = ($current == 1) ? 0 : 1;
    
    $upd = mysqli_query($conn, "UPDATE area_parkir SET status=$newStatus WHERE id=$id");
    if ($upd) {
        $label = $newStatus ? "diaktifkan" : "dinonaktifkan";
        log_admin_area($conn, (int)$u['id'], "AREA_STATUS", "Mengubah status area id=$id menjadi $label", $id);
        header("Location: area.php?msg=Area berhasil $label");
        exit;
    }
}

// MODE EDIT
$edit = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $qE = mysqli_query($conn, "SELECT * FROM area_parkir WHERE id=$id LIMIT 1");
    $edit = mysqli_fetch_assoc($qE);
}

// SIMPAN (Tambah / Update)
if (isset($_POST['save'])) {
    $id = (int)($_POST['id'] ?? 0);
    $nama_area = mysqli_real_escape_string($conn, trim($_POST['nama_area']));
    $lokasi = mysqli_real_escape_string($conn, trim($_POST['lokasi']));
    $kap_motor = (int)$_POST['kapasitas_motor'];
    $kap_mobil = (int)$_POST['kapasitas_mobil'];

    if ($nama_area === "") {
        $err = "Nama area wajib diisi.";
    } else {
        if ($id > 0) {
            $upd = mysqli_query($conn, "UPDATE area_parkir SET nama_area='$nama_area', lokasi='$lokasi', kapasitas_motor=$kap_motor, kapasitas_mobil=$kap_mobil WHERE id=$id");
            if ($upd) {
                log_admin_area($conn, (int)$u['id'], "AREA_UPDATE", "Update area $nama_area", $id);
                header("Location: area.php?msg=Area berhasil diupdate"); exit;
            }
        } else {
            // Default status 1 (Aktif) saat tambah baru
            $ins = mysqli_query($conn, "INSERT INTO area_parkir (nama_area, lokasi, kapasitas_motor, kapasitas_mobil, status) VALUES ('$nama_area', '$lokasi', $kap_motor, $kap_mobil, 1)");
            if ($ins) {
                $newId = mysqli_insert_id($conn);
                log_admin_area($conn, (int)$u['id'], "AREA_CREATE", "Tambah area $nama_area", $newId);
                header("Location: area.php?msg=Area berhasil ditambahkan"); exit;
            }
        }
    }
}

$qList = mysqli_query($conn, "SELECT * FROM area_parkir ORDER BY nama_area ASC");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Area | Admin Parkir</title>
    <link rel="stylesheet" href="../assets/css/owner.css">
    <style>
        .app-layout { display: flex; min-height: 100vh; background: #f4f7f6; }
        .sidebar { width: 260px; background: white; border-right: 1px solid #eef2f6; padding: 30px 20px; }
        .sidebar-brand { font-weight: 800; color: #1e3c72; font-size: 20px; margin-bottom: 40px; }
        .nav-menu a { display: flex; align-items: center; gap: 12px; padding: 14px 18px; text-decoration: none; color: #64748b; border-radius: 14px; margin-bottom: 8px; transition: 0.3s; }
        .nav-menu a.active { background: #1e3c72; color: white; font-weight: 600; box-shadow: 0 4px 12px rgba(30, 60, 114, 0.15); }
        .main-content { flex: 1; padding: 40px; }
        .card { background: white; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); }

        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px; }
        .input-group label { display: block; font-size: 11px; font-weight: 700; color: #8e8e93; text-transform: uppercase; margin-bottom: 8px; }
        .input-group input { width: 100%; padding: 12px; border: 1px solid #e2e8f0; border-radius: 12px; font-family: inherit; box-sizing: border-box; }
        
        .modern-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .modern-table th { text-align: left; padding: 15px; color: #8e8e93; font-size: 12px; text-transform: uppercase; border-bottom: 2px solid #f4f7f6; }
        .modern-table td { padding: 15px; border-bottom: 1px solid #f4f7f6; font-size: 14px; }
        
        .btn-action { padding: 8px 14px; border-radius: 10px; text-decoration: none; font-size: 12px; font-weight: 600; display: inline-block; }
        .btn-edit { background: #f0f7ff; color: #007aff; }
        .btn-toggle-off { background: #fff5f5; color: #ff3b30; }
        .btn-toggle-on { background: #dcfce7; color: #166534; }
        .btn-save { background: #1e3c72; color: white; border: none; padding: 12px 25px; border-radius: 12px; cursor: pointer; font-weight: 600; }
        
        .badge { padding: 4px 8px; border-radius: 6px; font-size: 10px; font-weight: 800; }
        .badge-active { background: #dcfce7; color: #166534; }
        .badge-inactive { background: #f1f5f9; color: #64748b; }

        .alert { padding: 15px; border-radius: 12px; margin-bottom: 25px; font-weight: 600; font-size: 14px; }
        .alert-ok { background: #dcfce7; color: #166534; }
        .alert-bad { background: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>

<div class="app-layout">
    <aside class="sidebar">
        <div class="sidebar-brand">ADMIN PARKIR</div>
        <nav class="nav-menu">
            <a href="index.php">📊 Dashboard</a>
            <a href="user.php">👥 Kelola User</a>
            <a href="tarif.php">💰 Kelola Tarif</a>
            <a href="area.php" class="active">📍 Kelola Area</a>
            <hr style="border:0; border-top:1px solid #f1f5f9; margin: 20px 0;">
            <a href="../logout.php" style="color: #ef4444;">🚪 Logout</a>
        </nav>
    </aside>

    <main class="main-content">
        <header style="margin-bottom: 40px;">
            <h1 style="font-size: 28px; font-weight: 800; margin: 0;">Kelola Area Parkir 📍</h1>
            <p style="color: #8e8e93; margin-top: 5px;">Atur pintu masuk dan status operasional area.</p>
        </header>

        <?php if($msg): ?> <div class="alert alert-ok">✅ <?= htmlspecialchars($msg) ?></div> <?php endif; ?>
        <?php if($err): ?> <div class="alert alert-bad">❌ <?= htmlspecialchars($err) ?></div> <?php endif; ?>

        <div class="card" style="margin-bottom: 30px; padding: 30px;">
            <h3 style="margin-top: 0; margin-bottom: 25px;"><?= $edit ? "✍️ Edit Area" : "➕ Tambah Area" ?></h3>
            <form method="POST">
                <input type="hidden" name="id" value="<?= $edit ? (int)$edit['id'] : 0 ?>">
                <div class="form-grid">
                    <div class="input-group">
                        <label>Nama Area</label>
                        <input type="text" name="nama_area" value="<?= $edit ? htmlspecialchars($edit['nama_area']) : "" ?>" placeholder="Contoh: Gate A" required>
                    </div>
                    <div class="input-group">
                        <label>Lokasi</label>
                        <input type="text" name="lokasi" value="<?= $edit ? htmlspecialchars($edit['lokasi']) : "" ?>" placeholder="Lantai / Sisi">
                    </div>
                    <div class="input-group">
                        <label>Kapasitas Motor</label>
                        <input type="number" name="kapasitas_motor" min="0" value="<?= $edit ? (int)$edit['kapasitas_motor'] : 0 ?>">
                    </div>
                    <div class="input-group">
                        <label>Kapasitas Mobil</label>
                        <input type="number" name="kapasitas_mobil" min="0" value="<?= $edit ? (int)$edit['kapasitas_mobil'] : 0 ?>">
                    </div>
                </div>
                <div style="display: flex; gap: 10px; align-items: center;">
                    <button type="submit" name="save" class="btn-save"><?= $edit ? "Simpan Perubahan" : "Daftarkan Area" ?></button>
                    <?php if($edit): ?> <a href="area.php" style="color: #8e8e93; font-size: 14px; text-decoration: none;">Batal</a> <?php endif; ?>
                </div>
            </form>
        </div>

        <div class="card" style="padding: 20px;">
            <table class="modern-table">
                <thead>
                    <tr>
                        <th>Status</th>
                        <th>Area</th>
                        <th>Lokasi</th>
                        <th>Kapasitas</th>
                        <th style="text-align: right;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($a = mysqli_fetch_assoc($qList)): ?>
                    <tr style="<?= $a['status'] == 0 ? 'opacity: 0.6;' : '' ?>">
                        <td>
                            <span class="badge <?= $a['status'] == 1 ? 'badge-active' : 'badge-inactive' ?>">
                                <?= $a['status'] == 1 ? 'AKTIF' : 'NONAKTIF' ?>
                            </span>
                        </td>
                        <td><b><?= htmlspecialchars($a['nama_area']) ?></b></td>
                        <td style="color: #8e8e93;"><?= htmlspecialchars($a['lokasi'] ?: '-') ?></td>
                        <td style="font-size: 12px;">
                            🏍️ <?= (int)$a['kapasitas_motor'] ?> | 🚗 <?= (int)$a['kapasitas_mobil'] ?>
                        </td>
                        <td style="text-align: right;">
                            <a href="area.php?edit=<?= $a['id'] ?>" class="btn-action btn-edit">Edit</a>
                            
                            <?php if($a['status'] == 1): ?>
                                <a href="area.php?toggle=<?= $a['id'] ?>&status=1" class="btn-action btn-toggle-off" onclick="return confirm('Nonaktifkan area ini?');">Nonaktifkan</a>
                            <?php else: ?>
                                <a href="area.php?toggle=<?= $a['id'] ?>&status=0" class="btn-action btn-toggle-on">Aktifkan</a>
                            <?php endif; ?>
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