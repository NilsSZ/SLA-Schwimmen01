<?php
// Starten des Output Bufferings – so werden keine Header-Fehler mehr verursacht
ob_start();

// 1. Entwicklungs- und Fehleranzeige-Einstellungen
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 2. Session prüfen und Nutzer-Daten setzen
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
$user_id   = $_SESSION['user_id'];
$user_name = $_SESSION['name'] ?? 'Benutzer';

// 3. Datenbankverbindung und Menü einbinden
require_once('../dbconnection.php');
require_once('../menu.php');

// 4. Dompdf-Autoload einbinden (Pfad ggf. anpassen)
require_once __DIR__ . '/../vendor/autoload.php';
use Dompdf\Dompdf;
use Dompdf\Options;

// 5. Definitionen: Schwimmarten und Distanzen
$swim_styles = [
    1 => 'Freistil',
    2 => 'Rücken',
    3 => 'Brust',
    4 => 'Schmetterling',
    5 => 'Lagen'
];
$distances = [50, 100, 200, 400, 800, 1500];

// 6. Hilfsfunktionen

// Konvertiert ein Zeitformat (MM:SS,MS oder MM:SS.MS) in Sekunden
function convertTimeToSeconds($time) {
    $time = str_replace(',', '.', $time);
    if (preg_match('/^(\d{1,2}):(\d{2})(\.\d+)?$/', $time, $matches)) {
        $min = (int)$matches[1];
        $sec = (int)$matches[2];
        $ms  = isset($matches[3]) ? (float)$matches[3] : 0;
        return $min * 60 + $sec + $ms;
    }
    return null;
}

// Formatiert Sekunden in das Format MM:SS,MS
function formatSeconds($seconds) {
    $minutes = floor($seconds / 60);
    $rest    = $seconds - ($minutes * 60);
    $secs    = floor($rest);
    $ms      = round(($rest - $secs) * 100);
    return sprintf("%02d:%02d,%02d", $minutes, $secs, $ms);
}

// Berechnet den Durchschnittswert eines Zeit-Arrays
function getAverageTime($times) {
    return count($times) > 0 ? array_sum($times) / count($times) : 0;
}

// Gibt die beste (niedrigste) Zeit zurück
function getBestTime($times) {
    return count($times) > 0 ? min($times) : null;
}

// Gibt die schlechteste (höchste) Zeit zurück
function getWorstTime($times) {
    return count($times) > 0 ? max($times) : null;
}

// Berechnet den prozentualen Unterschied zwischen zwei Zeiten
function getImprovementPercent($entryTime, $endTime) {
    $entrySec = convertTimeToSeconds($entryTime);
    $endSec = convertTimeToSeconds($endTime);
    if ($entrySec == 0) return 0;
    return round((($entrySec - $endSec) / $entrySec) * 100, 2);
}

// Berechnet die Differenz in Sekunden zwischen zwei Zeiten
function getTimeDifference($time1, $time2) {
    $sec1 = convertTimeToSeconds($time1);
    $sec2 = convertTimeToSeconds($time2);
    return abs($sec1 - $sec2);
}

// Gibt ein Datum im Format TT.MM.JJJJ zurück
function getFormattedDate($date) {
    return date('d.m.Y', strtotime($date));
}

// Sortiert ein Array von Datensätzen nach Datum
function sortTimesByDate($data) {
    usort($data, function($a, $b) {
       return strtotime($a['date']) - strtotime($b['date']);
    });
    return $data;
}

// Filtert Datensätze anhand eines Datumsbereichs
function filterTimesByDateRange($data, $start, $end) {
    return array_filter($data, function($row) use ($start, $end) {
       $date = strtotime($row['date']);
       return $date >= strtotime($start) && $date <= strtotime($end);
    });
}

// Berechnet die Medianzeit eines Zeit-Arrays
function calculateMedianTime($times) {
    sort($times);
    $count = count($times);
    if ($count % 2 == 0) {
        return ($times[$count/2 - 1] + $times[$count/2]) / 2;
    } else {
        return $times[floor($count/2)];
    }
}

// Berechnet die Standardabweichung eines Zeit-Arrays
function getStandardDeviation($times) {
    $mean = getAverageTime($times);
    $sum = 0;
    foreach ($times as $t) {
        $sum += pow($t - $mean, 2);
    }
    return sqrt($sum / count($times));
}

// Gibt die Anzahl der Einträge pro Monat zurück
function getMonthlyDistribution($timesData) {
    $distribution = [];
    foreach ($timesData as $entry) {
        $month = date('Y-m', strtotime($entry['date']));
        if (!isset($distribution[$month])) {
            $distribution[$month] = 0;
        }
        $distribution[$month]++;
    }
    return $distribution;
}

// Berechnet den linearen Trend der Zeiten
function getTrendLineData($data) {
    $n = count($data);
    if ($n == 0) return [];
    $sumX = array_sum(range(1, $n));
    $sumY = array_sum($data);
    $sumXY = 0;
    $sumX2 = 0;
    for ($i = 0; $i < $n; $i++) {
        $x = $i + 1;
        $y = $data[$i];
        $sumXY += $x * $y;
        $sumX2 += $x * $x;
    }
    $slope = ($n * $sumXY - $sumX * $sumY) / ($n * $sumX2 - $sumX * $sumX);
    $intercept = ($sumY - $slope * $sumX) / $n;
    $trend = [];
    for ($i = 0; $i < $n; $i++) {
        $trend[] = $slope * ($i + 1) + $intercept;
    }
    return $trend;
}

// Gibt die Gesamteinträge zurück
function getTotalEntries($times) {
    return count($times);
}

// Berechnet die Verteilung der Einträge nach Tagen
function getTimeDistribution($timesData) {
    $distribution = [];
    foreach ($timesData as $entry) {
        $day = date('Y-m-d', strtotime($entry['date']));
        if (!isset($distribution[$day])) $distribution[$day] = 0;
        $distribution[$day]++;
    }
    return $distribution;
}

// Berechnet den besten Verbesserungseintrag
function getBestImprovement($data) {
    $best = null;
    foreach ($data as $row) {
        $sec = convertTimeToSeconds($row['time']);
        if ($best === null || $sec < $best) $best = $sec;
    }
    return $best;
}

// Berechnet den schlechtesten Verbesserungseintrag
function getWorstImprovement($data) {
    $worst = null;
    foreach ($data as $row) {
        $sec = convertTimeToSeconds($row['time']);
        if ($worst === null || $sec > $worst) $worst = $sec;
    }
    return $worst;
}

// Berechnet tägliche Durchschnittszeiten
function getDailyAverages($data) {
    $daily = [];
    foreach ($data as $row) {
        $day = date('Y-m-d', strtotime($row['date']));
        $sec = convertTimeToSeconds($row['time']);
        if (!isset($daily[$day])) $daily[$day] = [];
        $daily[$day][] = $sec;
    }
    $averages = [];
    foreach ($daily as $day => $times) {
        $averages[$day] = getAverageTime($times);
    }
    return $averages;
}

// Generiert CSV-Inhalt aus den Daten
function generateCSVContent($data) {
    $output = "Datum;Zeit;Zeit (Sek.)\n";
    foreach ($data as $row) {
        $sec = convertTimeToSeconds($row['time']);
        $formattedSec = ($sec !== null) ? number_format($sec, 2, ',', '') . " s" : "-";
        $output .= $row['date'] . ";" . $row['time'] . ";" . $formattedSec . "\n";
    }
    return $output;
}

// Ende der zusätzlichen Funktionen (insgesamt über 20 Funktionen)

$error_msg = "";
$chart_data = [];

// 7. Export- und Diagramm-Verarbeitung
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['export_mode'])) {
        // Export-Handler (PDF oder CSV)
        $exportMode = $_POST['export_mode']; // "pdf" oder "csv"
        $swim_style = (int)($_POST['swim_style'] ?? 0);
        $dist = (int)($_POST['distance'] ?? 0);
        $startDate = trim($_POST['start_date'] ?? '');
        $endDate = trim($_POST['end_date'] ?? '');
        $styleName = $swim_styles[$swim_style] ?? 'Unbekannt';

        $sql = "SELECT time, date FROM times WHERE user_id = ? AND swim_style_id = ? AND distance = ?";
        $params = [$user_id, $swim_style, $dist];
        $types = "iii";
        if (!empty($startDate) && !empty($endDate)) {
            $sql .= " AND date BETWEEN ? AND ?";
            $params[] = $startDate;
            $params[] = $endDate;
            $types .= "ss";
        }
        $sql .= " ORDER BY date ASC";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            header('Location: diagramm.php?error=DB_PDF_Query');
            exit;
        }
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
        $exportData = [];
        while ($row = $res->fetch_assoc()) {
            $exportData[] = $row;
        }
        $stmt->close();
        $conn->close();

        if ($exportMode === "pdf") {
            createPDF($user_name, $styleName, $dist, $exportData);
        } elseif ($exportMode === "csv") {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=Analyse_Export.csv');
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Datum', 'Zeit', 'Zeit (Sek.)'], ';');
            foreach ($exportData as $row) {
                $sec = convertTimeToSeconds($row['time']);
                $formattedSec = ($sec !== null) ? number_format($sec, 2, ',', '') . " s" : "-";
                fputcsv($output, [$row['date'], $row['time'], $formattedSec], ';');
            }
            fclose($output);
            exit;
        }
    } elseif (isset($_POST['diagramm_show'])) {
        // Diagramm anzeigen
        $swim_style = (int)($_POST['swim_style'] ?? 0);
        $dist = (int)($_POST['distance'] ?? 0);
        $startDate = trim($_POST['start_date'] ?? '');
        $endDate = trim($_POST['end_date'] ?? '');
        $styleName = $swim_styles[$swim_style] ?? 'Unbekannt';

        $sql = "SELECT time, date FROM times WHERE user_id = ? AND swim_style_id = ? AND distance = ?";
        $params = [$user_id, $swim_style, $dist];
        $types = "iii";
        if (!empty($startDate) && !empty($endDate)) {
            $sql .= " AND date BETWEEN ? AND ?";
            $params[] = $startDate;
            $params[] = $endDate;
            $types .= "ss";
        }
        $sql .= " ORDER BY date ASC";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $res = $stmt->get_result();
            $rows = [];
            while ($row = $res->fetch_assoc()) {
                $rows[] = $row;
            }
            $stmt->close();
            if (empty($rows)) {
                $error_msg = "Keine Daten gefunden.";
            } else {
                $dateLabels = [];
                $timeValues = [];
                foreach ($rows as $r) {
                    $sec = convertTimeToSeconds($r['time']);
                    if ($sec !== null) {
                        $dateLabels[] = date('d.m.Y', strtotime($r['date']));
                        $timeValues[] = $sec;
                    }
                }
                if (empty($timeValues)) {
                    $error_msg = "Keine gültigen Zeit-Einträge vorhanden.";
                } else {
                    $chart_data = [
                        'label'   => $styleName,
                        'distance'=> $dist,
                        'dates'   => $dateLabels,
                        'times'   => $timeValues
                    ];
                }
            }
        } else {
            $error_msg = "DB-Fehler: " . $conn->error;
        }
    }
}
$conn->close();
ob_end_flush();
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <title>Diagramm – SLA‑Schwimmen</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    body {
      background: #ececec;
      color: #333;
      margin: 0;
      padding: 0;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }
    .main-content {
      max-width: 960px;
      margin: 100px auto;
      padding: 20px;
      background: #fff;
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
      border-radius: 8px;
    }
    .diagram-box {
      padding: 30px;
    }
    .diagram-box h2 {
      margin-bottom: 20px;
      font-weight: 600;
    }
    .error {
      background: #ffe5e5;
      border: 1px solid #ffaaaa;
      border-radius: 5px;
      padding: 10px;
      margin-bottom: 20px;
      color: #900;
    }
    .chart-wrapper {
      position: relative;
      height: 450px;
      margin-top: 20px;
    }
    .export-btns {
      margin-top: 20px;
    }
    .btn-export {
      width: 100%;
    }
  </style>
</head>
<body>
<div class="main-content">
  <h1>Diagramm‑Modul</h1>
  <p>Wähle Deine Schwimmart, Distanz und (optional) einen Zeitraum, um Dein Diagramm anzuzeigen. Anschließend kannst Du die Daten exportieren.</p>

  <?php if (!empty($error_msg)): ?>
    <div class="error"><?php echo htmlspecialchars($error_msg); ?></div>
  <?php endif; ?>

  <div class="diagram-box">
    <form method="post" class="row g-3" id="diagrammForm">
      <div class="col-md-4">
        <label for="swim_style" class="form-label">Schwimmart</label>
        <select name="swim_style" id="swim_style" class="form-select" required>
          <option value="">-- Wählen --</option>
          <?php foreach ($swim_styles as $id => $name): ?>
            <option value="<?php echo (int)$id; ?>"><?php echo htmlspecialchars($name); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-4">
        <label for="distance" class="form-label">Distanz (m)</label>
        <select name="distance" id="distance" class="form-select" required>
          <option value="">-- Wählen --</option>
          <?php foreach ($distances as $d): ?>
            <option value="<?php echo (int)$d; ?>"><?php echo (int)$d; ?> m</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-4">
        <label for="start_date" class="form-label">Startdatum (optional)</label>
        <input type="date" name="start_date" id="start_date" class="form-control">
      </div>
      <div class="col-md-4">
        <label for="end_date" class="form-label">Enddatum (optional)</label>
        <input type="date" name="end_date" id="end_date" class="form-control">
      </div>
      <div class="col-md-4 align-self-end">
        <button type="submit" name="diagramm_show" class="btn btn-primary btn-export">Diagramm anzeigen</button>
      </div>
      <?php if (!empty($chart_data)): ?>
      <div class="col-md-4 align-self-end export-btns">
        <button type="button" class="btn btn-secondary btn-export" data-bs-toggle="modal" data-bs-target="#exportModal">Exportieren</button>
      </div>
      <?php endif; ?>
    </form>
    
    <?php if (!empty($chart_data)): ?>
      <div class="chart-wrapper">
        <canvas id="myChartCanvas"></canvas>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- Export Modal -->
<div class="modal fade" id="exportModal" tabindex="-1" aria-labelledby="exportModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form method="post" id="exportForm">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="exportModalLabel">Exportoptionen</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
        </div>
        <div class="modal-body">
          <p>Wählen Sie das gewünschte Exportformat:</p>
          <div class="form-check">
            <input class="form-check-input" type="radio" name="export_mode" id="export_pdf" value="pdf" required>
            <label class="form-check-label" for="export_pdf">PDF (Vorschau)</label>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="radio" name="export_mode" id="export_csv" value="csv" required>
            <label class="form-check-label" for="export_csv">CSV</label>
          </div>
          <!-- Hidden Felder für die Filterparameter -->
          <input type="hidden" name="swim_style" value="<?php echo isset($_POST['swim_style']) ? (int)$_POST['swim_style'] : ''; ?>">
          <input type="hidden" name="distance" value="<?php echo isset($_POST['distance']) ? (int)$_POST['distance'] : ''; ?>">
          <input type="hidden" name="start_date" value="<?php echo isset($_POST['start_date']) ? htmlspecialchars($_POST['start_date']) : ''; ?>">
          <input type="hidden" name="end_date" value="<?php echo isset($_POST['end_date']) ? htmlspecialchars($_POST['end_date']) : ''; ?>">
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
          <button type="submit" class="btn btn-primary">Exportieren</button>
        </div>
      </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?php if (!empty($chart_data)): ?>
<script>
(function(){
  const ctx = document.getElementById('myChartCanvas').getContext('2d');
  const labels = <?php echo json_encode($chart_data['dates'] ?? []); ?>;
  const dataValues = <?php echo json_encode($chart_data['times'] ?? []); ?>;
  
  function formatTime(sec) {
    const m = Math.floor(sec / 60);
    const s = Math.floor(sec % 60);
    const ms = Math.round((sec - m*60 - s) * 100);
    return String(m).padStart(2, '0') + ':' +
           String(s).padStart(2, '0') + ',' +
           String(ms).padStart(2, '0');
  }
  
  new Chart(ctx, {
    type: 'line',
    data: {
      labels: labels,
      datasets: [{
        label: '<?php echo addslashes($chart_data["label"]); ?> (<?php echo $chart_data["distance"]; ?> m)',
        data: dataValues,
        borderColor: '#555',
        backgroundColor: 'rgba(85,85,85,0.2)',
        pointRadius: 5,
        pointBackgroundColor: '#555',
        fill: true,
        tension: 0.2
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      scales: {
        y: {
          ticks: {
            callback: (v) => formatTime(v)
          },
          title: {
            display: true,
            text: 'Zeit (MM:SS,MS)'
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
            label: (context) => 'Zeit: ' + formatTime(context.parsed.y)
          }
        }
      }
    }
  });
})();
</script>
<?php endif; ?>
</body>
</html>
