<?php
session_start();
require_once "../app/config/db.php";

$error = "";

if (isset($_POST['login'])) {

    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = mysqli_real_escape_string($conn, $_POST['password']);

    $query = mysqli_query($conn, "SELECT * FROM user WHERE username='$username' AND password='$password'");
    $user = mysqli_fetch_assoc($query);

    if ($user) {
        $_SESSION['user'] = $user;
        header("Location: dashboard.php");
        exit;
    } else {
        $error = "Username atau Password salah!";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Login Sistem Parkir</title>
    <link rel="stylesheet" href="assets/css/login.css">
</head>
<body>

<div class="login-box">
    <h2>Login Sistem Parkir</h2>

    <?php if($error): ?>
        <p class="error"><?php echo $error; ?></p>
    <?php endif; ?>

    <form method="POST">
        <input type="text" name="username" placeholder="Username" required>
        <input type="password" name="password" placeholder="Password" required>
        <button type="submit" name="login">Login</button>
    </form>
</div>

</body>
</html>