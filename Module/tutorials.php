<?php
// tutorials.php
session_start();
require_once '../dbconnection.php';

$user_id = $_SESSION['user_id'] ?? 0;
$is_admin = ($user_id == 2);

// Hole Tutorials: Wenn admin -> alle, sonst nur is_published=1
if ($is_admin) {
    $sql = "SELECT * FROM tutorials ORDER BY created_at DESC";
} else {
    $sql = "SELECT * FROM tutorials WHERE is_published = 1 ORDER BY created_at DESC";
}
$result = $conn->query($sql);

?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <title>Tutorials</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- Bootstrap CSS + Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.10.5/font/bootstrap-icons.min.css">
  <style>
    body { padding-top: 4.5rem; background: #f5f7fa; }
    .card-tutorial {
      border: none; 
      border-radius: 8px; 
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
      transition: transform 0.2s;
    }
    .card-tutorial:hover {
      transform: scale(1.02);
    }
    .tutorial-icon {
      font-size: 2rem;
      color: #0d6efd;
    }
  </style>
</head>
<body>

<?php include '../menu.php'; ?>

<div class="container mt-3">
  <h1 class="mb-4">Tutorials</h1>

  <?php if ($is_admin): ?>
    <a href="tutorial_create.php" class="btn btn-success mb-3">
      <i class="bi bi-plus-lg"></i> Neues Tutorial erstellen
    </a>
  <?php endif; ?>

  <div class="row">
    <?php while ($row = $result->fetch_assoc()): 
      $icon = ($row['tutorial_type'] === 'module')
        ? '' // Falls wir das Icon aus der modules-Tabelle holen müssten, bräuchten wir einen JOIN.
        : $row['icon']; 
    ?>
      <div class="col-md-4 mb-4">
        <div class="card card-tutorial p-3">
          <!-- Icon & Titel -->
          <div class="d-flex align-items-center mb-2">
            <?php if ($row['tutorial_type'] === 'module'): ?>
              <!-- Optional: wenn du in modules.icon was hinterlegt hast, 
                   könntest du das Icon hier ausgeben -->
              <i class="bi bi-app-indicator tutorial-icon me-2"></i>
            <?php else: ?>
              <i class="bi <?= htmlspecialchars($icon) ?> tutorial-icon me-2"></i>
            <?php endif; ?>
            <h5 class="mb-0"><?= htmlspecialchars($row['name']) ?></h5>
          </div>
          <p class="text-muted"><?= htmlspecialchars($row['short_description']) ?></p>
          <a href="tutorial_detail.php?id=<?= $row['id'] ?>" class="stretched-link"></a>
        </div>
      </div>
    <?php endwhile; ?>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
