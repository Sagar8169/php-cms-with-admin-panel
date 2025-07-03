<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require 'config.php';

$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_input = trim($_POST['username']);
    $password = $_POST['password'];

    // Check against username OR name OR email
    $stmt = $conn->prepare("SELECT * FROM authors WHERE (username = ? OR name = ? OR email = ?) AND role = 'admin' LIMIT 1");
    $stmt->bind_param("sss", $user_input, $user_input, $user_input);
    $stmt->execute();
    $result = $stmt->get_result();
    $admin = $result->fetch_assoc();

    if ($admin && password_verify($password, $admin['password'])) {
        $_SESSION['admin'] = $admin['id'];
        header("Location: dashboard.php");
        exit;
    } else {
        $error = "❌ Invalid credentials.";
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Login - My CMS</title>
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            background-color: #f1f1f1;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }

        .login-box {
            background: white;
            padding: 30px 40px;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            width: 100%;
            max-width: 400px;
        }

        .login-box h2 {
            text-align: center;
            margin-bottom: 25px;
            color: #222;
        }

        .login-box input[type="text"],
        .login-box input[type="password"] {
            width: 100%;
            padding: 12px;
            margin-bottom: 15px;
            border-radius: 5px;
            border: 1px solid #ccc;
            font-size: 16px;
        }

        .login-box button {
            width: 100%;
            padding: 12px;
            background-color: #0073aa;
            color: white;
            border: none;
            border-radius: 5px;
            font-weight: bold;
            font-size: 16px;
            cursor: pointer;
        }

        .login-box button:hover {
            background-color: #005e8c;
        }

        .error-msg {
            color: red;
            margin-bottom: 10px;
            text-align: center;
        }

        .branding {
            text-align: center;
            font-size: 14px;
            margin-top: 20px;
            color: #999;
        }
    </style>
</head>
<body>

<div class="login-box">
    <h2>🔐 Admin Login</h2>

    <?php if (!empty($error)): ?>
        <div class="error-msg"><?= $error ?></div>
    <?php endif; ?>

    <form method="POST">
        <input type="text" name="username" placeholder="Username or Email" required>
        <input type="password" name="password" placeholder="Password" required>
        <button type="submit">Log In</button>
    </form>

    <div class="branding">
        &copy; <?= date('Y') ?> My CMS. Powered by PHP & MySQL
    </div>
</div>

</body>
</html>
