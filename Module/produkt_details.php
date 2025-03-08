<?php
/********************************************************
 * PRODUKT DETAILS – MODULE
 * Zeigt alle Details eines ausgewählten Moduls an,
 * inkl. Beschreibung, Bewertungen, FAQ und PDF-Upload.
 ********************************************************/

error_reporting(E_ALL);
ini_set('display_errors', 1);
ob_start();
session_start();

// DB-Verbindung einbinden (alle Dateien liegen im selben Verzeichnis)
require_once 'dbconnection.php';

// Überprüfen, ob eine Produkt-ID übergeben wurde
if (!isset($_GET['id'])) {
    header("Location: online-shop.php");
    exit;
}

$product_id = (int) $_GET['id'];

// Produkt aus der Tabelle "modules" abrufen
$stmt = $conn->prepare("SELECT id, name, description, price, image FROM modules WHERE id = ?");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    echo "<div class='container mt-4'><div class='alert alert-danger'>Produkt nicht gefunden.</div></div>";
    exit;
}
$product = $result->fetch_assoc();
$stmt->close();

// (Optional) Bewertungen abrufen (falls Tabelle "reviews" existiert)
$reviews = [];
$reviewStmt = $conn->prepare("SELECT user_name, rating, comment, created_at FROM reviews WHERE module_id = ? ORDER BY created_at DESC");
if ($reviewStmt) {
    $reviewStmt->bind_param("i", $product_id);
    $reviewStmt->execute();
    $reviewResult = $reviewStmt->get_result();
    while ($row = $reviewResult->fetch_assoc()) {
        $reviews[] = $row;
    }
    $reviewStmt->close();
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <title><?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?> – Produktdetails</title>
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
    .product-header {
      margin-bottom: 2rem;
      text-align: center;
    }
    .product-img {
      max-width: 100%;
      height: auto;
      border-radius: 5px;
    }
    /* FAQ-Akkordeon */
    .accordion-button:not(.collapsed) {
      color: #fff;
      background-color: #0d6efd;
    }
    .accordion-item {
      border: 1px solid #0d6efd;
    }
    /* Sterne-Bewertung */
    .star-rating {
      direction: rtl;
      font-size: 1.5rem;
    }
    .star-rating input[type="radio"] {
      display: none;
    }
    .star-rating label {
      color: #bbb;
      cursor: pointer;
      transition: all 0.3s;
    }
    .star-rating input[type="radio"]:checked ~ label,
    .star-rating label:hover,
    .star-rating label:hover ~ label {
      color: #FFD700;
    }
    /* Kaufbereich */
    .buy-section {
      margin-top: 2rem;
      padding: 2rem;
      background: #fff;
      border-radius: 5px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
  </style>
</head>
<body>
  <!-- Menü einbinden -->
  <?php include 'menu.php'; ?>

  <div class="container">
    <div class="product-header">
      <h1><?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?></h1>
      <p class="lead">Preis: <?= number_format($product['price'], 2, ',', '.') ?> €</p>
    </div>

    <div class="row">
      <div class="col-md-6">
        <?php if (!empty($product['image'])): ?>
          <img src="<?= htmlspecialchars($product['image'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?>" class="product-img img-fluid">
        <?php else: ?>
          <img src="placeholder.jpg" alt="Kein Bild" class="product-img img-fluid">
        <?php endif; ?>
      </div>
      <div class="col-md-6">
        <!-- Tab-Navigation für Beschreibung, Bewertungen, FAQ und Kaufoptionen -->
        <ul class="nav nav-tabs" id="productTab" role="tablist">
          <li class="nav-item" role="presentation">
            <button class="nav-link active" id="beschreibung-tab" data-bs-toggle="tab" data-bs-target="#beschreibung" type="button" role="tab" aria-controls="beschreibung" aria-selected="true">Beschreibung</button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="bewertungen-tab" data-bs-toggle="tab" data-bs-target="#bewertungen" type="button" role="tab" aria-controls="bewertungen" aria-selected="false">Bewertungen</button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="faq-tab" data-bs-toggle="tab" data-bs-target="#faq" type="button" role="tab" aria-controls="faq" aria-selected="false">FAQ</button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="kaufen-tab" data-bs-toggle="tab" data-bs-target="#kaufen" type="button" role="tab" aria-controls="kaufen" aria-selected="false">Kaufen</button>
          </li>
        </ul>
        <div class="tab-content" id="productTabContent">
          <!-- Beschreibung -->
          <div class="tab-pane fade show active p-3" id="beschreibung" role="tabpanel" aria-labelledby="beschreibung-tab">
            <p><?= nl2br(htmlspecialchars($product['description'], ENT_QUOTES, 'UTF-8')); ?></p>
          </div>
          <!-- Bewertungen -->
          <div class="tab-pane fade p-3" id="bewertungen" role="tabpanel" aria-labelledby="bewertungen-tab">
            <?php if (count($reviews) > 0): ?>
              <?php foreach ($reviews as $review): ?>
                <div class="mb-3 border-bottom pb-2">
                  <h6><?= htmlspecialchars($review['user_name'], ENT_QUOTES, 'UTF-8'); ?> 
                    <small class="text-muted"><?= date("d.m.Y H:i", strtotime($review['created_at'])); ?></small>
                  </h6>
                  <div class="star-rating mb-1">
                    <?php
                    $rating = intval($review['rating']);
                    for ($i = 5; $i >= 1; $i--) {
                        echo ($i <= $rating) ? '<i class="bi bi-star-fill"></i> ' : '<i class="bi bi-star"></i> ';
                    }
                    ?>
                  </div>
                  <p><?= nl2br(htmlspecialchars($review['comment'], ENT_QUOTES, 'UTF-8')); ?></p>
                </div>
              <?php endforeach; ?>
            <?php else: ?>
              <p>Keine Bewertungen vorhanden.</p>
            <?php endif; ?>
            <!-- Bewertungsformular -->
            <div class="mt-4">
              <h5>Bewertung abgeben</h5>
              <form method="post" action="bewertung_absenden.php">
                <div class="star-rating mb-2">
                  <input id="rate5" type="radio" name="rating" value="5">
                  <label for="rate5"><i class="bi bi-star-fill"></i></label>
                  <input id="rate4" type="radio" name="rating" value="4">
                  <label for="rate4"><i class="bi bi-star-fill"></i></label>
                  <input id="rate3" type="radio" name="rating" value="3">
                  <label for="rate3"><i class="bi bi-star-fill"></i></label>
                  <input id="rate2" type="radio" name="rating" value="2">
                  <label for="rate2"><i class="bi bi-star-fill"></i></label>
                  <input id="rate1" type="radio" name="rating" value="1">
                  <label for="rate1"><i class="bi bi-star-fill"></i></label>
                </div>
                <div class="mb-3">
                  <textarea name="comment" class="form-control" rows="3" placeholder="Deine Bewertung..."></textarea>
                </div>
                <input type="hidden" name="module_id" value="<?= $product['id']; ?>">
                <button type="submit" class="btn btn-success">Bewertung absenden</button>
              </form>
            </div>
          </div>
          <!-- FAQ -->
          <div class="tab-pane fade p-3" id="faq" role="tabpanel" aria-labelledby="faq-tab">
            <div class="accordion" id="faqAccordion">
              <div class="accordion-item">
                <h2 class="accordion-header" id="faqHeadingOne">
                  <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#faqCollapseOne" aria-expanded="true" aria-controls="faqCollapseOne">
                    Frage 1: Wie funktioniert dieses Modul?
                  </button>
                </h2>
                <div id="faqCollapseOne" class="accordion-collapse collapse show" aria-labelledby="faqHeadingOne" data-bs-parent="#faqAccordion">
                  <div class="accordion-body">
                    Antwort: Das Modul hilft Dir, Deine Trainingsdaten zu optimieren und liefert detaillierte Analysen.
                  </div>
                </div>
              </div>
              <div class="accordion-item">
                <h2 class="accordion-header" id="faqHeadingTwo">
                  <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqCollapseTwo" aria-expanded="false" aria-controls="faqCollapseTwo">
                    Frage 2: Welche Voraussetzungen benötige ich?
                  </button>
                </h2>
                <div id="faqCollapseTwo" class="accordion-collapse collapse" aria-labelledby="faqHeadingTwo" data-bs-parent="#faqAccordion">
                  <div class="accordion-body">
                    Antwort: Du benötigst einen aktiven Account und ein kompatibles Endgerät, um das Modul zu nutzen.
                  </div>
                </div>
              </div>
              <!-- Weitere FAQ-Einträge können hier hinzugefügt werden -->
            </div>
            <!-- PDF Upload für zusätzliche Informationen -->
            <div class="mt-4">
              <h5>Weitere Informationen (PDF) hochladen</h5>
              <form method="post" action="upload_module_pdf.php" enctype="multipart/form-data">
                <input type="hidden" name="module_id" value="<?= htmlspecialchars($product['id'], ENT_QUOTES, 'UTF-8'); ?>">
                <div class="mb-3">
                  <label for="module_pdf" class="form-label">PDF auswählen:</label>
                  <input type="file" class="form-control" id="module_pdf" name="module_pdf" accept="application/pdf">
                </div>
                <button type="submit" class="btn btn-primary">PDF hochladen</button>
              </form>
            </div>
          </div>
          <!-- Kaufen -->
          <div class="tab-pane fade p-3" id="kaufen" role="tabpanel" aria-labelledby="kaufen-tab">
            <div class="buy-section text-center">
              <h4>Jetzt kaufen</h4>
              <p>Wähle, ob Du das Modul für Dich selbst oder als Geschenk erwerben möchtest.</p>
              <form method="post" action="stripe_checkout.php">
                <input type="hidden" name="module_id" value="<?= htmlspecialchars($product['id'], ENT_QUOTES, 'UTF-8'); ?>">
                <div class="mb-3">
                  <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="purchase_type" id="for_me" value="self" checked>
                    <label class="form-check-label" for="for_me">Für mich</label>
                  </div>
                  <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="purchase_type" id="as_gift" value="gift">
                    <label class="form-check-label" for="as_gift">Als Geschenk</label>
                  </div>
                </div>
                <!-- E-Mail-Feld, falls Geschenk ausgewählt -->
                <div class="mb-3" id="giftEmailField" style="display:none;">
                  <label for="gift_email" class="form-label">Empfänger-E-Mail</label>
                  <input type="email" class="form-control" name="gift_email" id="gift_email" placeholder="beispiel@domain.de">
                </div>
                <button type="submit" class="btn btn-lg btn-primary">
                  Jetzt bezahlen <i class="bi bi-currency-eur"></i>
                </button>
              </form>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div><!-- .container -->

  <!-- Footer einbinden -->
  <?php include 'footer.php'; ?>

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <!-- JavaScript zum Umschalten des E-Mail-Feldes -->
  <script>
    const forMeRadio = document.getElementById('for_me');
    const asGiftRadio = document.getElementById('as_gift');
    const giftEmailField = document.getElementById('giftEmailField');
    
    function toggleGiftEmail() {
      giftEmailField.style.display = asGiftRadio.checked ? 'block' : 'none';
    }
    
    forMeRadio.addEventListener('change', toggleGiftEmail);
    asGiftRadio.addEventListener('change', toggleGiftEmail);
    
    // Beim Laden der Seite prüfen
    toggleGiftEmail();
  </script>
</body>
</html>
<?php
ob_end_flush();
?>
