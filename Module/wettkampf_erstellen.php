<?php
/********************************************************
 * WETTKAMPF ERSTELLEN – Wettkampf und Starts anlegen
 * 
 * - Erfasst Wettkampfname, Ort, Start- und Enddatum.
 * - Optional: Mehrere Starts können hinzugefügt werden.
 * - Für jeden Start kann per Klick auf den Meldezeit-Button gewählt werden,
 *   ob die Meldezeit automatisch aus Trainingszeiten (times) oder
 *   Wettkampfzeiten (competition_starts) übernommen wird.
 *   Die jeweiligen Zeiten werden im Dropdown neben der Eingabe angezeigt.
 * - Fehler werden in einem Bootstrap-Modal als Popup ausgegeben.
 * - Modernes, sportliches Design.
 ********************************************************/

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['name'] ?? 'Benutzer';

require_once('../dbconnection.php');

$error_message = '';
$success_message = '';

// Schwimmarten abrufen
$swim_styles = [];
$stmt = $conn->prepare("SELECT id, name FROM swim_styles ORDER BY name ASC");
$stmt->execute();
$stmt->bind_result($sid, $sname);
while ($stmt->fetch()) {
    $swim_styles[$sid] = $sname;
}
$stmt->close();

// Optionale Distanzoptionen aus einer Tabelle (falls vorhanden)
$swim_style_distances = [];
$stmt = $conn->prepare("SELECT swim_style_id, distance FROM swim_style_distances ORDER BY distance ASC");
$stmt->execute();
$stmt->bind_result($dist_swim_style_id, $dist_distance);
while ($stmt->fetch()) {
    $swim_style_distances[$dist_swim_style_id][] = $dist_distance;
}
$stmt->close();

// Formularverarbeitung
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_competition'])) {
    $competition_name = trim($_POST['competition_name'] ?? '');
    $place = trim($_POST['place'] ?? '');
    $competition_date = $_POST['competition_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    $starts = $_POST['starts'] ?? [];

    // Basisvalidierung
    if (empty($competition_name) || empty($place) || empty($competition_date) || empty($end_date)) {
        $error_message = 'Bitte füllen Sie alle Felder für Name, Ort, Start- und Enddatum aus.';
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $competition_date) || !strtotime($competition_date)) {
        $error_message = 'Ungültiges Startdatum.';
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date) || !strtotime($end_date)) {
        $error_message = 'Ungültiges Enddatum.';
    } else {
        $start_ts = strtotime($competition_date);
        $end_ts = strtotime($end_date);
        if ($end_ts < $start_ts) {
            $error_message = 'Das Enddatum darf nicht vor dem Startdatum liegen.';
        }
    }

    if (empty($error_message)) {
        // Wettkampf in der Tabelle competitions speichern
        $stmt = $conn->prepare("INSERT INTO competitions (name, place, competition_date, end_date, user_id) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssi", $competition_name, $place, $competition_date, $end_date, $user_id);
        if (!$stmt->execute()) {
            $error_message = 'Fehler beim Speichern des Wettkampfes: ' . $stmt->error;
        }
        $competition_id = $stmt->insert_id;
        $stmt->close();

        // Optionale Starts speichern
        if (!empty($starts)) {
            foreach ($starts as $start) {
                $wk_nr = intval($start['wk_nr'] ?? 0);
                $start_swim_style = intval($start['swim_style'] ?? 0);
                $distance = intval($start['distance'] ?? 0);
                $entry_time = trim($start['entry_time'] ?? '');
                
                // Ungültige Einträge überspringen
                if ($start_swim_style < 1 || $distance < 1) {
                    continue;
                }
                if (!empty($entry_time) && !preg_match('/^\d{1,2}:\d{2},\d{2}$/', $entry_time)) {
                    // Wenn eingegebene Zeit nicht dem Format entspricht, überspringen
                    continue;
                }
                $stmt = $conn->prepare("INSERT INTO competition_starts (competition_id, wk_nr, swim_style_id, distance, entry_time) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("iiiis", $competition_id, $wk_nr, $start_swim_style, $distance, $entry_time);
                $stmt->execute();
                $stmt->close();
            }
        }
        $success_message = 'Wettkampf erfolgreich erstellt!';
    }
    if (!empty($error_message)) {
        header("Location: wettkampf_erstellen.php?error=" . urlencode($error_message));
        exit();
    } else {
        header("Location: wettkampf_erstellen.php?success=" . urlencode($success_message));
        exit();
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Wettkampf erstellen</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Bootstrap 5 CSS & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.10.5/font/bootstrap-icons.min.css">
    <style>
        body {
            background: #f8f9fa;
            padding-top: 70px;
        }
        /* Neuer Hero-Bereich im modernen Style */
        .hero {
            background: linear-gradient(135deg, #003366, #005599);
            color: #fff;
            padding: 2.5rem;
            border-radius: 10px;
            text-align: center;
            margin-bottom: 2rem;
            box-shadow: 0 2px 15px rgba(0,0,0,0.3);
        }
        /* Card-Style für das Formular */
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            padding: 25px;
            background: #fff;
        }
        .card h6 {
            font-weight: bold;
        }
        .form-label {
            font-weight: bold;
        }
        .btn-custom {
            font-weight: bold;
        }
        /* Dropdown für Meldezeitoptionen */
        .melde-dropdown {
            position: absolute;
            background: #fff;
            border: 1px solid #ccc;
            padding: 10px;
            z-index: 1000;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
            width: 220px;
        }
        .melde-dropdown div {
            padding: 5px;
            cursor: pointer;
            border-bottom: 1px solid #ddd;
        }
        .melde-dropdown div:last-child {
            border-bottom: none;
        }
        .melde-dropdown div:hover {
            background: #f0f0f0;
        }
        /* Modal für Fehler-Popups */
        .modal-header, .modal-footer { background: #005599; color: #fff; }
    </style>
</head>
<body>
    <?php include '../menu.php'; ?>

    <!-- Fehler-Modal (wird per JS aufgerufen, falls Fehler auftreten) -->
    <div class="modal fade" id="errorModal" tabindex="-1" aria-labelledby="errorModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="errorModalLabel">Fehler</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Schließen"></button>
          </div>
          <div class="modal-body" id="errorModalBody">
            <!-- Fehlermeldung wird hier eingefügt -->
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Schließen</button>
          </div>
        </div>
      </div>
    </div>

    <div class="container">
        <div class="hero">
            <h1>Wettkampf erstellen</h1>
            <p>Erstelle einen neuen Wettkampf und füge optional Starts hinzu.<br>
            Für jeden Start kannst Du per Klick auf den Pfeil die Meldezeit auswählen.<br>
            Die Optionen zeigen die aktuell schnellste Zeit aus den Trainings- oder Wettkampfzeiten an.</p>
        </div>

        <?php if (isset($_GET['error']) && !empty($_GET['error'])): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($_GET['error']); ?></div>
        <?php endif; ?>
        <?php if (isset($_GET['success']) && !empty($_GET['success'])): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($_GET['success']); ?></div>
        <?php endif; ?>

        <div class="card">
            <form method="post" action="wettkampf_erstellen.php" id="competition_form">
                <div class="mb-3">
                    <label for="competition_name" class="form-label">Name des Wettkampfes</label>
                    <input type="text" name="competition_name" id="competition_name" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label for="place" class="form-label">Ort</label>
                    <input type="text" name="place" id="place" class="form-control" required>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="competition_date" class="form-label">Startdatum</label>
                        <input type="date" name="competition_date" id="competition_date" class="form-control" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="end_date" class="form-label">Enddatum</label>
                        <input type="date" name="end_date" id="end_date" class="form-control" required>
                    </div>
                </div>

                <h5 class="mt-4">Optionale Starts hinzufügen</h5>
                <p class="text-muted">Füge hier Starts hinzu (optional). Du kannst später Änderungen vornehmen.</p>
                <div id="starts_container"></div>
                <button type="button" class="btn btn-secondary mb-3" id="add_start_btn">
                    <i class="bi bi-plus-circle"></i> Start hinzufügen
                </button>

                <button type="submit" name="create_competition" class="btn btn-success btn-custom w-100">
                    <i class="bi bi-check2-circle"></i> Wettkampf erstellen
                </button>
            </form>
        </div>
    </div>

    <?php include '../footer.php'; ?>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Funktion zum Anzeigen von Fehlern in einem Modal
        function showErrorModal(message) {
            document.getElementById('errorModalBody').innerText = message;
            var errorModal = new bootstrap.Modal(document.getElementById('errorModal'));
            errorModal.show();
        }

        document.addEventListener('DOMContentLoaded', function() {
            let startsCount = 0;
            const swimStyles = <?php echo json_encode($swim_styles); ?>;
            const swimStyleDistances = <?php echo json_encode($swim_style_distances); ?>;

            function addStart() {
                startsCount++;
                const container = document.getElementById('starts_container');
                const div = document.createElement('div');
                div.classList.add('mb-3', 'start-entry');
                div.innerHTML = `
                    <div class="card">
                        <div class="card-body">
                            <h6 class="card-title">Start ${startsCount}</h6>
                            <div class="row g-3">
                                <div class="col-md-2">
                                    <label class="form-label">WK-NR</label>
                                    <input type="number" name="starts[${startsCount}][wk_nr]" class="form-control" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Schwimmart</label>
                                    <select name="starts[${startsCount}][swim_style]" class="form-select swim-style-select" data-start-index="${startsCount}" required>
                                        <option value="">Bitte wählen...</option>
                                        <?php foreach ($swim_styles as $id => $name): ?>
                                            <option value="<?php echo $id; ?>"><?php echo htmlspecialchars($name); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Distanz (m)</label>
                                    <select name="starts[${startsCount}][distance]" class="form-select distance-select" data-start-index="${startsCount}" required>
                                        <option value="">Bitte wählen...</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Meldezeit</label>
                                    <div class="input-group">
                                      <input type="text" name="starts[${startsCount}][entry_time]" class="form-control" placeholder="MM:SS,MS">
                                      <button type="button" class="btn btn-outline-secondary" onclick="openMeldezeitOptions(${startsCount})">
                                        <i class="bi bi-arrow-down-circle"></i>
                                      </button>
                                    </div>
                                </div>
                                <div class="col-md-2 d-flex align-items-end">
                                    <button type="button" class="btn btn-danger remove-start-btn"><i class="bi bi-trash"></i></button>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                container.appendChild(div);
            }

            document.getElementById('add_start_btn').addEventListener('click', addStart);

            document.getElementById('starts_container').addEventListener('click', function(e) {
                if (e.target.classList.contains('remove-start-btn') || e.target.closest('.remove-start-btn')) {
                    e.target.closest('.start-entry').remove();
                }
            });

            document.getElementById('starts_container').addEventListener('change', function(e) {
                const target = e.target;
                if (target.classList.contains('swim-style-select')) {
                    const idx = target.getAttribute('data-start-index');
                    const swimStyleId = target.value;
                    const distanceSelect = document.querySelector(`.distance-select[data-start-index="${idx}"]`);
                    distanceSelect.innerHTML = '<option value="">Bitte wählen...</option>';
                    if (swimStyleId && swimStyleDistances[swimStyleId]) {
                        swimStyleDistances[swimStyleId].forEach(function(distance) {
                            const option = document.createElement('option');
                            option.value = distance;
                            option.textContent = distance + ' m';
                            distanceSelect.appendChild(option);
                        });
                    }
                }
            });

            // Öffnet ein Dropdown zur Auswahl der Meldezeit – ruft beide Quellen parallel ab
            window.openMeldezeitOptions = function(index) {
                const swimStyleSelect = document.querySelector(`.swim-style-select[data-start-index="${index}"]`);
                const distanceSelect = document.querySelector(`.distance-select[data-start-index="${index}"]`);
                if (!swimStyleSelect || swimStyleSelect.value === "" || !distanceSelect || distanceSelect.value === "") {
                    showErrorModal("Bitte wählen Sie zuerst eine Schwimmart und eine Distanz.");
                    return;
                }
                closeMeldezeitOptions(index);
                
                const swim_style_id = swimStyleSelect.value;
                const distance = distanceSelect.value;
                // Parallel beide Quellen abfragen
                Promise.all([
                    fetch(`fetch_fastest_time.php?source=training&swim_style_id=${swim_style_id}&distance=${distance}&user_id=<?php echo $user_id; ?>`)
                        .then(res => res.json()),
                    fetch(`fetch_fastest_time.php?source=wk&swim_style_id=${swim_style_id}&distance=${distance}`)
                        .then(res => res.json())
                ]).then(results => {
                    const training = results[0].time ? results[0].time : "Keine Trainingszeit";
                    const wk = results[1].time ? results[1].time : "Keine Wettkampfzeit";
                    // Erstelle Dropdown-Inhalt mit den Zeiten in Klammern
                    const dropdown = document.createElement('div');
                    dropdown.id = 'meldezeit_options_' + index;
                    dropdown.className = 'melde-dropdown';
                    dropdown.innerHTML = `
                        <div class="dropdown-option" onclick="selectMeldezeit(${index}, 'training')">
                            Schnellste Trainingszeit (${training})
                        </div>
                        <div class="dropdown-option" onclick="selectMeldezeit(${index}, 'wk')">
                            Schnellste Wettkampfzeit (${wk})
                        </div>
                        <div class="dropdown-option" onclick="closeMeldezeitOptions(${index})" style="text-align:right; color:#007bff;">
                            Schließen
                        </div>
                    `;
                    document.body.appendChild(dropdown);
                    // Positioniere das Dropdown relativ zum Button
                    const btn = document.querySelector(`input[name="starts[${index}][entry_time]"]`).nextElementSibling;
                    if (btn) {
                        const rect = btn.getBoundingClientRect();
                        dropdown.style.top = (rect.bottom + window.scrollY + 5) + "px";
                        dropdown.style.left = (rect.left + window.scrollX) + "px";
                    } else {
                        dropdown.style.top = "200px";
                        dropdown.style.left = "50%";
                    }
                }).catch(err => {
                    console.error(err);
                    showErrorModal("Fehler beim Abrufen der Zeiten.");
                    closeMeldezeitOptions(index);
                });
            };

            window.closeMeldezeitOptions = function(index) {
                const dropdown = document.getElementById('meldezeit_options_' + index);
                if (dropdown) {
                    dropdown.remove();
                }
            };

            // Setzt das ausgewählte Zeitformat in das Eingabefeld
            window.selectMeldezeit = function(index, source) {
                const swimStyleSelect = document.querySelector(`.swim-style-select[data-start-index="${index}"]`);
                const distanceSelect = document.querySelector(`.distance-select[data-start-index="${index}"]`);
                const swim_style_id = swimStyleSelect.value;
                const distance = distanceSelect.value;
                fetch(`fetch_fastest_time.php?source=${source}&swim_style_id=${swim_style_id}&distance=${distance}&user_id=<?php echo $user_id; ?>`)
                  .then(response => response.json())
                  .then(data => {
                      if (data.time) {
                          const entryInput = document.querySelector(`input[name="starts[${index}][entry_time]"]`);
                          entryInput.value = data.time;
                      } else {
                          showErrorModal("Keine Zeit gefunden.");
                      }
                      closeMeldezeitOptions(index);
                  })
                  .catch(err => {
                      console.error(err);
                      showErrorModal("Fehler beim Abrufen der Zeit.");
                      closeMeldezeitOptions(index);
                  });
            };
        });
    </script>
</body>
</html>
