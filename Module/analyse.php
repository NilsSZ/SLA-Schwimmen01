<?php
// analyse.php

// Fehleranzeige aktivieren (für Entwicklungszwecke)
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

// Lizenznehmername (für den Info-Satz im PDF)
$licensee_name = $user_name; // Hier kannst du den Lizenznehmernamen festlegen

// Datenbankverbindung einbinden
require_once(__DIR__ . '/../dbconnection.php');

// Composer Autoloader einbinden (für PDF-, CSV- und Excel-Export)
require_once(__DIR__ . '/../vendor/autoload.php');

// Benötigte Namespaces importieren
use League\Csv\Writer;
use Dompdf\Dompdf;
use Dompdf\Options;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

// Schwimmarten abrufen
$swim_styles = [];
$stmt = $conn->prepare("SELECT id, name FROM swim_styles ORDER BY name ASC");
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $swim_styles[$row['id']] = $row['name'];
    }
    $stmt->close();
} else {
    die("Fehler bei der Vorbereitung der Schwimmarten-Abfrage: " . $conn->error);
}

// Distanzen abrufen
$distances = [];
$stmt = $conn->prepare("SELECT DISTINCT distance FROM swim_style_distances ORDER BY distance ASC");
if ($stmt) {
    $stmt->execute();
    $stmt->bind_result($distance);
    while ($stmt->fetch()) {
        $distances[] = $distance;
    }
    $stmt->close();
} else {
    die("Fehler bei der Vorbereitung der Distanzen-Abfrage: " . $conn->error);
}

// Initialisiere Variablen
$swim_style_name = '';
$distance = '';
$chart_data = [];
$times = [];
$error_message = '';
$start_date = '';
$end_date = '';
$analyze_all = false;
$chart_type = 'line'; // Standard-Diagrammtyp
$include_qr_code = false; // QR-Code standardmäßig nicht einbinden

/**
 * Funktion zum Konvertieren der Zeit in Sekunden (MM:SS,MS)
 */
function convertTimeToSeconds($time) {
    $time = trim($time);
    if (preg_match('/^(\d{1,2}):(\d{2}),(\d{2})$/', $time, $matches)) {
        $minutes = (int)$matches[1];
        $seconds = (int)$matches[2];
        $milliseconds = (int)$matches[3];
        return ($minutes * 60) + $seconds + ($milliseconds / 100);
    } else {
        // Ungültiges Zeitformat
        return null;
    }
}

/**
 * Funktion zum Formatieren von Sekunden in MM:SS,MS
 */
function formatSeconds($seconds) {
    $minutes = floor($seconds / 60);
    $remaining_seconds = $seconds - ($minutes * 60);
    $whole_seconds = floor($remaining_seconds);
    $milliseconds = round(($remaining_seconds - $whole_seconds) * 100);
    return sprintf("%02d:%02d,%02d", $minutes, $whole_seconds, $milliseconds);
}

/**
 * Funktion zur Berechnung der linearen Regression
 */
function linearRegression($x, $y) {
    $n = count($x);
    if ($n !== count($y)) {
        throw new Exception('Die Anzahl der x- und y-Werte muss übereinstimmen.');
    }
    if ($n === 0) {
        throw new Exception('Es müssen mindestens ein Paar von x- und y-Werten vorhanden sein.');
    }

    $sum_x = array_sum($x);
    $sum_y = array_sum($y);
    $sum_xx = array_sum(array_map(function($xi) { return $xi * $xi; }, $x));
    $sum_xy = array_sum(array_map(function($xi, $yi) { return $xi * $yi; }, $x, $y));

    $slope = ($n * $sum_xy - $sum_x * $sum_y) / ($n * $sum_xx - $sum_x * $sum_x);
    $intercept = ($sum_y - $slope * $sum_x) / $n;

    return ['slope' => $slope, 'intercept' => $intercept];
}

// Funktion zur Berechnung des Medians
function calculateMedian($arr) {
    sort($arr);
    $count = count($arr);
    $middle = floor(($count - 1) / 2);
    if ($count % 2) {
        return $arr[$middle];
    } else {
        return ($arr[$middle] + $arr[$middle + 1]) / 2;
    }
}

// Funktion zur Berechnung der Varianz
function calculateVariance($arr, $mean) {
    $sum = 0;
    foreach ($arr as $value) {
        $sum += pow($value - $mean, 2);
    }
    return $sum / count($arr);
}

// Export-Funktionen

function exportToCSV($times, $swim_style_name, $distance) {
    // CSV Writer erstellen
    $csv = Writer::createFromString('');
    $csv->setDelimiter(';');

    // Header hinzufügen
    $csv->insertOne(['Datum', 'Zeit']);

    // Daten einfügen
    foreach ($times as $entry) {
        $date = date('d.m.Y', strtotime($entry['date']));
        $time_in_seconds = convertTimeToSeconds($entry['time']);
        $formatted_time = formatSeconds($time_in_seconds);
        $csv->insertOne([$date, $formatted_time]);
    }

    // CSV ausgeben
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="analyse_' . $swim_style_name . '_' . $distance . 'm.csv"');
    echo $csv->toString();
    exit();
}

function exportToPDF($chart_data, $times, $swim_style_name, $distance, $analyze_all, $start_date, $end_date, $licensee_name, $chart_type, $include_qr_code, $public_url) {
    // Optionen für Dompdf einstellen
    $options = new Options();
    $options->set('defaultFont', 'DejaVu Sans');
    $options->setIsRemoteEnabled(true); // Wichtig für das Laden externer Bilder
    $dompdf = new Dompdf($options);

    // Diagramm über QuickChart erzeugen
    $chartConfig = [
        'type' => $chart_type,
        'data' => [
            'labels' => $chart_data['dates'],
            'datasets' => [
                [
                    'label' => 'Zeiten',
                    'data' => $chart_data['times'],
                    'borderColor' => 'blue',
                    'backgroundColor' => 'rgba(0, 0, 255, 0.1)',
                    'fill' => true,
                    'tension' => 0.1,
                    'pointBackgroundColor' => 'blue',
                    'pointRadius' => 3
                ],
                [
                    'label' => 'Durchschnitt',
                    'data' => array_fill(0, count($chart_data['dates']), $chart_data['average']),
                    'borderColor' => 'red',
                    'borderDash' => [5, 5],
                    'fill' => false,
                    'tension' => 0.1
                ],
                [
                    'label' => 'Bestzeit',
                    'data' => array_fill(0, count($chart_data['dates']), $chart_data['best_time']),
                    'borderColor' => 'gold',
                    'borderDash' => [2, 2],
                    'fill' => false,
                    'tension' => 0.1
                ],
                [
                    'label' => 'Trendlinie',
                    'data' => $chart_data['trendline'],
                    'borderColor' => 'green',
                    'borderDash' => [3, 3],
                    'fill' => false,
                    'tension' => 0
                ]
            ]
        ],
        'options' => [
            'responsive' => true,
            'maintainAspectRatio' => false,
            'scales' => [
                'y' => [
                    'title' => [
                        'display' => true,
                        'text' => 'Zeit (Sekunden)'
                    ]
                ],
                'x' => [
                    'title' => [
                        'display' => true,
                        'text' => 'Datum'
                    ]
                ]
            ]
        ]
    ];

    // Chart-Konfiguration als JSON kodieren und URL für QuickChart erstellen
    $chartConfigJson = json_encode($chartConfig);
    $chartUrl = 'https://quickchart.io/chart?c=' . urlencode($chartConfigJson);

    // QR-Code erstellen, wenn gewünscht
    $qrCodeHtml = '';
    if ($include_qr_code && $public_url) {
        $options = new QROptions([
            'outputType' => QRCode::OUTPUT_IMAGE_PNG,
            'eccLevel' => QRCode::ECC_H,
            'scale' => 5,
        ]);

        $qrcode = (new QRCode($options))->render($public_url);

        $qrCodeDataUri = 'data:image/png;base64,' . base64_encode($qrcode);

        $qrCodeHtml = '<img src="' . $qrCodeDataUri . '" alt="QR Code" style="position: absolute; bottom: 20px; right: 20px;">';
    }

    // HTML-Inhalt für das PDF erstellen
    $html = '
    <html>
    <head>
        <style>
            body {
                font-family: DejaVu Sans, sans-serif;
                margin: 0;
                padding: 0;
            }
            header {
                background-color: #003366;
                color: white;
                text-align: center;
                padding: 10px 0;
            }
            h1 {
                margin: 0;
                font-size: 24px;
            }
            .content {
                padding: 20px;
            }
            .chart {
                text-align: center;
                margin-bottom: 20px;
            }
            .stats {
                margin-bottom: 20px;
            }
            .stats ul {
                list-style-type: none;
                padding: 0;
            }
            .stats li {
                margin-bottom: 5px;
            }
            .details table {
                width: 100%;
                border-collapse: collapse;
            }
            .details th, .details td {
                border: 1px solid #ddd;
                padding: 8px;
            }
            .details th {
                background-color: #f2f2f2;
            }
            footer {
                position: fixed;
                bottom: -30px;
                left: 0;
                right: 0;
                height: 50px;
                font-size: 12px;
                color: gray;
            }
            .footer-line {
                border-top: 1px solid #ddd;
                padding-top: 5px;
                text-align: center;
            }
            .pagenum:before {
                content: counter(page);
            }
        </style>
    </head>
    <body>
        <header>
            <h1>Analyse - ' . htmlspecialchars($swim_style_name) . ' - ' . htmlspecialchars($distance) . ' m</h1>
        </header>
        <div class="content">
            <div class="chart">
                <img src="' . $chartUrl . '" alt="Analyse-Diagramm" style="width:100%; height:auto;">
            </div>
            <div class="stats">
                <h2>Statistiken</h2>
                <ul>
                    <li><strong>Durchschnitt:</strong> ' . formatSeconds($chart_data['average']) . '</li>
                    <li><strong>Bestzeit:</strong> ' . formatSeconds($chart_data['best_time']) . '</li>
                    <li><strong>Median:</strong> ' . formatSeconds($chart_data['median']) . '</li>
                    <li><strong>Standardabweichung:</strong> ' . number_format($chart_data['std_deviation'], 2, ',', '.') . ' Sekunden</li>
                </ul>
            </div>
            <div class="details">
                <h2>Details</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Datum</th>
                            <th>Zeit</th>
                            <th>Abweichung zur Bestzeit (%)</th>
                            <th>Trend</th>
                        </tr>
                    </thead>
                    <tbody>';

    foreach ($times as $index => $entry) {
        $date = date('d.m.Y', strtotime($entry['date']));
        $time_in_seconds = convertTimeToSeconds($entry['time']);
        $formatted_time = formatSeconds($time_in_seconds);
        $current_time = $chart_data['times'][$index];
        $best_time = $chart_data['best_time'];
        $deviation = (($current_time - $best_time) / $best_time) * 100;
        $trend_value = $chart_data['trendline'][$index];
        $formatted_trend = formatSeconds($trend_value);

        $html .= '<tr>
                    <td>' . $date . '</td>
                    <td>' . $formatted_time . '</td>
                    <td>' . number_format($deviation, 2, ',', '.') . '%</td>
                    <td>' . $formatted_trend . '</td>
                  </tr>';
    }

    $html .= '
                    </tbody>
                </table>
            </div>
        </div>
        ' . $qrCodeHtml . '
        <footer>
            <div class="footer-line">
                Dieses Dokument wurde mit SLA-Schwimmen erzeugt. Lizenznehmer: ' . htmlspecialchars($licensee_name) . '
            </div>
            <div style="text-align: center;">Seite <span class="pagenum"></span></div>
        </footer>
    </body>
    </html>';

    // HTML in PDF umwandeln
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    // PDF ausgeben
    $dompdf->stream('analyse_' . $swim_style_name . '_' . $distance . 'm.pdf', ['Attachment' => true]);
    exit();
}

function exportToExcel($chart_data, $times, $swim_style_name, $distance, $licensee_name) {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // Titel setzen
    $sheet->setCellValue('A1', 'Analyse - ' . $swim_style_name . ' - ' . $distance . ' m');
    $sheet->mergeCells('A1:D1');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);

    // Statistiken hinzufügen
    $sheet->setCellValue('A3', 'Durchschnitt');
    $sheet->setCellValue('B3', formatSeconds($chart_data['average']));
    $sheet->setCellValue('A4', 'Bestzeit');
    $sheet->setCellValue('B4', formatSeconds($chart_data['best_time']));
    $sheet->setCellValue('A5', 'Median');
    $sheet->setCellValue('B5', formatSeconds($chart_data['median']));
    $sheet->setCellValue('A6', 'Standardabweichung');
    $sheet->setCellValue('B6', number_format($chart_data['std_deviation'], 2, ',', '.') . ' Sekunden');

    // Überschriften für die Tabelle
    $sheet->setCellValue('A8', 'Datum');
    $sheet->setCellValue('B8', 'Zeit');
    $sheet->setCellValue('C8', 'Abweichung zur Bestzeit (%)');
    $sheet->setCellValue('D8', 'Trend');

    // Daten einfügen
    $row = 9;
    foreach ($times as $index => $entry) {
        $date = date('d.m.Y', strtotime($entry['date']));
        $time_in_seconds = convertTimeToSeconds($entry['time']);
        $formatted_time = formatSeconds($time_in_seconds);
        $current_time = $chart_data['times'][$index];
        $best_time = $chart_data['best_time'];
        $deviation = (($current_time - $best_time) / $best_time) * 100;
        $trend_value = $chart_data['trendline'][$index];
        $formatted_trend = formatSeconds($trend_value);

        $sheet->setCellValue('A' . $row, $date);
        $sheet->setCellValue('B' . $row, $formatted_time);
        $sheet->setCellValue('C' . $row, number_format($deviation, 2, ',', '.') . '%');
        $sheet->setCellValue('D' . $row, $formatted_trend);
        $row++;
    }

    // Info-Satz hinzufügen
    $sheet->setCellValue('A' . ($row + 2), 'Dieses Dokument wurde mit SLA-Schwimmen erzeugt. Lizenznehmer: ' . $licensee_name);

    // Excel-Datei ausgeben
    $writer = new Xlsx($spreadsheet);
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="analyse_' . $swim_style_name . '_' . $distance . 'm.xlsx"');
    $writer->save('php://output');
    exit();
}

// Hauptverarbeitung

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['generate_analysis'])) {
        // Analyse generieren
        $swim_style_id = intval($_POST['swim_style']);
        $distance = intval($_POST['distance']);
        $analyze_all = isset($_POST['analyze_all']);
        $start_date = $_POST['start_date'] ?? '';
        $end_date = $_POST['end_date'] ?? '';
        $chart_type = $_POST['chart_type'] ?? 'line'; // Diagrammtyp aus dem Formular

        // Schwimmartname abrufen
        $swim_style_name = $swim_styles[$swim_style_id] ?? '';

        // Zeiten aus der Datenbank abrufen
        $times = [];
        if ($analyze_all) {
            $query = "SELECT time, date FROM times WHERE user_id = ? AND swim_style_id = ? AND distance = ? ORDER BY date ASC";
            $stmt = $conn->prepare($query);
            if ($stmt) {
                $stmt->bind_param("iii", $user_id, $swim_style_id, $distance);
                $stmt->execute();
                $stmt->bind_result($time, $date);
                while ($stmt->fetch()) {
                    $times[] = ['time' => $time, 'date' => $date];
                }
                $stmt->close();
            } else {
                die("Fehler bei der Vorbereitung der Zeiten-Abfrage: " . $conn->error);
            }
        } else {
            $query = "SELECT time, date FROM times WHERE user_id = ? AND swim_style_id = ? AND distance = ? AND date BETWEEN ? AND ? ORDER BY date ASC";
            $stmt = $conn->prepare($query);
            if ($stmt) {
                $stmt->bind_param("iiiss", $user_id, $swim_style_id, $distance, $start_date, $end_date);
                $stmt->execute();
                $stmt->bind_result($time, $date);
                while ($stmt->fetch()) {
                    $times[] = ['time' => $time, 'date' => $date];
                }
                $stmt->close();
            } else {
                die("Fehler bei der Vorbereitung der Zeiten-Abfrage: " . $conn->error);
            }
        }

        if (!empty($times)) {
            // Daten für Chart.js vorbereiten
            $time_values = [];
            $dates = [];
            foreach ($times as $entry) {
                $t = convertTimeToSeconds($entry['time']);
                if ($t !== null && is_numeric($t)) {
                    $time_values[] = $t;
                    $dates[] = date('d.m.Y', strtotime($entry['date']));
                } else {
                    // Ungültige Zeit, überspringen
                    continue;
                }
            }

            if (count($time_values) > 1) {
                // Statistische Berechnungen
                $average_seconds = array_sum($time_values) / count($time_values);
                $median_seconds = calculateMedian($time_values);
                $variance = calculateVariance($time_values, $average_seconds);
                $std_deviation = sqrt($variance);
                $best_time = min($time_values);
                $worst_time = max($time_values);

                // Lineare Regression durchführen
                // Umwandlung der Datumsangaben in numerische Werte (Timestamp)
                $x_values_numeric = array_map(function($date) {
                    return strtotime($date);
                }, $dates);

                try {
                    $regression_result = linearRegression($x_values_numeric, $time_values);
                    $slope = $regression_result['slope'];
                    $intercept = $regression_result['intercept'];

                    // Trendlinie berechnen
                    $trendline = [];
                    foreach ($x_values_numeric as $x) {
                        $trendline[] = $slope * $x + $intercept;
                    }

                    // Prognose der nächsten Zeit (einen Tag nach dem letzten Datum)
                    $last_date_numeric = end($x_values_numeric);
                    $next_x = $last_date_numeric + 86400; // 86400 Sekunden = 1 Tag
                    $predicted_time = $slope * $next_x + $intercept;

                    // Chart-Daten vorbereiten
                    $chart_data = [
                        'times' => $time_values,
                        'dates' => $dates,
                        'average' => $average_seconds,
                        'median' => $median_seconds,
                        'variance' => $variance,
                        'std_deviation' => $std_deviation,
                        'best_time' => $best_time,
                        'worst_time' => $worst_time,
                        'trendline' => $trendline,
                        'predicted_time' => $predicted_time
                    ];

                    // Analyse-Daten in der Session speichern
                    $_SESSION['chart_data'] = $chart_data;
                    $_SESSION['times'] = $times;
                    $_SESSION['swim_style_id'] = $swim_style_id;
                    $_SESSION['distance'] = $distance;
                    $_SESSION['analyze_all'] = $analyze_all;
                    $_SESSION['start_date'] = $start_date;
                    $_SESSION['end_date'] = $end_date;
                    $_SESSION['chart_type'] = $chart_type;

                } catch (Exception $e) {
                    $error_message = 'Fehler bei der Berechnung der linearen Regression: ' . $e->getMessage();
                }
            } else {
                $error_message = 'Nicht genügend gültige Daten vorhanden, um eine Analyse zu erstellen.';
            }
        } else {
            $error_message = 'Keine Daten vorhanden, um eine Analyse zu erstellen.';
        }
    } elseif (isset($_POST['export_analysis'])) {
        // Export durchführen
        // Hole die notwendigen Parameter aus der Session
        $swim_style_id = intval($_SESSION['swim_style_id'] ?? 0);
        $distance = intval($_SESSION['distance'] ?? 0);
        $analyze_all = $_SESSION['analyze_all'] ?? false;
        $start_date = $_SESSION['start_date'] ?? '';
        $end_date = $_SESSION['end_date'] ?? '';
        $chart_type = $_SESSION['chart_type'] ?? 'line';
        $include_qr_code = isset($_POST['include_qr_code']);
        $export_format = $_POST['export_format'] ?? '';

        // Schwimmartname abrufen
        $swim_style_name = $swim_styles[$swim_style_id] ?? '';

        // Zeiten und Analyse-Daten aus der Session abrufen
        $chart_data = $_SESSION['chart_data'] ?? null;
        $times = $_SESSION['times'] ?? null;

        if (empty($chart_data) || empty($times)) {
            $error_message = 'Keine Analyse-Daten gefunden. Bitte erstelle zuerst eine Analyse.';
        } else {
            // Erstellen eines öffentlichen Links, wenn QR-Code eingebunden werden soll
            $public_url = '';
            if ($include_qr_code) {
                // Einen eindeutigen Token generieren
                $token = bin2hex(random_bytes(16));

                // Token in der Datenbank speichern mit Verknüpfung zur Analyse
                $query = "INSERT INTO public_analyses (user_id, swim_style_id, distance, token, created_at) VALUES (?, ?, ?, ?, NOW())";
                $stmt = $conn->prepare($query);
                if ($stmt) {
                    $stmt->bind_param("iiis", $user_id, $swim_style_id, $distance, $token);
                    $stmt->execute();
                    $stmt->close();

                    // Öffentliche URL generieren
                    $public_url = 'https://deine-domain.de/public_analysis.php?token=' . $token;
                } else {
                    die("Fehler beim Speichern des öffentlichen Tokens: " . $conn->error);
                }
            }

            // Export durchführen
            if ($export_format === 'csv') {
                exportToCSV($times, $swim_style_name, $distance);
            } elseif ($export_format === 'pdf') {
                exportToPDF($chart_data, $times, $swim_style_name, $distance, $analyze_all, $start_date, $end_date, $licensee_name, $chart_type, $include_qr_code, $public_url);
            } elseif ($export_format === 'excel') {
                exportToExcel($chart_data, $times, $swim_style_name, $distance, $licensee_name);
            }
        }
    }
}

// Verbindung schließen
if ($conn && $conn->ping()) {
    $conn->close();
}
?>
<!-- HTML-Teil -->
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Analyse</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Bootstrap CSS und Chart.js -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css">
    <!-- Eigene CSS-Anpassungen -->
    <style>
        body {
            padding-top: 56px;
            background-color: #f8f9fa;
        }
        .chart-container {
            position: relative;
            height: 500px;
        }
        .card {
            margin-bottom: 20px;
        }
        .btn-custom {
            background-color: #007bff;
            color: #fff;
        }
        .btn-custom:hover {
            background-color: #0056b3;
        }
        /* Responsive Anpassungen */
        @media (max-width: 768px) {
            .chart-container {
                height: 300px;
            }
            .table-responsive {
                overflow-x: auto;
            }
        }
    </style>
</head>
<body>
    <!-- Menü einbinden -->
    <?php require_once(__DIR__ . '/../menu.php'); ?>

    <div class="container-fluid">
        <div class="row">
            <!-- Hauptinhalt -->
            <main class="col-12 px-md-4">
                <h2 class="mt-4">Analyse</h2>

                <?php if ($error_message): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header">
                        <h4>Analyse erstellen</h4>
                    </div>
                    <div class="card-body">
                        <form method="post" action="analyse.php">
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
                            <!-- Zeitraum wählen -->
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="analyze_all" name="analyze_all" <?php if ($analyze_all) echo 'checked'; ?>>
                                <label class="form-check-label" for="analyze_all">Alle Daten analysieren (Zeitspanne deaktivieren)</label>
                            </div>
                            <div id="date-range" <?php if ($analyze_all) echo 'style="display:none;"'; ?>>
                                <div class="mb-3">
                                    <label for="start_date" class="form-label">Startdatum</label>
                                    <input type="date" name="start_date" id="start_date" class="form-control" value="<?php echo htmlspecialchars($start_date); ?>">
                                </div>
                                <div class="mb-3">
                                    <label for="end_date" class="form-label">Enddatum</label>
                                    <input type="date" name="end_date" id="end_date" class="form-control" value="<?php echo htmlspecialchars($end_date); ?>">
                                </div>
                            </div>
                            <!-- Diagrammtyp auswählen -->
                            <div class="mb-3">
                                <label for="chart_type" class="form-label">Diagrammtyp</label>
                                <select name="chart_type" id="chart_type" class="form-select">
                                    <option value="line" <?php if ($chart_type == 'line') echo 'selected'; ?>>Liniendiagramm</option>
                                    <option value="bar" <?php if ($chart_type == 'bar') echo 'selected'; ?>>Balkendiagramm</option>
                                </select>
                            </div>
                            <button type="submit" name="generate_analysis" class="btn btn-custom">Analyse erstellen</button>
                        </form>
                    </div>
                </div>

                <?php if (isset($chart_data) && !empty($chart_data['times'])): ?>
                    <!-- Analyse anzeigen -->
                    <div class="card">
                        <div class="card-header text-center">
                            <h3><?php echo htmlspecialchars($swim_style_name) . ' - ' . htmlspecialchars($distance) . ' m'; ?></h3>
                            <?php if (!$analyze_all): ?>
                                <p>Zeitraum: <?php echo date('d.m.Y', strtotime($start_date)); ?> - <?php echo date('d.m.Y', strtotime($end_date)); ?></p>
                            <?php else: ?>
                                <p>Alle verfügbaren Daten</p>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="myChart"></canvas>
                            </div>
                            <!-- Statistiken -->
                            <div class="row mt-4">
                                <div class="col-md-3 col-sm-6">
                                    <div class="alert alert-info text-center">
                                        <h5>Durchschnitt</h5>
                                        <p><?php echo formatSeconds($chart_data['average']); ?></p>
                                    </div>
                                </div>
                                <div class="col-md-3 col-sm-6">
                                    <div class="alert alert-success text-center">
                                        <h5>Bestzeit</h5>
                                        <p><?php echo formatSeconds($chart_data['best_time']); ?></p>
                                    </div>
                                </div>
                                <div class="col-md-3 col-sm-6">
                                    <div class="alert alert-warning text-center">
                                        <h5>Median</h5>
                                        <p><?php echo formatSeconds($chart_data['median']); ?></p>
                                    </div>
                                </div>
                                <div class="col-md-3 col-sm-6">
                                    <div class="alert alert-secondary text-center">
                                        <h5>Standardabweichung</h5>
                                        <p><?php echo number_format($chart_data['std_deviation'], 2, ',', '.') . ' Sekunden'; ?></p>
                                    </div>
                                </div>
                            </div>
                            <!-- Prognose zukünftiger Zeiten -->
                            <h4 class="mt-4">Prognose der nächsten Zeit:</h4>
                            <p>Basierend auf deinem bisherigen Fortschritt wird deine nächste Zeit auf <strong><?php echo formatSeconds($chart_data['predicted_time']); ?></strong> geschätzt.</p>
                        </div>
                    </div>

                    <!-- Tabelle mit den Daten -->
                    <div class="card mt-4">
                        <div class="card-header">
                            <h4>Details</h4>
                        </div>
                        <div class="card-body table-responsive">
                            <table class="table table-striped" id="dataTable">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Datum</th>
                                        <th>Zeit</th>
                                        <th>Abweichung zur Bestzeit (%)</th>
                                        <th>Trend</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($times as $index => $entry): ?>
                                        <tr>
                                            <td><?php echo date('d.m.Y', strtotime($entry['date'])); ?></td>
                                            <td><?php echo formatSeconds(convertTimeToSeconds($entry['time'])); ?></td>
                                            <td><?php
                                                $current_time = $chart_data['times'][$index];
                                                $best_time = $chart_data['best_time'];
                                                $deviation = (($current_time - $best_time) / $best_time) * 100;
                                                echo number_format($deviation, 2, ',', '.') . '%';
                                            ?></td>
                                            <td><?php
                                                $trend_value = $chart_data['trendline'][$index];
                                                echo formatSeconds($trend_value);
                                            ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Exportoptionen -->
                    <div class="card mt-4">
                        <div class="card-header">
                            <h4>Analyse exportieren</h4>
                        </div>
                        <div class="card-body">
                            <form method="post" action="analyse.php">
                                <!-- Exportformat auswählen -->
                                <div class="mb-3">
                                    <label for="export_format" class="form-label">Exportformat</label>
                                    <select name="export_format" id="export_format" class="form-select">
                                        <option value="csv">CSV</option>
                                        <option value="pdf">PDF</option>
                                        <option value="excel">Excel</option>
                                    </select>
                                </div>
                                <!-- QR-Code Option (nur für PDF) -->
                                <div class="mb-3 form-check">
                                    <input type="checkbox" class="form-check-input" id="include_qr_code" name="include_qr_code" <?php if ($include_qr_code) echo 'checked'; ?>>
                                    <label class="form-check-label" for="include_qr_code">QR-Code im PDF einbinden</label>
                                </div>
                                <button type="submit" name="export_analysis" class="btn btn-custom">Analyse exportieren</button>
                            </form>
                        </div>
                    </div>

                <?php endif; ?>
            </main>
        </div>
    </div>

    <!-- Bootstrap JS und Popper.js -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- jQuery und DataTables JS -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>

    <!-- Custom JS -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Zeitspanne deaktivieren
            const analyzeAllCheckbox = document.getElementById('analyze_all');
            const dateRangeDiv = document.getElementById('date-range');

            analyzeAllCheckbox.addEventListener('change', function() {
                if (this.checked) {
                    dateRangeDiv.style.display = 'none';
                } else {
                    dateRangeDiv.style.display = 'block';
                }
            });

            <?php if (isset($chart_data) && !empty($chart_data['times'])): ?>
                // Daten für das Diagramm vorbereiten
                const ctx = document.getElementById('myChart').getContext('2d');
                const timesInSeconds = <?php echo json_encode($chart_data['times']); ?>;
                const dates = <?php echo json_encode($chart_data['dates']); ?>;
                const averageSeconds = <?php echo $chart_data['average']; ?>;
                const bestTime = <?php echo $chart_data['best_time']; ?>;
                const trendline = <?php echo json_encode($chart_data['trendline']); ?>;

                // Durchschnittslinie erstellen
                const averageLine = new Array(dates.length).fill(averageSeconds);
                // Bestzeit-Linie
                const bestTimeLine = new Array(dates.length).fill(bestTime);

                // Zeit formatieren
                function formatTime(seconds) {
                    const minutes = Math.floor(seconds / 60);
                    const remainingSeconds = seconds - minutes * 60;
                    const wholeSeconds = Math.floor(remainingSeconds);
                    const milliseconds = Math.round((remainingSeconds - wholeSeconds) * 100);
                    return `${minutes.toString().padStart(2, '0')}:${wholeSeconds.toString().padStart(2, '0')},${milliseconds.toString().padStart(2, '0')}`;
                }

                // Diagramm erstellen
                const myChart = new Chart(ctx, {
                    type: '<?php echo $chart_type; ?>',
                    data: {
                        labels: dates,
                        datasets: [{
                            label: 'Zeiten',
                            data: timesInSeconds,
                            borderColor: 'blue',
                            backgroundColor: 'rgba(0, 0, 255, 0.1)',
                            fill: true,
                            tension: 0.1,
                            pointBackgroundColor: 'blue',
                            pointRadius: 5
                        }, {
                            label: 'Durchschnitt',
                            data: averageLine,
                            borderColor: 'red',
                            borderDash: [5, 5],
                            fill: false,
                            tension: 0.1
                        }, {
                            label: 'Bestzeit',
                            data: bestTimeLine,
                            borderColor: 'gold',
                            borderDash: [2, 2],
                            fill: false,
                            tension: 0.1
                        }, {
                            label: 'Trendlinie',
                            data: trendline,
                            borderColor: 'green',
                            borderDash: [3, 3],
                            fill: false,
                            tension: 0
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                title: {
                                    display: true,
                                    text: 'Zeit (Minuten:Sekunden,Millisekunden)'
                                },
                                ticks: {
                                    callback: function(value) {
                                        return formatTime(value);
                                    }
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
                                        return context.dataset.label + ': ' + formatTime(context.parsed.y);
                                    }
                                }
                            }
                        }
                    }
                });

                // DataTables initialisieren
                $(document).ready(function() {
                    $('#dataTable').DataTable({
                        "language": {
                            "url": "//cdn.datatables.net/plug-ins/1.11.5/i18n/de_de.json"
                        }
                    });
                });
            <?php endif; ?>
        });
    </script>
</body>
</html>
