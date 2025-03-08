<?php
// leistungsentwicklung.php

// Fehleranzeige aktivieren (nur für Entwicklungszwecke)
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

// Datenbankverbindung einbinden
require_once('../dbconnection.php');

// Benutzerdaten
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['name'] ?? 'Benutzer';

// Schwimmarten abrufen
$swim_styles = [];
$stmt = $conn->prepare("SELECT id, name FROM swim_styles ORDER BY name ASC");
$stmt->execute();
$stmt->bind_result($id, $name);
while ($stmt->fetch()) {
    $swim_styles[$id] = $name;
}
$stmt->close();

// Distanzen abrufen
$distances = [];
$stmt = $conn->prepare("SELECT DISTINCT distance FROM swim_style_distances ORDER BY distance ASC");
$stmt->execute();
$stmt->bind_result($distance);
while ($stmt->fetch()) {
    $distances[] = $distance;
}
$stmt->close();

$times = [];
$swim_style_name = '';
$selected_distance = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $swim_style_id = intval($_POST['swim_style']);
    $distance = intval($_POST['distance']);

    $swim_style_name = $swim_styles[$swim_style_id] ?? '';
    $selected_distance = $distance;

    // Zeiten abrufen
    $stmt = $conn->prepare("
        SELECT date, time
        FROM times
        WHERE user_id = ? AND swim_style_id = ? AND distance = ?
        ORDER BY date ASC
    ");
    $stmt->bind_param("iii", $user_id, $swim_style_id, $distance);
    $stmt->execute();
    $stmt->bind_result($date, $time);

    while ($stmt->fetch()) {
        $times[] = [
            'date' => $date,
            'time' => $time
        ];
    }
    $stmt->close();

    if (empty($times)) {
        $error_message = 'Keine Daten für die ausgewählte Schwimmart und Distanz gefunden.';
    } else {
        // Berechnungen durchführen
        $sum_times = 0;
        $best_time = null;
        $worst_time = null;

        foreach ($times as &$entry) {
            $time_sec = timeToSeconds($entry['time']);
            $entry['time_in_seconds'] = $time_sec;
            $sum_times += $time_sec;
            if ($best_time === null || $time_sec < $best_time) {
                $best_time = $time_sec;
            }
            if ($worst_time === null || $time_sec > $worst_time) {
                $worst_time = $time_sec;
            }
        }
        unset($entry);

        $average_time = $sum_times / count($times);

        // Prozentuale Verbesserung berechnen
        for ($i = 1; $i < count($times); $i++) {
            $previous_time = $times[$i - 1]['time_in_seconds'];
            $current_time = $times[$i]['time_in_seconds'];
            $improvement = (($previous_time - $current_time) / $previous_time) * 100;
            $times[$i]['improvement'] = round($improvement, 2);
        }
    }
}

// Funktion zum Umrechnen von MM:SS.MS in Sekunden
function timeToSeconds($time) {
    $time = str_replace(',', '.', $time);
    list($minutes, $rest) = explode(':', $time);
    list($seconds, $milliseconds) = explode('.', $rest);
    $total_seconds = ($minutes * 60) + $seconds + ($milliseconds / 100);
    return $total_seconds;
}

// Funktion zum Umrechnen von Sekunden in MM:SS.MS
function secondsToTime($seconds) {
    $minutes = floor($seconds / 60);
    $remaining_seconds = $seconds - ($minutes * 60);
    $whole_seconds = floor($remaining_seconds);
    $milliseconds = round(($remaining_seconds - $whole_seconds) * 100);
    return sprintf('%02d:%02d.%02d', $minutes, $whole_seconds, $milliseconds);
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Leistungsentwicklung</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Bootstrap CSS und Chart.js -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Eigene CSS-Anpassungen -->
    <style>
        body {
            padding-top: 56px;
        }
        .chart-container {
            position: relative;
            height: 500px;
        }
    </style>
</head>
<body>
    <!-- Menü einbinden -->
    <?php include '../menu.php'; ?>

    <div class="container mt-5">
        <h2>Leistungsentwicklung</h2>

        <?php if ($error_message): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <form method="post" action="leistungsentwicklung.php">
            <!-- Schwimmart wählen -->
            <div class="mb-3">
                <label for="swim_style" class="form-label">Schwimmart</label>
                <select name="swim_style" id="swim_style" class="form-select" required>
                    <option value="">Bitte wählen...</option>
                    <?php foreach ($swim_styles as $id => $name): ?>
                        <option value="<?php echo $id; ?>" <?php if (isset($swim_style_id) && $swim_style_id == $id) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <!-- Distanz wählen -->
            <div class="mb-3">
                <label for="distance" class="form-label">Distanz (m)</label>
                <select name="distance" id="distance" class="form-select" required>
                    <option value="">Bitte wählen...</option>
                    <?php foreach ($distances as $dist): ?>
                        <option value="<?php echo $dist; ?>" <?php if (isset($distance) && $distance == $dist) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($dist); ?> m
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Anzeigen</button>
        </form>

        <?php if (!empty($times)): ?>
            <h3 class="mt-5 text-center"><?php echo htmlspecialchars($swim_style_name) . ' - ' . htmlspecialchars($selected_distance) . ' m'; ?></h3>

            <!-- Statistiken -->
            <div class="mt-4">
                <p><strong>Durchschnittliche Zeit:</strong> <?php echo secondsToTime($average_time); ?></p>
                <p><strong>Beste Zeit:</strong> <?php echo secondsToTime($best_time); ?></p>
                <p><strong>Schlechteste Zeit:</strong> <?php echo secondsToTime($worst_time); ?></p>
            </div>

            <!-- Tabelle mit Zeiten -->
            <div class="table-responsive mt-3">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Datum</th>
                            <th>Zeit</th>
                            <th>Verbesserung (%)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($times as $index => $entry): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($entry['date']); ?></td>
                                <td><?php echo htmlspecialchars($entry['time']); ?></td>
                                <td>
                                    <?php
                                    if ($index > 0) {
                                        echo htmlspecialchars($entry['improvement'] . '%');
                                    } else {
                                        echo '-';
                                    }
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Diagramm -->
            <div class="chart-container mt-5">
                <canvas id="performanceChart"></canvas>
            </div>
        <?php endif; ?>
    </div>

    <!-- Bootstrap JS und Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- Diagramm erstellen -->
    <script>
        <?php if (!empty($times)): ?>
            const ctx = document.getElementById('performanceChart').getContext('2d');
            const dates = <?php echo json_encode(array_column($times, 'date')); ?>;
            const timesInSeconds = <?php echo json_encode(array_column($times, 'time_in_seconds')); ?>;

            function formatTime(seconds) {
                const minutes = Math.floor(seconds / 60);
                const remainingSeconds = seconds - minutes * 60;
                const wholeSeconds = Math.floor(remainingSeconds);
                const milliseconds = Math.round((remainingSeconds - wholeSeconds) * 100);
                return `${minutes.toString().padStart(2, '0')}:${wholeSeconds.toString().padStart(2, '0')}.${milliseconds.toString().padStart(2, '0')}`;
            }

            const performanceChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: dates.map(date => {
                        const d = new Date(date);
                        return d.toLocaleDateString();
                    }),
                    datasets: [{
                        label: 'Zeit',
                        data: timesInSeconds,
                        borderColor: 'blue',
                        backgroundColor: 'rgba(0, 0, 255, 0.1)',
                        fill: true,
                        tension: 0.1
                    }]
                },
                options: {
                    scales: {
                        y: {
                            ticks: {
                                callback: function(value) {
                                    return formatTime(value);
                                }
                            },
                            title: {
                                display: true,
                                text: 'Zeit'
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Datum'
                            }
                        }
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return 'Zeit: ' + formatTime(context.parsed.y);
                                }
                            }
                        }
                    }
                }
            });
        <?php endif; ?>
    </script>

    <?php
    // Verbindung schließen
    $conn->close();
    ?>
</body>
</html>
