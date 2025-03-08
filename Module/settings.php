<?php
// Sitzung für 1 Tag einstellen
session_set_cookie_params(86400);
session_start();

// Überprüfen, ob der Benutzer angemeldet ist
if(!isset($_SESSION['name'])){
   header("Location: ../login.php");
   exit();
}

// Datenbankverbindung einbinden
include '../dbconnection.php';

// Fehler- und Erfolgsmeldungen initialisieren
$error = '';
$success = '';

// Aktuellen Benutzernamen speichern
$current_username = $_SESSION['name'];

// Aktuelle Benutzerdaten aus der Datenbank abrufen
$stmt = $conn->prepare("SELECT email FROM users WHERE name = ?");
$stmt->bind_param("s", $current_username);
$stmt->execute();
$stmt->bind_result($current_email);
$stmt->fetch();
$stmt->close();

// Profil aktualisieren
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['update_profile'])) {
        // Eingegebene Daten abrufen
        $new_username = trim($_POST['username']);
        $new_email = trim($_POST['email']);
        
        // Validierung
        if (empty($new_username) || empty($new_email)) {
            $error = "Benutzername und E-Mail dürfen nicht leer sein.";
        } else {
            // Überprüfen, ob der neue Benutzername bereits existiert
            $stmt = $conn->prepare("SELECT id FROM users WHERE name = ? AND name != ?");
            $stmt->bind_param("ss", $new_username, $current_username);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows > 0) {
                $error = "Der Benutzername ist bereits vergeben.";
            } else {
                // Aktualisierung durchführen
                $stmt = $conn->prepare("UPDATE users SET name = ?, email = ? WHERE name = ?");
                $stmt->bind_param("sss", $new_username, $new_email, $current_username);
                if ($stmt->execute()) {
                    $success = "Profil erfolgreich aktualisiert.";
                    $_SESSION['name'] = $new_username;
                    $current_username = $new_username;
                    $current_email = $new_email;
                } else {
                    $error = "Fehler beim Aktualisieren des Profils.";
                }
            }
            $stmt->close();
        }
    }
    
    // Passwort ändern
    if (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Validierung
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error = "Bitte füllen Sie alle Passwortfelder aus.";
        } elseif ($new_password != $confirm_password) {
            $error = "Die neuen Passwörter stimmen nicht überein.";
        } else {
            // Aktuelles Passwort überprüfen
            $stmt = $conn->prepare("SELECT password FROM users WHERE name = ?");
            $stmt->bind_param("s", $current_username);
            $stmt->execute();
            $stmt->bind_result($hashed_password);
            $stmt->fetch();
            $stmt->close();
            
            if (password_verify($current_password, $hashed_password)) {
                // Neues Passwort hashen und speichern
                $new_hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET password = ? WHERE name = ?");
                $stmt->bind_param("ss", $new_hashed_password, $current_username);
                if ($stmt->execute()) {
                    $success = "Passwort erfolgreich geändert.";
                } else {
                    $error = "Fehler beim Ändern des Passworts.";
                }
                $stmt->close();
            } else {
                $error = "Das aktuelle Passwort ist falsch.";
            }
        }
    }
    
    // Account löschen
    if (isset($_POST['delete_account'])) {
        // Benutzer aus der Datenbank löschen
        $stmt = $conn->prepare("DELETE FROM users WHERE name = ?");
        $stmt->bind_param("s", $current_username);
        if ($stmt->execute()) {
            session_destroy();
            header("Location: ../login.php");
            exit();
        } else {
            $error = "Fehler beim Löschen des Accounts.";
        }
        $stmt->close();
    }
}

// Schließen der Datenbankverbindung
$conn->close();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Benutzereinstellungen - SLA Schwimmen</title>
    <!-- Einbindung von CSS -->
    <link rel="stylesheet" type="text/css" href="../style.css">
    <!-- Font Awesome für Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- JavaScript-Datei -->
    <script src="../script.js" defer></script>
</head>
<body>
    <?php include '../menu.php'; ?>
    <div class="main-content">
        <header>
            <h1>Benutzereinstellungen</h1>
        </header>
        <div class="settings-container">
            <?php if($error) { echo '<p class="error">'.$error.'</p>'; } ?>
            <?php if($success) { echo '<p class="success">'.$success.'</p>'; } ?>
            
            <form method="post" action="">
                <h2>Profil aktualisieren</h2>
                <div class="form-group">
                    <label>Benutzername:</label>
                    <input type="text" name="username" value="<?php echo htmlspecialchars($current_username); ?>" required>
                </div>
                <div class="form-group">
                    <label>E-Mail:</label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($current_email); ?>" required>
                </div>
                <button type="submit" name="update_profile">Aktualisieren</button>
            </form>
            
            <form method="post" action="">
                <h2>Passwort ändern</h2>
                <div class="form-group">
                    <label>Aktuelles Passwort:</label>
                    <input type="password" name="current_password" required>
                </div>
                <div class="form-group">
                    <label>Neues Passwort:</label>
                    <input type="password" name="new_password" required>
                </div>
                <div class="form-group">
                    <label>Neues Passwort bestätigen:</label>
                    <input type="password" name="confirm_password" required>
                </div>
                <button type="submit" name="change_password">Passwort ändern</button>
            </form>
            
            <form method="post" action="">
                <h2>Account löschen</h2>
                <p class="warning">Warnung: Diese Aktion kann nicht rückgängig gemacht werden!</p>
                <button type="submit" name="delete_account" onclick="return confirm('Möchten Sie Ihren Account wirklich löschen?');">Account löschen</button>
            </form>
        </div>
    </div>
</body>
</html>
