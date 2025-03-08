<?php
// livetiming_update.php

require_once 'dbconnection.php';

if (isset($_GET['session_id'])) {
    $session_id = intval($_GET['session_id']);
    $last_timestamp = isset($_GET['timestamp']) ? intval($_GET['timestamp']) : 0;

    // Überprüfen, ob es Updates gibt
    $stmt = $conn->prepare("SELECT MAX(UNIX_TIMESTAMP(updated_at)) as last_update FROM competition_starts WHERE competition_id = (SELECT competition_id FROM livetiming_sessions WHERE id = ?)");
    $stmt->bind_param("i", $session_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $last_update = $result['last_update'] ?? 0;

    if ($last_update > ($last_timestamp / 1000)) {
        // Neue Daten abrufen
        $stmt = $conn->prepare("SELECT cs.*, ss.name as swim_style_name FROM competition_starts cs INNER JOIN swim_styles ss ON cs.swim_style_id = ss.id WHERE cs.competition_id = (SELECT competition_id FROM livetiming_sessions WHERE id = ?)");
        $stmt->bind_param("i", $session_id);
        $stmt->execute();
        $starts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // HTML generieren
        $html = '';
        foreach ($starts as $start) {
            $html .= '<tr>
                <td>' . htmlspecialchars($start['wk_nr']) . '</td>
                <td>' . htmlspecialchars($start['swim_style_name']) . '</td>
                <td>' . htmlspecialchars($start['distance']) . '</td>
                <td>' . htmlspecialchars($start['entry_time']) . '</td>
                <td>' . htmlspecialchars($start['swim_time'] ?? '-') . '</td>
                <td>' . htmlspecialchars($start['place'] ?? '-') . '</td>
            </tr>';
        }

        echo json_encode(['updated' => true, 'html' => $html]);
    } else {
        echo json_encode(['updated' => false]);
    }
}
?>
