<?php
// get_distances.php

header('Content-Type: application/json');

if (isset($_GET['swim_style_id'])) {
    $swim_style_id = intval($_GET['swim_style_id']);

    // Datenbankverbindung einbinden
    require_once('../dbconnection.php'); // Pfad anpassen

    $distances = [];
    $stmt = $conn->prepare("SELECT distance FROM swim_style_distances WHERE swim_style_id = ? ORDER BY distance ASC");
    $stmt->bind_param("i", $swim_style_id);
    $stmt->execute();
    $stmt->bind_result($distance);
    while ($stmt->fetch()) {
        $distances[] = $distance;
    }
    $stmt->close();
    $conn->close();

    echo json_encode($distances);
} else {
    echo json_encode([]);
}
?>
