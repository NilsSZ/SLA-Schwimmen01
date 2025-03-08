<?php
/********************************************************
 * IMPORT/EXPORT – Administrator-Modul
 * 
 * Funktionen:
 * - Export: Der Admin wählt, welche Module exportiert werden sollen (z.B. Daten, Wettkämpfe, Livetimings, Trainingspläne).
 *   Vor dem Export muss der Admin einen 20-stelligen Bestätigungscode und einen aktuellen 2FA-Code (TOTP) eingeben.
 *   Bei erfolgreicher Prüfung wird ein SQL-Dump der relevanten Daten (hier exemplarisch aus der Tabelle "times")
 *   generiert und als Download angeboten.
 * 
 * - Import: Der Admin wählt eine Datei (.sla oder .csv) aus. Anschließend wird eine Import-Vorschau angezeigt,
 *   damit der Admin überprüfen kann, ob der Inhalt passt. Danach kann der Import gestartet werden.
 * 
 * Hinweis: Der Export-Dump enthält nur die Datensätze des aktuell angemeldeten Admins (User-ID 111).
 ********************************************************/

// Entwicklungseinstellungen
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Session starten
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Nur Administrator (User 111) darf dieses Modul nutzen!
if ($_SESSION['user_id'] != 2) {
    die("Zugriff verweigert – Administratorrechte erforderlich.");
}

$user_id   = $_SESSION['user_id'];
$user_name = $_SESSION['name'] ?? 'Admin';

// DB-Verbindung einbinden (bitte Pfad ggf. anpassen)
require_once 'dbconnection.php';

// Kein Composer – daher eigene TOTP-Funktionen einbauen

// Funktion zum Dekodieren von Base32 (nur für Großbuchstaben und Ziffern 2-7)
function base32_decode($b32) {
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $b32 = strtoupper($b32);
    $l = strlen($b32);
    $n = 0;
    $j = 0;
    $binary = '';
    for ($i = 0; $i < $l; $i++) {
        $n = $n << 5;
        $n = $n + strpos($alphabet, $b32[$i]);
        $j += 5;
        if ($j >= 8) {
            $j -= 8;
            $binary .= chr(($n & (0xFF << $j)) >> $j);
        }
    }
    return $binary;
}

// TOTP-Funktion (RFC 6238-konform)
function getTOTP($secret, $timeSlice = null) {
    if ($timeSlice === null) {
        $timeSlice = floor(time() / 30);
    }
    $secretKey = base32_decode($secret);
    $time = pack('N*', 0) . pack('N*', $timeSlice);
    $hm = hash_hmac('sha1', $time, $secretKey, true);
    $offset = ord(substr($hm, -1)) & 0x0F;
    $hashPart = substr($hm, $offset, 4);
    $value = unpack('N', $hashPart)[1] & 0x7fffffff;
    return str_pad($value % 1000000, 6, '0', STR_PAD_LEFT);
}

// Fester 2FA-Secret für den Admin (in einer echten Anwendung in der DB speichern)
$admin_2fa_secret = 'JBSWY3DPEHPK3PXP'; // Beispiel‑Secret (Base32)

// Bestätigungscode (20 Zeichen)
define('EXPORT_CONFIRM_CODE', "Fg!A6@hG!jpB89KpedMK");

// Hilfsfunktionen für Flash-Messages
function setFlash($key, $msg) {
    $_SESSION["flash_$key"] = $msg;
}
function getFlash($key) {
    if (isset($_SESSION["flash_$key"])) {
        $m = $_SESSION["flash_$key"];
        unset($_SESSION["flash_$key"]);
        return $m;
    }
    return "";
}

// Funktion zum Exportieren von Testdaten (Beispielhaft: Export aus Tabelle "times" für den aktuell angemeldeten Admin)
function exportData($db) {
    $dump = "";
    // Beispiel: Exportiere alle Einträge aus "times" für den Admin (User 111)
    $stmt = $db->prepare("SELECT swim_style_id, distance, time, date FROM times WHERE user_id = ?");
    $current_user = $_SESSION['user_id'];
    $stmt->bind_param("i", $current_user);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        // Escape der Werte (hier sehr simpel – in einer echten Umgebung ggf. mit mysql_real_escape_string etc.)
        $swim_style_id = $db->real_escape_string($row['swim_style_id']);
        $distance      = $db->real_escape_string($row['distance']);
        $time          = $db->real_escape_string($row['time']);
        $date          = $db->real_escape_string($row['date']);
        $dump .= "INSERT INTO times (user_id, swim_style_id, distance, time, date, WKtime) VALUES ($current_user, '$swim_style_id', '$distance', '$time', '$date', 0);\n";
    }
    $stmt->close();
    return $dump;
}

// Funktion zum Importieren von SQL-Befehlen aus einer hochgeladenen Datei
function importData($db, $sql_dump, $import_password, $expected_password) {
    if ($import_password !== $expected_password) {
        return "Das eingegebene Passwort stimmt nicht.";
    }
    // Zerlege den Dump in einzelne SQL-Befehle
    $queries = array_filter(array_map('trim', explode(";\n", $sql_dump)));
    foreach ($queries as $query) {
        $query .= ";";
        if (!$db->query($query)) {
            return "Fehler beim Ausführen von SQL: " . $db->error;
        }
    }
    return "";
}

// -----------------------------
// Aktionen abarbeiten
// -----------------------------
$action = $_GET['action'] ?? 'dashboard';

// Export: Wenn Formular abgeschickt wurde
if ($action === 'export' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $selectedModules = $_POST['modules'] ?? []; // Array der ausgewählten Module (z.B. "Daten", "Wettkämpfe", "Livetimings", "Trainingspläne")
    $confirm_code = trim($_POST['confirm_code'] ?? '');
    $twofa_code   = trim($_POST['twofa_code'] ?? '');
    // Für diesen Export prüfen wir:
    if ($confirm_code !== EXPORT_CONFIRM_CODE) {
        setFlash('error', 'Der Bestätigungscode ist ungültig.');
        header("Location: import_export.php?action=export");
        exit();
    }
    if (getTOTP($admin_2fa_secret) !== $twofa_code) {
        setFlash('error', 'Der 2FA-Code ist ungültig.');
        header("Location: import_export.php?action=export");
        exit();
    }
    // Hier exportieren wir exemplarisch nur die Daten aus "times" (bei Bedarf können weitere Module ergänzt werden)
    $dump = exportData($conn);
    header('Content-Type: text/plain');
    header('Content-Disposition: attachment; filename="export_' . time() . '.sql"');
    echo $dump;
    exit();
}

// Import: Wenn Datei hochgeladen wurde
if ($action === 'import' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
        setFlash('error', 'Dateiupload fehlgeschlagen.');
        header("Location: import_export.php?action=import");
        exit();
    }
    $file_ext = strtolower(pathinfo($_FILES['import_file']['name'], PATHINFO_EXTENSION));
    if (!in_array($file_ext, ['sla', 'csv'])) {
        setFlash('error', 'Nur .sla- oder .csv-Dateien sind erlaubt.');
        header("Location: import_export.php?action=import");
        exit();
    }
    $file_contents = file_get_contents($_FILES['import_file']['tmp_name']);
    // Speichere den Dateiinhalt in der Session zur Vorschau
    $_SESSION['import_file_contents'] = $file_contents;
    header("Location: import_export.php?action=import_preview");
    exit();
}

// Import-Vorschau anzeigen
if ($action === 'import_preview') {
    $import_preview = $_SESSION['import_file_contents'] ?? '';
    $import_preview_short = substr($import_preview, 0, 1000);
}

// Import-Bestätigung: Nach der Vorschau
if ($action === 'import_confirm' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $sql_dump = base64_decode($_POST['import_data'] ?? '');
    $import_password = trim($_POST['import_password'] ?? '');
    // Hier erwartet der Administrator ein bestimmtes Passwort – in diesem Beispiel setzen wir es fest
    $expected_import_password = "DeinSicheresPasswort123!"; // Passe diesen Wert an
    $result = importData($conn, $sql_dump, $import_password, $expected_import_password);
    if ($result !== "") {
        setFlash('error', $result);
    } else {
        setFlash('success', 'Daten erfolgreich importiert.');
    }
    header("Location: import_export.php?action=dashboard");
    exit();
}

// Löschen der Testdaten (zum Beispiel)
if ($action === 'delete') {
    $conn->query("DELETE FROM test_data WHERE user_id = 111");
    setFlash('success', 'Alle Test-Datensätze wurden gelöscht.');
    header("Location: import_export.php?action=dashboard");
    exit();
}

// Download aller Testdaten
if ($action === 'download') {
    $dump = exportData($conn);
    header('Content-Type: text/plain');
    header('Content-Disposition: attachment; filename="test_data_' . time() . '.sql"');
    echo $dump;
    exit();
}

// WICHTIG: Schließe die DB-Verbindung erst am Ende (damit keine "mysqli object is already closed" Fehler entstehen)

?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <title>Import/Export – SLA-Schwimmen</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- Bootstrap CSS & Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.min.css">
  <style>
    body {
      background: #f0f4f8;
      padding-top: 80px;
      font-family: 'Segoe UI', sans-serif;
    }
    .container {
      max-width: 960px;
    }
    .section-title {
      margin-bottom: 20px;
      font-weight: 600;
      color: #003366;
    }
    .card {
      margin-bottom: 20px;
      border-radius: 10px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
    .btn-copy {
      cursor: pointer;
    }
    .loading-spinner {
      display: none;
      text-align: center;
      margin-top: 10px;
    }
  </style>
</head>
<body>
<?php include('menu.php'); ?>

<div class="container">
  <h1 class="mb-4">Import/Export</h1>

  <?php if (getFlash('error')): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars(getFlash('error')); ?></div>
  <?php endif; ?>
  <?php if (getFlash('success')): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars(getFlash('success')); ?></div>
  <?php endif; ?>

  <!-- Navigation Tabs -->
  <ul class="nav nav-tabs" id="importExportTabs" role="tablist">
    <li class="nav-item" role="presentation">
      <button class="nav-link <?php echo ($action=='dashboard' ? 'active' : ''); ?>" id="dashboard-tab" data-bs-toggle="tab" data-bs-target="#dashboard" type="button" role="tab">Dashboard</button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link <?php echo ($action=='export' ? 'active' : ''); ?>" id="export-tab" data-bs-toggle="tab" data-bs-target="#export" type="button" role="tab">Export</button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link <?php echo ($action=='import' || $action=='import_preview' ? 'active' : ''); ?>" id="import-tab" data-bs-toggle="tab" data-bs-target="#import" type="button" role="tab">Import</button>
    </li>
  </ul>

  <div class="tab-content mt-4">
    <!-- Dashboard -->
    <div class="tab-pane fade <?php echo ($action=='dashboard' ? 'show active' : ''); ?>" id="dashboard" role="tabpanel">
      <div class="card">
        <div class="card-body">
          <h5 class="section-title">Testdaten Übersicht</h5>
          <?php
          $modules = ['Daten', 'Wettkämpfe', 'Livetimings', 'Trainingspläne'];
          echo '<ul class="list-group">';
          foreach ($modules as $m) {
              $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM test_data WHERE user_id = 111 AND module = ?");
              $stmt->bind_param("s", $m);
              $stmt->execute();
              $res = $stmt->get_result()->fetch_assoc();
              $cnt = $res['cnt'] ?? 0;
              $stmt->close();
              echo '<li class="list-group-item d-flex justify-content-between align-items-center">' . htmlspecialchars($m) . '<span class="badge bg-primary rounded-pill">' . $cnt . '</span></li>';
          }
          echo '</ul>';
          ?>
          <div class="mt-3">
            <a href="?action=download" class="btn btn-secondary">Alle Testdaten herunterladen</a>
            <a href="?action=delete" class="btn btn-danger" onclick="return confirm('Alle Testdaten wirklich löschen?');">Alle Testdaten löschen</a>
          </div>
        </div>
      </div>
    </div>
    <!-- Export -->
    <div class="tab-pane fade <?php echo ($action=='export' ? 'show active' : ''); ?>" id="export" role="tabpanel">
      <div class="card">
        <div class="card-header">Exportieren</div>
        <div class="card-body">
          <form method="post" action="import_export.php?action=export" id="exportForm">
            <div class="mb-3">
              <label class="form-label">Module auswählen</label>
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="modules[]" value="Daten" id="mod_daten">
                <label class="form-check-label" for="mod_daten">Daten</label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="modules[]" value="Wettkämpfe" id="mod_wettkampfe">
                <label class="form-check-label" for="mod_wettkampfe">Wettkämpfe</label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="modules[]" value="Livetimings" id="mod_livetimings">
                <label class="form-check-label" for="mod_livetimings">Livetimings</label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="modules[]" value="Trainingspläne" id="mod_trainingsplane">
                <label class="form-check-label" for="mod_trainingsplane">Trainingspläne</label>
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label">20-Zeichen Bestätigungscode</label>
              <input type="text" name="confirm_code" class="form-control" placeholder="Fg!A6@hG!jpB89KpedMK" required>
            </div>
            <div class="mb-3">
              <label class="form-label">2FA-Code (mit Ihrem Authenticator generieren)</label>
              <input type="text" name="twofa_code" class="form-control" placeholder="z. B. 123456" required>
            </div>
            <button type="submit" class="btn btn-success">Export starten</button>
          </form>
          <div id="exportSpinner" class="loading-spinner">
            <div class="spinner-border text-primary" role="status">
              <span class="visually-hidden">Lädt...</span>
            </div>
          </div>
        </div>
      </div>
    </div>
    <!-- Import -->
    <div class="tab-pane fade <?php echo ($action=='import' || $action=='import_preview' ? 'show active' : ''); ?>" id="import" role="tabpanel">
      <div class="card">
        <div class="card-header">Importieren</div>
        <div class="card-body">
          <?php if ($action == 'import_preview'): ?>
            <h5>Import-Vorschau</h5>
            <pre><?php echo htmlspecialchars($import_preview); ?></pre>
            <form method="post" action="import_export.php?action=import_confirm">
              <input type="hidden" name="import_data" value="<?php echo htmlspecialchars(base64_encode($_SESSION['import_file_contents'])); ?>">
              <div class="mb-3">
                <label class="form-label">Import-Passwort</label>
                <input type="password" name="import_password" class="form-control" placeholder="Passwort eingeben" required>
              </div>
              <button type="submit" class="btn btn-primary">Import starten</button>
            </form>
          <?php else: ?>
            <form method="post" action="import_export.php?action=import" enctype="multipart/form-data">
              <div class="mb-3">
                <label class="form-label">Datei auswählen (.sla oder .csv)</label>
                <input type="file" name="import_file" class="form-control" accept=".sla,.csv" required>
              </div>
              <button type="submit" class="btn btn-info">Import-Vorschau öffnen</button>
            </form>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
  
  <hr>
  <p class="text-center text-muted">Dieses Modul dient ausschließlich dem Import/Export von Daten.</p>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
  // Einfaches Beispiel: Beim Absenden des Exportformulars wird der Spinner eingeblendet.
  document.getElementById('exportForm')?.addEventListener('submit', function(){
    document.getElementById('exportSpinner').style.display = 'block';
  });
</script>
<?php
// Schließe die DB-Verbindung erst am Ende
if ($conn) {
    $conn->close();
}
?>
</body>
</html>
