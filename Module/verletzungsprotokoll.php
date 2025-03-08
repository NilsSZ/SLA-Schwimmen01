<?php
/********************************************************
 * VERLETZUNGSPROTOKOLL – Modul 19
 * 
 * Hier kannst Du einen neuen Verletzungsprotokolleintrag erfassen:
 * - Wähle einen Verletzungstyp (z. B. "Schirf-Wunde", "Schnupfen",
 *   "Muskelzerrung", "Verstauchung", "Prellung", "Sonstiges")
 * - Gib den Datumsbereich (Beginn und Ende) an
 * - Setze optional, ob ein Krankenhausbesuch stattgefunden hat.
 * 
 * Im zweiten Tab werden alle Deine Einträge sowie ein Diagramm angezeigt,
 * das pro Monat zählt, wie viele Einträge (Verletzungen) Du erfasst hast.
 ********************************************************/

// Fehleranzeige aktivieren (nur in der Entwicklung!)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Session starten und Login-Check
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
$user_id   = $_SESSION['user_id'];
$user_name = $_SESSION['name'] ?? 'Benutzer';

// Datenbankverbindung einbinden
require_once 'dbconnection.php';

// Flash-Funktionen
function setFlash($key, $msg) {
    $_SESSION['flash'][$key] = $msg;
}
function getFlash($key) {
    if (isset($_SESSION['flash'][$key])) {
        $m = $_SESSION['flash'][$key];
        unset($_SESSION['flash'][$key]);
        return $m;
    }
    return null;
}
$error_message   = getFlash('error');
$success_message = getFlash('success');

// Mögliche Verletzungstypen
$injury_types = [
    'Schirf-Wunde',
    'Schnupfen',
    'Muskelzerrung',
    'Verstauchung',
    'Prellung',
    'Sonstiges'
];

// Formularverarbeitung – Neuer Eintrag
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_entry'])) {
    $injury_type      = trim($_POST['injury_type'] ?? '');
    $start_date_input = $_POST['start_date'] ?? '';
    $end_date_input   = $_POST['end_date'] ?? '';
    $hospital         = isset($_POST['hospital_visited']) ? 1 : 0;

    // Validierung
    if (empty($injury_type) || !in_array($injury_type, $injury_types)) {
        $error_message = 'Bitte wählen Sie einen gültigen Verletzungstyp.';
    } elseif (empty($start_date_input) || empty($end_date_input)) {
        $error_message = 'Bitte geben Sie den gesamten Datumsbereich an.';
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date_input) || !strtotime($start_date_input)) {
        $error_message = 'Ungültiges Startdatum. Bitte verwenden Sie das Format YYYY-MM-DD.';
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date_input) || !strtotime($end_date_input)) {
        $error_message = 'Ungültiges Enddatum. Bitte verwenden Sie das Format YYYY-MM-DD.';
    } elseif (strtotime($end_date_input) < strtotime($start_date_input)) {
        $error_message = 'Das Enddatum darf nicht vor dem Startdatum liegen.';
    }

    if (empty($error_message)) {
        $stmt = $conn->prepare("INSERT INTO injury_protocols (user_id, injury_type, start_date, end_date, hospital_visited) VALUES (?, ?, ?, ?, ?)");
        if (!$stmt) {
            $error_message = 'Datenbankfehler: ' . $conn->error;
        } else {
            $stmt->bind_param("isssi", $user_id, $injury_type, $start_date_input, $end_date_input, $hospital);
            if ($stmt->execute()) {
                $success_message = 'Neuer Protokolleintrag wurde erfolgreich gespeichert.';
            } else {
                $error_message = 'Fehler beim Speichern: ' . $stmt->error;
            }
            $stmt->close();
        }
        // Nach dem Speichern leiten wir zurück (um Doppelspeicherungen zu vermeiden)
        if (!empty($error_message)) {
            setFlash('error', $error_message);
        } else {
            setFlash('success', $success_message);
        }
        header("Location: verletzungsprotokoll.php");
        exit();
    } else {
        setFlash('error', $error_message);
        header("Location: verletzungsprotokoll.php");
        exit();
    }
}

// Jetzt: Alle Protokolleinträge für den Nutzer abrufen (Sortierung: neueste zuerst)
$stmt = $conn->prepare("SELECT id, injury_type, start_date, end_date, hospital_visited, created_at FROM injury_protocols WHERE user_id = ? ORDER BY start_date DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$protocols = [];
while ($row = $result->fetch_assoc()) {
    $protocols[] = $row;
}
$stmt->close();

// Für das Diagramm: Zähle, wie oft in jedem Monat (basierend auf dem Startdatum) ein Eintrag erfolgt ist
$monthlyCounts = array_fill(1, 12, 0);
foreach ($protocols as $p) {
    $month = (int) date('n', strtotime($p['start_date']));
    $monthlyCounts[$month]++;
}
$labels = [];
$dataPoints = [];
for ($m = 1; $m <= 12; $m++) {
    $labels[] = date('F', mktime(0, 0, 0, $m, 10));
    $dataPoints[] = $monthlyCounts[$m];
}

$conn->close();
ob_end_flush();
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <title>Verletzungsprotokoll – SLA-Schwimmen</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- Bootstrap CSS & Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.min.css">
  <!-- Chart.js -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    body {
      background: #f8f9fa;
      padding-top: 70px;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }
    .hero {
      background: linear-gradient(135deg, #1e3d59, #2a5298);
      color: #fff;
      padding: 2rem;
      border-radius: 0.5rem;
      text-align: center;
      margin-bottom: 30px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.2);
    }
    .nav-tabs .nav-link {
      font-weight: bold;
    }
    .tab-content {
      margin-top: 20px;
    }
    /* Tabelle */
    table thead th {
      background: #005599;
      color: #fff;
    }
  </style>
</head>
<body>
  <?php include 'menu.php'; ?>

  <div class="container">
    <div class="hero">
      <h1>Verletzungsprotokoll</h1>
      <p>Erfasse Deine Verletzungen und behalte den Überblick</p>
    </div>

    <!-- Flash-Meldungen -->
    <?php if ($error_message): ?>
      <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
    <?php endif; ?>
    <?php if ($success_message): ?>
      <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
    <?php endif; ?>

    <!-- Bootstrap Tabs -->
    <ul class="nav nav-tabs" id="protocolTabs" role="tablist">
      <li class="nav-item" role="presentation">
        <button class="nav-link active" id="newEntry-tab" data-bs-toggle="tab" data-bs-target="#newEntry" type="button" role="tab" aria-controls="newEntry" aria-selected="true">Neuer Eintrag</button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="overview-tab" data-bs-toggle="tab" data-bs-target="#overview" type="button" role="tab" aria-controls="overview" aria-selected="false">Übersicht & Diagramm</button>
      </li>
    </ul>
    <div class="tab-content" id="protocolTabsContent">
      <!-- Tab 1: Neuer Eintrag -->
      <div class="tab-pane fade show active" id="newEntry" role="tabpanel" aria-labelledby="newEntry-tab">
        <div class="card mt-4">
          <div class="card-body">
            <h4 class="card-title mb-3">Neuen Protokolleintrag erfassen</h4>
            <form method="post" action="verletzungsprotokoll.php">
              <input type="hidden" name="new_entry" value="1">
              <div class="mb-3">
                <label for="injury_type" class="form-label">Verletzungstyp</label>
                <select name="injury_type" id="injury_type" class="form-select" required>
                  <option value="">Bitte wählen...</option>
                  <?php foreach ($injury_types as $type): ?>
                    <option value="<?= htmlspecialchars($type) ?>"><?= htmlspecialchars($type) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="mb-3">
                <label for="start_date" class="form-label">Beginn des Zeitraums</label>
                <input type="date" name="start_date" id="start_date" class="form-control" required>
              </div>
              <div class="mb-3">
                <label for="end_date" class="form-label">Ende des Zeitraums</label>
                <input type="date" name="end_date" id="end_date" class="form-control" required>
              </div>
              <div class="form-check mb-3">
                <input type="checkbox" name="hospital_visited" id="hospital_visited" class="form-check-input">
                <label for="hospital_visited" class="form-check-label">Krankenhausbesuch</label>
              </div>
              <button type="submit" class="btn btn-primary">Speichern</button>
            </form>
          </div>
        </div>
      </div>
      <!-- Tab 2: Übersicht & Diagramm -->
      <div class="tab-pane fade" id="overview" role="tabpanel" aria-labelledby="overview-tab">
        <div class="card mt-4">
          <div class="card-body">
            <h4 class="card-title">Deine Einträge</h4>
            <?php if (count($protocols) === 0): ?>
              <p class="text-center">Keine Protokolleinträge vorhanden.</p>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-striped">
                  <thead>
                    <tr>
                      <th>ID</th>
                      <th>Verletzungstyp</th>
                      <th>Beginn</th>
                      <th>Ende</th>
                      <th>Krankenhaus</th>
                      <th>Erfasst am</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($protocols as $p): ?>
                      <tr>
                        <td><?= htmlspecialchars($p['id']) ?></td>
                        <td><?= htmlspecialchars($p['injury_type']) ?></td>
                        <td><?= htmlspecialchars($p['start_date']) ?></td>
                        <td><?= htmlspecialchars($p['end_date']) ?></td>
                        <td><?= $p['hospital_visited'] ? 'Ja' : 'Nein' ?></td>
                        <td><?= htmlspecialchars($p['created_at']) ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>
        </div>
        <div class="card mt-4">
          <div class="card-body">
            <h4 class="card-title">Verletzungen pro Monat</h4>
            <canvas id="injuryChart"></canvas>
          </div>
        </div>
      </div>
    </div>
  </div>

  <?php include 'footer.php'; ?>

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <!-- Chart.js -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script>
    // Diagramm initialisieren, wenn der Tab "Übersicht" aktiv ist
    document.addEventListener("DOMContentLoaded", function() {
      const ctx = document.getElementById('injuryChart').getContext('2d');
      const injuryChart = new Chart(ctx, {
          type: 'bar',
          data: {
              labels: <?= json_encode($labels) ?>,
              datasets: [{
                  label: 'Anzahl Verletzungen',
                  data: <?= json_encode($dataPoints) ?>,
                  backgroundColor: 'rgba(54, 162, 235, 0.5)',
                  borderColor: 'rgba(54, 162, 235, 1)',
                  borderWidth: 1
              }]
          },
          options: {
              scales: {
                  y: {
                      beginAtZero: true,
                      ticks: {
                          stepSize: 1,
                          precision: 0
                      }
                  }
              },
              plugins: {
                  legend: {
                      display: false
                  }
              }
          }
      });
    });
  </script>
</body>
</html>
