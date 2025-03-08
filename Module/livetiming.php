<?php
/********************************************************
 * LIVETIMING – SCHWIMMER EDITION
 * Modernes, wasserinspiriertes Design
 * - Bootstrap Date/Time Picker (Flatpickr)
 * - Unterschiedliche Menüs für Athleten & Zuschauer
 * - PDF-Popup für Download-Link
 * - Anzeige aktiver Zuschauer und Toasts (nur einmal)
 ********************************************************/

// --- Start: Output-Buffering & Session ---
ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- DB-Verbindung einbinden (Pfad ggf. anpassen) ---
require_once __DIR__ . '/../dbconnection.php';

// --- Hilfsfunktionen ---
function e($str){ return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8'); }
function setFlash($k, $m){ $_SESSION['flash'][$k] = $m; }
function getFlash($k){ 
    if (!isset($_SESSION['flash'][$k])) { return null; }
    $v = $_SESSION['flash'][$k];
    unset($_SESSION['flash'][$k]);
    return $v;
}
function generateJoinCode($len = 8) {
    $chars = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
    $out = '';
    for ($i = 0; $i < $len; $i++) {
        $out .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $out;
}

// --- Aktuelle Aktion ermitteln ---
$action = $_GET['action'] ?? 'dashboard';


// ============================
// 1) NEUES LIVETIMING ERSTELLEN
// ============================
if ($action === 'do_create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $comp_id    = (int)($_POST['competition_id'] ?? 0);
    $start_date = $_POST['start_date'] ?? '';
    $join_method= $_POST['join_method'] ?? 'direct';
    $welc       = trim($_POST['welcome_message'] ?? '');
    $farew      = trim($_POST['farewell_message'] ?? '');
    $allow_com  = isset($_POST['allow_comments']) ? 1 : 0;
    $show_endt  = isset($_POST['show_endtime']) ? 1 : 0;
    
    if ($comp_id < 1 || !$start_date) {
        setFlash('error', 'Bitte alle benötigten Felder ausfüllen.');
        header('Location: ?action=create');
        exit;
    }
    // Wettkampfname ermitteln
    $chk = $conn->prepare("SELECT name FROM competitions WHERE id = ?");
    $chk->bind_param("i", $comp_id);
    $chk->execute();
    $cw = $chk->get_result()->fetch_assoc();
    $chk->close();
    if (!$cw) {
        setFlash('error', 'Ungültiger Wettkampf.');
        header('Location: ?action=create');
        exit;
    }
    $compName = $cw['name'];
    
    $joinCode = null;
    if ($join_method === 'code') {
        $joinCode = generateJoinCode(8);
    }
    
    // Korrigierter Bind-Typ: "i" (user_id), "s" (competition_name), "s" (start_date),
    // "s" (join_method), "s" (join_code), "s" (welcome_message), "s" (farewell_message),
    // "i" (allow_comments), "i" (show_endtime)
    $sql = "INSERT INTO livetiming_sessions
            (user_id, competition_name, start_date, join_method, join_code, is_active, welcome_message, farewell_message, allow_comments, show_endtime)
            VALUES (?, ?, ?, ?, ?, 1, ?, ?, ?, ?)";
    $st = $conn->prepare($sql);
    $uid = $_SESSION['user_id'] ?? 0;
    $st->bind_param("issssssss", $uid, $compName, $start_date, $join_method, $joinCode, $welc, $farew, $allow_com, $show_endt);
    $st->execute();
    $newId = $st->insert_id;
    $st->close();
    
    setFlash('success', 'Neues Livetiming wurde erstellt.');
    header("Location: ?action=detail&id=$newId");
    exit;
}


// ============================
// 2) SESSION BEENDEN
// ============================
if ($action === 'end_session' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $sid = (int)$_POST['session_id'];
    $up = $conn->prepare("UPDATE livetiming_sessions SET is_active = 0, end_date = NOW() WHERE id = ? AND user_id = ?");
    $up->bind_param("ii", $sid, $_SESSION['user_id']);
    $up->execute();
    $up->close();
    setFlash('success', 'Session beendet.');
    header("Location: ?action=detail&id=$sid");
    exit;
}


// ============================
// 3) PDF-HOCHLADEN
// ============================
if ($action === 'upload_pdf' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $sid = (int)$_POST['session_id'];
    // Besitz-Check
    $cc = $conn->prepare("SELECT id FROM livetiming_sessions WHERE id = ? AND user_id = ?");
    $cc->bind_param("ii", $sid, $_SESSION['user_id']);
    $cc->execute();
    $ex = $cc->get_result()->fetch_assoc();
    $cc->close();
    if (!$ex) {
        setFlash('error', 'Keine Berechtigung oder Session nicht gefunden.');
        header('Location: ?action=dashboard');
        exit;
    }
    if (!isset($_FILES['pdf_file']) || $_FILES['pdf_file']['error'] !== UPLOAD_ERR_OK) {
        setFlash('error', 'Dateiupload fehlgeschlagen.');
        header("Location: ?action=detail&id=$sid");
        exit;
    }
    $fn  = $_FILES['pdf_file']['name'];
    $tmp = $_FILES['pdf_file']['tmp_name'];
    $ext = strtolower(pathinfo($fn, PATHINFO_EXTENSION));
    if ($ext !== 'pdf') {
        setFlash('error', 'Nur PDF-Dateien erlaubt!');
        header("Location: ?action=detail&id=$sid");
        exit;
    }
    $uploadDir = __DIR__ . '/livetiming_uploads';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    $newN = 'LT_' . $sid . '_' . time() . '.pdf';
    $dest = $uploadDir . '/' . $newN;
    if (move_uploaded_file($tmp, $dest)) {
        $rel = 'livetiming_uploads/' . $newN;
        $u2 = $conn->prepare("UPDATE livetiming_sessions SET pdf_path = ? WHERE id = ?");
        $u2->bind_param("si", $rel, $sid);
        $u2->execute();
        $u2->close();
        // Markiere in der Session, dass der PDF-Popup angezeigt werden soll (nur einmal)
        $_SESSION["pdf_popup_$sid"] = true;
        setFlash('success', 'PDF hochgeladen.');
    } else {
        setFlash('error', 'Fehler beim Speichern der Datei.');
    }
    header("Location: ?action=detail&id=$sid");
    exit;
}


// ============================
// 4) AJAX: check_updates (Wettkampfstarter abrufen)
// ============================
if ($action === 'check_updates') {
    header('Content-Type: application/json; charset=utf-8');
    $sid = (int)($_GET['session_id'] ?? 0);
    if ($sid < 1) {
        echo json_encode(['error' => 'invalid session_id']);
        exit;
    }
    $sx = $conn->prepare("SELECT * FROM livetiming_sessions WHERE id = ?");
    $sx->bind_param("i", $sid);
    $sx->execute();
    $sess = $sx->get_result()->fetch_assoc();
    $sx->close();
    if (!$sess) {
        echo json_encode(['error' => 'session not found']);
        exit;
    }
    $sql = "SELECT cs.id, cs.wk_nr, cs.distance, cs.swim_time, cs.place,
                   cs.disqualified, cs.disq_reason,
                   ss.name AS swim_style_name
            FROM competition_starts cs
            JOIN competitions co ON co.id = cs.competition_id
            LEFT JOIN swim_styles ss ON ss.id = cs.swim_style_id
            WHERE co.name = ?
            ORDER BY cs.wk_nr ASC";
    $p = $conn->prepare($sql);
    $p->bind_param("s", $sess['competition_name']);
    $p->execute();
    $rows = $p->get_result()->fetch_all(MYSQLI_ASSOC);
    $p->close();
    echo json_encode([
        'success'     => true,
        'starts'      => $rows,
        'showEndtime' => (bool)$sess['show_endtime']
    ]);
    exit;
}


// ============================
// 5) KOMMENTAR ABSENDEN
// ============================
if ($action === 'post_comment' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $sid  = (int)$_POST['session_id'];
    // Der im Viewer gespeicherte Name wird genutzt
    $name = $_SESSION['viewer_name'] ?? trim($_POST['viewer_name'] ?? '');
    $cmt  = trim($_POST['comment'] ?? '');
    if ($sid < 1 || !$name || !$cmt) {
        setFlash('error', 'Bitte fülle alle Felder aus.');
        header("Location: ?action=viewer&sid=$sid");
        exit;
    }
    $c1 = $conn->prepare("SELECT allow_comments, is_active FROM livetiming_sessions WHERE id = ?");
    $c1->bind_param("i", $sid);
    $c1->execute();
    $sss = $c1->get_result()->fetch_assoc();
    $c1->close();
    if (!$sss) {
        setFlash('error', 'Session nicht gefunden.');
        header("Location: ?action=viewer&sid=$sid");
        exit;
    }
    if (!$sss['allow_comments']) {
        setFlash('error', 'Kommentare sind nicht erlaubt.');
        header("Location: ?action=viewer&sid=$sid");
        exit;
    }
    if (!$sss['is_active']) {
        setFlash('error', 'Session nicht aktiv, Kommentar nicht möglich.');
        header("Location: ?action=viewer&sid=$sid");
        exit;
    }
    $ins = $conn->prepare("INSERT INTO livetiming_comments (session_id, viewer_name, comment) VALUES (?, ?, ?)");
    $ins->bind_param("iss", $sid, $name, $cmt);
    $ins->execute();
    $ins->close();
    setFlash('success', 'Kommentar hinzugefügt.');
    header("Location: ?action=viewer&sid=$sid");
    exit;
}


// ============================
// 6) START (Podest/DQ) AKTUALISIEREN
// ============================
if ($action === 'update_start' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $start_id   = (int)$_POST['start_id'];
    $session_id = (int)$_POST['session_id'];
    $pl = (int)($_POST['place'] ?? 0);
    if ($pl < 1 || $pl > 3) $pl = null;
    $dq  = isset($_POST['disqualified']) ? 1 : 0;
    $dqr = trim($_POST['disq_reason'] ?? '');
    
    $q = "SELECT cs.competition_id, c.name as comp_name, ls.id as session_id, ls.user_id
          FROM competition_starts cs
          JOIN competitions c ON c.id = cs.competition_id
          JOIN livetiming_sessions ls ON ls.competition_name = c.name
          WHERE cs.id = ? AND ls.id = ?";
    $ck2 = $conn->prepare($q);
    $ck2->bind_param("ii", $start_id, $session_id);
    $ck2->execute();
    $rs = $ck2->get_result()->fetch_assoc();
    $ck2->close();
    if (!$rs) {
        setFlash('error', 'Start nicht gefunden oder passt nicht zur Session.');
        header("Location: ?action=detail&id=$session_id");
        exit;
    }
    if ($rs['user_id'] != $_SESSION['user_id']) {
        setFlash('error', 'Keine Berechtigung.');
        header("Location: ?action=detail&id=$session_id");
        exit;
    }
    $uu2 = $conn->prepare("UPDATE competition_starts SET place = ?, disqualified = ?, disq_reason = ? WHERE id = ?");
    $uu2->bind_param("iisi", $pl, $dq, $dqr, $start_id);
    $uu2->execute();
    $uu2->close();
    setFlash('success', 'Podest/DQ aktualisiert.');
    header("Location: ?action=detail&id=$session_id");
    exit;
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <title>Livetiming – Schwimmer Edition</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- Bootstrap CSS, Bootstrap Icons & Flatpickr CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
  <!-- Google Font -->
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600&display=swap">
  <style>
    /* Modern & schwimmerorientiert */
    body {
      background: linear-gradient(135deg, #d0e7f9, #a0c4f9);
      font-family: 'Open Sans', sans-serif;
      padding-top: 80px;
    }
    .hero {
      background: linear-gradient(135deg, #1e3d59, #2a5298);
      color: #fff;
      padding: 2rem;
      border-radius: 0.5rem;
      margin-bottom: 1.5rem;
      text-align: center;
      box-shadow: 0 2px 8px rgba(0,0,0,0.2);
    }
    .card {
      border: none;
      border-radius: 0.5rem;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
      margin-bottom: 1.5rem;
    }
    .nav-tabs .nav-link.active {
      background-color: #e9ecef;
      border-color: #dee2e6 #dee2e6 #fff;
    }
    .toast-container {
      position: fixed;
      top: 90px;
      right: 20px;
      z-index: 1050;
    }
  </style>
</head>
<body>
  <!-- Menü: Wenn Zuschauer, anderes Menü laden -->
  <?php
  if ($action === 'viewer') {
      include __DIR__ . '/menu_viewer.php';
  } else {
      include __DIR__ . '/menu.php';
  }
  ?>

  <div class="container">
    <?php if ($fe = getFlash('error')): ?>
      <div class="alert alert-danger mt-3"><?= e($fe) ?></div>
    <?php endif; ?>
    <?php if ($fs = getFlash('success')): ?>
      <div class="alert alert-success mt-3"><?= e($fs) ?></div>
    <?php endif; ?>

    <?php
    // SWITCH: Anzeige je nach Aktion
    switch ($action) {

      // ---------- CREATE ----------
      case 'create':
        $zz = $conn->query("SELECT id, name, competition_date FROM competitions WHERE competition_date >= CURDATE() ORDER BY competition_date ASC");
        $allZ = $zz->fetch_all(MYSQLI_ASSOC);
        ?>
        <div class="hero">
          <h1>Livetiming erstellen</h1>
        </div>
        <div class="card">
          <div class="card-body">
            <form method="post" action="?action=do_create">
              <div class="mb-3">
                <label class="form-label">Wettkampf</label>
                <select name="competition_id" class="form-select" required>
                  <option value="">-- wählen --</option>
                  <?php foreach ($allZ as $co): ?>
                    <option value="<?= $co['id'] ?>">
                      <?= e($co['name'] . ' (' . date('d.m.Y', strtotime($co['competition_date'])) . ')') ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <!-- Datum & Uhrzeit: Flatpickr -->
              <div class="mb-3">
                <label class="form-label">Start-Datum &amp; Uhrzeit</label>
                <input type="text" name="start_date" class="form-control datetimepicker" placeholder="Wähle Datum und Uhrzeit" required>
              </div>
              <div class="mb-3">
                <label class="form-label">Beitrittsmethode</label>
                <select name="join_method" class="form-select">
                  <option value="direct">Direkt (kein Code)</option>
                  <option value="code">Mit Code</option>
                </select>
              </div>
              <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" id="allow_comments" name="allow_comments" value="1">
                <label class="form-check-label" for="allow_comments">Kommentare erlauben</label>
              </div>
              <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" id="show_endtime" name="show_endtime" value="1" checked>
                <label class="form-check-label" for="show_endtime">Endzeiten für Zuschauer anzeigen</label>
              </div>
              <div class="mb-3">
                <label class="form-label">Willkommensnachricht</label>
                <textarea name="welcome_message" class="form-control" rows="2"></textarea>
              </div>
              <div class="mb-3">
                <label class="form-label">Abschiedsnachricht</label>
                <textarea name="farewell_message" class="form-control" rows="2"></textarea>
              </div>
              <button class="btn btn-primary" type="submit"><i class="bi bi-check-circle"></i> Erstellen</button>
              <a href="?action=dashboard" class="btn btn-secondary">Abbrechen</a>
            </form>
          </div>
        </div>
        <?php
        break;

      // ---------- DETAIL (Athletenansicht) ----------
      case 'detail':
        if (!isset($_GET['id'])) {
            echo '<div class="alert alert-danger">Keine ID.</div>';
            break;
        }
        $sid = (int)$_GET['id'];
        $ch = $conn->prepare("SELECT * FROM livetiming_sessions WHERE id = ? AND user_id = ?");
        $ch->bind_param("ii", $sid, $_SESSION['user_id']);
        $ch->execute();
        $sess = $ch->get_result()->fetch_assoc();
        $ch->close();
        if (!$sess) {
            echo '<div class="alert alert-danger">Session nicht gefunden oder keine Berechtigung.</div>';
            echo '<a href="?action=dashboard" class="btn btn-secondary">Zurück</a>';
            break;
        }
        ?>
        <div class="hero">
          <h1><?= e($sess['competition_name']) ?></h1>
          <p>Start: <?= date('d.m.Y H:i', strtotime($sess['start_date'])) ?></p>
        </div>
        <!-- Liste der Zuschauer (distinct aus Kommentaren) -->
        <div class="mb-3">
          <h5>Aktive Zuschauer:</h5>
          <?php
          $qv = $conn->prepare("SELECT DISTINCT viewer_name FROM livetiming_comments WHERE session_id = ?");
          $qv->bind_param("i", $sid);
          $qv->execute();
          $viewers = $qv->get_result()->fetch_all(MYSQLI_ASSOC);
          $qv->close();
          if ($viewers):
              echo '<ul class="list-group">';
              foreach ($viewers as $v) {
                  echo '<li class="list-group-item">' . e($v['viewer_name']) . '</li>';
              }
              echo '</ul>';
          else:
              echo '<p>Keine Zuschauer bisher.</p>';
          endif;
          ?>
        </div>
        <ul class="nav nav-tabs" role="tablist">
          <li class="nav-item">
            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#overview" type="button" role="tab">Übersicht</button>
          </li>
          <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#starts" type="button" role="tab">Starts (Podest / DQ)</button>
          </li>
        </ul>
        <div class="tab-content p-3 bg-white border border-top-0">
          <div class="tab-pane fade show active" id="overview" role="tabpanel">
            <p><strong>Beitritt:</strong> <?= $sess['join_method'] === 'code' ? 'Code: ' . e($sess['join_code']) : 'Direkt' ?></p>
            <p><strong>Kommentare:</strong> <?= $sess['allow_comments'] ? 'Ja' : 'Nein' ?></p>
            <p><strong>Endzeiten:</strong> <?= $sess['show_endtime'] ? 'Ja' : 'Nein' ?></p>
            <p><strong>Willkommen:</strong><br><?= nl2br(e($sess['welcome_message'])) ?></p>
            <p><strong>Abschied:</strong><br><?= nl2br(e($sess['farewell_message'])) ?></p>
            <?php if ($sess['pdf_path']): ?>
              <p>PDF: <a href="<?= e($sess['pdf_path']) ?>" target="_blank">Ansehen</a></p>
            <?php endif; ?>
            <?php if ($sess['is_active']): ?>
              <form method="post" action="?action=end_session" onsubmit="return confirm('Beenden?');" class="mb-3">
                <input type="hidden" name="session_id" value="<?= $sess['id'] ?>">
                <button class="btn btn-warning"><i class="bi bi-x-circle"></i> Beenden</button>
              </form>
              <form method="post" action="?action=upload_pdf" enctype="multipart/form-data">
                <input type="hidden" name="session_id" value="<?= $sess['id'] ?>">
                <div class="mb-2">
                  <label class="form-label">PDF hochladen</label>
                  <input type="file" name="pdf_file" class="form-control" accept="application/pdf">
                </div>
                <button class="btn btn-secondary"><i class="bi bi-upload"></i> Upload</button>
              </form>
            <?php else: ?>
              <div class="alert alert-info">Session ist beendet.</div>
            <?php endif; ?>
          </div>
          <div class="tab-pane fade" id="starts" role="tabpanel">
            <?php
            $qq = "SELECT cs.*, ss.name as swim_style_name
                   FROM competition_starts cs
                   JOIN competitions co ON co.id = cs.competition_id
                   LEFT JOIN swim_styles ss ON ss.id = cs.swim_style_id
                   WHERE co.name = ?
                   ORDER BY cs.wk_nr ASC";
            $s2 = $conn->prepare($qq);
            $s2->bind_param("s", $sess['competition_name']);
            $s2->execute();
            $arr = $s2->get_result()->fetch_all(MYSQLI_ASSOC);
            $s2->close();
            if (!$arr) {
                echo '<p>Keine Starts vorhanden.</p>';
            } else {
                echo '<div class="table-responsive"><table class="table table-striped">';
                echo '<thead class="table-dark"><tr>
                      <th>WK</th><th>Distanz</th><th>Art</th>
                      <th>Endzeit</th><th>Platz</th><th>DQ?</th><th>Bearbeiten</th>
                      </tr></thead><tbody>';
                foreach ($arr as $rw) {
                    echo '<tr>
                          <td>' . $rw['wk_nr'] . '</td>
                          <td>' . $rw['distance'] . 'm</td>
                          <td>' . e($rw['swim_style_name'] ?? '') . '</td>
                          <td>' . ($rw['swim_time'] ?: '-') . '</td>
                          <td>' . ($rw['place'] ?: '-') . '</td>
                          <td>' . ($rw['disqualified'] ? 'Ja (' . e($rw['disq_reason']) . ')' : 'Nein') . '</td>
                          <td>
                            <button class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#editStartModal' . $rw['id'] . '">
                              <i class="bi bi-pencil"></i> Bearbeiten
                            </button>
                          </td>
                          </tr>';
                    ?>
                    <div class="modal fade" id="editStartModal<?= $rw['id'] ?>" tabindex="-1">
                      <div class="modal-dialog">
                        <div class="modal-content">
                          <form method="post" action="?action=update_start">
                            <input type="hidden" name="start_id" value="<?= $rw['id'] ?>">
                            <input type="hidden" name="session_id" value="<?= $sess['id'] ?>">
                            <div class="modal-header">
                              <h5 class="modal-title">Start bearbeiten (WK<?= $rw['wk_nr'] ?>)</h5>
                              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                              <div class="mb-3">
                                <label class="form-label">Platz (1–3)</label>
                                <input type="number" name="place" class="form-control" value="<?= ($rw['place'] ? $rw['place'] : '') ?>" min="1" max="3">
                              </div>
                              <div class="mb-3 form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="disq<?= $rw['id'] ?>" name="disqualified" <?= $rw['disqualified'] ? 'checked' : '' ?>>
                                <label class="form-check-label" for="disq<?= $rw['id'] ?>">Disqualifiziert?</label>
                              </div>
                              <div class="mb-3">
                                <label class="form-label">Grund (falls DQ)</label>
                                <input type="text" name="disq_reason" class="form-control" value="<?= e($rw['disq_reason']) ?>">
                              </div>
                            </div>
                            <div class="modal-footer">
                              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                              <button class="btn btn-primary" type="submit"><i class="bi bi-save"></i> Speichern</button>
                            </div>
                          </form>
                        </div>
                      </div>
                    </div>
                    <?php
                }
                echo '</tbody></table></div>';
            }
            ?>
          </div>
        </div>
        <hr>
        <a href="?action=dashboard" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Zurück</a>
        <a href="?action=viewer&sid=<?= $sess['id'] ?>" target="_blank" class="btn btn-outline-primary"><i class="bi bi-tv"></i> Zuschauer-Ansicht</a>
        <?php
        break;

      // ---------- VIEWER (Zuschaueransicht) ----------
      case 'viewer':
        if (!isset($_GET['sid'])) {
            echo '<div class="alert alert-danger">Keine Session-ID.</div>';
            break;
        }
        $sid = (int)$_GET['sid'];
        $sz = $conn->prepare("SELECT * FROM livetiming_sessions WHERE id = ?");
        $sz->bind_param("i", $sid);
        $sz->execute();
        $sess = $sz->get_result()->fetch_assoc();
        $sz->close();
        if (!$sess) {
            echo '<div class="alert alert-danger">Session nicht gefunden.</div>';
            break;
        }
        // Automatisch Viewer-ID vergeben (falls noch nicht vorhanden)
        if (!isset($_SESSION['viewer_id'])) {
            $_SESSION['viewer_id'] = bin2hex(random_bytes(4));
        }
        // Falls Kommentare erlaubt sind, muss der Zuschauer seinen Namen eingeben (einmalig)
        if ($sess['allow_comments'] && empty($_SESSION['viewer_name'])) {
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['viewer_name'])) {
                $_SESSION['viewer_name'] = trim($_POST['viewer_name']);
                header("Location: ?action=viewer&sid=$sid");
                exit;
            } else {
                ?>
                <div class="hero">
                  <h1>Willkommen, Zuschauer!</h1>
                </div>
                <div class="card">
                  <div class="card-body">
                    <p>Bitte gib Deinen Namen ein, damit Dein Kommentar für den gesamten Wettkampf gespeichert wird.</p>
                    <form method="post" action="?action=viewer&sid=<?= $sid ?>">
                      <div class="mb-3">
                        <input type="text" name="viewer_name" class="form-control" placeholder="Dein Name" required>
                      </div>
                      <button type="submit" class="btn btn-primary"><i class="bi bi-person-check"></i> Weiter</button>
                    </form>
                  </div>
                </div>
                <?php
                exit;
            }
        }
        ?>
        <div class="toast-container" id="toastContainer"></div>
        <div class="hero">
          <h1><?= e($sess['competition_name']) ?></h1>
        </div>
        <?php
        $startT = strtotime($sess['start_date']);
        $now = time();
        // PDF-Popup: Wenn PDF existiert und Flag gesetzt, dann Popup anzeigen (nur einmal)
        if (!empty($sess['pdf_path']) && isset($_SESSION["pdf_popup_$sid"])) {
            // Nach Anzeige löschen, damit Popup nur einmal erscheint
            unset($_SESSION["pdf_popup_$sid"]);
            ?>
            <!-- PDF-Popup Modal -->
            <div class="modal fade" id="pdfModal" tabindex="-1">
              <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                  <div class="modal-header">
                    <h5 class="modal-title">PDF verfügbar</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                  </div>
                  <div class="modal-body">
                    <p>Ein PDF wurde hochgeladen. Du kannst es hier herunterladen:</p>
                    <a href="<?= e($sess['pdf_path']) ?>" target="_blank" class="btn btn-primary"><i class="bi bi-download"></i> PDF herunterladen</a>
                  </div>
                  <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Schließen</button>
                  </div>
                </div>
              </div>
            </div>
            <?php
        }
        if ($now < $startT): ?>
          <div class="alert alert-warning">Startet am <?= date('d.m.Y H:i', $startT) ?></div>
          <div id="countdown" style="font-size:1.5rem;font-weight:bold;"></div>
          <p><?= nl2br(e($sess['welcome_message'])) ?></p>
          <script>
            let diff = <?= $startT - $now ?>;
            function updateCD() {
              if (diff <= 0) { location.reload(); return; }
              let d = Math.floor(diff / 86400);
              let h = Math.floor((diff % 86400) / 3600);
              let m = Math.floor((diff % 3600) / 60);
              let s = Math.floor(diff % 60);
              document.getElementById('countdown').textContent = `Noch ${d}T ${h}h ${m}m ${s}s`;
              diff--;
            }
            updateCD();
            setInterval(updateCD, 1000);
          </script>
        <?php else: ?>
          <?php if (!$sess['is_active']): ?>
            <div class="alert alert-secondary">Session beendet.</div>
            <p><?= nl2br(e($sess['farewell_message'])) ?></p>
          <?php else: ?>
            <div class="alert alert-success">Livetiming ist aktiv!</div>
            <p><?= nl2br(e($sess['welcome_message'])) ?></p>
            <hr>
            <h4>Aktuelle Starts</h4>
            <div id="startsContainer"></div>
            <script>
              // Nutzung von localStorage, um Toasts nur einmal anzuzeigen
              function showToast(msg, key) {
                if (!localStorage.getItem(key)) {
                  let c = document.getElementById('toastContainer');
                  let d = document.createElement('div');
                  d.className = 'toast align-items-center text-bg-info border-0 show mb-2';
                  d.innerHTML = `
                    <div class="d-flex">
                      <div class="toast-body">${msg}</div>
                      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                    </div>
                  `;
                  c.appendChild(d);
                  setTimeout(() => { d.remove(); }, 5000);
                  localStorage.setItem(key, 'shown');
                }
              }
              let knownTimes = {}, knownPlaces = {}, knownDQ = {};
              function pollUpdates() {
                fetch('?action=check_updates&session_id=<?= $sid ?>')
                  .then(r => r.json())
                  .then(d => {
                    if (!d.success) return;
                    let showEndtime = d.showEndtime;
                    let h = '<table class="table table-striped">';
                    h += '<thead class="table-dark"><tr><th>WK</th><th>Distanz</th><th>Art</th>';
                    if (showEndtime) h += '<th>Endzeit</th>';
                    h += '<th>Platz</th><th>DQ?</th></tr></thead><tbody>';
                    d.starts.forEach(st => {
                      h += '<tr>';
                      h += '<td>' + st.wk_nr + '</td>';
                      h += '<td>' + st.distance + 'm</td>';
                      h += '<td>' + (st.swim_style_name || '') + '</td>';
                      if (showEndtime) {
                        let t = st.swim_time ? st.swim_time : '-';
                        h += '<td>' + t + '</td>';
                      }
                      let p = st.place ? st.place : '-';
                      h += '<td>' + p + '</td>';
                      let dq = st.disqualified ? 'Ja' + (st.disq_reason ? ' (' + st.disq_reason + ')' : '') : 'Nein';
                      h += '<td>' + dq + '</td>';
                      h += '</tr>';
                      
                      if (st.swim_time && st.swim_time !== (knownTimes[st.id]||'')) {
                        showToast(`Neue Endzeit: ${st.distance}m ${st.swim_style_name||''} = ${st.swim_time}`, 'toast_time_'+st.id);
                      }
                      knownTimes[st.id] = st.swim_time;
                      
                      if ((st.place||'') !== (knownPlaces[st.id]||'')) {
                        showToast(`Neuer Podestplatz: WK${st.wk_nr} -> Platz ${st.place}`, 'toast_place_'+st.id);
                      }
                      knownPlaces[st.id] = st.place;
                      
                      if ((st.disqualified||0) && (st.disqualified !== (knownDQ[st.id]||0))) {
                        showToast(`Disqualifikation WK${st.wk_nr}: ${st.disq_reason||''}`, 'toast_dq_'+st.id);
                      }
                      knownDQ[st.id] = st.disqualified;
                    });
                    h += '</tbody></table>';
                    document.getElementById('startsContainer').innerHTML = h;
                  });
              }
              pollUpdates();
              setInterval(pollUpdates, 5000);
            </script>
            <?php if ($sess['allow_comments']): ?>
              <hr>
              <h4>Kommentare</h4>
              <?php
              $cc = $conn->prepare("SELECT * FROM livetiming_comments WHERE session_id = ? ORDER BY created_at ASC");
              $cc->bind_param("i", $sid);
              $cc->execute();
              $allC = $cc->get_result()->fetch_all(MYSQLI_ASSOC);
              $cc->close();
              ?>
              <div style="max-height:300px; overflow:auto; background:#fff; border:1px solid #ccc; border-radius:5px; padding:10px;">
                <?php if (!$allC): ?>
                  <p>Keine Kommentare vorhanden.</p>
                <?php else: ?>
                  <?php foreach ($allC as $com): ?>
                    <p><strong><?= e($com['viewer_name']) ?>:</strong> <?= nl2br(e($com['comment'])) ?><br>
                      <small><?= e($com['created_at']) ?></small>
                    </p>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>
              <div class="card mt-3">
                <div class="card-body">
                  <h5 class="card-title">Neuer Kommentar</h5>
                  <form method="post" action="?action=post_comment">
                    <input type="hidden" name="session_id" value="<?= $sid ?>">
                    <div class="mb-3">
                      <label class="form-label">Dein Kommentar</label>
                      <textarea name="comment" rows="2" class="form-control" required></textarea>
                    </div>
                    <button class="btn btn-primary" type="submit"><i class="bi bi-send"></i> Absenden</button>
                  </form>
                </div>
              </div>
            <?php endif; ?>
          <?php endif; ?>
        <?php endif; ?>
        <?php
        break;

      // ---------- DASHBOARD ----------
      default:
        ?>
        <div class="hero">
          <h1>Livetiming Übersicht</h1>
        </div>
        <div class="card">
          <div class="card-body">
            <a href="?action=create" class="btn btn-primary mb-3"><i class="bi bi-plus-circle"></i> Neue Session</a>
            <?php
            $q2 = $conn->prepare("SELECT * FROM livetiming_sessions WHERE user_id = ? ORDER BY created_at DESC");
            $q2->bind_param("i", $_SESSION['user_id']);
            $q2->execute();
            $arr = $q2->get_result()->fetch_all(MYSQLI_ASSOC);
            $q2->close();
            if (!$arr) {
                echo '<p>Keine Livetiming-Sessions vorhanden.</p>';
            } else {
                echo '<div class="table-responsive"><table class="table table-striped">';
                echo '<thead class="table-dark"><tr>
                      <th>ID</th><th>Wettkampf</th><th>Startzeit</th><th>Aktiv</th><th>Aktion</th>
                      </tr></thead><tbody>';
                foreach ($arr as $rw) {
                    echo '<tr>
                          <td>' . $rw['id'] . '</td>
                          <td>' . e($rw['competition_name']) . '</td>
                          <td>' . e($rw['start_date']) . '</td>
                          <td>' . ($rw['is_active'] ? 'Ja' : 'Nein') . '</td>
                          <td>
                            <a href="?action=detail&id=' . $rw['id'] . '" class="btn btn-sm btn-info"><i class="bi bi-eye"></i> Details</a>
                            <a href="?action=viewer&sid=' . $rw['id'] . '" target="_blank" class="btn btn-sm btn-secondary"><i class="bi bi-tv"></i> Viewer</a>
                          </td>
                          </tr>';
                }
                echo '</tbody></table></div>';
            }
            ?>
          </div>
        </div>
        <?php
        break;
    }
    ?>
  </div><!-- .container -->

  <div class="toast-container" id="toastContainer"></div>

  <!-- Bootstrap JS, Flatpickr JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
  <script>
    // Initialisiere Flatpickr für Datum/Uhrzeit-Felder
    flatpickr(".datetimepicker", {
        enableTime: true,
        dateFormat: "Y-m-d\\TH:i",
    });
    // Falls PDF-Popup vorhanden, Modal automatisch anzeigen
    <?php if ($action==='viewer' && !empty($sess['pdf_path']) && !isset($_SESSION["pdf_popup_$sid"])): ?>
      var pdfModal = new bootstrap.Modal(document.getElementById('pdfModal'));
      pdfModal.show();
    <?php elseif ($action==='viewer' && !empty($sess['pdf_path']) && isset($_SESSION["pdf_popup_$sid"])): ?>
      // Modal wird hier über PHP direkt in den HTML-Code eingebunden und automatisch angezeigt
      var pdfModal = new bootstrap.Modal(document.getElementById('pdfModal'));
      pdfModal.show();
    <?php endif; ?>
  </script>
 <?php  include __DIR__ . '/footer.php'; ?>
</body>
</html>
<?php
// Ende des Output-Buffers
ob_end_flush();
?>
