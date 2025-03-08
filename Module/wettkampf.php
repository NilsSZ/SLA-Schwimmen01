<?php
/********************************************************
 * WETTKAMPF – Modul zur Verwaltung von Wettkampfs–Starts
 * 
 * In der Detailansicht eines Wettkampfs werden alle Starts
 * in einer Tabelle angezeigt. Für jeden Start wird die Meldezeit
 * (entry_time) sowie die Endzeit (swim_time) dargestellt. Die
 * Endzeit erscheint als direkt editierbares Input‑Feld, zu dem
 * jeweils ein „Speichern“‑Button gehört. Wird ein neuer Endzeitwert
 * eingegeben, wird dieser in der Tabelle competition_starts
 * gespeichert und (falls vorhanden) in der Tabelle times als
 * Wettkampfzeit (WKtime = 1) eingetragen.
 ********************************************************/

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
$user_id   = $_SESSION['user_id'];
$user_name = $_SESSION['name'] ?? 'Benutzer';

require_once 'dbconnection.php';

// Flash-Messages
function setFlash($msg) {
    $_SESSION['flash'] = $msg;
}
function getFlash() {
    if (isset($_SESSION['flash'])) {
        $m = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $m;
    }
    return "";
}
$flash = getFlash();

// Zeit-Funktionen
function convertTimeToSeconds($time) {
    $parts = explode(":", $time);
    if (count($parts) < 2) return 0;
    $min = intval($parts[0]);
    $secParts = explode(",", $parts[1]);
    $sec = intval($secParts[0]);
    $ms = (isset($secParts[1])) ? floatval("0." . $secParts[1]) : 0;
    return $min * 60 + $sec + $ms;
}
function improvementMessage($entry, $end) {
    $e = convertTimeToSeconds($entry);
    $d = convertTimeToSeconds($end);
    if ($e <= 0) return "";
    $diff = $e - $d;
    $pct = round(($diff / $e) * 100, 2);
    if ($diff > 0) {
        return "Verbessert um " . round($diff, 2) . " Sek. (" . $pct . "%)";
    } elseif ($diff < 0) {
        return "Verschlechtert um " . round(abs($diff), 2) . " Sek. (" . $pct . "%)";
    } else {
        return "Keine Veränderung";
    }
}

// ---------------------
// POST-Verarbeitung
// ---------------------

// A) Wettkampf bearbeiten (z. B. Überschrift ändern)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_competition'])) {
    $comp_id   = intval($_POST['comp_id'] ?? 0);
    $comp_name = trim($_POST['comp_name'] ?? '');
    $comp_date = trim($_POST['comp_date'] ?? '');
    if (empty($comp_name) || empty($comp_date)) {
        setFlash("Bitte Wettkampfname und Datum ausfüllen.");
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $comp_date) || !strtotime($comp_date)) {
        setFlash("Ungültiges Datum (Format YYYY-MM-DD).");
    } else {
        $st = $conn->prepare("UPDATE competitions SET name = ?, competition_date = ? WHERE id = ? AND user_id = ?");
        $st->bind_param("ssii", $comp_name, $comp_date, $comp_id, $user_id);
        $st->execute();
        $st->close();
        setFlash("Wettkampf aktualisiert.");
    }
    header("Location: wettkampf.php?id=" . $comp_id);
    exit();
}

// B) Neuen Start hinzufügen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_start'])) {
    $comp_id    = intval($_POST['comp_id'] ?? 0);
    $wk_nr      = intval($_POST['wk_nr'] ?? 0);
    $swim_style = intval($_POST['swim_style'] ?? 0);
    $distance   = intval($_POST['distance'] ?? 0);
    $entry_time = trim($_POST['entry_time'] ?? '');
    $lauf       = trim($_POST['lauf'] ?? '');
    $bahn       = trim($_POST['bahn'] ?? '');
    if ($wk_nr <= 0 || $swim_style <= 0 || $distance <= 0 || empty($entry_time)) {
        setFlash("Bitte alle Felder (WK-Nr, Schwimmart, Distanz, Meldezeit) ausfüllen.");
    } elseif (!preg_match('/^\d{2}:\d{2},\d{2}$/', $entry_time)) {
        setFlash("Meldezeit-Format ungültig (erforderlich: mm:ss,ms).");
    } else {
        $st = $conn->prepare("INSERT INTO competition_starts (user_id, competition_id, wk_nr, swim_style_id, distance, entry_time, lauf, bahn)
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $st->bind_param("iiiissss", $user_id, $comp_id, $wk_nr, $swim_style, $distance, $entry_time, $lauf, $bahn);
        $st->execute();
        $st->close();
        setFlash("Neuer Start wurde hinzugefügt.");
    }
    header("Location: wettkampf.php?id=" . $comp_id);
    exit();
}

// C) Start bearbeiten – Endzeit ändern (Inline-Formular)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_start'])) {
    $start_id   = intval($_POST['start_id'] ?? 0);
    $comp_id    = intval($_POST['comp_id'] ?? 0);
    // Wir erwarten hier, dass WK-Nr, Schwimmart, Distanz, Lauf und Bahn unverändert bleiben
    $wk_nr      = intval($_POST['wk_nr'] ?? 0);
    $swim_style = intval($_POST['swim_style'] ?? 0);
    $distance   = intval($_POST['distance'] ?? 0);
    $entry_time = trim($_POST['entry_time'] ?? '');
    $end_time   = trim($_POST['end_time'] ?? ''); // Endzeit (direkt im Input-Feld)
    $lauf       = trim($_POST['lauf'] ?? '');
    $bahn       = trim($_POST['bahn'] ?? '');

    if ($wk_nr <= 0 || $swim_style <= 0 || $distance <= 0) {
        setFlash("Bitte WK-Nr, Schwimmart und Distanz korrekt ausfüllen.");
    } elseif (!empty($entry_time) && !preg_match('/^\d{2}:\d{2},\d{2}$/', $entry_time)) {
        setFlash("Meldezeit ungültig (Format: mm:ss,ms).");
    } elseif (!empty($end_time) && !preg_match('/^\d{2}:\d{2},\d{2}$/', $end_time)) {
        setFlash("Endzeit ungültig (Format: mm:ss,ms).");
    } else {
        // Update in competition_starts (Endzeit wird in der Spalte swim_time gespeichert)
        $st = $conn->prepare("UPDATE competition_starts
                              SET wk_nr = ?, swim_style_id = ?, distance = ?, entry_time = ?, swim_time = ?, lauf = ?, bahn = ?
                              WHERE id = ? AND user_id = ?");
        $st->bind_param("iiiisssii", $wk_nr, $swim_style, $distance, $entry_time, $end_time, $lauf, $bahn, $start_id, $user_id);
        $st->execute();
        $st->close();
        
        // Falls Endzeit eingegeben wurde, auch in die Tabelle times übernehmen
        if (!empty($end_time)) {
            $st2 = $conn->prepare("
              INSERT INTO times (user_id, swim_style_id, distance, time, date, WKtime)
              SELECT cs.user_id, cs.swim_style_id, cs.distance, ?,
                     c.competition_date, 1
              FROM competition_starts cs
              JOIN competitions c ON c.id = cs.competition_id
              WHERE cs.id = ? AND cs.user_id = ?
            ");
            $st2->bind_param("sii", $end_time, $start_id, $user_id);
            $st2->execute();
            $st2->close();
            
            if (!empty($entry_time)) {
                $imp = improvementMessage($entry_time, $end_time);
                setFlash($imp ?: "Start aktualisiert.");
            } else {
                setFlash("Start aktualisiert.");
            }
        } else {
            setFlash("Start aktualisiert (ohne Endzeit).");
        }
    }
    header("Location: wettkampf.php?id=" . $comp_id);
    exit();
}

// D) Start löschen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_start'])) {
    $start_id = intval($_POST['start_id'] ?? 0);
    $comp_id  = intval($_POST['comp_id'] ?? 0);
    $del = $conn->prepare("DELETE FROM competition_starts WHERE id = ? AND competition_id = ?");
    $del->bind_param("ii", $start_id, $comp_id);
    $del->execute();
    $del->close();
    setFlash("Start gelöscht.");
    header("Location: wettkampf.php?id=" . $comp_id);
    exit();
}

// ---------------------
// GET-Anfragen – Detailansicht oder Übersicht
// ---------------------
if (isset($_GET['id'])) {
    // Detailansicht eines Wettkampfs
    $comp_id = intval($_GET['id']);
    // Wettkampfdaten abrufen
    $st = $conn->prepare("SELECT name, competition_date, user_id FROM competitions WHERE id = ? AND user_id = ?");
    $st->bind_param("ii", $comp_id, $user_id);
    $st->execute();
    $st->bind_result($comp_name, $comp_date, $owner_uid);
    if (!$st->fetch()) {
        $st->close();
        die("Wettkampf nicht gefunden oder keine Berechtigung.");
    }
    $st->close();
    
    // Alle Starts abrufen
    $starts = [];
    $q = $conn->prepare("SELECT id, wk_nr, swim_style_id, distance, entry_time, swim_time, lauf, bahn 
                         FROM competition_starts 
                         WHERE competition_id = ? 
                         ORDER BY wk_nr ASC");
    $q->bind_param("i", $comp_id);
    $q->execute();
    $res = $q->get_result();
    while ($row = $res->fetch_assoc()) {
        $starts[] = $row;
    }
    $q->close();
    
    // Definition der Schwimmarten (für Anzeige und JS)
    $swim_styles = [
      "1" => "Delphin",
      "2" => "Rücken",
      "3" => "Brust",
      "4" => "Kraul",
      "5" => "Lagen"
    ];
    
    // Distanz-Map: je nach Schwimmart
    $distanceMap = [
      "1" => [50, 100, 200, 400, 600, 800, 1000, 1500, 1700, 1800],
      "2" => [50, 100, 200, 400, 600, 800, 1000, 1500, 1700, 1800],
      "3" => [50, 100, 200, 400, 600, 800, 1000, 1500, 1700, 1800],
      "4" => [50, 100, 200, 400, 600, 800, 1000, 1500, 1700, 1800],
      "5" => [100, 200, 400, 600, 800],
    ];
    
    // Prüfen, ob alle Starts bereits eine Endzeit haben
    $all_done = (count($starts) > 0);
    foreach ($starts as $s) {
        if (empty($s['swim_time'])) {
            $all_done = false;
            break;
        }
    }
    
    // Bearbeitungsmodus (über ?mode=edit)
    $editMode = (isset($_GET['mode']) && $_GET['mode'] === 'edit');
    $flash = getFlash();
    ?>
    <!DOCTYPE html>
    <html lang="de">
    <head>
      <meta charset="UTF-8">
      <title><?= htmlspecialchars($comp_name) ?> – Wettkampf</title>
      <meta name="viewport" content="width=device-width, initial-scale=1">
      <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
      <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.10.5/font/bootstrap-icons.min.css">
      <style>
        body { background: #f8f9fa; padding-top: 70px; }
        .header-hero {
          background: linear-gradient(135deg, #003366, #005599);
          color: #fff;
          padding: 2rem;
          margin-bottom: 2rem;
          border-radius: 10px;
          text-align: center;
          position: relative;
          box-shadow: 0 2px 12px rgba(0,0,0,0.3);
        }
        .mode-switch { position: absolute; bottom: 10px; right: 10px; }
        .table th, .table td { vertical-align: middle; text-align: center; }
        .improvement-pos { background: #d4edda; }
        .improvement-neg { background: #f8d7da; }
        /* Input-Felder in Modal & Inline */
        .form-control-sm { max-width: 120px; display: inline-block; }
      </style>
    </head>
    <body>
      <?php include 'menu.php'; ?>
      <div class="container mt-4">
        <!-- Hero-Bereich -->
        <div class="header-hero">
          <?php if ($editMode): ?>
            <form method="post" action="wettkampf.php?id=<?= $comp_id ?>">
              <input type="hidden" name="update_competition" value="1">
              <input type="hidden" name="comp_id" value="<?= $comp_id ?>">
              <div class="mb-2">
                <input type="text" name="comp_name" class="form-control" value="<?= htmlspecialchars($comp_name) ?>" required>
              </div>
              <div class="mb-2">
                <input type="date" name="comp_date" class="form-control" value="<?= htmlspecialchars($comp_date) ?>" required>
              </div>
              <button type="submit" class="btn btn-light btn-sm">Speichern</button>
            </form>
          <?php else: ?>
            <h1><?= htmlspecialchars($comp_name) ?></h1>
            <p><?= date('d.m.Y', strtotime($comp_date)) ?></p>
          <?php endif; ?>
          <div class="mode-switch">
            <?php if ($editMode): ?>
              <a href="wettkampf.php?id=<?= $comp_id ?>" class="btn btn-outline-light btn-sm">
                <i class="bi bi-eye"></i> Ansicht
              </a>
            <?php else: ?>
              <a href="wettkampf.php?id=<?= $comp_id ?>&mode=edit" class="btn btn-outline-light btn-sm">
                <i class="bi bi-pencil-square"></i> Bearbeiten
              </a>
            <?php endif; ?>
          </div>
        </div>
        
        <?php if ($flash): ?>
          <div class="alert alert-info"><?= htmlspecialchars($flash) ?></div>
        <?php endif; ?>
        
        <h3>Starts</h3>
        <?php if ($editMode): ?>
          <div class="d-flex justify-content-end mb-3">
            <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#modalAddStart">
              <i class="bi bi-plus-circle"></i> Start hinzufügen
            </button>
          </div>
        <?php endif; ?>
        
        <?php if (!count($starts)): ?>
          <p class="text-center">Keine Starts vorhanden.</p>
        <?php else: ?>
          <table class="table table-bordered">
            <thead class="table-light">
              <tr>
                <th>WK-Nr</th>
                <th>Schwimmart</th>
                <th>Distanz</th>
                <th>Meldezeit</th>
                <th>Endzeit</th>
                <th>Lauf</th>
                <th>Bahn</th>
                <th>Verbesserung</th>
                <?php if ($editMode): ?><th>Aktion</th><?php endif; ?>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($starts as $s):
                $rowClass = "";
                $improvement = "";
                if (!empty($s['swim_time'])) {
                    $diff = convertTimeToSeconds($s['entry_time']) - convertTimeToSeconds($s['swim_time']);
                    if ($diff > 0) $rowClass = 'improvement-pos';
                    elseif ($diff < 0) $rowClass = 'improvement-neg';
                    $improvement = improvementMessage($s['entry_time'], $s['swim_time']);
                }
              ?>
              <tr class="<?= $rowClass ?>">
                <td><?= $s['wk_nr'] ?></td>
                <td><?= isset($swim_styles[$s['swim_style_id']]) ? htmlspecialchars($swim_styles[$s['swim_style_id']]) : 'Unbekannt' ?></td>
                <td><?= $s['distance'] ?></td>
                <td><?= htmlspecialchars($s['entry_time']) ?></td>
                <td>
                  <!-- Inline-Formular für Endzeit -->
                  <form method="post" action="wettkampf.php?id=<?= $comp_id ?>">
                    <input type="hidden" name="update_start" value="1">
                    <input type="hidden" name="start_id" value="<?= $s['id'] ?>">
                    <input type="hidden" name="comp_id" value="<?= $comp_id ?>">
                    <input type="hidden" name="wk_nr" value="<?= $s['wk_nr'] ?>">
                    <input type="hidden" name="swim_style" value="<?= $s['swim_style_id'] ?>">
                    <input type="hidden" name="distance" value="<?= $s['distance'] ?>">
                    <input type="hidden" name="lauf" value="<?= htmlspecialchars($s['lauf']) ?>">
                    <input type="hidden" name="bahn" value="<?= htmlspecialchars($s['bahn']) ?>">
                    <input type="text" name="end_time" value="<?= htmlspecialchars($s['swim_time']) ?>" 
                           class="form-control form-control-sm" placeholder="Endzeit (mm:ss,ms)">
                    <button type="submit" class="btn btn-sm btn-primary mt-1">Speichern</button>
                  </form>
                </td>
                <td><?= htmlspecialchars($s['lauf'] ?? '') ?></td>
                <td><?= htmlspecialchars($s['bahn'] ?? '') ?></td>
                <td><?= htmlspecialchars($improvement) ?></td>
                <?php if ($editMode): ?>
                <td>
                  <!-- Zusätzlich: Edit-Button (optional, öffnet ein Modal) -->
                  <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#modalEditStart"
                          data-start-id="<?= $s['id'] ?>"
                          data-wk-nr="<?= $s['wk_nr'] ?>"
                          data-swim-style="<?= $s['swim_style_id'] ?>"
                          data-distance="<?= $s['distance'] ?>"
                          data-entry-time="<?= $s['entry_time'] ?>"
                          data-end-time="<?= $s['swim_time'] ?>"
                          data-lauf="<?= $s['lauf'] ?>"
                          data-bahn="<?= $s['bahn'] ?>">
                    <i class="bi bi-pencil-square"></i>
                  </button>
                  <form method="post" action="wettkampf.php?id=<?= $comp_id ?>" style="display:inline-block;" onsubmit="return confirm('Diesen Start wirklich löschen?');">
                    <input type="hidden" name="delete_start" value="1">
                    <input type="hidden" name="start_id" value="<?= $s['id'] ?>">
                    <input type="hidden" name="comp_id" value="<?= $comp_id ?>">
                    <button type="submit" class="btn btn-sm btn-danger">
                      <i class="bi bi-trash"></i>
                    </button>
                  </form>
                </td>
                <?php endif; ?>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
        
        <?php if ($all_done && count($starts) > 0): ?>
          <div class="alert alert-success text-center">
            Alle Starts haben eine Endzeit!<br>
            <a href="wettkampf_auswertung.php?competition_id=<?= $comp_id ?>" class="btn btn-success mt-2">
              PDF-Auswertung herunterladen
            </a>
            <br>
            Link zum Teilen:
            <input type="text" class="form-control d-inline-block w-auto"
                   value="https://www.sla-schwimmen.de/sla-projekt/module/wettkampf_auswertung.php?competition_id=<?= $comp_id ?>"
                   readonly>
          </div>
        <?php endif; ?>
      </div>
      
      <!-- Modal: ADD Start -->
      <div class="modal fade" id="modalAddStart" tabindex="-1">
        <div class="modal-dialog">
          <form method="post" action="wettkampf.php?id=<?= $comp_id ?>">
            <input type="hidden" name="add_start" value="1">
            <input type="hidden" name="comp_id" value="<?= $comp_id ?>">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title">Neuen Start hinzufügen</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
              </div>
              <div class="modal-body">
                <div class="mb-3">
                  <label class="form-label">WK-Nr</label>
                  <input type="number" name="wk_nr" class="form-control" required>
                </div>
                <div class="mb-3">
                  <label class="form-label">Schwimmart</label>
                  <select name="swim_style" id="add_swim_style" class="form-select" required>
                    <option value="">Bitte wählen...</option>
                    <?php foreach ($swim_styles as $sid => $sname): ?>
                      <option value="<?= $sid ?>"><?= htmlspecialchars($sname) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="mb-3">
                  <label class="form-label">Distanz (m)</label>
                  <select name="distance" id="add_distance" class="form-select" required></select>
                </div>
                <div class="mb-3">
                  <label class="form-label">Meldezeit (mm:ss,ms)</label>
                  <div class="input-group">
                    <input type="text" name="entry_time" id="add_entry_time" class="form-control" placeholder="z.B. 02:23,45" required>
                    <button type="button" class="btn btn-outline-secondary"
                            onclick="openBesttimeDropdown(this, 'add_entry', '-1', '-1')">
                      <i class="bi bi-caret-down-fill"></i>
                    </button>
                  </div>
                </div>
                <div class="mb-3">
                  <label class="form-label">Lauf</label>
                  <input type="text" name="lauf" class="form-control">
                </div>
                <div class="mb-3">
                  <label class="form-label">Bahn</label>
                  <input type="text" name="bahn" class="form-control">
                </div>
              </div>
              <div class="modal-footer">
                <button type="submit" class="btn btn-success">Hinzufügen</button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
              </div>
            </div>
          </form>
        </div>
      </div>
      
      <!-- Modal: EDIT Start -->
      <div class="modal fade" id="modalEditStart" tabindex="-1">
        <div class="modal-dialog">
          <form method="post" action="wettkampf.php">
            <input type="hidden" name="update_start" value="1">
            <input type="hidden" name="start_id" id="edit_start_id">
            <input type="hidden" name="comp_id" value="<?= $comp_id ?>">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title">Start bearbeiten</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
              </div>
              <div class="modal-body">
                <div class="mb-3">
                  <label class="form-label">WK-Nr</label>
                  <input type="number" name="wk_nr" id="edit_wk_nr" class="form-control" required>
                </div>
                <div class="mb-3">
                  <label class="form-label">Schwimmart</label>
                  <select name="swim_style" id="edit_swim_style" class="form-select" required>
                    <option value="">Bitte wählen...</option>
                    <?php foreach ($swim_styles as $sid => $sname): ?>
                      <option value="<?= $sid ?>"><?= htmlspecialchars($sname) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="mb-3">
                  <label class="form-label">Distanz (m)</label>
                  <select name="distance" id="edit_distance" class="form-select" required></select>
                </div>
                <div class="mb-3">
                  <label class="form-label">Meldezeit (mm:ss,ms)</label>
                  <div class="input-group">
                    <input type="text" name="entry_time" id="edit_entry_time" class="form-control" required>
                    <button type="button" class="btn btn-outline-secondary"
                            onclick="openBesttimeDropdown(this, 'edit_entry', '-1', '-1')">
                      <i class="bi bi-caret-down-fill"></i>
                    </button>
                  </div>
                </div>
                <div class="mb-3">
                  <label class="form-label">Endzeit (mm:ss,ms)</label>
                  <div class="input-group">
                    <input type="text" name="end_time" id="edit_end_time" class="form-control">
                    <button type="button" class="btn btn-outline-secondary"
                            onclick="openBesttimeDropdown(this, 'edit_end', '-1', '-1')">
                      <i class="bi bi-caret-down-fill"></i>
                    </button>
                  </div>
                </div>
                <div class="mb-3">
                  <label class="form-label">Lauf</label>
                  <input type="text" name="lauf" id="edit_lauf" class="form-control">
                </div>
                <div class="mb-3">
                  <label class="form-label">Bahn</label>
                  <input type="text" name="bahn" id="edit_bahn" class="form-control">
                </div>
              </div>
              <div class="modal-footer">
                <button type="submit" class="btn btn-primary">Speichern</button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
              </div>
            </div>
          </form>
        </div>
      </div>
      
      <?php include 'footer.php'; ?>
      
      <!-- Bootstrap JS & Flatpickr -->
      <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
      <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
      <script>
        // Initialisiere Flatpickr (falls benötigt)
        flatpickr(".datetimepicker", {
          enableTime: true,
          dateFormat: "Y-m-d\\TH:i"
        });
        
        // Distanz-Map aus PHP
        const distanceMap = <?php echo json_encode($distanceMap); ?>;
        
        // Für das ADD-Modal: Schwimmart => Distanz
        document.getElementById('add_swim_style')?.addEventListener('change', function() {
          const styleId = this.value;
          const distSel = document.getElementById('add_distance');
          distSel.innerHTML = '';
          if (distanceMap[styleId]) {
            distanceMap[styleId].forEach(d => {
              const opt = document.createElement('option');
              opt.value = d;
              opt.textContent = d;
              distSel.appendChild(opt);
            });
          }
        });
        
        // EDIT-Modal: Dynamisch die Distanzliste füllen
        function fillEditDistances(styleId, selectedDist) {
          const distSel = document.getElementById('edit_distance');
          distSel.innerHTML = '';
          if (distanceMap[styleId]) {
            distanceMap[styleId].forEach(d => {
              const opt = document.createElement('option');
              opt.value = d;
              opt.textContent = d;
              if (d == selectedDist) opt.selected = true;
              distSel.appendChild(opt);
            });
          }
        }
        document.getElementById('edit_swim_style')?.addEventListener('change', function() {
          fillEditDistances(this.value, null);
        });
        
        // Bestzeit-Dropdown-Funktion
        let currentDropdown = null;
        function openBesttimeDropdown(btn, mode, styleId, distVal) {
          closeDropdown();
          
          let actualStyleId = styleId, actualDistVal = distVal;
          if (styleId === '-1') {
            if (mode === 'add_entry') {
              actualStyleId = document.getElementById('add_swim_style').value;
              actualDistVal = document.getElementById('add_distance').value;
            } else if (mode === 'edit_entry' || mode === 'edit_end') {
              actualStyleId = document.getElementById('edit_swim_style').value;
              actualDistVal = document.getElementById('edit_distance').value;
            }
          }
          if (!actualStyleId || !actualDistVal) {
            alert("Bitte zuerst Schwimmart und Distanz wählen.");
            return;
          }
          
          const dd = document.createElement('div');
          dd.className = 'besttime-dropdown';
          dd.id = 'besttimeDD';
          dd.innerHTML = `
            <div onclick="fetchTime('${mode}', 'training', '${actualStyleId}', '${actualDistVal}')">(???) Schnellste Trainingszeit</div>
            <div onclick="fetchTime('${mode}', 'wk', '${actualStyleId}', '${actualDistVal}')">(???) Schnellste Wettkampfzeit</div>
            <div style="text-align:right;color:#007bff;" onclick="closeDropdown()">Schließen</div>
          `;
          document.body.appendChild(dd);
          currentDropdown = dd;
          
          const rect = btn.getBoundingClientRect();
          dd.style.top = (rect.bottom + window.scrollY) + 'px';
          dd.style.left = (rect.left + window.scrollX) + 'px';
          
          // Parallele Abfragen
          Promise.all([
            fetch(`fetch_fastest_time.php?source=training&swim_style_id=${actualStyleId}&distance=${actualDistVal}&user_id=<?= $user_id ?>`).then(r => r.json()),
            fetch(`fetch_fastest_time.php?source=wk&swim_style_id=${actualStyleId}&distance=${actualDistVal}&user_id=<?= $user_id ?>`).then(r => r.json())
          ]).then(results => {
            let train = results[0].time || "Keine Trainingszeit";
            let wkt = results[1].time || "Keine Wettkampfzeit";
            const divs = dd.querySelectorAll('div');
            divs[0].innerHTML = `(${train}) Schnellste Trainingszeit`;
            divs[1].innerHTML = `(${wkt}) Schnellste Wettkampfzeit`;
          }).catch(err => {
            console.error(err);
          });
        }
        function closeDropdown() {
          if (currentDropdown) {
            currentDropdown.remove();
            currentDropdown = null;
          }
        }
        window.addEventListener('click', function(e) {
          if (currentDropdown && !currentDropdown.contains(e.target)) {
            if (!e.target.closest('.btn-outline-secondary') && !e.target.closest('.besttime-dropdown')) {
              closeDropdown();
            }
          }
        });
        function fetchTime(mode, source, styleId, distVal) {
          closeDropdown();
          fetch(`fetch_fastest_time.php?source=${source}&swim_style_id=${styleId}&distance=${distVal}&user_id=<?= $user_id ?>`)
            .then(r => r.json())
            .then(d => {
              if (!d.time) {
                alert("Keine Zeit gefunden.");
                return;
              }
              const val = d.time;
              if (mode === 'add_entry') {
                document.getElementById('add_entry_time').value = val;
              } else if (mode === 'edit_entry') {
                document.getElementById('edit_entry_time').value = val;
              } else if (mode === 'edit_end') {
                document.getElementById('edit_end_time').value = val;
              }
            })
            .catch(err => {
              console.error(err);
              alert("Fehler beim Laden der Zeit.");
            });
        }
      </script>
    </body>
    </html>
    <?php
} else {
    // Übersichtsansicht: Zukunft & Vergangenheit
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $today = date('Y-m-d');
    
    // Zukünftige Wettkämpfe
    $condF = "WHERE user_id = $user_id AND competition_date >= '$today'";
    if ($search !== '') {
        $condF .= " AND name LIKE '%" . $conn->real_escape_string($search) . "%'";
    }
    $future = [];
    $fQ = $conn->query("SELECT id, name, competition_date FROM competitions $condF ORDER BY competition_date ASC");
    if ($fQ) {
        while ($r = $fQ->fetch_assoc()) {
            $future[] = $r;
        }
        $fQ->free();
    }
    
    // Vergangene Wettkämpfe + Pagination
    $condP = "WHERE user_id = $user_id AND competition_date < '$today'";
    if ($search !== '') {
        $condP .= " AND name LIKE '%" . $conn->real_escape_string($search) . "%'";
    }
    $countQ = $conn->query("SELECT COUNT(*) as total FROM competitions $condP");
    $totalPast = 0;
    if ($countQ) {
        $rw = $countQ->fetch_assoc();
        $totalPast = $rw['total'];
        $countQ->free();
    }
    $perPage = 6;
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $offset = ($page - 1) * $perPage;
    $past = [];
    $pQ = $conn->query("SELECT id, name, competition_date FROM competitions $condP ORDER BY competition_date DESC LIMIT $perPage OFFSET $offset");
    if ($pQ) {
        while ($x = $pQ->fetch_assoc()) {
            $past[] = $x;
        }
        $pQ->free();
    }
    $totalPages = ceil($totalPast / $perPage);
    ?>
    <!DOCTYPE html>
    <html lang="de">
    <head>
      <meta charset="UTF-8">
      <title>Wettkämpfe – SLA-Schwimmen</title>
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
      <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.10.5/font/bootstrap-icons.min.css">
      <style>
        body { background: #f8f9fa; padding-top: 70px; }
        .hero-title {
          background: linear-gradient(135deg, #003366, #005599);
          color: #fff;
          padding: 2rem;
          margin-bottom: 2rem;
          border-radius: 10px;
          text-align: center;
          box-shadow: 0 2px 12px rgba(0,0,0,0.3);
        }
        .competition-card {
          background: #fff;
          border: none;
          border-radius: 10px;
          box-shadow: 0 2px 8px rgba(0,0,0,0.1);
          padding: 1.5rem;
          text-align: center;
          transition: transform 0.3s, box-shadow 0.3s;
          cursor: pointer;
          margin-bottom: 20px;
        }
        .competition-card:hover {
          transform: translateY(-3px);
          box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }
        .competition-card h4 { margin: 0.5rem 0; }
        .pagination { justify-content: center; }
      </style>
    </head>
    <body>
      <?php include 'menu.php'; ?>
      
      <div class="container mt-4">
        <div class="hero-title">
          <h1>Wettkämpfe</h1>
          <p>Hier findest du deine bevorstehenden und vergangenen Wettkämpfe.<br>
             Über die Suchfunktion kannst du gezielt nach Namen suchen.</p>
        </div>
        
        <!-- Suchfeld -->
        <form method="get" class="row g-2 mb-4">
          <div class="col-md-8">
            <input type="text" name="search" class="form-control" placeholder="Wettkampf suchen..." value="<?= htmlspecialchars($search) ?>">
          </div>
          <div class="col-md-4">
            <button class="btn btn-primary w-100" type="submit">Suchen</button>
          </div>
        </form>
        
        <!-- Tabs -->
        <ul class="nav nav-tabs" role="tablist">
          <li class="nav-item">
            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#futureTab" type="button" role="tab">
              Zukunft
            </button>
          </li>
          <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#pastTab" type="button" role="tab">
              Vergangenheit
            </button>
          </li>
        </ul>
        <div class="tab-content">
          <!-- Zukunft -->
          <div class="tab-pane fade show active" id="futureTab" role="tabpanel">
            <div class="row g-4 mt-3">
              <?php if (!count($future)): ?>
                <p class="text-center">Keine zukünftigen Wettkämpfe gefunden.</p>
              <?php else: ?>
                <?php foreach ($future as $fc): ?>
                  <div class="col-md-4">
                    <div class="competition-card" onclick="window.location.href='wettkampf.php?id=<?= $fc['id'] ?>'">
                      <h4><?= htmlspecialchars($fc['name']) ?></h4>
                      <p><?= date('d.m.Y', strtotime($fc['competition_date'])) ?></p>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </div>
          <!-- Vergangenheit -->
          <div class="tab-pane fade" id="pastTab" role="tabpanel">
            <div class="row g-4 mt-3">
              <?php if (!count($past)): ?>
                <p class="text-center">Keine vergangenen Wettkämpfe gefunden.</p>
              <?php else: ?>
                <?php foreach ($past as $pc): ?>
                  <div class="col-md-4">
                    <div class="competition-card" onclick="window.location.href='wettkampf.php?id=<?= $pc['id'] ?>'">
                      <h4><?= htmlspecialchars($pc['name']) ?></h4>
                      <p><?= date('d.m.Y', strtotime($pc['competition_date'])) ?></p>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
            <?php if ($totalPages > 1): ?>
              <nav>
                <ul class="pagination">
                  <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                    <li class="page-item <?= ($p == $page) ? 'active' : '' ?>">
                      <a class="page-link" href="?search=<?= urlencode($search) ?>&page=<?= $p ?>#pastTab"><?= $p ?></a>
                    </li>
                  <?php endfor; ?>
                </ul>
              </nav>
            <?php endif; ?>
          </div>
        </div>
      </div>
      
      <?php include 'footer.php'; ?>
      <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    </body>
    </html>
    <?php
}
$conn->close();
?>
