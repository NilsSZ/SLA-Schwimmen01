<?php
// tutorial_detail.php
session_start();
require_once '../dbconnection.php';

$tutorial_id = $_GET['id'] ?? 0;
$user_id = $_SESSION['user_id'] ?? 0;
$is_admin = ($user_id == 2);

// Tutorial laden
$sql = "SELECT * FROM tutorials WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $tutorial_id);
$stmt->execute();
$res = $stmt->get_result();
$tutorial = $res->fetch_assoc();
$stmt->close();

if (!$tutorial) {
    die("Tutorial nicht gefunden.");
}

// Falls nicht veröffentlicht & nicht Admin -> Abbruch
if (!$is_admin && $tutorial['is_published'] == 0) {
    die("Dieses Tutorial ist noch nicht veröffentlicht.");
}

// Daten aufbereiten
$description_html = $tutorial['description'];  // Enthält HTML
$steps_html       = $tutorial['steps'];        // Falls du Steps als HTML oder JSON speicherst
$q_and_a_html     = $tutorial['q_and_a'];      // Ggf. HTML
$version_history  = $tutorial['version_history'];
$video_url        = $tutorial['video_url'];
$attachments_json = $tutorial['attachments'];  // Falls du z.B. JSON mit Dateinamen hast

?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <title><?= htmlspecialchars($tutorial['name']) ?> – Tutorial</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- Bootstrap + Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.10.5/font/bootstrap-icons.min.css">
  <style>
    body { padding-top: 4.5rem; background: #f5f7fa; }
    .card { border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); border: none; }
  </style>
</head>
<body>

<?php include '../menu.php'; ?>

<div class="container mt-3">
  <h1><?= htmlspecialchars($tutorial['name']) ?></h1>
  <p class="text-muted"><?= htmlspecialchars($tutorial['short_description']) ?></p>

  <div class="card p-3 mb-4">
    <!-- Beschreibung -->
    <h4>Beschreibung</h4>
    <div><?= $description_html ?></div>

    <!-- Schritte (falls vorhanden) -->
    <?php if (!empty($steps_html)): ?>
      <hr>
      <h4>Schritte</h4>
      <div><?= $steps_html ?></div>
    <?php endif; ?>

    <!-- Q&A (falls vorhanden) -->
    <?php if (!empty($q_and_a_html)): ?>
      <hr>
      <h4>Q & A</h4>
      <div><?= $q_and_a_html ?></div>
    <?php endif; ?>

    <!-- Video (falls vorhanden) -->
    <?php if (!empty($video_url)): ?>
      <hr>
      <h4>Video</h4>
      <div class="ratio ratio-16x9">
        <iframe src="<?= htmlspecialchars($video_url) ?>" frameborder="0" allowfullscreen></iframe>
      </div>
    <?php endif; ?>

    <!-- Attachments (falls vorhanden) -->
    <?php if (!empty($attachments_json)): 
      // Beispiel: attachments_json = '["handbuch.pdf","screenshots.zip"]'
      $attachments = json_decode($attachments_json, true) ?: [];
    ?>
      <hr>
      <h4>Downloads</h4>
      <ul>
        <?php foreach ($attachments as $file): ?>
          <li>
            <a href="uploads/<?= urlencode($file) ?>" download><?= htmlspecialchars($file) ?></a>
            <small class="text-muted d-block">© Urheberrechte beachten!</small>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>

    <!-- Version-Historie -->
    <?php if (!empty($version_history)): ?>
      <hr>
      <h4>Version-Historie</h4>
      <pre><?= htmlspecialchars($version_history) ?></pre>
    <?php endif; ?>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
