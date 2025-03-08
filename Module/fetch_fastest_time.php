<?php
/**
 * fetch_fastest_time.php
 * 
 * Erwartete GET-Parameter:
 *   - user_id (int)
 *   - swim_style_id (int) // Numeric ID
 *   - distance (int)
 *   - source: 'training', 'wk' oder 'sprint'
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../dbconnection.php';

// Aus Parametern lesen:
$user_id       = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$swim_style_id = isset($_GET['swim_style_id']) ? (int)$_GET['swim_style_id'] : 0;
$distance      = isset($_GET['distance']) ? (int)$_GET['distance'] : 0;
$source        = isset($_GET['source']) ? $_GET['source'] : 'training';

$time = ''; // Rückgabewert, zunächst leer

if ($source === 'wk') {
    // 1) Schnellste Wettkampfzeit
    //    z.B. in "competition_starts(swim_time)" oder "times(WKtime=1)"
    //    Beispiel: competition_starts
    $stmt = $conn->prepare("
        SELECT swim_time
        FROM competition_starts
        WHERE swim_style_id = ?
          AND distance = ?
        ORDER BY swim_time ASC
        LIMIT 1
    ");
    $stmt->bind_param("ii", $swim_style_id, $distance);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $time = $row['swim_time'];
    }
    $stmt->close();
}
elseif ($source === 'sprint') {
    // 2) Schnellste Sprintzeit, z.B. in training_tasks.entry_time
    //    (Annahme: intensity='Sprint' und wir sortieren entry_time aufsteigend)
    $stmt = $conn->prepare("
        SELECT entry_time
        FROM training_tasks
        WHERE swim_style_id = ?
          AND distance = ?
          AND intensity='Sprint'
          AND entry_time IS NOT NULL
          AND entry_time <> ''
        ORDER BY entry_time ASC
        LIMIT 1
    ");
    $stmt->bind_param("ii", $swim_style_id, $distance);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $time = $row['entry_time'];
    }
    $stmt->close();
}
else {
    // 3) "training" (Standard): Suche in times nach WKtime=0 (reine Trainingszeit),
    //    oder WKtime=1, falls du es so brauchst. 
    //    Hier Beispiel: WKtime=0 => private Trainingszeit
    $stmt = $conn->prepare("
        SELECT time
        FROM times
        WHERE user_id = ?
          AND swim_style_id = ?
          AND distance = ?
          AND WKtime = 0
        ORDER BY time ASC
        LIMIT 1
    ");
    $stmt->bind_param("iii", $user_id, $swim_style_id, $distance);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $time = $row['time'];
    }
    $stmt->close();
}

$conn->close();

// JSON-Ausgabe
header('Content-Type: application/json');
echo json_encode(['time' => $time]);
