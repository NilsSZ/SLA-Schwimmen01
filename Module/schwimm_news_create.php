<?php
session_start();
require_once '../dbconnection.php';

// Admin-Check: Nur User mit ID 2 oder is_admin==1 dürfen News erstellen.
function isAdmin() {
    return isset($_SESSION['user_id']) &&
           ($_SESSION['user_id'] == 2 || (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1));
}

if (!isAdmin()) {
    die("Nur Admin dürfen Schwimm-News erstellen.");
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $content = $_POST['content'] ?? '';
    
    // Bild-Upload (optional)
    $image = null;
    if (!empty($_FILES['image']['name'])) {
        $image = basename($_FILES['image']['name']);
        move_uploaded_file($_FILES['image']['tmp_name'], "../uploads/" . $image);
    }
    
    // Status: Speichern als Entwurf (is_published=0) oder direkt veröffentlichen (is_published=1)
    $action = $_POST['action'] ?? 'save';
    $is_published = ($action === 'publish') ? 1 : 0;
    
    $stmt = $conn->prepare("INSERT INTO news (title, content, image, is_published, created_by) VALUES (?,?,?,?,?)");
    $stmt->bind_param("sssii", $title, $content, $image, $is_published, $user_id);
    if ($stmt->execute()) {
        header("Location: schwimm_news_list.php");
        exit();
    } else {
        echo "Fehler beim Speichern: " . $stmt->error;
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <title>Neue Schwimm-News erstellen</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- Bootstrap CSS & Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- CKEditor 5 Classic Build -->
  <script src="https://cdn.ckeditor.com/ckeditor5/35.0.1/classic/ckeditor.js"></script>
  <style>
    body { padding-top: 4.5rem; background: #eef2f7; }
    .card { border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
  </style>
</head>
<body>
  <?php include '../menu.php'; ?>
  <div class="container mt-3">
    <h1>Neue Schwimm-News erstellen</h1>
    <div class="card p-4">
      <form method="post" action="schwimm_news_create.php" enctype="multipart/form-data">
        <div class="mb-3">
          <label class="form-label">Titel</label>
          <input type="text" name="title" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Inhalt</label>
          <textarea name="content" id="editor" class="form-control"></textarea>
        </div>
        <div class="mb-3">
          <label class="form-label">Bild (optional)</label>
          <input type="file" name="image" class="form-control">
          <small class="text-muted">Das Bild wird in den News als Titelbild angezeigt.</small>
        </div>
        <div class="mb-3">
          <button type="submit" name="action" value="save" class="btn btn-secondary">Als Entwurf speichern</button>
          <button type="submit" name="action" value="publish" class="btn btn-primary">Veröffentlichen</button>
        </div>
      </form>
    </div>
  </div>
  <script>
    ClassicEditor
      .create(document.querySelector('#editor'), {
          licenseKey: 'eyJhbGciOiJFUzI1NiJ9.eyJleHAiOjE3NzI0MDk1OTksImp0aSI6ImEyNDVlZTU4LTA5M2QtNGNjYi1hMjg3LTNiNDdmMTdhMzBjNSIsImxpY2Vuc2VkSG9zdHMiOlsiMTI3LjAuMC4xIiwibG9jYWxob3N0IiwiMTkyLjE2OC4qLioiLCIxMC4qLiouKiIsIjE3Mi4qLiouKiIsIioudGVzdCIsIioubG9jYWxob3N0IiwiKi5sb2NhbCJdLCJ1c2FnZUVuZHBvaW50IjoiaHR0cHM6Ly9wcm94eS1ldmVudC5ja2VkaXRvci5jb20iLCJkaXN0cmlidXRpb25DaGFubmVsIjpbImNsb3VkIiwiZHJ1cGFsIl0sImxpY2Vuc2VUeXBlIjoiZGV2ZWxvcG1lbnQiLCJmZWF0dXJlcyI6WyJEUlVQIl0sInZjIjoiMDY0YTliMTMifQ.s5LHXOzH038Q3wUXXtmE_w7_MJioa66NXAtgMQLACFG7I4K1QMxGmTyJVZ-UyozIpmTPy57yBXKYpxwWtRkr3Q'
      })
      .catch(error => { console.error(error); });
  </script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
