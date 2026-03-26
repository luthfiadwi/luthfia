<?php
if (session_status() === PHP_SESSION_NONE) session_start();

function require_role($role)
{
    if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== $role) {
        header("Location: /parkir-ilut/public/dashboard.php");
        exit;
    }
}