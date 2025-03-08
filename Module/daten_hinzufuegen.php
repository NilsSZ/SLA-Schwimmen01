<?php
/********************************************************
 * DATEN HINZUFÜGEN – Trainingsdaten erfassen
 * 
 * - Erfasst das Datum über ein <input type="date"> (Format: YYYY-MM-DD),
 *   die Schwimmart, Distanz, Zeit (Format mm:ss,ms) und
 *   ob die Zeit als Wettkampf‑Meldezeit gespeichert werden soll.
 * - Der Nutzer muss eingeloggt sein.
 ********************************************************/

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


ob_start();
session_start();
require_once('../dbconnection.php');

// Sicherstellen, dass der Nutzer eingeloggt ist
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}
$user_id   = $_SESSION['user_id'];
$user_name = $_SESSION['name'] ?? 'Benutzer';

$error_message   = '';
$success_message = '';

// Schwimmarten abrufen
$swim_styles = [];
$stmt = $conn->prepare("SELECT id, name FROM swim_styles ORDER BY name ASC");
$stmt->execute();
$stmt->bind_result($sid, $sname);
while ($stmt->fetch()) {
    $swim_styles[$sid] = $sname;
}
$stmt->close();

// Formularverarbeitung
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $swim_style   = $_POST['swim_style'] ?? '';
    $distance     = $_POST['distance'] ?? '';
    $custom_distance = $_POST['custom_distance'] ?? '';
    $swim_time    = trim($_POST['swim_time'] ?? '');
    $date         = trim($_POST['date'] ?? '');
    $is_wk_time   = isset($_POST['is_wk_time']) ? 1 : 0; // Checkbox

    // Falls eine benutzerdefinierte Distanz eingegeben wurde, diese verwenden
    if (!empty($custom_distance)) {
        $distance = $custom_distance;
    }

    // Validierung
    if (empty($swim_style) || empty($distance) || empty($swim_time) || empty($date)) {
        $error_message = 'Alle Felder müssen ausgefüllt sein!';
    } elseif (!preg_match('/^\d{2}:\d{2},\d{2}$/', $swim_time)) {
        $error_message = 'Ungültiges Zeitformat! Verwende mm:ss,ms (z. B. 02:23,01).';
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !strtotime($date)) {
        $error_message = 'Ungültiges Datumsformat! Verwende das Format YYYY-MM-DD.';
    }
    
    if (empty($error_message)) {
        $stmt = $conn->prepare("INSERT INTO times (user_id, swim_style_id, distance, time, date, WKtime) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iiissi", $user_id, $swim_style, $distance, $swim_time, $date, $is_wk_time);
        if ($stmt->execute()) {
            $success_message = 'Zeit erfolgreich hinzugefügt!';
        } else {
            $error_message = 'Fehler beim Speichern der Daten: ' . $stmt->error;
        }
        $stmt->close();
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <title>Daten hinzufügen – Trainingsdaten erfassen</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
  <!-- Bootstrap 5 CSS & Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.10.5/font/bootstrap-icons.min.css">
  <style>
    body {
      font-family: 'Roboto', sans-serif;
      background: #f8f9fa;
      padding-top: 70px;
    }
    /* Neuer Hero-Bereich – nur der Header wird dunkel */
    .hero-header {
      background: linear-gradient(90deg, #0D1B2A, #1C2541);
      color: #fff;
      padding: 2.5rem;
      border-radius: 8px;
      text-align: center;
      margin-bottom: 30px;
      box-shadow: 0 4px 8px rgba(0,0,0,0.3);
    }
    .hero-header h1 {
      font-size: 2.5rem;
      font-weight: 700;
      margin-bottom: 0.5rem;
    }
    .hero-header p {
      font-size: 1.2rem;
      margin: 0;
    }
    /* Card-Design für das Formular (hell) */
    .card {
      background: #ffffff;
      border: none;
      border-radius: 8px;
      box-shadow: 0 4px 8px rgba(0,0,0,0.1);
      padding: 20px;
      margin-bottom: 20px;
    }
    .form-label {
      font-weight: 600;
    }
    .info-icon {
      cursor: pointer;
      color: #007bff;
    }
    input[type="date"].form-control {
      max-width: 220px;
      border: 1px solid #007bff;
      color: #007bff;
      font-weight: 600;
    }
  </style>
</head>
<body>
  <?php include '../menu.php'; ?>

  <div class="container">
    <!-- Neuer dunkler Header -->
    <div class="hero-header">
      <h1>Trainingsdaten erfassen</h1>
      <p>Füge Deine Schwimmzeiten hinzu, um Deine Leistung zu verfolgen.</p>
    </div>

    <?php if (!empty($error_message)): ?>
      <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
    <?php endif; ?>
    <?php if (!empty($success_message)): ?>
      <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
    <?php endif; ?>

    <div class="card">
      <form method="post" action="daten_hinzufuegen.php">
        <!-- Schwimmart -->
        <div class="mb-3">
          <label for="swim_style" class="form-label">Schwimmart</label>
          <select name="swim_style" id="swim_style" class="form-select" required>
            <option value="">Bitte wählen...</option>
            <?php foreach ($swim_styles as $id => $style): ?>
              <option value="<?php echo $id; ?>"><?php echo htmlspecialchars($style); ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Distanz -->
        <div class="mb-3">
          <label for="distance" class="form-label">Distanz (m)</label>
          <!-- Standard-Dropdown -->
          <select name="distance" id="distance" class="form-select" required>
            <option value="">Bitte wählen...</option>
            <option value="50">50 m</option>
            <option value="100">100 m</option>
            <option value="200">200 m</option>
            <option value="400">400 m</option>
            <option value="600">600 m</option>
            <option value="800">800 m</option>
            <option value="1500">1500 m</option>
            <option value="1700">1700 m</option>
          </select>
          <!-- Checkbox, um benutzerdefinierte Distanz zu aktivieren -->
          <div class="form-check mt-2">
            <input type="checkbox" class="form-check-input" id="custom_distance_checkbox">
            <label class="form-check-label" for="custom_distance_checkbox">Andere Länge eingeben</label>
          </div>
          <!-- Eingabefeld für benutzerdefinierte Distanz (initial versteckt) -->
          <div class="mt-2" id="custom_distance_div" style="display:none;">
            <input type="number" name="custom_distance" class="form-control" placeholder="z. B. 125">
          </div>
        </div>

        <!-- Zeit -->
        <div class="mb-3">
          <label for="swim_time" class="form-label">Zeit (mm:ss,ms)</label>
          <input type="text" name="swim_time" id="swim_time" class="form-control" placeholder="02:23,01" required pattern="^\d{2}:\d{2},\d{2}$">
          <small class="text-muted">Format: mm:ss,ms (z. B. 02:23,01)</small>
        </div>

        <!-- Datum -->
        <div class="mb-3">
          <label for="date" class="form-label">Datum</label>
          <input type="date" name="date" id="date" class="form-control" required>
          <small class="text-muted">Format: YYYY-MM-DD</small>
        </div>

        <!-- Checkbox: Als Wettkampf-Meldezeit speichern -->
        <div class="mb-3 form-check">
          <input type="checkbox" name="is_wk_time" value="1" id="is_wk_time" class="form-check-input">
          <label for="is_wk_time" class="form-check-label">
            Als Wettkampf-Meldezeit speichern 
            <i class="bi bi-info-circle info-icon" data-bs-toggle="popover" data-bs-trigger="hover" data-bs-content="Falls aktiviert, wird diese Zeit als Wettkampf-Meldezeit gespeichert." title="Info"></i>
          </label>
        </div>

        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-save"></i> Speichern</button>
      </form>
    </div>
  </div>

  <?php include '../footer.php'; ?>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // Initialisiere Popover
    var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    var popoverList = popoverTriggerList.map(function (el) {
      return new bootstrap.Popover(el);
    });
    
    // Zeige/verberge das benutzerdefinierte Distanz-Eingabefeld
    document.getElementById('custom_distance_checkbox').addEventListener('change', function() {
      const customDiv = document.getElementById('custom_distance_div');
      if (this.checked) {
        customDiv.style.display = 'block';
        document.getElementById('distance').disabled = true;
      } else {
        customDiv.style.display = 'none';
        document.getElementById('distance').disabled = false;
      }
    });
  </script>
</body>
</html>
<?php
if (ob_get_length()) { ob_end_flush(); }
?>
