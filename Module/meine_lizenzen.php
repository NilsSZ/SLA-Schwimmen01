<?php
/********************************************************
 * MEINE LIZENZEN
 * Zeigt alle erworbenen Module/Lizenzen des Benutzers an.
 ********************************************************/
error_reporting(E_ALL);
ini_set('display_errors', 1);
ob_start();
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

require_once 'dbconnection.php';

$stmt = $conn->prepare("SELECT l.id, l.license_code, l.invoice_pdf, l.purchase_date, m.name, m.description, m.info_pdf, m.file_name 
                        FROM licenses l 
                        INNER JOIN modules m ON l.module_id = m.id 
                        WHERE l.user_id = ? 
                        ORDER BY l.purchase_date DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$licenses = [];
while ($row = $result->fetch_assoc()) {
    $licenses[] = $row;
}
$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Meine Lizenzen</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Bootstrap CSS & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
      body { background: #f8f9fa; padding-top: 70px; }
      .license-card { margin-bottom: 20px; }
      .license-header { text-align: center; margin-bottom: 30px; }
    </style>
</head>
<body>
    <?php include 'menu.php'; ?>
    <div class="container">
        <div class="license-header">
            <h1>Meine Lizenzen</h1>
            <p>Hier siehst Du alle Module, die Du erworben hast.</p>
        </div>
        <?php if (count($licenses) > 0): ?>
            <div class="row">
                <?php foreach ($licenses as $lic): ?>
                    <div class="col-md-4">
                        <div class="card license-card">
                            <?php if (!empty($lic['info_pdf'])): ?>
                                <img src="<?= htmlspecialchars($lic['info_pdf'], ENT_QUOTES, 'UTF-8'); ?>" class="card-img-top" alt="<?= htmlspecialchars($lic['name'], ENT_QUOTES, 'UTF-8'); ?>">
                            <?php else: ?>
                                <img src="placeholder.jpg" class="card-img-top" alt="Kein Bild">
                            <?php endif; ?>
                            <div class="card-body">
                                <h5 class="card-title"><?= htmlspecialchars($lic['name'], ENT_QUOTES, 'UTF-8'); ?></h5>
                                <p class="fw-bold">Lizenzcode: <span class="text-primary"><?= htmlspecialchars($lic['license_code'], ENT_QUOTES, 'UTF-8'); ?></span></p>
                                <p class="text-muted">Erworben am: <?= date("d.m.Y H:i", strtotime($lic['purchase_date'])); ?></p>
                                <div class="d-grid gap-2">
                                    <?php if (!empty($lic['invoice_pdf'])): ?>
                                        <a href="<?= htmlspecialchars($lic['invoice_pdf'], ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-outline-secondary" target="_blank">
                                            Rechnung herunterladen
                                        </a>
                                    <?php endif; ?>
                                    <a href="produkt_details.php?id=<?= $lic['module_id']; ?>" class="btn btn-success">
                                        Modul öffnen <i class="bi bi-box-arrow-in-right"></i>
                                    </a>
                                    <!-- Rückerstattungsanfrage -->
                                    <a href="refund_request.php?license_id=<?= $lic['id']; ?>" class="btn btn-warning">
                                        Rückerstattung anfordern <i class="bi bi-arrow-counterclockwise"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-info text-center">Du hast noch keine Lizenzen erworben.</div>
        <?php endif; ?>
    </div>
    <?php include 'footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
ob_end_flush();
?>
