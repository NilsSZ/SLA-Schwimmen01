<?php
// Module/get_times.php

session_start();

// Überprüfen, ob der Benutzer angemeldet ist
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Nicht angemeldet.']);
    exit();
}

include '../dbconnection.php';

$current_user_id = $_SESSION['user_id'];

if (isset($_POST['swim_style_id']) && isset($_POST['distance'])) {
    $swim_style_id = intval($_POST['swim_style_id']);
    $distance = intval($_POST['distance']);

    $stmt = $conn->prepare("
        SELECT date_format(t.date, '%d.%m.%Y') as date_formatted, t.time
        FROM times t
        WHERE t.user_id = ? AND t.swim_style_id = ? AND t.distance = ?
        ORDER BY t.date ASC
    ");
    $stmt->bind_param("iii", $current_user_id, $swim_style_id, $distance);
    $stmt->execute();
    $result = $stmt->get_result();

    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            'date' => $row['date_formatted'],
            'time' => $row['time']
        ];
    }
    $stmt->close();
    $conn->close();

    if (count($data) > 0) {
        echo json_encode(['status' => 'success', 'data' => $data]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Keine Daten verfügbar.']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Ungültige Anfrage.']);
}
?>
