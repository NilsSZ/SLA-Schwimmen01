<?php
/********************************************************
 * DASHBOARD – Modulauswahl
 *
 * Dieses Dashboard konzentriert sich ausschließlich auf die
 * Modulauswahl. Der Nutzer sieht große Kacheln, über die er
 * direkt in ein Modul (z. B. "Daten hinzufügen", "Wettkampf",
 * "Livetiming", "Bestzeiten", "Trainingsplan" und "Online Shop")
 * gelangen kann.
 ********************************************************/

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();

// Sicherstellen, dass der Nutzer eingeloggt ist
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id   = $_SESSION['user_id'];
$user_name = $_SESSION['name'] ?? 'Benutzer';

// Beispielhafter Admin-Check: Nur Nutzer mit ID 2 haben Adminrechte
$is_admin = ($user_id == 2);

require_once 'dbconnection.php';

/* ---------- 1. Statische Module ---------- */
// Hier haben wir den bisherigen Satz fester Module. Wir entfernen den bisherigen
// Wettkampf-Erstellungslink und fügen stattdessen eine neue Kachel "Wettkampf" ein,
// die per Klick ein Popup (Modal) öffnet.
$static_tiles = [
    [
        'title'       => 'Daten hinzufügen',
        'link'        => '/sla-projekt/module/daten_hinzufuegen.php',
        'icon'        => 'bi-clock-history',
        'description' => 'Erfasse Deine Trainingszeiten.'
    ],
    [
        'title'       => 'Wettkampf',
        'link'        => '',  // Link leer – der Klick öffnet das Modal
        'icon'        => 'bi-trophy',
        'description' => 'Wettkampffunktionen: Erstellen oder Liste anzeigen.'
    ],
    [
        'title'       => 'Livetiming',
        'link'        => '/sla-projekt/module/livetiming.php',
        'icon'        => 'bi-stopwatch',
        'description' => 'Starte den Livetiming-Modus live.'
    ],
    [
        'title'       => 'Bestzeiten',
        'link'        => '/sla-projekt/module/meine_zeiten.php',
        'icon'        => 'bi-bar-chart-line',
        'description' => 'Sieh Dir Deine persönlichen Bestzeiten an.'
    ],
    [
        'title'       => 'Trainingsplan',
        'link'        => '/sla-projekt/module/trainingsplan.php',
        'icon'        => 'bi-activity',
        'description' => 'Verwalte Deinen Trainingsplan.'
    ],
    [
        'title'       => 'Online Shop',
        'link'        => '/sla-projekt/module/online-shop.php',
        'icon'        => 'bi-shop',
        'description' => 'Kaufe Module und Trainingshilfen.'
    ]
];

/* ---------- 2. Admin-Tiles abrufen ---------- */
$admin_tiles = [];
if ($is_admin) {
    $result = $conn->query("SELECT id, title, link, icon, description FROM admin_tiles ORDER BY id DESC");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $admin_tiles[] = $row;
        }
    }
}

/* ---------- 3. Alle Tiles zusammenführen ---------- */
$all_tiles = array_merge($static_tiles, $admin_tiles);

$conn->close();
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <title>Dashboard – SLA-Schwimmen</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <!-- Bootstrap 5 CSS & Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.min.css">
  <style>
    body {
      background: #e9ecef;
      padding-top: 80px;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }
    /* Neuer Header: Eleganter, dunkelblauer Header */
    .main-header {
      background: linear-gradient(135deg, #003366, #002244);
      color: #fff;
      padding: 3rem 1rem;
      text-align: center;
      margin-bottom: 40px;
    }
    .main-header h1 {
      font-size: 3rem;
      font-weight: 700;
      margin-bottom: 0.5rem;
    }
    .main-header p {
      font-size: 1.3rem;
      margin: 0;
    }
    /* Modul-Kacheln */
    .module-card {
      transition: transform 0.3s, box-shadow 0.3s;
      cursor: pointer;
      background: #fff;
      border: none;
      border-radius: 10px;
      padding: 1.5rem;
      text-align: center;
      margin-bottom: 20px;
    }
    .module-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 4px 12px rgba(0,0,0,0.3);
    }
    .module-icon {
      font-size: 3rem;
      color: #0056b3;
    }
    .module-title {
      font-weight: 600;
      margin-top: 1rem;
    }
    .module-desc {
      font-size: 0.9rem;
      color: #6c757d;
      margin-top: 0.5rem;
    }
  </style>
</head>
<body>
  <?php include 'menu.php'; ?>

  <!-- Neuer Header -->
  <div class="main-header">
    <h1>SLA-Schwimmen Dashboard</h1>
    <p>Willkommen, <?= htmlspecialchars($user_name, ENT_QUOTES, 'UTF-8'); ?> – wähle dein Modul</p>
  </div>

  <div class="container my-4">
    <!-- Modul-Auswahl Banner -->
    <div class="mb-4 text-center">
      <h2>Modulauswahl</h2>
      <p>Klicke auf eine Kachel, um das gewünschte Modul zu starten.</p>
    </div>

    <?php if (isset($flash_error) && $flash_error): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($flash_error) ?></div>
    <?php endif; ?>
    <?php if (isset($flash_success) && $flash_success): ?>
      <div class="alert alert-success"><?= htmlspecialchars($flash_success) ?></div>
    <?php endif; ?>

    <!-- Modul-Kacheln -->
    <div class="row g-4">
      <?php foreach ($all_tiles as $tile): ?>
        <div class="col-md-4">
          <?php if($tile['title'] == 'Wettkampf'): ?>
            <!-- Bei der Wettkampf-Kachel wird kein direkter Link gesetzt, sondern ein Modal geöffnet -->
            <div class="module-card" onclick="openWettkampfModal()">
              <i class="bi <?= htmlspecialchars($tile['icon']) ?> module-icon"></i>
              <h5 class="module-title"><?= htmlspecialchars($tile['title']) ?></h5>
              <p class="module-desc"><?= htmlspecialchars($tile['description']) ?></p>
            </div>
          <?php else: ?>
            <div class="module-card" onclick="window.location.href='<?= htmlspecialchars($tile['link']) ?>'">
              <i class="bi <?= htmlspecialchars($tile['icon']) ?> module-icon"></i>
              <h5 class="module-title"><?= htmlspecialchars($tile['title']) ?></h5>
              <p class="module-desc"><?= htmlspecialchars($tile['description']) ?></p>
            </div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Wettkampf Modal -->
  <div class="modal fade" id="wettkampfModal" tabindex="-1" aria-labelledby="wettkampfModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header bg-primary text-white">
          <h5 class="modal-title" id="wettkampfModalLabel">Wettkampf Optionen</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Schließen"></button>
        </div>
        <div class="modal-body text-center">
          <p>Bitte wähle eine Option:</p>
          <div class="d-grid gap-2">
            <a href="/sla-projekt/module/wettkampf_erstellen.php" class="btn btn-primary"><i class="bi bi-pencil-square"></i> Wettkampf erstellen</a>
            <a href="/sla-projekt/module/wettkampf.php" class="btn btn-secondary"><i class="bi bi-list-ul"></i> Wettkampf Liste</a>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Schließen</button>
        </div>
      </div>
    </div>
  </div>

  <?php include 'footer.php'; ?>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    function openWettkampfModal() {
      var wettkampfModal = new bootstrap.Modal(document.getElementById('wettkampfModal'));
      wettkampfModal.show();
    }
  </script>
</body>
</html>
<?php if (ob_get_length()) { ob_end_flush(); } ?>
