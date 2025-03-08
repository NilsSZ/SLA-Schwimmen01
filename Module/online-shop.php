<?php
// Fehlerberichterstattung (nur in der Entwicklung)
error_reporting(E_ALL);
ini_set('display_errors', 1);

ob_start();
session_start();

// DB-Verbindung einbinden (alle Dateien liegen im selben Verzeichnis)
require_once 'dbconnection.php';
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <title>Module kaufen – Online Shop</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- Bootstrap CSS und Bootstrap Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
  <!-- Custom CSS -->
  <style>
    body {
      background: #f8f9fa;
      padding-top: 70px;
    }
    .shop-header {
      background: linear-gradient(135deg, #0d6efd, #66b2ff);
      color: #fff;
      padding: 2rem;
      text-align: center;
      margin-bottom: 2rem;
    }
    .product-card {
      transition: transform 0.3s;
    }
    .product-card:hover {
      transform: scale(1.02);
    }
    .product-img {
      height: 200px;
      object-fit: cover;
    }
  </style>
</head>
<body>
  <!-- Menü einbinden -->
  <?php include 'menu.php'; ?>

  <div class="container">
    <div class="shop-header">
      <h1>Module kaufen</h1>
      <p>Wähle das Modul, das zu Dir passt und erweitere Deine Trainingsmöglichkeiten.</p>
    </div>
    
    <?php
    // Module aus der Datenbank abrufen (Annahme: Es gibt eine Tabelle "modules" mit Feldern: id, name, description, price, image)
    $sql = "SELECT * FROM modules ORDER BY name ASC";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0):
    ?>
      <div class="row g-4">
        <?php while ($module = $result->fetch_assoc()): ?>
          <div class="col-md-4">
            <div class="card product-card h-100">
              <?php if (!empty($module['image'])): ?>
                <img src="<?= htmlspecialchars($module['image'], ENT_QUOTES, 'UTF-8') ?>" class="card-img-top product-img" alt="<?= htmlspecialchars($module['name'], ENT_QUOTES, 'UTF-8') ?>">
              <?php else: ?>
                <img src="placeholder.jpg" class="card-img-top product-img" alt="Kein Bild">
              <?php endif; ?>
              <div class="card-body d-flex flex-column">
                <h5 class="card-title"><?= htmlspecialchars($module['name'], ENT_QUOTES, 'UTF-8') ?></h5>
                <p class="card-text"><?= htmlspecialchars($module['description'], ENT_QUOTES, 'UTF-8') ?></p>
                <div class="mt-auto">
                  <p class="fw-bold">Preis: <?= number_format($module['price'], 2, ',', '.') ?> €</p>
                 <a href="produkt_details.php?id=<?= $module['id'] ?>" class="btn btn-primary w-100">
    Details anzeigen <i class="bi bi-info-circle"></i>
</a>

                </div>
              </div>
            </div>
          </div>
        <?php endwhile; ?>
      </div>
    <?php else: ?>
      <div class="alert alert-info">Es wurden keine Module gefunden.</div>
    <?php endif; ?>
  </div><!-- .container -->

  <!-- Footer einbinden -->
  <?php include 'footer.php'; ?>

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
ob_end_flush();
?>
