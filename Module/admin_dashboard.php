<?php
// admin_dashboard.php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] != 2) {
    header("Location: access_denied.php");
    exit();
}
require_once 'dbconnection.php';

// Beispiel: Zähle die Anzahl der Module und Refund-Anfragen
$resultModules = $conn->query("SELECT COUNT(*) as total FROM modules");
$rowModules = $resultModules->fetch_assoc();
$totalModules = $rowModules['total'] ?? 0;

$resultRefund = $conn->query("SELECT COUNT(*) as total FROM refund_requests WHERE status = 'pending'");
$rowRefund = $resultRefund->fetch_assoc();
$totalRefund = $rowRefund['total'] ?? 0;

$conn->close();
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <title>Admin Dashboard – Übersicht</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background: #f0f2f5; padding-top: 70px; }
    .dashboard-tile {
      transition: transform 0.3s;
      text-decoration: none;
    }
    .dashboard-tile:hover {
      transform: scale(1.05);
    }
    .tile {
      background: #0d6efd;
      color: #fff;
      border-radius: 8px;
      padding: 20px;
      text-align: center;
      margin-bottom: 20px;
    }
    .tile-icon {
      font-size: 3rem;
      margin-bottom: 10px;
    }
  </style>
</head>
<body>
  <?php include 'menu.php'; ?>
  <div class="container">
    <h1 class="text-center my-4">Admin Dashboard</h1>
    <div class="row">
      <!-- Kachel: Shop steuern -->
      <div class="col-md-4">
        <a href="shop.php" class="dashboard-tile">
          <div class="tile">
            <div class="tile-icon"><i class="bi bi-shop"></i></div>
            <h4>Shop steuern</h4>
            <p><?= $totalModules; ?> Module</p>
          </div>
        </a>
      </div>
      <!-- Kachel: Refund-Anfragen -->
      <div class="col-md-4">
        <a href="refund_admin.php" class="dashboard-tile" style="text-decoration: none;">
          <div class="tile" style="background: #dc3545;">
            <div class="tile-icon"><i class="bi bi-arrow-counterclockwise"></i></div>
            <h4>Refunds verwalten</h4>
            <p><?= $totalRefund; ?> Anfragen</p>
          </div>
        </a>
      </div>
      <!-- Weitere Kacheln für andere Funktionen -->
      <div class="col-md-4">
        <a href="module_manage.php" class="dashboard-tile">
          <div class="tile" style="background: #6f42c1;">
            <div class="tile-icon"><i class="bi bi-gear"></i></div>
            <h4>Module verwalten</h4>
            <p>Bearbeiten, hinzufügen, löschen</p>
          </div>
        </a>
      </div>
    </div>
  </div>
  <?php include 'footer.php'; ?>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
