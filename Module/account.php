<?php
// account.php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Nur eingeloggte Nutzer dürfen diese Seite sehen
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'dbconnection.php';

$user_id = $_SESSION['user_id'];
$error   = "";
$success = "";

// Verarbeite Formularübermittlungen
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Account-Informationen aktualisieren
    if (isset($_POST['update_account'])) {
        $new_username = trim($_POST['username'] ?? "");
        $new_email    = trim($_POST['email'] ?? "");

        if (empty($new_username)) {
            $error = "Der Benutzername darf nicht leer sein.";
        } elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
            $error = "Bitte geben Sie eine gültige E-Mail-Adresse ein.";
        } else {
            $stmt = $conn->prepare("UPDATE users SET name = ?, email = ? WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param("ssi", $new_username, $new_email, $user_id);
                if ($stmt->execute()) {
                    $success = "Account erfolgreich aktualisiert.";
                    $_SESSION['name'] = $new_username;
                } else {
                    $error = "Fehler: " . $stmt->error;
                }
                $stmt->close();
            } else {
                $error = "Datenbankfehler: " . $conn->error;
            }
        }
    }
    // 2. Passwort ändern
    elseif (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'] ?? "";
        $new_password     = $_POST['new_password'] ?? "";
        $confirm_password = $_POST['confirm_password'] ?? "";
        
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error = "Bitte alle Passwortfelder ausfüllen.";
        } elseif ($new_password !== $confirm_password) {
            $error = "Die neuen Passwörter stimmen nicht überein.";
        } else {
            // Überprüfe das aktuelle Passwort
            $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $stmt->bind_result($hashed_password);
            if ($stmt->fetch()) {
                if (password_verify($current_password, $hashed_password)) {
                    $stmt->close();
                    $new_hashed = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt2 = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $stmt2->bind_param("si", $new_hashed, $user_id);
                    if ($stmt2->execute()) {
                        $success = "Passwort erfolgreich geändert.";
                    } else {
                        $error = "Fehler beim Aktualisieren des Passworts: " . $stmt2->error;
                    }
                    $stmt2->close();
                } else {
                    $error = "Aktuelles Passwort ist falsch.";
                    $stmt->close();
                }
            } else {
                $error = "Benutzer nicht gefunden.";
                $stmt->close();
            }
        }
    }
}

// Aktuelle Nutzerdaten abrufen
$stmt = $conn->prepare("SELECT name, email FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($current_username, $current_email);
$stmt->fetch();
$stmt->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <title>Account Einstellungen – SLA-Schwimmen</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- Bootstrap 5 CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Bootstrap Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
  <style>
    /* Neuer Header mit Transparenz und modernem Look */
    header {
      background: rgba(0, 38, 77, 0.85);
      padding: 20px 0;
      position: fixed;
      top: 0;
      width: 100%;
      z-index: 1000;
    }
    header .container {
      display: flex;
      align-items: center;
      justify-content: space-between;
    }
    header .logo {
      font-family: 'Montserrat', sans-serif;
      font-size: 1.8rem;
      color: #fff;
      font-weight: 700;
    }
    header nav a {
      color: #fff;
      margin-left: 20px;
      text-decoration: none;
      font-weight: 500;
    }
    /* Seiten-Layout */
    body {
      padding-top: 80px;
      background: #f8f9fa;
    }
    .account-container {
      max-width: 600px;
      margin: 20px auto;
    }
    .card {
      border-radius: 10px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
      padding: 30px;
      background: #fff;
    }
    h2.section-title {
      margin-bottom: 20px;
      font-family: 'Montserrat', sans-serif;
      color: #00324d;
    }
    .form-label {
      font-weight: 600;
    }
    .btn-custom {
      background-color: #0278AE;
      color: #fff;
      border: none;
      border-radius: 8px;
      padding: 12px 20px;
      font-weight: 600;
      transition: background 0.3s ease;
    }
    .btn-custom:hover {
      background-color: #01648e;
    }
    .alert {
      margin-top: 10px;
    }
  </style>
</head>
<body>
  <!-- Neuer Header -->
  <header>
    <div class="container">
      <div class="logo"><i class="bi bi-droplet-fill"></i> SLA-Schwimmen</div>
      <nav>
        <a href="dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
        <a href="account.php"><i class="bi bi-person-circle"></i> Mein Account</a>
        <a href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
      </nav>
    </div>
  </header>

  <div class="account-container">
    <div class="card mb-4">
      <h2 class="section-title">Account Informationen</h2>
      <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
      <?php endif; ?>
      <?php if (!empty($success)): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
      <?php endif; ?>
      <form method="post" action="account.php">
        <div class="mb-3">
          <label for="username" class="form-label">Benutzername</label>
          <input type="text" id="username" name="username" class="form-control" value="<?php echo htmlspecialchars($current_username); ?>" required>
        </div>
        <div class="mb-3">
          <label for="email" class="form-label">E‑Mail-Adresse</label>
          <input type="email" id="email" name="email" class="form-control" value="<?php echo htmlspecialchars($current_email); ?>" required>
        </div>
        <button type="submit" name="update_account" class="btn btn-custom w-100">Änderungen speichern</button>
      </form>
    </div>

    <div class="card">
      <h2 class="section-title">Passwort ändern</h2>
      <form method="post" action="account.php">
        <div class="mb-3">
          <label for="current_password" class="form-label">Aktuelles Passwort</label>
          <input type="password" id="current_password" name="current_password" class="form-control" required>
        </div>
        <div class="mb-3">
          <label for="new_password" class="form-label">Neues Passwort</label>
          <input type="password" id="new_password" name="new_password" class="form-control" required>
        </div>
        <div class="mb-3">
          <label for="confirm_password" class="form-label">Neues Passwort bestätigen</label>
          <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
        </div>
        <button type="submit" name="change_password" class="btn btn-custom w-100">Passwort ändern</button>
      </form>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
