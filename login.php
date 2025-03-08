<?php
// login.php

session_set_cookie_params(86400);
session_start();
include 'dbconnection.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name']);
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT id, password FROM users WHERE name = ?");
    $stmt->bind_param("s", $name);
    $stmt->execute();
    $stmt->bind_result($user_id, $hashed_password);
    if ($stmt->fetch()) {
        if (password_verify($password, $hashed_password)) {
            $_SESSION['name'] = $name;
            $_SESSION['user_id'] = $user_id;
            header("Location: dashboard.php");
            exit();
        } else {
            $error = "Ungültiger Name oder Passwort.";
        }
    } else {
        $error = "Ungültiger Name oder Passwort.";
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Login - SLA Schwimmen</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700&family=Poppins:wght@300;500;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #0278AE, #51ADCF, #A5D8FF);
            background-size: 400% 400%;
            animation: bgAnim 15s ease infinite;
            margin: 0;
            padding: 0;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        @keyframes bgAnim {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        .login-container {
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 40px 30px;
            width: 100%;
            max-width: 380px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.15);
            text-align: center;
            animation: fadeIn 0.8s ease both;
        }
        @keyframes fadeIn {
            0% { opacity: 0; transform: scale(0.9); }
            100% { opacity: 1; transform: scale(1); }
        }
        .login-logo img {
            width: 100px;
            border-radius: 10px;
            margin-bottom: 15px;
        }
        .login-title {
            font-family: 'Montserrat', sans-serif;
            font-weight: 700;
            font-size: 26px;
            color: #333;
            margin-bottom: 20px;
        }
        .error {
            color: #d9534f;
            margin-bottom: 15px;
            font-size: 14px;
            font-weight: 600;
        }
        .form-control {
            border-radius: 10px;
            height: 50px;
            font-size: 16px;
            margin-bottom: 15px;
        }
        .btn-custom {
            background: #0278AE;
            color: #fff;
            border: none;
            border-radius: 10px;
            padding: 14px;
            width: 100%;
            font-weight: 600;
            font-size: 16px;
            box-shadow: 0 5px 15px rgba(2,120,174,0.3);
            transition: all 0.3s ease;
        }
        .btn-custom:hover {
            background: #01648e;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(2,120,174,0.4);
        }
        .footer-link {
            margin-top: 20px;
            font-size: 14px;
        }
        .footer-link a {
            color: #0278AE;
            text-decoration: none;
            font-weight: 500;
        }
        .footer-link a:hover {
            color: #01648e;
            text-decoration: underline;
        }
        .brand-subtitle {
            font-size: 14px;
            color: #555;
            margin-bottom: 30px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-logo">
            <img src="src/Bild1.jpg" alt="Logo">
        </div>
        <h2 class="login-title">SLA Schwimmen</h2>
        <div class="brand-subtitle">Tauche ein in deine Performance</div>
        <form method="post" action="">
            <?php if (isset($error)) { echo '<p class="error">' . htmlspecialchars($error) . '</p>'; } ?>
            <input type="text" name="name" class="form-control" placeholder="Benutzername" required>
            <input type="password" name="password" class="form-control" placeholder="Passwort" required>
            <button type="submit" class="btn btn-custom">Login</button>
        </form>
        <div class="footer-link">
            <a href="#">Passwort vergessen?</a>
        </div>
    </div>
</body>
</html>
