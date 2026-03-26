<?php
session_start();
require_once "../../app/middleware/auth.php";
require_once "../../app/middleware/role.php";
require_once "../../app/config/db.php";

require_role('admin');

$u = $_SESSION['user'];
$msg = $_GET['msg'] ?? "";
$err = "";

// ===== TOGGLE STATUS AKTIF (Ganti dari Hapus) =====
if (isset($_GET['toggle_aktif'])) {
    $id = (int)$_GET['toggle_aktif'];
    
    // Proteksi: Jangan sampai admin menonaktifkan dirinya sendiri
    if ($id == $u['id']) {
        $err = "Anda tidak bisa menonaktifkan akun sendiri demi keamanan sistem.";
    } else {
        $current = (int)$_GET['current'];
        $new_val = ($current == 1) ? 0 : 1;
        
        mysqli_query($conn, "UPDATE user SET aktif=$new_val WHERE id=$id");
        $label = $new_val ? "diaktifkan" : "dinonaktifkan";
        header("Location: user.php?msg=User berhasil $label");
        exit;
    }
}

// ===== MODE EDIT =====
$edit = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $edit = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM user WHERE id=$id"));
}

// ===== SIMPAN =====
if (isset($_POST['save'])) {
    $id = (int)($_POST['id'] ?? 0);
    $nama = mysqli_real_escape_string($conn, trim($_POST['nama']));
    $username = mysqli_real_escape_string($conn, trim($_POST['username']));
    $password = trim($_POST['password']);
    $role = $_POST['role'];
    $aktif = isset($_POST['aktif']) ? 1 : 0;

    if ($id > 0) {
        // UPDATE
        $sql = "UPDATE user SET nama='$nama', username='$username', role='$role', aktif=$aktif";
        if ($password != "") {
            $passEsc = mysqli_real_escape_string($conn, $password);
            $sql .= ", password='$passEsc'";
        }
        $sql .= " WHERE id=$id";
        mysqli_query($conn, $sql);
        header("Location: user.php?msg=Data user diperbarui"); exit;
    } else {
        // TAMBAH
        if ($password == "") { 
            $err = "Password wajib diisi untuk user baru."; 
        } else {
            $passEsc = mysqli_real_escape_string($conn, $password);
            mysqli_query($conn, "INSERT INTO user (nama, username, password, role, aktif) VALUES ('$nama','$username','$passEsc','$role',$aktif)");
            header("Location: user.php?msg=User baru berhasil ditambahkan"); exit;
        }
    }
}

$qList = mysqli_query($conn, "SELECT * FROM user ORDER BY aktif DESC, nama ASC");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola User | Admin Parkir</title>
    <link rel="stylesheet" href="../assets/css/owner.css">
    <style>
        .app-layout { display: flex; min-height: 100vh; background: #f4f7f6; }
        .sidebar { width: 260px; background: white; border-right: 1px solid #eef2f6; padding: 30px 20px; }
        .sidebar-brand { font-weight: 800; color: #1e3c72; font-size: 20px; margin-bottom: 40px; }
        .nav-menu a { display: flex; align-items: center; gap: 12px; padding: 14px 18px; text-decoration: none; color: #64748b; border-radius: 14px; margin-bottom: 8px; transition: 0.3s; }
        .nav-menu a.active { background: #1e3c72; color: white; font-weight: 600; box-shadow: 0 4px 12px rgba(30, 60, 114, 0.15); }
        .main-content { flex: 1; padding: 40px; }
        .card { background: white; border-radius: 16px; padding: 25px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); }

        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px; }
        .input-group label { display: block; font-size: 11px; font-weight: 700; color: #8e8e93; text-transform: uppercase; margin-bottom: 8px; }
        .input-group input, .input-group select { width: 100%; padding: 12px; border: 1px solid #e2e8f0; border-radius: 12px; font-family: inherit; box-sizing: border-box; }
        
        .modern-table { width: 100%; border-collapse: collapse; }
        .modern-table th { text-align: left; padding: 15px; color: #8e8e93; font-size: 11px; text-transform: uppercase; border-bottom: 2px solid #f4f7f6; }
        .modern-table td { padding: 15px; border-bottom: 1px solid #f4f7f6; font-size: 14px; }
        
        .badge { padding: 5px 10px; border-radius: 8px; font-size: 10px; font-weight: 700; }
        .badge-aktif { background: #dcfce7; color: #166534; }
        .badge-non { background: #f1f5f9; color: #64748b; }
        
        .btn-action { padding: 8px 12px; border-radius: 10px; text-decoration: none; font-size: 12px; font-weight: 600; display: inline-block; }
        .btn-edit { background: #f0f7ff; color: #007aff; }
        .btn-off { background: #fff5f5; color: #ff3b30; margin-left: 5px; }
        .btn-on { background: #dcfce7; color: #166534; margin-left: 5px; }
        .btn-save { background: #1e3c72; color: white; border: none; padding: 12px 25px; border-radius: 12px; cursor: pointer; font-weight: 600; }
        
        .alert { padding: 15px; border-radius: 12px; margin-bottom: 20px; font-size: 14px; }
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
            <a href="user.php" class="active">👥 Kelola User</a>
            <a href="tarif.php">💰 Kelola Tarif</a>
            <a href="area.php">📍 Kelola Area</a>
            <a href="#">📑 Laporan</a>
            <hr style="border:0; border-top:1px solid #f1f5f9; margin: 20px 0;">
            <a href="../logout.php" style="color: #ef4444;">🚪 Logout</a>
        </nav>
    </aside>

    <main class="main-content">
        <header style="margin-bottom: 40px;">
            <h1 style="font-size: 28px; font-weight: 800; margin: 0;">Kelola User👥</h1>
            <p style="color: #8e8e93; margin-top: 5px;">Kelola status aktif dan hak akses pengguna.</p>
        </header>

        <?php if($msg): ?> <div class="alert alert-ok">✅ <?= htmlspecialchars($msg) ?></div> <?php endif; ?>
        <?php if($err): ?> <div class="alert alert-bad">❌ <?= htmlspecialchars($err) ?></div> <?php endif; ?>

        <div class="card" style="margin-bottom: 30px;">
            <h3 style="margin-top: 0; margin-bottom: 25px;"><?= $edit ? "✍️ Edit Pengguna" : "➕ Tambah User Baru" ?></h3>
            <form method="POST">
                <input type="hidden" name="id" value="<?= $edit['id'] ?? 0 ?>">
                <div class="form-grid">
                    <div class="input-group">
                        <label>Nama Lengkap</label>
                        <input type="text" name="nama" value="<?= $edit['nama'] ?? "" ?>" placeholder="Nama lengkap staf..." required>
                    </div>
                    <div class="input-group">
                        <label>Username</label>
                        <input type="text" name="username" value="<?= $edit['username'] ?? "" ?>" placeholder="Username login..." required>
                    </div>
                    <div class="input-group">
                        <label>Password <?= $edit ? "(Kosongkan jika tetap)" : "" ?></label>
                        <input type="password" name="password" placeholder="••••••••">
                    </div>
                    <div class="input-group">
                        <label>Role</label>
                        <select name="role">
                            <option value="petugas" <?= (@$edit['role'] == "petugas") ? "selected" : "" ?>>Petugas</option>
                            <option value="admin" <?= (@$edit['role'] == "admin") ? "selected" : "" ?>>Admin</option>
                            <option value="owner" <?= (@$edit['role'] == "owner") ? "selected" : "" ?>>Owner</option>
                        </select>
                    </div>
                </div>
                <div style="display: flex; align-items: center; gap: 20px;">
                    <button type="submit" name="save" class="btn-save"><?= $edit ? "Perbarui" : "Simpan" ?></button>
                    <label style="cursor: pointer; font-size: 14px; display: flex; align-items: center; gap: 5px;">
                        <input type="checkbox" name="aktif" <?= (!isset($edit) || @$edit['aktif'] == 1) ? "checked" : "" ?>> Akun Aktif
                    </label>
                    <?php if($edit): ?> <a href="user.php" style="color:#8e8e93; text-decoration:none; font-size:14px;">Batal</a> <?php endif; ?>
                </div>
            </form>
        </div>

        <div class="card">
            <table class="modern-table">
                <thead>
                    <tr>
                        <th>Status</th>
                        <th>Nama</th>
                        <th>Username</th>
                        <th>Role</th>
                        <th style="text-align: right;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($r = mysqli_fetch_assoc($qList)): ?>
                    <tr style="<?= $r['aktif'] == 0 ? 'opacity: 0.6;' : '' ?>">
                        <td>
                            <span class="badge <?= $r['aktif'] ? 'badge-aktif' : 'badge-non' ?>">
                                <?= $r['aktif'] ? "AKTIF" : "NONAKTIF" ?>
                            </span>
                        </td>
                        <td><b><?= htmlspecialchars($r['nama']) ?></b></td>
                        <td style="color: #64748b;"><?= htmlspecialchars($r['username']) ?></td>
                        <td style="text-transform: capitalize;"><?= $r['role'] ?></td>
                        <td style="text-align: right;">
                            <a href="user.php?edit=<?= $r['id'] ?>" class="btn-action btn-edit">Edit</a>
                            <?php if($r['id'] != $u['id']): ?>
                                <?php if($r['aktif'] == 1): ?>
                                    <a href="user.php?toggle_aktif=<?= $r['id'] ?>&current=1" class="btn-action btn-off" onclick="return confirm('Nonaktifkan user ini? User ini tidak akan bisa login.')">Nonaktifkan</a>
                                <?php else: ?>
                                    <a href="user.php?toggle_aktif=<?= $r['id'] ?>&current=0" class="btn-action btn-on">Aktifkan</a>
                                <?php endif; ?>
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