<?php
// tutorial_edit.php
session_start();
require_once '../dbconnection.php';

function isAdmin() {
    return (
        isset($_SESSION['user_id']) &&
        ($_SESSION['user_id'] == 2 || (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1))
    );
}

if (!isAdmin()) {
    die("Nur Admin dürfen Tutorials bearbeiten.");
}

$tutorial_id = $_GET['id'] ?? 0;
$user_id = $_SESSION['user_id'] ?? 0;

// Tutorial laden
$stmt = $conn->prepare("SELECT * FROM tutorials WHERE id = ?");
$stmt->bind_param("i", $tutorial_id);
$stmt->execute();
$res = $stmt->get_result();
$tutorial = $res->fetch_assoc();
$stmt->close();

if (!$tutorial) {
    die("Tutorial nicht gefunden.");
}

// Hole alle Module (für Dropdown)
$modules = [];
$result = $conn->query("SELECT id, name, icon FROM modules ORDER BY name ASC");
while ($row = $result->fetch_assoc()) {
    $modules[] = $row;
}

// Falls Formular abgeschickt:
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tutorial_type     = $_POST['tutorial_type'] ?? 'function';
    $module_id         = ($_POST['module_id'] ?? '') ?: null;
    $icon              = $_POST['icon'] ?? '';
    $name              = trim($_POST['name'] ?? '');
    $short_description = trim($_POST['short_description'] ?? '');
    $description       = $_POST['description'] ?? '';
    
    // Video-Upload: Falls neues Video hochgeladen wurde, ersetzen
    if (!empty($_FILES['video_file']['name'])) {
        // Lösche altes Video, falls vorhanden
        if (!empty($tutorial['video_file'])) {
            @unlink("../uploads/" . $tutorial['video_file']);
        }
        $video_file = basename($_FILES['video_file']['name']);
        move_uploaded_file($_FILES['video_file']['tmp_name'], "../uploads/" . $video_file);
    } else {
        $video_file = $tutorial['video_file'];
    }
    
    // Attachments: Falls neue Dateien hochgeladen werden, anhängen
    $attachments = json_decode($tutorial['attachments'], true) ?: [];
    if (!empty($_FILES['attachments']['name'][0])) {
        foreach ($_FILES['attachments']['tmp_name'] as $idx => $tmp) {
            $filename = basename($_FILES['attachments']['name'][$idx]);
            move_uploaded_file($tmp, "../uploads/" . $filename);
            $attachments[] = $filename;
        }
    }
    $attachments_json = json_encode($attachments);
    
    // Störung und weitere Felder könnten hier auch bearbeitet werden – in diesem Beispiel
    // übernehmen wir sie direkt aus dem Formular (issue_description, module_deactivated, issue_expires_at)
    $issue_description = $_POST['issue_description'] ?? $tutorial['issue_description'];
    $module_deactivated = isset($_POST['module_deactivated']) ? 1 : 0;
    $issue_expires_at = $_POST['issue_expires_at'] ?? $tutorial['issue_expires_at'];
    
    $stmt = $conn->prepare("
        UPDATE tutorials SET 
          tutorial_type = ?,
          module_id = ?,
          icon = ?,
          name = ?,
          short_description = ?,
          description = ?,
          video_file = ?,
          attachments = ?,
          issue_description = ?,
          module_deactivated = ?,
          issue_expires_at = ?,
          updated_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ");
    $stmt->bind_param("sissssssisssi",
        $tutorial_type,
        $module_id,
        $icon,
        $name,
        $short_description,
        $description,
        $video_file,
        $attachments_json,
        $issue_description,
        $module_deactivated,
        $issue_expires_at,
        $tutorial_id
    );
    if ($stmt->execute()) {
        header("Location: tutorial_detail.php?id=" . $tutorial_id);
        exit();
    } else {
        echo "Fehler beim Aktualisieren: " . $stmt->error;
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <title>Tutorial bearbeiten – <?= htmlspecialchars($tutorial['name']) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- Bootstrap 5 CSS & Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.min.css">
  <!-- CKEditor 5 Classic Build -->
  <script src="https://cdn.ckeditor.com/ckeditor5/35.0.1/classic/ckeditor.js"></script>
  <style>
    body { padding-top: 4.5rem; background: #f5f7fa; }
    .card { border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); border: none; }
    .download-card {
      border: 1px solid #ddd;
      border-radius: 8px;
      padding: 1rem;
      display: flex;
      align-items: center;
      gap: 1rem;
      margin-bottom: 1rem;
      background: #fff;
    }
    .download-card i { font-size: 2rem; color: #d9534f; }
  </style>
</head>
<body>
<?php include '../menu.php'; ?>

<div class="container mt-3">
  <h1>Tutorial bearbeiten – <?= htmlspecialchars($tutorial['name']) ?></h1>
  <div class="card p-4 mb-4">
    <form method="post" action="tutorial_edit.php?id=<?= $tutorial_id ?>" enctype="multipart/form-data">
      <!-- Tutorial-Typ -->
      <div class="mb-3">
        <label class="form-label">Tutorial-Typ</label>
        <select name="tutorial_type" id="tutorial_type" class="form-select" onchange="toggleTypeFields()">
          <option value="function" <?= ($tutorial['tutorial_type'] === 'function') ? 'selected' : '' ?>>Funktion</option>
          <option value="module" <?= ($tutorial['tutorial_type'] === 'module') ? 'selected' : '' ?>>Modul</option>
        </select>
      </div>
      <!-- Modul-Auswahl -->
      <div class="mb-3" id="moduleSelect" style="display: <?= ($tutorial['tutorial_type'] === 'module') ? 'block' : 'none' ?>;">
        <label class="form-label">Modul auswählen</label>
        <select name="module_id" class="form-select" id="moduleDropdown" onchange="setModuleIcon()">
          <option value="">Bitte wählen...</option>
          <?php foreach ($modules as $mod): ?>
            <option value="<?= $mod['id'] ?>" data-icon="<?= htmlspecialchars($mod['icon']) ?>"
              <?= ($tutorial['module_id'] == $mod['id']) ? 'selected' : '' ?>>
              <?= htmlspecialchars($mod['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <!-- Funktion: Icon -->
      <div class="mb-3" id="functionIcon" style="display: <?= ($tutorial['tutorial_type'] === 'function') ? 'block' : 'none' ?>;">
        <label class="form-label">Icon (Bootstrap Icons)</label>
        <input type="text" name="icon" class="form-control" placeholder="bi-alarm, bi-clock, ..." value="<?= htmlspecialchars($tutorial['icon']) ?>">
        <small class="text-muted">Tipp: Siehe <a href="https://icons.getbootstrap.com/" target="_blank">Bootstrap Icons</a></small>
      </div>
      <!-- Titel -->
      <div class="mb-3">
        <label class="form-label">Titel</label>
        <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($tutorial['name']) ?>" required>
      </div>
      <!-- Kurzbeschreibung -->
      <div class="mb-3">
        <label class="form-label">Kurzbeschreibung</label>
        <input type="text" name="short_description" class="form-control" value="<?= htmlspecialchars($tutorial['short_description']) ?>" required>
      </div>
      <!-- Beschreibung -->
      <div class="mb-3">
        <label class="form-label">Beschreibung</label>
        <textarea name="description" id="editor" class="form-control"><?= htmlspecialchars($tutorial['description']) ?></textarea>
      </div>
      <!-- Video-Upload -->
      <div class="mb-3">
        <label class="form-label">Video (optional)</label>
        <?php if (!empty($tutorial['video_file'])): ?>
          <p>Aktuelles Video: <?= htmlspecialchars($tutorial['video_file']) ?></p>
        <?php endif; ?>
        <input type="file" name="video_file" class="form-control">
        <small class="text-muted">Falls ein neues Video hochgeladen wird, ersetzt es das alte.</small>
      </div>
      <!-- Attachments -->
      <div class="mb-3">
        <label class="form-label">Weitere Dateien (optional)</label>
        <?php if (!empty($tutorial['attachments'])): ?>
          <div>
            <?php 
              $att = json_decode($tutorial['attachments'], true) ?: [];
              foreach ($att as $file): 
            ?>
              <div class="download-card">
                <i class="bi bi-file-earmark-pdf"></i>
                <a href="../uploads/<?= urlencode($file) ?>" download><?= htmlspecialchars($file) ?></a>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
        <input type="file" name="attachments[]" multiple class="form-control">
        <small class="text-muted">Neue Dateien werden zu den bestehenden hinzugefügt.</small>
      </div>
      <!-- Störung (Issue) -->
      <div class="mb-3">
        <label class="form-label">Störung (optional)</label>
        <textarea name="issue_description" class="form-control" id="issue_editor" rows="3"><?= htmlspecialchars($tutorial['issue_description']) ?></textarea>
        <div class="form-check mt-2">
          <input type="checkbox" class="form-check-input" id="module_deactivated" name="module_deactivated" <?= ($tutorial['module_deactivated']) ? 'checked' : '' ?>>
          <label class="form-check-label" for="module_deactivated">Modul als deaktiviert markieren</label>
        </div>
        <div class="mt-2">
          <label class="form-label">Störung gilt bis (optional)</label>
          <input type="datetime-local" name="issue_expires_at" class="form-control" value="<?= !empty($tutorial['issue_expires_at']) ? date('Y-m-d\TH:i', strtotime($tutorial['issue_expires_at'])) : '' ?>">
          <small class="text-muted">Nach diesem Zeitpunkt wird die Störung nicht mehr angezeigt.</small>
        </div>
      </div>
      <!-- Aktionen -->
      <button type="submit" class="btn btn-primary">Änderungen speichern</button>
    </form>
  </div>
</div>

<script>
function toggleTypeFields() {
  const val = document.getElementById('tutorial_type').value;
  document.getElementById('moduleSelect').style.display = (val === 'module') ? 'block' : 'none';
  document.getElementById('functionIcon').style.display = (val === 'function') ? 'block' : 'none';
}

function setModuleIcon() {
  const dropdown = document.getElementById('moduleDropdown');
  const iconInput = document.querySelector('input[name="icon"]');
  const selectedOption = dropdown.options[dropdown.selectedIndex];
  if (selectedOption && selectedOption.dataset.icon) {
    iconInput.value = selectedOption.dataset.icon;
  } else {
    iconInput.value = "";
  }
}

ClassicEditor
    .create(document.querySelector('#editor'), {
        licenseKey: 'eyJhbGciOiJFUzI1NiJ9.eyJleHAiOjE3NzI0MDk1OTksImp0aSI6ImEyNDVlZTU4LTA5M2QtNGNjYi1hMjg3LTNiNDdmMTdhMzBjNSIsImxpY2Vuc2VkSG9zdHMiOlsiMTI3LjAuMC4xIiwibG9jYWxob3N0IiwiMTkyLjE2OC4qLioiLCIxMC4qLiouKiIsIjE3Mi4qLiouKiIsIioudGVzdCIsIioubG9jYWxob3N0IiwiKi5sb2NhbCJdLCJ1c2FnZUVuZHBvaW50IjoiaHR0cHM6Ly9wcm94eS1ldmVudC5ja2VkaXRvci5jb20iLCJkaXN0cmlidXRpb25DaGFubmVsIjpbImNsb3VkIiwiZHJ1cGFsIl0sImxpY2Vuc2VUeXBlIjoiZGV2ZWxvcG1lbnQiLCJmZWF0dXJlcyI6WyJEUlVQIl0sInZjIjoiMDY0YTliMTMifQ.s5LHXOzH038Q3wUXXtmE_w7_MJioa66NXAtgMQLACFG7I4K1QMxGmTyJVZ-UyozIpmTPy57yBXKYpxwWtRkr3Q'
    })
    .catch(error => {
        console.error(error);
    });

ClassicEditor
    .create(document.querySelector('#issue_editor'))
    .catch(error => {
        console.error(error);
    });
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
