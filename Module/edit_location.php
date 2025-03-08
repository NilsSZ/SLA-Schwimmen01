<?php
// Sitzung starten
session_set_cookie_params(86400);
session_start();

// Überprüfen, ob der Benutzer angemeldet ist
if(!isset($_SESSION['user_id'])){
   header("Location: ../login.php");
   exit();
}

// Datenbankverbindung einbinden
include '../dbconnection.php';

$user_id = $_SESSION['user_id'];
$location_id = intval($_GET['id']);

// Überprüfen, ob der Ort existiert und dem Benutzer gehört
$stmt = $conn->prepare("SELECT name, address, pool_length, num_lanes FROM locations WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $location_id, $user_id);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows == 0) {
    $stmt->close();
    header("Location: locations.php");
    exit();
}

$stmt->bind_result($name, $address, $pool_length, $num_lanes);
$stmt->fetch();
$stmt->close();

$error = '';
$success = '';

// Formularverarbeitung
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name']);
    $address = trim($_POST['address']);
    $pool_length = trim($_POST['pool_length']);
    $num_lanes = trim($_POST['num_lanes']);

    // Validierung
    if (empty($name)) {
        $error = "Der Name des Ortes ist erforderlich.";
    } else {
        // Update in der Datenbank
        $stmt = $conn->prepare("UPDATE locations SET name = ?, address = ?, pool_length = ?, num_lanes = ? WHERE id = ? AND user_id = ?");
        $stmt->bind_param("sssiii", $name, $address, $pool_length, $num_lanes, $location_id, $user_id);
        if ($stmt->execute()) {
            $success = "Ort erfolgreich aktualisiert.";
        } else {
            $error = "Fehler beim Aktualisieren des Ortes.";
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
    <title>Ort bearbeiten - SLA Schwimmen</title>
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
            <h1>Ort bearbeiten</h1>
        </header>
        <div class="form-container">
            <?php if($error) { echo '<p class="error">'.$error.'</p>'; } ?>
            <?php if($success) { echo '<p class="success">'.$success.'</p>'; } ?>
            
            <form method="post" action="">
                <div class="form-group">
                    <label>Name des Ortes:</label>
                    <input type="text" name="name" value="<?php echo htmlspecialchars($name); ?>" required>
                </div>
                <div class="form-group">
                    <label>Anschrift des Ortes (optional):</label>
                    <input type="text" name="address" value="<?php echo htmlspecialchars($address); ?>">
                </div>
                <div class="form-group">
                    <label>Bahnlänge (optional):</label>
                    <input type="text" name="pool_length" value="<?php echo htmlspecialchars($pool_length); ?>">
                </div>
                <div class="form-group">
                    <label>Anzahl Bahnen (optional):</label>
                    <input type="number" name="num_lanes" value="<?php echo htmlspecialchars($num_lanes); ?>">
                </div>
                <button type="submit">Aktualisieren</button>
            </form>
        </div>
    </div>
</body>
</html>
