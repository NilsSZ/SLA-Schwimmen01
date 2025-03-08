<?php
// Module/view_times.php

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

// Aktuelle Benutzer-ID abrufen
$current_user_id = $_SESSION['user_id'];

// Zeiten abrufen
$times = [];
$stmt = $conn->prepare("
    SELECT st.*, l.name AS location_name
    FROM swim_times st
    LEFT JOIN locations l ON st.location_id = l.id
    WHERE st.user_id = ?
    ORDER BY st.style ASC, st.date DESC
");
$stmt->bind_param("i", $current_user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $times[] = $row;
}
$stmt->close();

// Schließen der Datenbankverbindung
$conn->close();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Zeiten anzeigen - SLA Schwimmen</title>
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
            <h1>Meine Zeiten</h1>
        </header>
        <div class="times-container">
            <?php if (count($times) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Schwimmart</th>
                            <th>Distanz</th>
                            <th>Zeit</th>
                            <th>Datum</th>
                            <th>Ort</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($times as $time): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($time['style']); ?></td>
                                <td><?php echo $time['distance']; ?> m</td>
                                <td>
                                    <?php
                                    printf('%d:%02d,%02d', $time['time_minutes'], $time['time_seconds'], $time['time_milliseconds']);
                                    ?>
                                </td>
                                <td><?php echo date('d.m.Y', strtotime($time['date'])); ?></td>
                                <td><?php echo htmlspecialchars($time['location_name']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>Noch keine Zeiten eingetragen.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
