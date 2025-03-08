<?php
// Module/locations.php

// Sitzung für 1 Tag einstellen
session_set_cookie_params(86400);
session_start();

// Überprüfen, ob der Benutzer angemeldet ist
if (!isset($_SESSION['name']) || !isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// Datenbankverbindung einbinden
include '../dbconnection.php';

// Fehler- und Erfolgsmeldungen initialisieren
$error = '';
$success = '';

// Aktuelle Benutzer-ID abrufen
$current_user_id = $_SESSION['user_id'];

// Neuen Ort hinzufügen
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_location'])) {
    $name = trim($_POST['name']);
    $address = trim($_POST['address']);
    $lane_length = !empty($_POST['lane_length']) ? intval($_POST['lane_length']) : NULL;
    $lane_count = !empty($_POST['lane_count']) ? intval($_POST['lane_count']) : NULL;

    if (empty($name)) {
        $error = "Der Name des Ortes ist erforderlich.";
    } else {
        // Transaktion starten
        $conn->begin_transaction();
        try {
            // Ort in 'locations' hinzufügen
            $stmt = $conn->prepare("INSERT INTO locations (name, address, lane_length, lane_count) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssii", $name, $address, $lane_length, $lane_count);
            if (!$stmt->execute()) {
                throw new Exception("Fehler beim Hinzufügen des Ortes: " . $stmt->error);
            }
            $location_id = $stmt->insert_id;
            $stmt->close();

            // Zuordnung in 'user_locations' hinzufügen
            $stmt = $conn->prepare("INSERT INTO user_locations (user_id, location_id) VALUES (?, ?)");
            $stmt->bind_param("ii", $current_user_id, $location_id);
            if (!$stmt->execute()) {
                throw new Exception("Fehler beim Zuordnen des Ortes zum Benutzer: " . $stmt->error);
            }
            $stmt->close();

            // Transaktion abschließen
            $conn->commit();
            $success = "Ort erfolgreich hinzugefügt.";
        } catch (Exception $e) {
            // Transaktion zurücksetzen
            $conn->rollback();
            $error = $e->getMessage();
        }
    }
}

// Ort bearbeiten
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['edit_location'])) {
    $id = intval($_POST['location_id']);
    $name = trim($_POST['name']);
    $address = trim($_POST['address']);
    $lane_length = !empty($_POST['lane_length']) ? intval($_POST['lane_length']) : NULL;
    $lane_count = !empty($_POST['lane_count']) ? intval($_POST['lane_count']) : NULL;

    if (empty($name)) {
        $error = "Der Name des Ortes ist erforderlich.";
    } else {
        // Überprüfen, ob der Benutzer berechtigt ist
        $stmt = $conn->prepare("SELECT * FROM user_locations WHERE user_id = ? AND location_id = ?");
        $stmt->bind_param("ii", $current_user_id, $id);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows == 1) {
            $stmt->close();

            $stmt = $conn->prepare("UPDATE locations SET name = ?, address = ?, lane_length = ?, lane_count = ? WHERE id = ?");
            $stmt->bind_param("ssiii", $name, $address, $lane_length, $lane_count, $id);
            if ($stmt->execute()) {
                $success = "Ort erfolgreich aktualisiert.";
            } else {
                $error = "Fehler beim Aktualisieren des Ortes: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $error = "Sie sind nicht berechtigt, diesen Ort zu bearbeiten.";
            $stmt->close();
        }
    }
}

// Ort löschen
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);

    // Überprüfen, ob der Benutzer berechtigt ist
    $stmt = $conn->prepare("SELECT * FROM user_locations WHERE user_id = ? AND location_id = ?");
    $stmt->bind_param("ii", $current_user_id, $delete_id);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows == 1) {
        $stmt->close();

        // Transaktion starten
        $conn->begin_transaction();
        try {
            // Ort aus 'locations' löschen
            $stmt = $conn->prepare("DELETE FROM locations WHERE id = ?");
            $stmt->bind_param("i", $delete_id);
            if (!$stmt->execute()) {
                throw new Exception("Fehler beim Löschen des Ortes: " . $stmt->error);
            }
            $stmt->close();

            // Zuordnung aus 'user_locations' löschen (wird automatisch gelöscht aufgrund ON DELETE CASCADE)
            // Transaktion abschließen
            $conn->commit();
            $success = "Ort erfolgreich gelöscht.";
        } catch (Exception $e) {
            // Transaktion zurücksetzen
            $conn->rollback();
            $error = $e->getMessage();
        }
    } else {
        $error = "Sie sind nicht berechtigt, diesen Ort zu löschen.";
        $stmt->close();
    }
}

// Alle Orte des aktuellen Benutzers abrufen
$locations = [];
$stmt = $conn->prepare("
    SELECT l.*
    FROM locations l
    INNER JOIN user_locations ul ON l.id = ul.location_id
    WHERE ul.user_id = ?
");
$stmt->bind_param("i", $current_user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $locations[] = $row;
}
$stmt->close();

// Schließen der Datenbankverbindung
$conn->close();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Orte verwalten - SLA Schwimmen</title>
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
            <h1>Orte verwalten</h1>
        </header>
        <div class="locations-container">
            <?php if($error) { echo '<p class="error">'.$error.'</p>'; } ?>
            <?php if($success) { echo '<p class="success">'.$success.'</p>'; } ?>
            
            <!-- Formular zum Hinzufügen eines neuen Ortes -->
            <h2>Neuen Ort hinzufügen</h2>
            <form method="post" action="">
                <div class="form-group">
                    <label>Name des Ortes:</label>
                    <input type="text" name="name" required>
                </div>
                <div class="form-group">
                    <label>Anschrift des Ortes (optional):</label>
                    <input type="text" name="address">
                </div>
                <div class="form-group">
                    <label>Bahnlänge (optional):</label>
                    <input type="number" name="lane_length" min="0">
                </div>
                <div class="form-group">
                    <label>Anzahl Bahnen (optional):</label>
                    <input type="number" name="lane_count" min="0">
                </div>
                <button type="submit" name="add_location">Speichern</button>
            </form>
            
            <!-- Liste aller Orte -->
            <h2>Alle Orte</h2>
            <?php if (count($locations) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Anschrift</th>
                            <th>Bahnlänge</th>
                            <th>Anzahl Bahnen</th>
                            <th>Aktionen</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($locations as $location): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($location['name']); ?></td>
                                <td><?php echo htmlspecialchars($location['address']); ?></td>
                                <td><?php echo $location['lane_length']; ?></td>
                                <td><?php echo $location['lane_count']; ?></td>
                                <td>
                                    <a href="locations.php?edit_id=<?php echo $location['id']; ?>">Bearbeiten</a> |
                                    <a href="locations.php?delete_id=<?php echo $location['id']; ?>" onclick="return confirm('Möchten Sie diesen Ort wirklich löschen?');">Löschen</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>Keine Orte vorhanden.</p>
            <?php endif; ?>
            
            <!-- Formular zum Bearbeiten eines Ortes -->
            <?php
            if (isset($_GET['edit_id'])):
                $edit_id = intval($_GET['edit_id']);
                // Ort aus der Datenbank abrufen
                include '../dbconnection.php';
                $stmt = $conn->prepare("
                    SELECT l.*
                    FROM locations l
                    INNER JOIN user_locations ul ON l.id = ul.location_id
                    WHERE ul.user_id = ? AND l.id = ?
                ");
                $stmt->bind_param("ii", $current_user_id, $edit_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $location = $result->fetch_assoc();
                $stmt->close();
                $conn->close();
                if ($location):
            ?>
            <h2>Ort bearbeiten</h2>
            <form method="post" action="">
                <input type="hidden" name="location_id" value="<?php echo $location['id']; ?>">
                <div class="form-group">
                    <label>Name des Ortes:</label>
                    <input type="text" name="name" value="<?php echo htmlspecialchars($location['name']); ?>" required>
                </div>
                <div class="form-group">
                    <label>Anschrift des Ortes (optional):</label>
                    <input type="text" name="address" value="<?php echo htmlspecialchars($location['address']); ?>">
                </div>
                <div class="form-group">
                    <label>Bahnlänge (optional):</label>
                    <input type="number" name="lane_length" min="0" value="<?php echo $location['lane_length']; ?>">
                </div>
                <div class="form-group">
                    <label>Anzahl Bahnen (optional):</label>
                    <input type="number" name="lane_count" min="0" value="<?php echo $location['lane_count']; ?>">
                </div>
                <button type="submit" name="edit_location">Aktualisieren</button>
            </form>
            <?php
                else:
                    echo '<p class="error">Ort nicht gefunden oder Sie sind nicht berechtigt, diesen Ort zu bearbeiten.</p>';
                endif;
            endif;
            ?>
        </div>
    </div>
</body>
</html>
