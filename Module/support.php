<?php
// support.php – Support-Modul

// Session starten und Login prüfen
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
$user_id   = $_SESSION['user_id'];
$user_name = $_SESSION['name'] ?? 'Benutzer';

// DB-Verbindung (Passe den Pfad ggf. an)
require_once('dbconnection.php');

// Festlegen, wer als Supporter gilt (hier standardmäßig User-ID 2)
$is_support_admin = ($user_id == 2);

// Hilfsfunktion: Flash-Message
function setFlash($key, $msg) {
    $_SESSION['flash'][$key] = $msg;
}
function getFlash($key) {
    if (isset($_SESSION['flash'][$key])) {
        $msg = $_SESSION['flash'][$key];
        unset($_SESSION['flash'][$key]);
        return $msg;
    }
    return "";
}

// Wenn der Nutzer eine neue Support-Anfrage absendet (normaler User)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_ticket'])) {
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $data_access = isset($_POST['data_access']) ? 1 : 0;
    if (empty($subject) || empty($message)) {
        setFlash('error', "Bitte Betreff und Nachricht eingeben.");
    } else {
        // Erzeuge eine Fallnummer (Beispiel: SLA- + 12 zufällige Zeichen)
        $case_number = "SLA-" . strtoupper(bin2hex(random_bytes(6)));
        $stmt = $conn->prepare("INSERT INTO support_tickets (case_number, user_id, subject, message, data_access, status, created_at) VALUES (?, ?, ?, ?, ?, 'open', NOW())");
        $stmt->bind_param("sissi", $case_number, $user_id, $subject, $message, $data_access);
        if ($stmt->execute()) {
            setFlash('success', "Support-Anfrage gesendet. Ihre Fallnummer lautet: $case_number");
        } else {
            setFlash('error', "Fehler beim Senden der Anfrage: " . $stmt->error);
        }
        $stmt->close();
    }
    header("Location: support.php");
    exit();
}

// Support‑Fälle abrufen
$tickets = [];
if ($is_support_admin) {
    // Supporter sehen alle Fälle (ggf. mit Suchfunktion)
    if (isset($_GET['search_case']) && !empty(trim($_GET['search_case']))) {
        $search = trim($_GET['search_case']);
        // Der Nutzer gibt nur den numerischen Teil ein – füge den Präfix hinzu:
        $search = "SLA-" . $search;
        $stmt = $conn->prepare("SELECT * FROM support_tickets WHERE case_number = ? ORDER BY created_at DESC");
        $stmt->bind_param("s", $search);
    } else {
        $stmt = $conn->prepare("SELECT * FROM support_tickets ORDER BY created_at DESC");
    }
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $tickets[] = $row;
    }
    $stmt->close();
} else {
    // Normale Nutzer sehen nur ihre eigenen Fälle
    $stmt = $conn->prepare("SELECT * FROM support_tickets WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $tickets[] = $row;
    }
    $stmt->close();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <title>Support – SLA-Schwimmen</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- Bootstrap CSS & Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.min.css">
  <style>
    body {
        background: #f0f2f5;
        padding-top: 70px;
    }
    .container {
        max-width: 960px;
    }
    .support-form, .ticket-card {
        background: #fff;
        border-radius: 8px;
        padding: 20px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        margin-bottom: 20px;
    }
    .support-form h2 {
        background: #007bff;
        color: #fff;
        padding: 10px;
        border-radius: 8px 8px 0 0;
        margin: -20px -20px 20px -20px;
        text-align: center;
    }
    .ticket-card h5 {
        margin-bottom: 10px;
    }
    .ticket-card .ticket-meta {
        font-size: 0.9rem;
        color: #555;
    }
    .search-form .input-group-text {
        background: #007bff;
        color: #fff;
    }
  </style>
</head>
<body>
<div class="container">
  <h1 class="mb-4">Support</h1>
  <?php if ($flash_error = getFlash('error')): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($flash_error); ?></div>
  <?php endif; ?>
  <?php if ($flash_success = getFlash('success')): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($flash_success); ?></div>
  <?php endif; ?>

  <?php if (!$is_support_admin): ?>
    <!-- Formular für normale Nutzer: Neue Support-Anfrage -->
    <div class="support-form">
      <h2>Neue Support-Anfrage</h2>
      <form method="post" action="support.php">
        <div class="mb-3">
          <label for="subject" class="form-label">Betreff</label>
          <input type="text" id="subject" name="subject" class="form-control" required>
        </div>
        <div class="mb-3">
          <label for="message" class="form-label">Nachricht</label>
          <textarea id="message" name="message" rows="4" class="form-control" required></textarea>
        </div>
        <div class="mb-3 form-check">
          <input type="checkbox" id="data_access" name="data_access" class="form-check-input">
          <label for="data_access" class="form-check-label">Ich erteile Support Zugriff auf meine Daten (24h)</label>
        </div>
        <button type="submit" name="submit_ticket" class="btn btn-primary w-100">Absenden</button>
      </form>
    </div>
  <?php else: ?>
    <!-- Suchformular für Supporter/Admin -->
    <div class="mb-4">
      <form method="get" action="support.php" class="search-form input-group">
        <span class="input-group-text">SLA-</span>
        <input type="text" name="search_case" class="form-control" placeholder="Fallnummer (nur Nummer eingeben)">
        <button type="submit" class="btn btn-secondary">Suchen</button>
      </form>
    </div>
  <?php endif; ?>

  <!-- Liste der Support-Tickets -->
  <h2>Support-Fälle</h2>
  <?php if (empty($tickets)): ?>
    <p>Keine Support-Fälle gefunden.</p>
  <?php else: ?>
    <?php foreach ($tickets as $ticket): ?>
      <div class="ticket-card">
        <h5><?php echo htmlspecialchars($ticket['case_number']); ?> – <?php echo htmlspecialchars($ticket['subject']); ?></h5>
        <p class="ticket-meta">
          Von User: <?php echo htmlspecialchars($ticket['user_id']); ?> – Erstellt am: <?php echo htmlspecialchars($ticket['created_at']); ?> – Status: <?php echo htmlspecialchars($ticket['status']); ?>
        </p>
        <p><?php echo nl2br(htmlspecialchars($ticket['message'])); ?></p>
        <?php if ($is_support_admin): ?>
          <a href="support_ticket.php?case=<?php echo urlencode($ticket['case_number']); ?>" class="btn btn-sm btn-primary">Ticket öffnen</a>
        <?php else: ?>
          <a href="support_ticket.php?case=<?php echo urlencode($ticket['case_number']); ?>" class="btn btn-sm btn-primary">Ticket anzeigen</a>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
