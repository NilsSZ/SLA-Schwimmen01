<?php
// meine_zeiten.php

// Fehleranzeige aktivieren (nur für Entwicklungszwecke)
// Entferne diese Zeilen in der Produktionsumgebung
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Session starten
session_start();

// Überprüfen, ob der Benutzer angemeldet ist
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['name'] ?? 'Benutzer';

// Datenbankverbindung einbinden
require_once('../dbconnection.php');

// Schwimmarten abrufen
$swim_styles = [];
$stmt = $conn->prepare("SELECT id, name FROM swim_styles ORDER BY name ASC");
$stmt->execute();
$stmt->bind_result($id, $name);
while ($stmt->fetch()) {
    $swim_styles[$id] = $name;
}
$stmt->close();

// Formularverarbeitung für Bearbeiten und Löschen
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['edit_entry'])) {
        // Eintrag bearbeiten
        $edit_id = intval($_POST['edit_id']);
        $date = $_POST['date'];
        $swim_style_id = intval($_POST['swim_style']);
        $distance = intval($_POST['distance']);
        $time = $_POST['time'];

        // Datenbank aktualisieren
        $stmt = $conn->prepare("UPDATE times SET date = ?, swim_style_id = ?, distance = ?, time = ? WHERE id = ? AND user_id = ?");
        $stmt->bind_param("siisii", $date, $swim_style_id, $distance, $time, $edit_id, $user_id);
        $stmt->execute();
        $stmt->close();

        // Seite neu laden
        header('Location: meine_zeiten.php');
        exit();
    } elseif (isset($_POST['delete_id'])) {
        // Eintrag löschen
        $delete_id = intval($_POST['delete_id']);

        // Aus der Datenbank löschen
        $stmt = $conn->prepare("DELETE FROM times WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $delete_id, $user_id);
        $stmt->execute();
        $stmt->close();

        // Seite neu laden
        header('Location: meine_zeiten.php');
        exit();
    }
}

// Filterwerte abrufen
$filter_swim_style = $_GET['swim_style'] ?? '';
$filter_distance = $_GET['distance'] ?? '';

// Zeiten aus der Datenbank abrufen
$query = "SELECT times.id, times.time, times.date, times.distance, swim_styles.name FROM times INNER JOIN swim_styles ON times.swim_style_id = swim_styles.id WHERE times.user_id = ?";
$params = [$user_id];
$types = 'i';

if ($filter_swim_style !== '') {
    $query .= " AND times.swim_style_id = ?";
    $params[] = $filter_swim_style;
    $types .= 'i';
}

if ($filter_distance !== '') {
    $query .= " AND times.distance = ?";
    $params[] = $filter_distance;
    $types .= 'i';
}

$query .= " ORDER BY times.date DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$stmt->bind_result($time_id, $time, $date, $distance, $swim_style_name);

$times = [];
while ($stmt->fetch()) {
    $times[] = [
        'id' => $time_id,
        'time' => $time,
        'date' => $date,
        'distance' => $distance,
        'swim_style_id' => array_search($swim_style_name, $swim_styles),
        'swim_style_name' => $swim_style_name
    ];
}
$stmt->close();

// Verbindung schließen
$conn->close();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Meine Zeiten</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Eigenes CSS -->
    <link rel="stylesheet" href="../css/style.css">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <!-- Eigene CSS-Anpassungen -->
    <style>
        body {
            padding-top: 56px;
        }
        /* Seitenmenü anpassen */
        .sidebar {
            position: fixed;
            top: 56px;
            bottom: 0;
            left: 0;
            padding: 0;
            z-index: 100;
            box-shadow: inset -1px 0 0 rgba(0, 0, 0, .1);
        }
    </style>
</head>
<body>
    <!-- Menü einbinden -->
    <?php require_once('../menu.php'); ?>

    <div class="container-fluid">
        <div class="row">
            <!-- Seitenmenü -->
            <nav class="col-md-2 d-none d-md-block bg-light sidebar">
                <!-- Seitenmenü-Inhalt -->
                <?php require_once('../menu.php'); ?>
            </nav>

            <!-- Hauptinhalt -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <h2 class="mt-4">Meine Zeiten</h2>

                <!-- Filterformular -->
                <form method="get" action="meine_zeiten.php" class="row g-3 mb-4">
                    <div class="col-md-4">
                        <label for="swim_style" class="form-label">Schwimmart</label>
                        <select name="swim_style" id="swim_style" class="form-select">
                            <option value="">Alle</option>
                            <?php foreach ($swim_styles as $id => $name): ?>
                                <option value="<?php echo $id; ?>" <?php if ($filter_swim_style == $id) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="distance" class="form-label">Distanz (m)</label>
                        <input type="number" name="distance" id="distance" class="form-control" placeholder="z.B. 100" value="<?php echo htmlspecialchars($filter_distance); ?>">
                    </div>
                    <div class="col-md-4 align-self-end">
                        <button type="submit" class="btn btn-primary">Filtern</button>
                        <a href="meine_zeiten.php" class="btn btn-secondary">Filter zurücksetzen</a>
                    </div>
                </form>

                <!-- Zeiten-Tabelle -->
                <div class="table-responsive">
                    <table class="table table-striped table-bordered">
                        <thead class="table-dark">
                            <tr>
                                <th>Datum</th>
                                <th>Schwimmart</th>
                                <th>Distanz (m)</th>
                                <th>Zeit</th>
                                <th>Aktionen</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($times) > 0): ?>
                                <?php foreach ($times as $entry): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($entry['date']); ?></td>
                                        <td><?php echo htmlspecialchars($entry['swim_style_name']); ?></td>
                                        <td><?php echo htmlspecialchars($entry['distance']); ?></td>
                                        <td><?php echo htmlspecialchars($entry['time']); ?></td>
                                        <td>
                                            <!-- Bearbeiten-Button -->
                                            <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $entry['id']; ?>">Bearbeiten</button>

                                            <!-- Löschen-Formular -->
                                            <form method="post" action="meine_zeiten.php" style="display:inline-block;">
                                                <input type="hidden" name="delete_id" value="<?php echo $entry['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Möchten Sie diesen Eintrag wirklich löschen?');">Löschen</button>
                                            </form>
                                        </td>
                                    </tr>

                                    <!-- Bearbeiten-Modal -->
                                    <div class="modal fade" id="editModal<?php echo $entry['id']; ?>" tabindex="-1" aria-labelledby="editModalLabel<?php echo $entry['id']; ?>" aria-hidden="true">
                                      <div class="modal-dialog">
                                        <div class="modal-content">
                                          <form method="post" action="meine_zeiten.php">
                                            <div class="modal-header">
                                              <h5 class="modal-title" id="editModalLabel<?php echo $entry['id']; ?>">Eintrag bearbeiten</h5>
                                              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
                                            </div>
                                            <div class="modal-body">
                                              <!-- Formularelemente -->
                                              <input type="hidden" name="edit_id" value="<?php echo $entry['id']; ?>">

                                              <div class="mb-3">
                                                  <label for="date<?php echo $entry['id']; ?>" class="form-label">Datum</label>
                                                  <input type="date" name="date" id="date<?php echo $entry['id']; ?>" class="form-control" value="<?php echo htmlspecialchars($entry['date']); ?>" required>
                                              </div>
                                              <div class="mb-3">
                                                  <label for="swim_style<?php echo $entry['id']; ?>" class="form-label">Schwimmart</label>
                                                  <select name="swim_style" id="swim_style<?php echo $entry['id']; ?>" class="form-select" required>
                                                      <?php foreach ($swim_styles as $id => $name): ?>
                                                          <option value="<?php echo $id; ?>" <?php if ($entry['swim_style_id'] == $id) echo 'selected'; ?>>
                                                              <?php echo htmlspecialchars($name); ?>
                                                          </option>
                                                      <?php endforeach; ?>
                                                  </select>
                                              </div>
                                              <div class="mb-3">
                                                  <label for="distance<?php echo $entry['id']; ?>" class="form-label">Distanz (m)</label>
                                                  <input type="number" name="distance" id="distance<?php echo $entry['id']; ?>" class="form-control" value="<?php echo htmlspecialchars($entry['distance']); ?>" required>
                                              </div>
                                              <div class="mb-3">
                                                  <label for="time<?php echo $entry['id']; ?>" class="form-label">Zeit</label>
                                                  <input type="text" name="time" id="time<?php echo $entry['id']; ?>" class="form-control" value="<?php echo htmlspecialchars($entry['time']); ?>" required>
                                              </div>
                                            </div>
                                            <div class="modal-footer">
                                              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                                              <button type="submit" name="edit_entry" class="btn btn-primary">Speichern</button>
                                            </div>
                                          </form>
                                        </div>
                                      </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center">Keine Einträge gefunden.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </main>
        </div>
    </div>

    <!-- Bootstrap JS und Popper.js -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
