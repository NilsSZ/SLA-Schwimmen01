<?php
// success.php

// Stripe-Bibliothek manuell einbinden (ohne Composer)
require_once '/kunden/homepages/4/d1007545866/htdocs/SLA-Projekt/stripe-php-master/init.php';
// Für den PDF-Generator: FPDF (manuell heruntergeladen)
require_once '/kunden/homepages/4/d1007545866/htdocs/fpdf/fpdf.php';
// PHPMailer manuell einbinden (ohne Composer)
require_once '/kunden/homepages/4/d1007545866/htdocs/SLA-Projekt/PHPMailer-master/src/PHPMailer.php';
require_once '/kunden/homepages/4/d1007545866/htdocs/SLA-Projekt/PHPMailer-master/src/SMTP.php';
require_once '/kunden/homepages/4/d1007545866/htdocs/SLA-Projekt/PHPMailer-master/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

\Stripe\Stripe::setApiKey('sk_test_51Qp9j0RsaJvBoRs2uDPbKySkvspNKaTFE0SynxY6CZuU7FX0Y1T4xtfZKgnRzgUGnMyyIVDlawxyJ7vNDlkboxeZ00UVYQXeD7');

session_start();

// Parameter aus der URL abrufen
$session_id   = $_GET['session_id'] ?? '';
$purchaseType = $_GET['purchase_type'] ?? 'self'; // "self" oder "gift"
$module_id    = (int) ($_GET['module_id'] ?? 0);
$giftEmail    = $_GET['gift_email'] ?? '';

// Funktion: Lizenzcode erzeugen (8-stellig, z. B. "SELF-XXXXXX" oder "GIFT-XXXXXX")
function generateLicenseCode($prefix = 'SELF', $len = 6) {
    $chars = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
    $code = '';
    for ($i = 0; $i < $len; $i++) {
        $code .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $prefix . '-' . strtoupper($code);
}

// Funktion: PDF-Rechnung mit Lizenzcode erzeugen und speichern
function generateLicensePDF($licenseCode) {
    $pdf = new FPDF();
    $pdf->AddPage();
    $pdf->SetFont('Arial','B',16);
    $pdf->Cell(0,10,'Deine Modulrechnung',0,1,'C');
    $pdf->Ln(10);
    $pdf->SetFont('Arial','',14);
    $pdf->Cell(0,10,'Lizenzcode: ' . $licenseCode,0,1,'C');
    // Hier kannst Du weitere Rechnungsdetails einfügen
    $invoiceDir = __DIR__ . '/invoices';
    if (!is_dir($invoiceDir)) {
        mkdir($invoiceDir, 0777, true);
    }
    $filePath = $invoiceDir . '/invoice_' . $licenseCode . '.pdf';
    $pdf->Output('F', $filePath);
    return $filePath;
}

// Funktion: E-Mail mit Rechnung und Lizenzcode versenden (verwende Deine SMTP-Daten)
function sendInvoiceEmail($recipient, $subject, $body, $licenseCode, $pdfFile = null) {
    $mail = new PHPMailer(true);
    try {
        // SMTP-Konfiguration – bitte anpassen!
        $mail->isSMTP();
        $mail->Host       = 'smtp.deinedomain.de';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'smtp_benutzer@deinedomain.de';
        $mail->Password   = 'dein_smtp_passwort';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        
        $mail->setFrom('no-reply@deinedomain.de', 'SLA-Schwimmen');
        $mail->addAddress($recipient);
        
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = nl2br($body);
        $mail->AltBody = $body;
        
        if ($pdfFile && file_exists($pdfFile)) {
            $mail->addAttachment($pdfFile);
        }
        
        $mail->send();
    } catch (Exception $e) {
        error_log("E-Mail konnte nicht gesendet werden. Mailer Error: " . $mail->ErrorInfo);
    }
}

// Simuliere hier – normalerweise prüfst Du in der Erfolgverarbeitung die Stripe Checkout Session
// Für dieses Beispiel gehen wir davon aus, dass die Zahlung erfolgreich war.

if ($purchaseType === 'self') {
    $licenseCode = generateLicenseCode('SELF');
} else {
    $licenseCode = generateLicenseCode('GIFT');
}

// Hier solltest Du auch in Deiner DB einen Eintrag in der Tabelle "licenses" vornehmen.
// Das Beispiel überspringt diesen Schritt (DB-Update), da er projektspezifisch ist.

// Erstelle die PDF-Rechnung
$pdfFile = generateLicensePDF($licenseCode);

// Bestimme den Empfänger: Für "self" die eigene E-Mail aus der Session, für "gift" die angegebene E-Mail (Fallback: eigene E-Mail)
$recipient = ($purchaseType === 'gift' && !empty($giftEmail))
    ? $giftEmail
    : ($_SESSION['email'] ?? 'deine_email@domain.de');

// Versende die Rechnung per E-Mail
if ($purchaseType === 'self') {
    $emailSubject = 'Deine Modulrechnung';
    $emailBody = "Dein Modul wurde erfolgreich freigeschaltet.\nLizenzcode: $licenseCode\n\nVielen Dank für Deinen Kauf!";
} else {
    $emailSubject = 'Dein Geschenkmodul – Rechnung & Freischaltcode';
    $emailBody = "Vielen Dank für den Kauf als Geschenk!\n\nDein Freischaltcode lautet: $licenseCode\nBitte bewahre diesen Code gut auf.\n\nDie Rechnung findest Du im Anhang.";
}
sendInvoiceEmail($recipient, $emailSubject, $emailBody, $licenseCode, $pdfFile);
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <title>Vielen Dank für Deinen Kauf</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background: #f8f9fa; padding-top: 70px; }
    .success-card {
      max-width: 600px;
      margin: 30px auto;
    }
    .success-card .card-header {
      background: #28a745;
      color: #fff;
      font-size: 1.5rem;
      text-align: center;
    }
    .success-card .card-body {
      text-align: center;
    }
    .license-code {
      font-size: 1.25rem;
      font-weight: bold;
      color: #dc3545;
    }
  </style>
</head>
<body>
  <?php include 'menu.php'; ?>
  <div class="container">
    <div class="card success-card shadow">
      <div class="card-header">
        Vielen Dank für Deinen Kauf!
      </div>
      <div class="card-body">
        <?php if($purchaseType === 'self'): ?>
          <p>Dein Modul wurde erfolgreich aktiviert.</p>
          <p>Dein Lizenzcode lautet:</p>
          <p class="license-code"><?= htmlspecialchars($licenseCode, ENT_QUOTES, 'UTF-8'); ?></p>
          <p>Eine Rechnung wurde an Deine E-Mail gesendet.</p>
        <?php else: ?>
          <p>Dein Geschenk wurde erfolgreich versendet.</p>
          <p>Der Freischaltcode lautet:</p>
          <p class="license-code"><?= htmlspecialchars($licenseCode, ENT_QUOTES, 'UTF-8'); ?></p>
          <p>Die Rechnung und der Geschenkcode wurden an die angegebene E-Mail gesendet.</p>
        <?php endif; ?>
        <a href="meine_lizenzen.php" class="btn btn-primary mt-3">Zu meinen Lizenzen</a>
        <a href="online-shop.php" class="btn btn-secondary mt-3">Weitere Module kaufen</a>
      </div>
    </div>
  </div>
  <?php include 'footer.php'; ?>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
ob_end_flush();
?>