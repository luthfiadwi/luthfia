<?php
session_start();
require_once "../app/middleware/auth.php";

$role = $_SESSION['user']['role'];

if ($role === 'admin') {
    header("Location: admin/index.php");
    exit;
}

if ($role === 'petugas') {
    header("Location: petugas/index.php");
    exit;
}

if ($role === 'owner') {
    header("Location: owner/index.php");
    exit;
}

echo "Role tidak dikenali.";