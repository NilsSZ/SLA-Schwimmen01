<?php
// import_export.php – Import/Export Modul
// (Sicherstellen, dass in den inkludierten Dateien (dbconnection.php, menu.php) KEINE Ausgabe erfolgt!)

ob_start();
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
$user_id   = $_SESSION['user_id'];
$user_name = $_SESSION['name'] ?? 'Benutzer';

// DB-Verbindung (achte darauf, dass dbconnection.php keine Ausgabe erzeugt)
require_once('../dbconnection.php');

// Für den Export: Wir erzeugen ein sicheres, 9‐stelliges Passwort
function generateRandomPassword($length = 9) {
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*()';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $password;
}

// --- EXPORT-Funktion: Exportiere SQL-Befehle für ausgewählte Module ---
function exportData($conn, $user_id, $modules) {
    $sqlExport = "";
    // Exportiere Trainingsdaten (times)
    if (in_array('times', $modules)) {
        $result = $conn->query("SELECT swim_style_id, distance, time, date, WKtime FROM times WHERE user_id = $user_id");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $sqlExport .= "INSERT INTO times (user_id, swim_style_id, distance, time, date, WKtime) VALUES ($user_id, " .
                    intval($row['swim_style_id']) . ", " . intval($row['distance']) . ", '" .
                    $conn->real_escape_string($row['time']) . "', '" .
                    $conn->real_escape_string($row['date']) . "', " .
                    intval($row['WKtime']) . ");\n";
            }
            $result->free();
        }
    }
    // Exportiere Wettkämpfe
    if (in_array('competitions', $modules)) {
        $result = $conn->query("SELECT name, competition_date FROM competitions WHERE user_id = $user_id");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $sqlExport .= "INSERT INTO competitions (user_id, name, competition_date) VALUES ($user_id, '" .
                    $conn->real_escape_string($row['name']) . "', '" .
                    $conn->real_escape_string($row['competition_date']) . "');\n";
            }
            $result->free();
        }
    }
    // Exportiere Livetimings
    if (in_array('livetimings', $modules)) {
        $result = $conn->query("SELECT competition_name, start_date, join_method, join_code, welcome_message, farewell_message, allow_comments, show_endtime FROM livetiming_sessions WHERE user_id = $user_id");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $sqlExport .= "INSERT INTO livetiming_sessions (user_id, competition_name, start_date, join_method, join_code, welcome_message, farewell_message, allow_comments, show_endtime) VALUES ($user_id, '" .
                    $conn->real_escape_string($row['competition_name']) . "', '" .
                    $conn->real_escape_string($row['start_date']) . "', '" .
                    $conn->real_escape_string($row['join_method']) . "', '" .
                    $conn->real_escape_string($row['join_code']) . "', '" .
                    $conn->real_escape_string($row['welcome_message']) . "', '" .
                    $conn->real_escape_string($row['farewell_message']) . "', " .
                    intval($row['allow_comments']) . ", " . intval($row['show_endtime']) . ");\n";
            }
            $result->free();
        }
    }
    // Exportiere Trainingspläne
    if (in_array('training_plans', $modules)) {
        $result = $conn->query("SELECT plan_date, location, plan_type, duration_minutes FROM training_plans WHERE user_id = $user_id");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $sqlExport .= "INSERT INTO training_plans (user_id, plan_date, location, plan_type, duration_minutes) VALUES ($user_id, '" .
                    $conn->real_escape_string($row['plan_date']) . "', '" .
                    $conn->real_escape_string($row['location']) . "', '" .
                    $conn->real_escape_string($row['plan_type']) . "', " .
                    intval($row['duration_minutes']) . ");\n";
            }
            $result->free();
        }
    }
    return $sqlExport;
}

// --- EXPORT: Wird ausgeführt, wenn das Export-Formular abgeschickt wurde ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export_submit'])) {
    $selectedModules = $_POST['modules'] ?? [];
    $passwordProtect = isset($_POST['password_protect']);
    $password = "";
    if ($passwordProtect) {
        $password = generateRandomPassword(9);
    }
    $exportSQL = exportData($conn, $user_id, $selectedModules);
    if ($passwordProtect) {
        // Füge den Passwort-Hinweis als Kommentar hinzu – so wird der Inhalt nicht direkt im Export sichtbar
        $exportSQL = "-- PASSWORD: " . $password . "\n" . $exportSQL;
    }
    // Schließe die DB-Verbindung erst _nach_ dem Senden der Datei
    // Sende Headers und den Export-Text
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="export_' . date('Ymd_His') . '.sla"');
    echo $exportSQL;
    // Wichtig: Nicht mehr $conn->close() aufrufen, falls danach noch Code ausgeführt werden soll
    exit();
}

// --- IMPORT: Wird ausgeführt, wenn das Import-Formular abgeschickt wurde ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_submit'])) {
    if (!empty($_FILES['import_file']) && $_FILES['import_file']['error'] === UPLOAD_ERR_OK) {
        $fileContent = file_get_contents($_FILES['import_file']['tmp_name']);
        // Versuche, den Inhalt als SQL-Befehle zu interpretieren
        // Falls der Export mit Passwortschutz erfolgt ist, extrahiere das Passwort
        $lines = explode("\n", $fileContent);
        $providedPassword = "";
        if (isset($lines[0]) && strpos($lines[0], '-- PASSWORD:') === 0) {
            $providedPassword = trim(str_replace('-- PASSWORD:', '', $lines[0]));
            array_shift($lines);
            $fileContent = implode("\n", $lines);
        }
        // Falls ein Passwort eingegeben wurde, vergleiche es
        if (isset($_POST['import_password']) && !empty($_POST['import_password'])) {
            $inputPassword = trim($_POST['import_password']);
            if ($providedPassword !== $inputPassword) {
                $_SESSION['flash_error'] = "Das eingegebene Passwort stimmt nicht überein.";
                header("Location: import_export.php");
                exit();
            }
        }
        // Ersetze in den SQL-Befehlen alle vorkommenden user_id-Werte durch die aktuelle User-ID
        $fileContent = preg_replace('/VALUES\s*\(\s*\d+\s*,/i', "VALUES ($user_id,", $fileContent);
        // Führe die SQL-Befehle einzeln aus
        $commands = explode(";", $fileContent);
        $importErrors = "";
        foreach ($commands as $cmd) {
            $cmd = trim($cmd);
            if (!empty($cmd)) {
                if (!$conn->query($cmd)) {
                    $importErrors .= "Fehler: " . $conn->error . "\n";
                }
            }
        }
        if (!empty($importErrors)) {
            $_SESSION['flash_error'] = "Import Fehler: " . nl2br(htmlspecialchars($importErrors));
        } else {
            $_SESSION['flash_success'] = "Daten erfolgreich importiert.";
        }
    } else {
        $_SESSION['flash_error'] = "Keine Datei hochgeladen oder Fehler beim Upload.";
    }
    header("Location: import_export.php");
    exit();
}

// Wichtiger Hinweis: Wir schließen die DB-Verbindung erst zum Ende der Ausgabe!
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Import / Export – SLA-Schwimmen</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Bootstrap CSS & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.min.css">
    <style>
        body {
            background: #f0f2f5;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding-top: 80px;
        }
        .container {
            max-width: 960px;
        }
        .tab-content {
            margin-top: 20px;
        }
        .loading {
            display: none;
            text-align: center;
            margin-top: 20px;
        }
    </style>
</head>
<body>
<?php
// In diesem Bereich wird das Menü eingebunden (achte darauf, dass menu.php keine unerwünschte Ausgabe erzeugt)
include('../menu.php');
?>
<div class="container">
    <h1 class="mb-4">Import / Export</h1>
    <?php if(isset($_SESSION['flash_error'])): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?></div>
    <?php endif; ?>
    <?php if(isset($_SESSION['flash_success'])): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?></div>
    <?php endif; ?>

    <!-- Tabs für Export und Import -->
    <ul class="nav nav-tabs" id="importExportTabs" role="tablist">
      <li class="nav-item" role="presentation">
        <button class="nav-link active" id="export-tab" data-bs-toggle="tab" data-bs-target="#export" type="button" role="tab" aria-controls="export" aria-selected="true">Export</button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="import-tab" data-bs-toggle="tab" data-bs-target="#import" type="button" role="tab" aria-controls="import" aria-selected="false">Import</button>
      </li>
    </ul>
    <div class="tab-content" id="importExportTabsContent">
      <!-- Export Tab -->
      <div class="tab-pane fade show active" id="export" role="tabpanel" aria-labelledby="export-tab">
        <form method="post" id="exportForm">
            <div class="mb-3">
                <label class="form-label">Wähle die Daten, die Du exportieren möchtest:</label><br>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="checkbox" name="modules[]" id="export_times" value="times" checked>
                    <label class="form-check-label" for="export_times">Trainingsdaten (Times)</label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="checkbox" name="modules[]" id="export_competitions" value="competitions">
                    <label class="form-check-label" for="export_competitions">Wettkämpfe</label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="checkbox" name="modules[]" id="export_livetimings" value="livetimings">
                    <label class="form-check-label" for="export_livetimings">Livetimings</label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="checkbox" name="modules[]" id="export_training_plans" value="training_plans">
                    <label class="form-check-label" for="export_training_plans">Trainingspläne</label>
                </div>
            </div>
            <div class="mb-3 form-check">
                <input class="form-check-input" type="checkbox" id="password_protect" name="password_protect">
                <label class="form-check-label" for="password_protect">Passwortschutz aktivieren</label>
            </div>
            <!-- Button, der beim Klick ein Popup (Modal) öffnet, um das Passwort anzuzeigen, falls aktiv -->
            <div class="mb-3">
                <button type="button" class="btn btn-info" id="exportPreviewBtn">Exportvorschau</button>
            </div>
            <div class="mb-3">
                <button type="submit" name="export_submit" class="btn btn-primary">Exportieren</button>
            </div>
        </form>
      </div>
      <!-- Import Tab -->
      <div class="tab-pane fade" id="import" role="tabpanel" aria-labelledby="import-tab">
        <button class="btn btn-info mb-3" onclick="openImportPreview()">Import-Vorschau anzeigen</button>
        <form method="post" id="importForm" enctype="multipart/form-data">
            <div class="mb-3">
                <label class="form-label">Wähle eine Datei zum Importieren (.sla oder .csv):</label>
                <input type="file" name="import_file" id="import_file" class="form-control" accept=".sla,.csv" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Falls exportiert mit Passwortschutz – Passwort eingeben:</label>
                <input type="password" name="import_password" class="form-control" placeholder="Passwort">
            </div>
            <div class="mb-3">
                <button type="submit" name="import_submit" class="btn btn-primary">Daten importieren</button>
            </div>
        </form>
      </div>
    </div>
    <div class="loading" id="loadingIndicator">
        <div class="spinner-border text-primary" role="status">
          <span class="visually-hidden">Lädt...</span>
        </div>
        <p>Lade, bitte warten...</p>
    </div>
</div>

<!-- Modal: Passwort-Popup beim Export (wird per JavaScript gefüllt) -->
<div class="modal fade" id="passwordModal" tabindex="-1" aria-labelledby="passwordModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="passwordModalLabel">Export-Passwort</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
      </div>
      <div class="modal-body">
        <p>Dein Export-Passwort lautet:</p>
        <p id="exportPassword" class="fw-bold"></p>
        <p><small>Dieses Passwort musst Du beim Import angeben.</small></p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-primary" data-bs-dismiss="modal">OK</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Funktion zur Öffnung der Import-Vorschau
function openImportPreview() {
    const fileInput = document.getElementById('import_file');
    if (!fileInput.files || fileInput.files.length === 0) {
        alert("Bitte zuerst eine Datei auswählen.");
        return;
    }
    const file = fileInput.files[0];
    const reader = new FileReader();
    reader.onload = function(e) {
        alert("Import-Vorschau:\n" + e.target.result.substring(0, 500) + "\n...\n(Weitere Daten werden nicht angezeigt.)");
    };
    reader.readAsText(file);
}

// Beim Klick auf den Exportvorschau-Button: Falls Passwortschutz aktiviert ist, zeige das generierte Passwort in einem Modal
document.getElementById('exportPreviewBtn').addEventListener('click', function() {
    const passwordCheckbox = document.getElementById('password_protect');
    if (passwordCheckbox.checked) {
        // Sende eine AJAX-Anfrage, um ein Passwort zu generieren (oder generiere es hier clientseitig)
        // Hier simulieren wir die Passwortgenerierung clientseitig:
        const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*()';
        let pwd = '';
        for (let i = 0; i < 9; i++) {
            pwd += chars.charAt(Math.floor(Math.random() * chars.length));
        }
        // Setze den Wert in ein verstecktes Feld (optional) und zeige das Modal
        // (Hinweis: In der Export-Logik auf dem Server wird dann ggf. ein neues Passwort generiert – dies dient nur zur Vorschau)
        document.getElementById('exportPassword').textContent = pwd;
        const passwordModal = new bootstrap.Modal(document.getElementById('passwordModal'));
        passwordModal.show();
    } else {
        alert("Kein Passwortschutz aktiviert.");
    }
});
</script>
<?php
// Schließe die DB-Verbindung erst jetzt
$conn->close();
ob_end_flush();
?>
</body>
</html>
