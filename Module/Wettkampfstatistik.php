<?php
// Wettkampfstatistik.php

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

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['name'] ?? 'Benutzer';

// Prüfen, ob das Modul zum ersten Mal geöffnet wird
if (!isset($_SESSION['wettkampfstatistik_first_visit'])) {
    $_SESSION['wettkampfstatistik_first_visit'] = true;
    $show_popup = true;
} else {
    $show_popup = false;
}

// Datenbankverbindung einbinden
require_once('../dbconnection.php');

// Autoloader einbinden
require_once '../vendor/autoload.php';

// Dompdf einbinden
use Dompdf\Dompdf;
use Dompdf\Options;

// Hilfsfunktionen
function timeToSeconds($time_str) {
    if (preg_match('/^(\d+):(\d+),(\d+)$/', $time_str, $matches)) {
        $minutes = (int)$matches[1];
        $seconds = (int)$matches[2];
        $milliseconds = (int)$matches[3];
        return ($minutes * 60) + $seconds + ($milliseconds / 100);
    } else {
        return (float)$time_str;
    }
}

function secondsToTime($seconds) {
    $minutes = floor($seconds / 60);
    $seconds_remainder = $seconds % 60;
    $milliseconds = round(($seconds_remainder - floor($seconds_remainder)) * 100);
    return sprintf('%02d:%02d,%02d', $minutes, floor($seconds_remainder), $milliseconds);
}

// Initialisierung der Variablen
$start_date = $_POST['start_date'] ?? '';
$end_date = $_POST['end_date'] ?? '';
$swim_style_id = $_POST['swim_style'] ?? '';
$distance = $_POST['distance'] ?? '';
$search_term = $_POST['search_term'] ?? '';
$competitions = [];
$error_message = '';

// Schwimmstile abrufen
$swim_styles = [];
$swim_style_query = "SELECT id, name FROM swim_styles";
$result = $conn->query($swim_style_query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $swim_styles[$row['id']] = $row['name'];
    }
    $result->free();
}

// Distanzen abrufen
$distances = [];
$distance_query = "SELECT DISTINCT distance FROM competition_starts ORDER BY distance ASC";
$result = $conn->query($distance_query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $distances[] = $row['distance'];
    }
    $result->free();
}

// Wenn PDF-Download oder Druck angefordert wurde
if ((isset($_GET['download_pdf']) || isset($_GET['print_pdf'])) && isset($_GET['competition_id'])) {
    $competition_id = intval($_GET['competition_id']);
    $diagrams_per_page = isset($_GET['diagrams_per_page']) ? intval($_GET['diagrams_per_page']) : 4;

    // Wettbewerb abrufen
    $competition = null;
    $query = "SELECT id, name, competition_date, place FROM competitions WHERE id = ?";
    $stmt = $conn->prepare($query);
    if ($stmt) {
        $stmt->bind_param("i", $competition_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $competition = $result->fetch_assoc();
        $stmt->close();
    }

    if (!$competition) {
        die('Wettbewerb nicht gefunden.');
    }

    // Starts des Benutzers in diesem Wettbewerb abrufen
    $starts = [];
    $query = "SELECT s.id, s.distance, s.swim_time, s.entry_time, s.swim_style_id, ss.name AS swim_style_name, s.wk_nr, s.date
              FROM competition_starts s
              JOIN swim_styles ss ON s.swim_style_id = ss.id
              WHERE s.user_id = ? AND s.competition_id = ?";
    $stmt = $conn->prepare($query);
    if ($stmt) {
        $stmt->bind_param("ii", $user_id, $competition_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $starts[] = $row;
        }
        $stmt->close();
    }

    // Diagrammdaten vorbereiten
    $charts_data = [];
    foreach ($starts as $start) {
        $swim_style = $start['swim_style_name'];
        $distance = $start['distance'];
        $key = $swim_style . ' ' . $distance . 'm';

        // Alle Zeiten des Benutzers für diese Schwimmart und Distanz abrufen
        $times = [];
        $dates = [];
        $query = "SELECT swim_time, date FROM competition_starts
                  WHERE user_id = ? AND swim_style_id = ? AND distance = ?
                  ORDER BY date ASC";
        $stmt = $conn->prepare($query);
        if ($stmt) {
            $stmt->bind_param("iii", $user_id, $start['swim_style_id'], $distance);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $times[] = timeToSeconds($row['swim_time']);
                $dates[] = date('d.m.Y', strtotime($row['date']));
            }
            $stmt->close();
        }

        $charts_data[] = [
            'labels' => $dates,
            'data' => $times,
            'title' => $key
        ];
    }

    // PDF generieren
    generatePDF($competition, $starts, $charts_data, $user_name, $diagrams_per_page);

    // Wenn 'print_pdf' gesetzt ist, direkt den Druckdialog öffnen
    if (isset($_GET['print_pdf'])) {
        // Temporäre PDF-Datei erstellen
        $pdf_content = $GLOBALS['pdf_content'];
        $temp_pdf = tempnam(sys_get_temp_dir(), 'pdf');
        file_put_contents($temp_pdf, $pdf_content);

        // Header setzen, um den PDF-Druck zu initiieren
        header('Content-Type: application/pdf');
        header('Content-Length: ' . filesize($temp_pdf));
        header('Content-Disposition: inline; filename="Auswertung_' . $competition['name'] . '.pdf"');
        readfile($temp_pdf);

        // Temporäre Datei löschen
        unlink($temp_pdf);
        exit();
    } else {
        exit();
    }
}

// Wenn Details eines Wettkampfs angezeigt werden sollen
if (isset($_GET['competition_id'])) {
    $competition_id = intval($_GET['competition_id']);

    // Wettbewerb abrufen
    $competition = null;
    $query = "SELECT id, name, competition_date, place FROM competitions WHERE id = ?";
    $stmt = $conn->prepare($query);
    if ($stmt) {
        $stmt->bind_param("i", $competition_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $competition = $result->fetch_assoc();
        $stmt->close();
    }

    if (!$competition) {
        $error_message = 'Wettbewerb nicht gefunden.';
    } else {
        // Starts des Benutzers in diesem Wettbewerb abrufen
        $starts = [];
        $query = "SELECT s.id, s.distance, s.swim_time, s.entry_time, s.swim_style_id, ss.name AS swim_style_name, s.wk_nr, s.date
                  FROM competition_starts s
                  JOIN swim_styles ss ON s.swim_style_id = ss.id
                  WHERE s.user_id = ? AND s.competition_id = ?";
        $stmt = $conn->prepare($query);
        if ($stmt) {
            $stmt->bind_param("ii", $user_id, $competition_id);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $starts[] = $row;
            }
            $stmt->close();
        }

        // Diagrammdaten vorbereiten
        $charts_data = [];
        foreach ($starts as $start) {
            $swim_style = $start['swim_style_name'];
            $distance = $start['distance'];
            $key = $swim_style . ' ' . $distance . 'm';

            // Alle Zeiten des Benutzers für diese Schwimmart und Distanz abrufen
            $times = [];
            $dates = [];
            $query = "SELECT swim_time, date FROM competition_starts
                      WHERE user_id = ? AND swim_style_id = ? AND distance = ?
                      ORDER BY date ASC";
            $stmt = $conn->prepare($query);
            if ($stmt) {
                $stmt->bind_param("iii", $user_id, $start['swim_style_id'], $distance);
                $stmt->execute();
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    $times[] = timeToSeconds($row['swim_time']);
                    $dates[] = date('d.m.Y', strtotime($row['date']));
                }
                $stmt->close();
            }

            $charts_data[$start['id']] = [
                'labels' => $dates,
                'data' => $times,
                'title' => $key
            ];
        }
    }
}

// Wettbewerbe abrufen
if (!isset($competition)) {
    $query = "SELECT DISTINCT c.id, c.name, c.competition_date, c.place
              FROM competitions c
              JOIN competition_starts s ON c.id = s.competition_id
              WHERE s.user_id = ?";
    $params = [$user_id];
    $types = 'i';

    if ($search_term) {
        $query .= " AND c.name LIKE ?";
        $params[] = '%' . $search_term . '%';
        $types .= 's';
    }

    if ($start_date) {
        $query .= " AND c.competition_date >= ?";
        $params[] = $start_date;
        $types .= 's';
    }
    if ($end_date) {
        $query .= " AND c.competition_date <= ?";
        $params[] = $end_date;
        $types .= 's';
    }
    if ($swim_style_id) {
        $query .= " AND s.swim_style_id = ?";
        $params[] = $swim_style_id;
        $types .= 'i';
    }
    if ($distance) {
        $query .= " AND s.distance = ?";
        $params[] = $distance;
        $types .= 'i';
    }

    $query .= " ORDER BY c.competition_date DESC";

    $stmt = $conn->prepare($query);
    if ($stmt) {
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $competitions[] = $row;
        }
        $stmt->close();
    } else {
        $error_message = "Fehler bei der Abfrage: " . $conn->error;
    }
}

// Verbindung schließen
$conn->close();

// Funktion zum Generieren des PDFs
function generatePDF($competition, $starts, $charts_data, $user_name, $diagrams_per_page) {
    // Optionen für Dompdf setzen
    $options = new Options();
    $options->set('isRemoteEnabled', true);

    // Dompdf initialisieren
    $dompdf = new Dompdf($options);

    // HTML für das PDF generieren
    ob_start();
    ?>
    <!DOCTYPE html>
    <html lang="de">
    <head>
        <meta charset="UTF-8">
        <title>Auswertung - <?php echo htmlspecialchars($competition['name']); ?></title>
        <style>
            body { font-family: Arial, sans-serif; margin: 0; padding: 0; }
            h1, h2, h3 { color: #007bff; }
            h1 { text-align: center; }
            table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: center; }
            th { background-color: #007bff; color: #fff; }
            .chart-row { display: flex; flex-wrap: wrap; justify-content: center; }
            .chart-container {
                width: <?php echo ($diagrams_per_page == 1) ? '100%' : (($diagrams_per_page == 2 || $diagrams_per_page == 3) ? '48%' : '48%'); ?>;
                margin-bottom: 20px;
                margin-right: 1%;
                margin-left: 1%;
                text-align: center;
            }
            .chart-container img {
                max-width: 100%;
                height: auto;
            }
            .footer {
                position: fixed;
                bottom: 0;
                left: 0;
                right: 0;
                height: 30px;
                text-align: center;
                font-size: 10px;
                color: #555;
                border-top: 1px solid #ddd;
                padding-top: 5px;
            }
            .page-break { page-break-after: always; }
        </style>
    </head>
    <body>
        <h1>Auswertung - <?php echo htmlspecialchars($competition['name']); ?></h1>
        <p>Datum: <?php echo date('d.m.Y', strtotime($competition['competition_date'])); ?></p>
        <p>Ort: <?php echo htmlspecialchars($competition['place']); ?></p>

        <table>
            <thead>
                <tr>
                    <th>WK-NR</th>
                    <th>Schwimmart</th>
                    <th>Distanz</th>
                    <th>Meldezeit</th>
                    <th>Endzeit</th>
                    <th>Verbesserung (Sek)</th>
                    <th>Verbesserung (%)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($starts as $start): ?>
                    <?php
                    // Verbesserung berechnen
                    $entry_time_sec = timeToSeconds($start['entry_time']);
                    $swim_time_sec = timeToSeconds($start['swim_time']);
                    $verbesserung_sekunden = $entry_time_sec - $swim_time_sec;
                    $verbesserung_prozent = ($verbesserung_sekunden / $entry_time_sec) * 100;
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($start['wk_nr']); ?></td>
                        <td><?php echo htmlspecialchars($start['swim_style_name']); ?></td>
                        <td><?php echo htmlspecialchars($start['distance']); ?> m</td>
                        <td><?php echo htmlspecialchars($start['entry_time']); ?></td>
                        <td><?php echo htmlspecialchars($start['swim_time']); ?></td>
                        <td><?php echo number_format($verbesserung_sekunden, 2, ',', ''); ?></td>
                        <td><?php echo number_format($verbesserung_prozent, 2, ',', '') . ' %'; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php
        // Diagramme anzeigen
        $chart_count = 0;
        $total_charts = count($charts_data);

        foreach ($charts_data as $chart) {
            if ($chart_count % $diagrams_per_page == 0) {
                if ($chart_count > 0) {
                    echo '</div>'; // Vorherige Zeile schließen
                }
                echo '<div class="chart-row">';
            }

            // Diagramm generieren
            $chart_url = 'https://quickchart.io/chart?c=' . urlencode(json_encode([
                'type' => 'line',
                'data' => [
                    'labels' => $chart['labels'],
                    'datasets' => [[
                        'label' => $chart['title'],
                        'data' => $chart['data'],
                        'borderColor' => 'rgb(0, 123, 255)',
                        'backgroundColor' => 'rgba(0, 123, 255, 0.5)',
                        'fill' => false,
                    ]]
                ],
                'options' => [
                    'title' => [
                        'display' => true,
                        'text' => $chart['title']
                    ],
                    'scales' => [
                        'yAxes' => [[
                            'scaleLabel' => [
                                'display' => true,
                                'labelString' => 'Zeit (Sekunden)'
                            ]
                        ]],
                        'xAxes' => [[
                            'scaleLabel' => [
                                'display' => true,
                                'labelString' => 'Datum'
                            ]
                        ]]
                    ]
                ]
            ]));

            ?>
            <div class="chart-container" <?php if ($diagrams_per_page == 3 && $chart_count % $diagrams_per_page == 2) echo 'style="width: 98%;"'; ?>>
                <img src="<?php echo $chart_url; ?>" alt="Diagramm">
            </div>
            <?php
            $chart_count++;
            if ($chart_count % $diagrams_per_page == 0 || $chart_count == $total_charts) {
                echo '</div>'; // Zeile schließen
                if ($chart_count < $total_charts) {
                    echo '<div class="page-break"></div>'; // Seitenumbruch
                }
            }
        }
        ?>

        <!-- Footer -->
        <div class="footer">
            Dieses Dokument wurde mit SLA-Schwimmen generiert. Lizenznehmer: <?php echo htmlspecialchars($user_name); ?>.
        </div>
    </body>
    </html>
    <?php
    $html = ob_get_clean();

    // PDF generieren
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    // PDF-Inhalt speichern
    $GLOBALS['pdf_content'] = $dompdf->output();

    // Wenn 'download_pdf' gesetzt ist, PDF herunterladen
    if (isset($_GET['download_pdf'])) {
        $dompdf->stream('Auswertung_' . $competition['name'] . '.pdf', array("Attachment" => 1));
    }
}
?>
<!-- HTML-Teil -->
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Wettkampfstatistik</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Bootstrap CSS und Chart.js -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Eigene CSS-Anpassungen -->
    <style>
        body {
            padding-top: 56px;
            background-color: #f5f5f5;
            color: #333;
        }
        .navbar {
            background-color: #007bff;
        }
        .navbar-brand, .navbar-nav .nav-link {
            color: #fff !important;
        }
        .card {
            margin-bottom: 20px;
        }
        h2, h3, h4 {
            color: #007bff;
        }
        .btn-primary {
            background-color: #007bff;
            border: none;
        }
        .btn-primary:hover {
            background-color: #0056b3;
        }
        .table thead {
            background-color: #007bff;
            color: #fff;
        }
        .chart-container {
            position: relative;
            height: 400px;
            margin-bottom: 40px;
        }
        .print-button {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 100;
        }
        .filter-form {
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <!-- Menü einbinden -->
    <?php include('../menu.php'); ?>

    <div class="container">
        <h2 class="mt-4">Wettkampfstatistik</h2>

        <?php if ($error_message): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <?php if (isset($competition)): ?>
            <!-- Detailseite des Wettbewerbs -->
            <div class="card">
                <div class="card-body">
                    <h3><?php echo htmlspecialchars($competition['name']); ?></h3>
                    <p>Datum: <?php echo date('d.m.Y', strtotime($competition['competition_date'])); ?></p>
                    <p>Ort: <?php echo htmlspecialchars($competition['place']); ?></p>
                    <a href="Wettkampfstatistik.php" class="btn btn-secondary">Zurück zur Übersicht</a>
                    <!-- Button zum Öffnen des Tutorials -->
                    <a href="/kunden/homepages/4/d1007545866/htdocs/SLA-Projekt/Document/Module/Modul Diagramm SLA-Schwimmen.pdf" class="btn btn-success">Tutorial öffnen</a>
                    <!-- Druckbutton -->
                    <button class="btn btn-info" data-bs-toggle="modal" data-bs-target="#printModal">Drucken</button>
                </div>
            </div>

            <!-- Tabelle der Starts -->
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>WK-NR</th>
                        <th>Schwimmart</th>
                        <th>Distanz</th>
                        <th>Meldezeit</th>
                        <th>Endzeit</th>
                        <th>Verbesserung (Sek)</th>
                        <th>Verbesserung (%)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($starts as $start): ?>
                        <?php
                        // Verbesserung berechnen
                        $entry_time_sec = timeToSeconds($start['entry_time']);
                        $swim_time_sec = timeToSeconds($start['swim_time']);
                        $verbesserung_sekunden = $entry_time_sec - $swim_time_sec;
                        $verbesserung_prozent = ($verbesserung_sekunden / $entry_time_sec) * 100;

                        // Zeilenklasse für Verbesserung
                        $row_class = $verbesserung_sekunden > 0 ? 'table-success' : 'table-danger';
                        ?>
                        <tr class="<?php echo $row_class; ?>">
                            <td><?php echo htmlspecialchars($start['wk_nr']); ?></td>
                            <td><?php echo htmlspecialchars($start['swim_style_name']); ?></td>
                            <td><?php echo htmlspecialchars($start['distance']); ?> m</td>
                            <td><?php echo htmlspecialchars($start['entry_time']); ?></td>
                            <td><?php echo htmlspecialchars($start['swim_time']); ?></td>
                            <td><?php echo number_format($verbesserung_sekunden, 2, ',', ''); ?></td>
                            <td><?php echo number_format($verbesserung_prozent, 2, ',', '') . ' %'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Diagramme anzeigen -->
            <h4>Leistungsentwicklung</h4>
            <div class="row">
                <?php foreach ($charts_data as $start_id => $chart_data): ?>
                    <div class="col-md-6">
                        <div class="chart-container">
                            <canvas id="chart-<?php echo $start_id; ?>"></canvas>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Druckmodal -->
            <div class="modal fade" id="printModal" tabindex="-1" aria-labelledby="printModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <form method="get" action="Wettkampfstatistik.php">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="printModalLabel">Drucken</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
                            </div>
                            <div class="modal-body">
                                <input type="hidden" name="competition_id" value="<?php echo $competition['id']; ?>">
                                <input type="hidden" name="print_pdf" value="1">
                                <div class="mb-3">
                                    <label for="diagrams_per_page" class="form-label">Diagramme pro Seite:</label>
                                    <select name="diagrams_per_page" id="diagrams_per_page" class="form-select">
                                        <option value="1">1</option>
                                        <option value="2">2</option>
                                        <option value="3">3</option>
                                        <option value="4" selected>4</option>
                                    </select>
                                </div>
                                <p>Bitte wählen Sie die gewünschten Druckeinstellungen aus.</p>
                            </div>
                            <div class="modal-footer">
                                <button type="submit" class="btn btn-primary">Drucken</button>
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

        <?php else: ?>
            <!-- Popup beim ersten Öffnen -->
            <?php if ($show_popup): ?>
                <div class="modal" tabindex="-1" id="welcomeModal">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Nutzung des Moduls <code>Wettkampfstatistik</code></h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
                            </div>
                            <div class="modal-body">
                                <p>Hey,</p>
                                <p>vielen Dank, dass du dich für dieses Modul entschieden hast. Du kannst den Anhang herunterladen, dort findest du ein Tutorial!</p>
                            </div>
                            <div class="modal-footer">
                                <a href="/kunden/homepages/4/d1007545866/htdocs/SLA-Projekt/Document/Module/Modul Diagramm SLA-Schwimmen.pdf" class="btn btn-primary">Anhang herunterladen</a>
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Schließen</button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Filterformular -->
            <form method="post" action="Wettkampfstatistik.php" class="filter-form">
                <div class="row">
                    <div class="col-md-3">
                        <label for="search_term" class="form-label">Wettbewerb suchen</label>
                        <input type="text" name="search_term" id="search_term" class="form-control" value="<?php echo htmlspecialchars($search_term); ?>">
                    </div>
                    <div class="col-md-2">
                        <label for="start_date" class="form-label">Startdatum</label>
                        <input type="date" name="start_date" id="start_date" class="form-control" value="<?php echo htmlspecialchars($start_date); ?>">
                    </div>
                    <div class="col-md-2">
                        <label for="end_date" class="form-label">Enddatum</label>
                        <input type="date" name="end_date" id="end_date" class="form-control" value="<?php echo htmlspecialchars($end_date); ?>">
                    </div>
                    <div class="col-md-2">
                        <label for="swim_style" class="form-label">Schwimmart</label>
                        <select name="swim_style" id="swim_style" class="form-select">
                            <option value="">Alle</option>
                            <?php foreach ($swim_styles as $id => $name): ?>
                                <option value="<?php echo $id; ?>" <?php if ($swim_style_id == $id) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="distance" class="form-label">Distanz (m)</label>
                        <select name="distance" id="distance" class="form-select">
                            <option value="">Alle</option>
                            <?php foreach ($distances as $dist): ?>
                                <option value="<?php echo $dist; ?>" <?php if ($distance == $dist) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($dist); ?> m
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-1 align-self-end">
                        <button type="submit" class="btn btn-primary">Filtern</button>
                    </div>
                </div>
            </form>

            <!-- Übersicht der Wettbewerbe -->
            <div class="row">
                <?php foreach ($competitions as $competition): ?>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($competition['name']); ?></h5>
                                <p class="card-text">Datum: <?php echo date('d.m.Y', strtotime($competition['competition_date'])); ?></p>
                                <p class="card-text">Ort: <?php echo htmlspecialchars($competition['place']); ?></p>
                                <a href="Wettkampfstatistik.php?competition_id=<?php echo $competition['id']; ?>" class="btn btn-primary">Details ansehen</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Bootstrap JS und Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <?php if (isset($charts_data)): ?>
        <!-- Diagramm-Skripte -->
        <script>
            <?php foreach ($charts_data as $start_id => $chart_data): ?>
                var ctx = document.getElementById('chart-<?php echo $start_id; ?>').getContext('2d');
                var chartData = {
                    labels: <?php echo json_encode($chart_data['labels']); ?>,
                    datasets: [{
                        label: '<?php echo $chart_data['title']; ?>',
                        data: <?php echo json_encode($chart_data['data']); ?>,
                        backgroundColor: 'rgba(0, 123, 255, 0.2)',
                        borderColor: 'rgba(0, 123, 255, 1)',
                        borderWidth: 2,
                        tension: 0.1,
                        pointBackgroundColor: 'rgba(0, 123, 255, 1)',
                        pointBorderColor: '#fff',
                        pointHoverBackgroundColor: '#fff',
                        pointHoverBorderColor: 'rgba(0, 123, 255, 1)',
                    }]
                };
                var chart = new Chart(ctx, {
                    type: 'line',
                    data: chartData,
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: false,
                                title: {
                                    display: true,
                                    text: 'Zeit (Sekunden)'
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
                            legend: {
                                display: true,
                                position: 'top',
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        var seconds = context.parsed.y;
                                        var minutes = Math.floor(seconds / 60);
                                        var remainingSeconds = (seconds % 60).toFixed(2);
                                        return 'Zeit: ' + minutes + ':' + (remainingSeconds < 10 ? '0' : '') + remainingSeconds.replace('.', ',');
                                    }
                                }
                            }
                        }
                    }
                });
            <?php endforeach; ?>
        </script>
    <?php endif; ?>

    <!-- Popup-Skript -->
    <?php if ($show_popup): ?>
        <script>
            var welcomeModal = new bootstrap.Modal(document.getElementById('welcomeModal'), {
                keyboard: false,
                backdrop: 'static'
            });
            welcomeModal.show();
        </script>
    <?php endif; ?>
</body>
</html>
