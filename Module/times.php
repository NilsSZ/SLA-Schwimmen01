<?php
// Module/times.php

// Fehleranzeige aktivieren (für Debugging)
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Start der Session
session_start();

// Überprüfen, ob der Benutzer angemeldet ist
if (!isset($_SESSION['name']) || !isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// Datenbankverbindung einbinden
include '../dbconnection.php';

// Aktuelle Benutzer-ID abrufen
$current_user_id = $_SESSION['user_id'];

// Fehler- und Erfolgsmeldungen initialisieren
$error = '';
$success = '';

// Schwimmarten abrufen
$swim_styles = [];
$result = $conn->query("SELECT * FROM swim_styles");
while ($row = $result->fetch_assoc()) {
    $swim_styles[$row['id']] = $row['name'];
}
$result->close();

// Orte abrufen (optional)
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

// Neuen Eintrag hinzufügen
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_time'])) {
    $swim_style_id = intval($_POST['swim_style']);
    $distance = intval($_POST['distance']);
    $time = trim($_POST['time']);
    $location_id = !empty($_POST['location']) ? intval($_POST['location']) : NULL;

    if (empty($time)) {
        $error = "Die geschwommene Zeit ist erforderlich.";
    } else {
        $stmt = $conn->prepare("INSERT INTO times (user_id, swim_style_id, distance, time, location_id) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("iiisi", $current_user_id, $swim_style_id, $distance, $time, $location_id);
        if ($stmt->execute()) {
            $success = "Zeit erfolgreich hinzugefügt.";
        } else {
            $error = "Fehler beim Hinzufügen der Zeit: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Eintrag bearbeiten
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['edit_time'])) {
    $time_id = intval($_POST['time_id']);
    $swim_style_id = intval($_POST['swim_style']);
    $distance = intval($_POST['distance']);
    $time = trim($_POST['time']);
    $location_id = !empty($_POST['location']) ? intval($_POST['location']) : NULL;

    if (empty($time)) {
        $error = "Die geschwommene Zeit ist erforderlich.";
    } else {
        // Überprüfen, ob der Benutzer berechtigt ist
        $stmt = $conn->prepare("SELECT id FROM times WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $time_id, $current_user_id);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows == 1) {
            $stmt->close();

            $stmt = $conn->prepare("UPDATE times SET swim_style_id = ?, distance = ?, time = ?, location_id = ? WHERE id = ?");
            $stmt->bind_param("iisii", $swim_style_id, $distance, $time, $location_id, $time_id);
            if ($stmt->execute()) {
                $success = "Zeit erfolgreich aktualisiert.";
            } else {
                $error = "Fehler beim Aktualisieren der Zeit: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $error = "Sie sind nicht berechtigt, diesen Eintrag zu bearbeiten.";
            $stmt->close();
        }
    }
}

// Eintrag löschen
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);

    // Überprüfen, ob der Benutzer berechtigt ist
    $stmt = $conn->prepare("SELECT id FROM times WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $delete_id, $current_user_id);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows == 1) {
        $stmt->close();

        $stmt = $conn->prepare("DELETE FROM times WHERE id = ?");
        $stmt->bind_param("i", $delete_id);
        if ($stmt->execute()) {
            $success = "Eintrag erfolgreich gelöscht.";
        } else {
            $error = "Fehler beim Löschen des Eintrags: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $error = "Sie sind nicht berechtigt, diesen Eintrag zu löschen.";
        $stmt->close();
    }
}

// Statistiken abrufen
// Schnellste Zeiten pro Schwimmart abrufen
$fastest_times = [];
foreach ($swim_styles as $style_id => $style_name) {
    $stmt = $conn->prepare("
        SELECT time, distance
        FROM times
        WHERE user_id = ? AND swim_style_id = ?
        ORDER BY STR_TO_DATE(REPLACE(time, ',', '.'), '%i:%s.%f') ASC
        LIMIT 1
    ");
    $stmt->bind_param("ii", $current_user_id, $style_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $fastest_time = $result->fetch_assoc();
    if ($fastest_time) {
        $fastest_times[$style_name] = $fastest_time;
    }
    $stmt->close();
}

// Alle Schwimmzeiten abrufen (mit optionalen Filtern)
$conditions = [];
$params = [];
$param_types = '';

if (isset($_GET['filter'])) {
    if (!empty($_GET['filter_swim_style'])) {
        $conditions[] = 't.swim_style_id = ?';
        $params[] = intval($_GET['filter_swim_style']);
        $param_types .= 'i';
    }
    if (!empty($_GET['filter_distance'])) {
        $conditions[] = 't.distance = ?';
        $params[] = intval($_GET['filter_distance']);
        $param_types .= 'i';
    }
    if (!empty($_GET['filter_location'])) {
        $conditions[] = 't.location_id = ?';
        $params[] = intval($_GET['filter_location']);
        $param_types .= 'i';
    }
    if (!empty($_GET['filter_date_from']) && !empty($_GET['filter_date_to'])) {
        $conditions[] = 'DATE(t.date) BETWEEN ? AND ?';
        $params[] = $_GET['filter_date_from'];
        $params[] = $_GET['filter_date_to'];
        $param_types .= 'ss';
    }
}

$sql = "
    SELECT t.*, s.name AS swim_style_name, l.name AS location_name
    FROM times t
    INNER JOIN swim_styles s ON t.swim_style_id = s.id
    LEFT JOIN locations l ON t.location_id = l.id
    WHERE t.user_id = ?
";

$params = array_merge([$current_user_id], $params);
$param_types = 'i' . $param_types;

if ($conditions) {
    $sql .= ' AND ' . implode(' AND ', $conditions);
}

$sql .= ' ORDER BY t.date DESC';

$stmt = $conn->prepare($sql);

// Fehlerbehandlung für die prepare-Methode
if ($stmt === false) {
    die('Prepare failed: ' . htmlspecialchars($conn->error));
}

// Bindet die Parameter nur, wenn es welche gibt
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();
$all_times = [];
while ($row = $result->fetch_assoc()) {
    $all_times[] = $row;
}
$stmt->close();

// Datenbankverbindung bleibt offen, damit sie später noch verwendet werden kann
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Schwimmzeiten - SLA Schwimmen</title>
    <!-- Font Awesome für Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- jQuery für AJAX -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Eigenes JavaScript -->
    <script src="../script.js" defer></script>
    <style>
        /* Integriertes CSS für das Modul */

        /* Allgemeine Einstellungen */
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: 'Helvetica Neue', Arial, sans-serif;
            background-color: #f0f2f5;
            color: #333;
        }

        a {
            text-decoration: none;
            color: inherit;
        }

        /* Menü-Icon für mobile Ansicht */
        .menu-toggle {
            position: fixed;
            top: 15px;
            left: 15px;
            font-size: 24px;
            color: #004080;
            cursor: pointer;
            z-index: 1000;
            transition: opacity 0.3s;
        }

        /* Menü-Icon ausblenden, wenn Sidebar offen ist (Desktop-Ansicht) */
        @media screen and (min-width: 769px) {
            .menu-toggle {
                display: none;
            }
        }

        /* Sidebar-Menü */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 250px;
            height: 100%;
            background-color: #004080;
            color: #fff;
            transition: transform 0.3s ease;
            overflow-y: auto;
            z-index: 999;
        }

        .sidebar.closed {
            transform: translateX(-250px);
        }

        /* Wenn Sidebar geschlossen ist, Menü-Icon anzeigen */
        .sidebar.closed ~ .menu-toggle {
            display: block;
        }

        .sidebar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            background-color: #003366;
        }

        .sidebar-header h3 {
            margin: 0;
            font-size: 24px;
        }

        #close-btn {
            cursor: pointer;
        }

        .sidebar ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .sidebar ul li {
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar ul li a {
            display: block;
            color: #fff;
            text-decoration: none;
            padding: 15px;
            transition: background 0.3s;
        }

        .sidebar ul li a:hover {
            background-color: rgba(0, 0, 0, 0.1);
        }

        .sidebar ul li a i {
            margin-right: 10px;
        }

        /* Hauptinhalt */
        .main-content {
            margin-left: 250px;
            padding: 20px;
            transition: margin-left 0.3s ease;
        }

        .sidebar.closed ~ .main-content {
            margin-left: 0;
        }

        header {
            background-color: #fff;
            padding: 20px;
            margin-bottom: 20px;
            border-bottom: 2px solid #004080;
        }

        header h1 {
            margin: 0;
            font-size: 28px;
            color: #004080;
        }

        /* Dashboard-Widgets */
        .dashboard-widgets {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 40px;
        }

        .widget {
            background-color: #fff;
            flex: 1 1 calc(33.333% - 20px);
            padding: 30px;
            border-radius: 8px;
            border-left: 5px solid #004080;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            max-width: calc(33.333% - 20px);
            display: flex;
            align-items: center;
        }

        .widget-icon {
            font-size: 50px;
            color: #004080;
            margin-right: 20px;
        }

        .widget-content h3 {
            margin: 0;
            margin-bottom: 10px;
            color: #333;
            font-size: 22px;
        }

        .widget-content p {
            font-size: 18px;
            margin: 0;
            color: #666;
        }

        /* Kacheln */
        .tiles {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 40px;
        }

        .tile {
            background-color: #fff;
            flex: 1 1 calc(50% - 20px);
            padding: 60px;
            border-radius: 8px;
            text-align: center;
            color: #004080;
            font-size: 28px;
            font-weight: bold;
            position: relative;
            overflow: hidden;
            transition: background-color 0.3s;
            max-width: calc(50% - 20px);
        }

        .tile:hover {
            background-color: #f0f0f0;
        }

        .tile i {
            font-size: 70px;
            margin-bottom: 20px;
        }

        .tile a {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            color: inherit;
            text-decoration: none;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        /* Formulare */
        .form-container,
        .filter-container {
            background-color: #fff;
            padding: 30px;
            border-radius: 8px;
            margin-bottom: 40px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .form-container h2,
        .filter-container h2 {
            margin-bottom: 30px;
            color: #004080;
            text-align: center;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            font-weight: bold;
            display: block;
            margin-bottom: 8px;
            color: #333;
        }

        .form-group input[type="text"],
        .form-group input[type="number"],
        .form-group input[type="date"],
        .form-group select {
            width: 100%;
            padding: 14px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 16px;
        }

        .form-group select {
            appearance: none;
            background-image: url('data:image/svg+xml;charset=US-ASCII,<svg xmlns="http://www.w3.org/2000/svg" width="4" height="5" viewBox="0 0 4 5"><path fill="%23333" d="M2 0L0 2h4z"/></svg>');
            background-repeat: no-repeat;
            background-position: right 10px top 50%;
            background-size: 10px 10px;
        }

        .form-group input[type="text"]:focus,
        .form-group input[type="number"]:focus,
        .form-group input[type="date"]:focus,
        .form-group select:focus {
            border-color: #004080;
            outline: none;
        }

        .form-group button {
            padding: 12px 20px;
            background-color: #004080;
            color: #fff;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
        }

        .form-group button:hover {
            background-color: #0059b3;
        }

        /* Meldungen */
        .success {
            color: green;
            margin-bottom: 15px;
            font-weight: bold;
            text-align: center;
        }

        .error {
            color: red;
            margin-bottom: 15px;
            font-weight: bold;
            text-align: center;
        }

        /* Tabellen */
        .times-table {
            background-color: #fff;
            padding: 30px;
            border-radius: 8px;
            margin-bottom: 40px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .times-table h2 {
            margin-bottom: 20px;
            color: #004080;
        }

        .times-table table {
            width: 100%;
            border-collapse: collapse;
        }

        .times-table th, .times-table td {
            padding: 12px 15px;
            border: 1px solid #ddd;
            text-align: center;
        }

        .times-table th {
            background-color: #f4f4f4;
        }

        .times-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        .times-table td a {
            margin: 0 5px;
            color: #004080;
        }

        .times-table td a:hover {
            color: #0059b3;
        }

        /* Pagination */
        .pagination {
            text-align: center;
            margin-bottom: 40px;
        }

        .pagination a {
            display: inline-block;
            padding: 10px 15px;
            margin: 0 5px;
            background-color: #f4f4f4;
            color: #333;
            text-decoration: none;
            border-radius: 5px;
        }

        .pagination a.active {
            background-color: #004080;
            color: #fff;
        }

        /* Responsives Design */
        @media screen and (max-width: 1200px) {
            .widget, .tile {
                flex: 1 1 calc(50% - 20px);
                max-width: calc(50% - 20px);
            }
        }

        @media screen and (max-width: 768px) {
            .dashboard-widgets, .tiles {
                flex-direction: column;
            }

            .main-content {
                margin-left: 0;
            }

            .sidebar {
                transform: translateX(-250px);
            }

            .sidebar.closed {
                transform: translateX(-250px);
            }

            .sidebar.closed ~ .menu-toggle {
                display: block;
            }

            .menu-toggle {
                display: block;
            }
        }

    </style>
</head>
<body>
    <?php include '../menu.php'; ?>
    <div class="main-content">
        <!-- Hauptinhalt der Seite -->
        <header>
            <h1>Schwimmzeiten</h1>
        </header>

        <!-- Dashboard-Widgets -->
        <div class="dashboard-widgets">
            <!-- Widget für Gesamtanzahl der Schwimmzeiten -->
            <div class="widget">
                <div class="widget-icon">
                    <i class="fas fa-swimmer"></i>
                </div>
                <div class="widget-content">
                    <h3>Gesamtzeiten</h3>
                    <p>
                        <?php
                        $total_times = count($all_times);
                        echo $total_times;
                        ?>
                    </p>
                </div>
            </div>

            <!-- Widget für schnellste Zeit -->
            <div class="widget">
                <div class="widget-icon">
                    <i class="fas fa-tachometer-alt"></i>
                </div>
                <div class="widget-content">
                    <h3>Schnellste Zeit</h3>
                    <p>
                        <?php
                        // Schnellste Zeit insgesamt abrufen
                        $stmt = $conn->prepare("
                            SELECT t.time, s.name AS swim_style_name
                            FROM times t
                            INNER JOIN swim_styles s ON t.swim_style_id = s.id
                            WHERE t.user_id = ?
                            ORDER BY STR_TO_DATE(REPLACE(t.time, ',', '.'), '%i:%s.%f') ASC
                            LIMIT 1
                        ");
                        $stmt->bind_param("i", $current_user_id);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $fastest_overall = $result->fetch_assoc();
                        $stmt->close();

                        if ($fastest_overall) {
                            echo htmlspecialchars($fastest_overall['time']) . " (" . htmlspecialchars($fastest_overall['swim_style_name']) . ")";
                        } else {
                            echo "N/A";
                        }
                        ?>
                    </p>
                </div>
            </div>

            <!-- Widget für zuletzt hinzugefügte Zeit -->
            <div class="widget">
                <div class="widget-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="widget-content">
                    <h3>Letzte Zeit</h3>
                    <p>
                        <?php
                        if (!empty($all_times)) {
                            echo htmlspecialchars($all_times[0]['time']) . " (" . htmlspecialchars($all_times[0]['swim_style_name']) . ")";
                        } else {
                            echo "N/A";
                        }
                        ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- Kacheln -->
        <div class="tiles">
            <!-- Kachel zum Hinzufügen einer neuen Zeit -->
            <div class="tile">
                <a href="#add-time">
                    <i class="fas fa-plus-circle"></i>
                    <span>Neue Zeit hinzufügen</span>
                </a>
            </div>

            <!-- Kachel zum Anzeigen aller Zeiten -->
            <div class="tile">
                <a href="#all-times">
                    <i class="fas fa-list"></i>
                    <span>Alle Zeiten anzeigen</span>
                </a>
            </div>
        </div>

        <!-- Formular zum Hinzufügen einer neuen Zeit -->
        <div class="form-container" id="add-time">
            <h2><i class="fas fa-plus-circle"></i> Neue Schwimmzeit hinzufügen</h2>
            <?php if($error) { echo '<p class="error">'.$error.'</p>'; } ?>
            <?php if($success) { echo '<p class="success">'.$success.'</p>'; } ?>
            <form method="post" action="">
                <div class="form-group">
                    <label for="swim_style">Schwimmart:</label>
                    <div class="custom-select">
                        <select name="swim_style" id="swim_style" required>
                            <option value="">-- Wählen Sie eine Schwimmart --</option>
                            <?php foreach ($swim_styles as $id => $name): ?>
                                <option value="<?php echo $id; ?>"><?php echo htmlspecialchars($name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label for="distance">Distanz:</label>
                    <div class="custom-select">
                        <select name="distance" id="distance" required>
                            <option value="">-- Wählen Sie zuerst eine Schwimmart --</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label for="time">Zeit (MM:SS,MS):</label>
                    <input type="text" name="time" id="time" placeholder="0:35,56" required>
                </div>
                <div class="form-group">
                    <label for="location">Ort (optional):</label>
                    <div class="custom-select">
                        <select name="location" id="location">
                            <option value="">-- Wählen Sie einen Ort --</option>
                            <?php foreach ($locations as $location): ?>
                                <option value="<?php echo $location['id']; ?>"><?php echo htmlspecialchars($location['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <button type="submit" name="add_time"><i class="fas fa-save"></i> Speichern</button>
                </div>
            </form>
        </div>

        <!-- Filterformular -->
        <div class="filter-container">
            <h2><i class="fas fa-filter"></i> Filter</h2>
            <form method="get" action="">
                <div class="form-group">
                    <label for="filter_swim_style">Schwimmart:</label>
                    <div class="custom-select">
                        <select name="filter_swim_style" id="filter_swim_style">
                            <option value="">-- Alle --</option>
                            <?php foreach ($swim_styles as $id => $name): ?>
                                <option value="<?php echo $id; ?>" <?php if (isset($_GET['filter_swim_style']) && $_GET['filter_swim_style'] == $id) echo 'selected'; ?>><?php echo htmlspecialchars($name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label for="filter_distance">Distanz:</label>
                    <input type="number" name="filter_distance" id="filter_distance" value="<?php echo isset($_GET['filter_distance']) ? intval($_GET['filter_distance']) : ''; ?>">
                </div>
                <div class="form-group">
                    <label for="filter_location">Ort:</label>
                    <div class="custom-select">
                        <select name="filter_location" id="filter_location">
                            <option value="">-- Alle --</option>
                            <?php foreach ($locations as $location): ?>
                                <option value="<?php echo $location['id']; ?>" <?php if (isset($_GET['filter_location']) && $_GET['filter_location'] == $location['id']) echo 'selected'; ?>><?php echo htmlspecialchars($location['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label>Datumsspanne:</label>
                    <input type="date" name="filter_date_from" value="<?php echo isset($_GET['filter_date_from']) ? $_GET['filter_date_from'] : ''; ?>">
                    <span>bis</span>
                    <input type="date" name="filter_date_to" value="<?php echo isset($_GET['filter_date_to']) ? $_GET['filter_date_to'] : ''; ?>">
                </div>
                <div class="form-group">
                    <button type="submit" name="filter"><i class="fas fa-filter"></i> Filtern</button>
                </div>
            </form>
        </div>

        <!-- Tabelle mit allen Schwimmzeiten -->
        <div class="times-table" id="all-times">
            <h2><i class="fas fa-list"></i> Alle Schwimmzeiten</h2>
            <?php if (count($all_times) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Datum</th>
                            <th>Schwimmart</th>
                            <th>Distanz</th>
                            <th>Zeit</th>
                            <th>Ort</th>
                            <th>Aktionen</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_times as $time): ?>
                            <tr>
                                <td><?php echo date('d.m.Y', strtotime($time['date'])); ?></td>
                                <td><?php echo htmlspecialchars($time['swim_style_name']); ?></td>
                                <td><?php echo $time['distance']; ?> m</td>
                                <td><?php echo htmlspecialchars($time['time']); ?></td>
                                <td><?php echo htmlspecialchars($time['location_name']); ?></td>
                                <td>
                                    <a href="?edit_id=<?php echo $time['id']; ?>"><i class="fas fa-edit"></i></a>
                                    <a href="?delete_id=<?php echo $time['id']; ?>" onclick="return confirm('Möchten Sie diesen Eintrag wirklich löschen?');"><i class="fas fa-trash-alt"></i></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>Keine Schwimmzeiten gefunden.</p>
            <?php endif; ?>
        </div>

        <!-- Formular zum Bearbeiten einer Zeit -->
        <?php
        if (isset($_GET['edit_id'])):
            $edit_id = intval($_GET['edit_id']);
            // Überprüfen, ob der Benutzer berechtigt ist
            $stmt = $conn->prepare("SELECT * FROM times WHERE id = ? AND user_id = ?");
            $stmt->bind_param("ii", $edit_id, $current_user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $time_entry = $result->fetch_assoc();
            $stmt->close();

            if ($time_entry):
        ?>
        <div class="form-container">
            <h2><i class="fas fa-edit"></i> Schwimmzeit bearbeiten</h2>
            <form method="post" action="">
                <input type="hidden" name="time_id" value="<?php echo $time_entry['id']; ?>">
                <div class="form-group">
                    <label for="swim_style">Schwimmart:</label>
                    <div class="custom-select">
                        <select name="swim_style" id="edit_swim_style" required>
                            <?php foreach ($swim_styles as $id => $name): ?>
                                <option value="<?php echo $id; ?>" <?php if ($id == $time_entry['swim_style_id']) echo 'selected'; ?>><?php echo htmlspecialchars($name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label for="distance">Distanz:</label>
                    <div class="custom-select">
                        <select name="distance" id="edit_distance" required>
                            <!-- Distanzen werden per AJAX geladen -->
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label for="time">Zeit (MM:SS,MS):</label>
                    <input type="text" name="time" id="time" value="<?php echo htmlspecialchars($time_entry['time']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="location">Ort (optional):</label>
                    <div class="custom-select">
                        <select name="location" id="location">
                            <option value="">-- Wählen Sie einen Ort --</option>
                            <?php foreach ($locations as $location): ?>
                                <option value="<?php echo $location['id']; ?>" <?php if ($location['id'] == $time_entry['location_id']) echo 'selected'; ?>><?php echo htmlspecialchars($location['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <button type="submit" name="edit_time"><i class="fas fa-save"></i> Aktualisieren</button>
                </div>
            </form>
        </div>
        <!-- Distanzen für das Bearbeitungsformular laden -->
        <script>
            $(document).ready(function() {
                // Distanzen für die ausgewählte Schwimmart laden
                var swimStyleId = $('#edit_swim_style').val();
                var selectedDistance = <?php echo $time_entry['distance']; ?>;
                loadDistancesEdit(swimStyleId, selectedDistance);

                $('#edit_swim_style').change(function() {
                    var swimStyleId = $(this).val();
                    loadDistancesEdit(swimStyleId, null);
                });

                function loadDistancesEdit(swimStyleId, selectedDistance) {
                    if (swimStyleId) {
                        $.ajax({
                            url: 'get_distances.php',
                            type: 'POST',
                            data: {swim_style_id: swimStyleId},
                            success: function(data) {
                                $('#edit_distance').html(data);
                                if (selectedDistance) {
                                    $('#edit_distance').val(selectedDistance);
                                }
                            }
                        });
                    } else {
                        $('#edit_distance').html('<option value="">-- Wählen Sie zuerst eine Schwimmart --</option>');
                    }
                }
            });
        </script>
        <?php
            else:
                echo '<p class="error">Eintrag nicht gefunden oder Sie sind nicht berechtigt, diesen Eintrag zu bearbeiten.</p>';
            endif;
        endif;

        // Datenbankverbindung schließen
        $conn->close();
        ?>
    </div>

    <!-- JavaScript für die dynamische Distanz-Auswahl -->
    <script>
        $(document).ready(function() {
            $('#swim_style').change(function() {
                var swimStyleId = $(this).val();
                if (swimStyleId) {
                    $.ajax({
                        url: 'get_distances.php',
                        type: 'POST',
                        data: {swim_style_id: swimStyleId},
                        success: function(data) {
                            $('#distance').html(data);
                        }
                    });
                } else {
                    $('#distance').html('<option value="">-- Wählen Sie zuerst eine Schwimmart --</option>');
                }
            });
        });
    </script>
</body>
</html>
