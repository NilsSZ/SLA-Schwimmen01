<?php
// schwimm_news_detail.php
session_start();
require_once '../dbconnection.php';

function isAdmin() {
    return isset($_SESSION['user_id']) &&
           ($_SESSION['user_id'] == 2 || (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1));
}

$admin = isAdmin();
$news_id = $_GET['id'] ?? 0;

$stmt = $conn->prepare("SELECT * FROM news WHERE id = ?");
$stmt->bind_param("i", $news_id);
$stmt->execute();
$result = $stmt->get_result();
$news = $result->fetch_assoc();
$stmt->close();

if (!$news) {
    die("News-Beitrag nicht gefunden.");
}
if (!$admin && $news['is_published'] == 0) {
    die("Dieser Beitrag ist noch nicht veröffentlicht.");
}

// Attachments
$attachments = [];
if (!empty($news['attachments'])) {
    $attachments = json_decode($news['attachments'], true) ?: [];
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <title><?= htmlspecialchars($news['title']) ?> – Schwimm-News</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- Bootstrap 5 CSS & Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.min.css">
  <style>
    body { background: #f5f7fa; padding-top: 4.5rem; }
    .news-detail { background: #fff; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); padding: 2rem; margin-bottom: 2rem; }
    .news-title { font-size: 2rem; font-weight: bold; }
    .news-date { font-size: 0.9rem; color: #6c757d; }
    .download-card {
      border: 1px solid #ddd; border-radius: 8px; padding: 1rem;
      display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem;
      background: #fff;
    }
    .download-card i { font-size: 2rem; color: #d9534f; }
  </style>
</head>
<body>
  <?php include '../menu.php'; ?>
  <div class="container mt-3">
    <div class="news-detail">
      <?php if (!empty($news['image'])): ?>
        <img src="../uploads/<?= urlencode($news['image']) ?>" alt="<?= htmlspecialchars($news['title']) ?>" class="img-fluid mb-3">
      <?php endif; ?>
      <div class="news-title"><?= htmlspecialchars($news['title']) ?></div>
      <div class="news-date"><?= date('d.m.Y H:i', strtotime($news['created_at'])) ?></div>
      <div class="mt-3">
        <?= $news['content'] ?>
      </div>
      <?php if (!empty($attachments)): ?>
        <hr>
        <h5>Downloads</h5>
        <?php foreach ($attachments as $file): ?>
          <div class="download-card">
            <i class="bi bi-file-earmark-pdf"></i>
            <a href="../uploads/<?= urlencode($file) ?>" download><?= htmlspecialchars($file) ?></a>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
      <?php if ($admin): ?>
        <hr>
        <div class="d-flex gap-2">
          <a href="schwimm_news_edit.php?id=<?= $news['id'] ?>" class="btn btn-primary"><i class="bi bi-pencil-square"></i> Bearbeiten</a>
          <a href="schwimm_news_delete.php?id=<?= $news['id'] ?>" onclick="return confirm('Beitrag wirklich löschen?');" class="btn btn-danger"><i class="bi bi-trash"></i> Löschen</a>
          <?php if ($news['is_published'] == 0): ?>
            <a href="schwimm_news_publish.php?id=<?= $news['id'] ?>" class="btn btn-success"><i class="bi bi-check2-circle"></i> Veröffentlichen</a>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
