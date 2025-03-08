<?php
// refund_request.php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$license_id = isset($_GET['license_id']) ? (int)$_GET['license_id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Hier speicherst Du die Rückerstattungsanfrage in einer Tabelle oder sendest eine E-Mail an den Admin.
    // Beispiel: In einer Tabelle "refund_requests" speichern (diese Tabelle musst Du anlegen)
    require_once 'dbconnection.php';
    $reason = trim($_POST['reason'] ?? '');
    $stmt = $conn->prepare("INSERT INTO refund_requests (license_id, user_id, reason, request_date) VALUES (?, ?, ?, NOW())");
    $stmt->bind_param("iis", $license_id, $user_id, $reason);
    $stmt->execute();
    $stmt->close();
    $conn->close();
    
    echo "<div class='container mt-4'><div class='alert alert-success'>Deine Rückerstattungsanfrage wurde erfolgreich übermittelt. Unser Support wird sich in Kürze bei Dir melden.</div></div>";
    exit();
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <title>Rückerstattung anfordern</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background: #f8f9fa; padding-top: 70px; }
  </style>
</head>
<body>
  <?php include 'menu.php'; ?>
  <div class="container">
    <h1 class="mt-4">Rückerstattung anfordern</h1>
    <form method="post" action="refund_request.php?license_id=<?= $license_id; ?>">
      <div class="mb-3">
        <label for="reason" class="form-label">Grund der Rückerstattungsanfrage</label>
        <textarea name="reason" id="reason" class="form-control" rows="4" placeholder="Bitte beschreibe den Grund der Rückerstattung..."></textarea>
      </div>
      <button type="submit" class="btn btn-warning">Anfrage absenden</button>
    </form>
  </div>
  <?php include 'footer.php'; ?>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
