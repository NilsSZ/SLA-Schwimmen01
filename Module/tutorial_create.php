<?php
// tutorial_create.php
session_start();
require_once '../dbconnection.php';

$user_id = $_SESSION['user_id'] ?? 0;
if ($user_id != 2) {
    die("Nur Admin darf Tutorials erstellen.");
}

// Wenn Formular abgesendet:
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Daten aus dem Formular lesen
    $tutorial_type      = $_POST['tutorial_type'] ?? 'function'; // 'module' oder 'function'
    $module_id          = $_POST['module_id'] ?? null;
    $icon               = $_POST['icon'] ?? ''; // nur relevant wenn function
    $name               = $_POST['name'] ?? '';
    $short_description  = $_POST['short_description'] ?? '';
    $description        = $_POST['description'] ?? '';
    $steps              = $_POST['steps'] ?? '';
    $q_and_a            = $_POST['q_and_a'] ?? '';
    $version_history    = $_POST['version_history'] ?? '';
    $video_url          = $_POST['video_url'] ?? '';
    $is_published       = ($_POST['action'] === 'publish') ? 1 : 0;

    // Falls es eine neu erstellte Version ist, Standard "1.0.0 – Veröffentlichung"
    // Du könntest hier eine Logik einbauen, die version_history auto-füllt, falls leer
    if (empty($version_history)) {
        $version_history = "1.0.0 - Veröffentlichung";
    }

    // Dateien hochladen (z. B. attachments)
    // Hier nur ein Beispiel, falls du multiple Files anhängst
    $attachments = [];
    if (!empty($_FILES['attachments']['name'][0])) {
        foreach ($_FILES['attachments']['tmp_name'] as $idx => $tmp) {
            $filename = $_FILES['attachments']['name'][$idx];
            // In Ordner "uploads" speichern
            $destination = '../uploads/' . $filename;
            move_uploaded_file($tmp, $destination);
            $attachments[] = $filename;
        }
    }
    $attachments_json = json_encode($attachments);

    // Insert in DB
    $stmt = $conn->prepare("
        INSERT INTO tutorials 
          (tutorial_type, module_id, icon, name, short_description, description, steps, q_and_a, version_history, video_url, attachments, is_published, created_by)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)
    ");
    $stmt->bind_param(
        "sissssssssssi",
        $tutorial_type,
        $module_id,
        $icon,
        $name,
        $short_description,
        $description,
        $steps,
        $q_and_a,
        $version_history,
        $video_url,
        $attachments_json,
        $is_published,
        $user_id
    );
    if ($stmt->execute()) {
        header("Location: tutorials.php");
        exit();
    } else {
        echo "Fehler beim Speichern: " . $stmt->error;
    }
    $stmt->close();
}

// Hole alle Module für Dropdown
$modules_res = $conn->query("SELECT id, name, icon FROM modules ORDER BY name ASC");
$modules = [];
while ($m = $modules_res->fetch_assoc()) {
    $modules[] = $m;
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <title>Neues Tutorial erstellen</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- Bootstrap + Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.10.5/font/bootstrap-icons.min.css">
  <!-- CKEditor (WYSIWYG) -->
  <script src="https://cdn.ckeditor.com/4.20.2/standard/ckeditor.js"></script>
  <style>
    body { padding-top: 4.5rem; background: #f5f7fa; }
    .card { border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); border: none; }
  </style>
</head>
<body>
<?php include '../menu.php'; ?>

<div class="container mt-3">
  <h1>Neues Tutorial erstellen</h1>
  <div class="card p-4">
    <form method="post" action="tutorial_create.php" enctype="multipart/form-data">
      <!-- Tutorial-Typ: Modul oder Funktion -->
      <div class="mb-3">
        <label class="form-label">Tutorial-Typ</label>
        <select name="tutorial_type" id="tutorial_type" class="form-select" onchange="toggleTypeFields()">
          <option value="function">Funktion</option>
          <option value="module">Modul</option>
        </select>
      </div>
      <!-- Falls Modul -->
      <div class="mb-3" id="moduleSelect" style="display:none;">
        <label class="form-label">Modul auswählen</label>
        <select name="module_id" class="form-select">
          <option value="">Bitte wählen...</option>
          <?php foreach ($modules as $mod): ?>
            <option value="<?= $mod['id'] ?>">
              <?= htmlspecialchars($mod['name']) ?> (Icon: <?= $mod['icon'] ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <!-- Falls Funktion: Icon auswählen -->
      <div class="mb-3" id="functionIcon" style="display:block;">
        <label class="form-label">Icon (Bootstrap Icons, z.B. "bi-alarm")</label>
        <input type="text" name="icon" class="form-control" placeholder="bi-alarm, bi-clock, ...">
        <small class="text-muted">Tipp: Liste siehe <a href="https://icons.getbootstrap.com/" target="_blank">Bootstrap Icons</a></small>
      </div>

      <!-- Titel -->
      <div class="mb-3">
        <label class="form-label">Titel</label>
        <input type="text" name="name" class="form-control" required>
      </div>
      <!-- Kurzbeschreibung -->
      <div class="mb-3">
        <label class="form-label">Kurzbeschreibung</label>
        <input type="text" name="short_description" class="form-control" required>
      </div>
      <!-- Beschreibung (CKEditor) -->
      <div class="mb-3">
        <label class="form-label">Beschreibung</label>
        <textarea name="description" id="description" class="form-control"></textarea>
      </div>
      <!-- Schritte (CKEditor) -->
      <div class="mb-3">
        <label class="form-label">Schritte (Tutorial-Anleitung)</label>
        <textarea name="steps" id="steps" class="form-control"></textarea>
      </div>
      <!-- Q&A (CKEditor) -->
      <div class="mb-3">
        <label class="form-label">Q&A</label>
        <textarea name="q_and_a" id="q_and_a" class="form-control"></textarea>
      </div>
      <!-- Version (z. B. 1.0.0 - Veröffentlichung) -->
      <div class="mb-3">
        <label class="form-label">Version-Historie</label>
        <textarea name="version_history" class="form-control" placeholder="1.0.0 - Veröffentlichung"></textarea>
      </div>
      <!-- Video-URL -->
      <div class="mb-3">
        <label class="form-label">Video-URL (optional)</label>
        <input type="text" name="video_url" class="form-control">
      </div>
      <!-- Dateien anhängen -->
      <div class="mb-3">
        <label class="form-label">Dateien anhängen (optional)</label>
        <input type="file" name="attachments[]" multiple class="form-control">
        <small class="text-muted">© Urheberrechte beachten!</small>
      </div>

      <!-- Aktionen -->
      <button type="submit" name="action" value="save" class="btn btn-secondary">Speichern (Entwurf)</button>
      <button type="submit" name="action" value="publish" class="btn btn-primary">Veröffentlichen</button>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
CKEDITOR.replace('description');
CKEDITOR.replace('steps');
CKEDITOR.replace('q_and_a');

function toggleTypeFields() {
  const val = document.getElementById('tutorial_type').value;
  document.getElementById('moduleSelect').style.display = (val === 'module') ? 'block' : 'none';
  document.getElementById('functionIcon').style.display = (val === 'function') ? 'block' : 'none';
}
</script>
</body>
</html>
