<?php
// sprints_tests.php

// Fehleranzeige (Entwicklung)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ZUERST Session starten, bevor irgendetwas ausgegeben wird
session_start();

// Überprüfen, ob der Benutzer angemeldet ist
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['name'] ?? 'Benutzer';

// Datenbankverbindung
require_once('/kunden/homepages/4/d1007545866/htdocs/SLA-Projekt/dbconnection.php');

// Flash-Message-Funktionen
function setFlashMessage($key, $message) {
    $_SESSION['flash_messages'][$key] = $message;
}
function getFlashMessage($key) {
    if (isset($_SESSION['flash_messages'][$key])) {
        $msg = $_SESSION['flash_messages'][$key];
        unset($_SESSION['flash_messages'][$key]);
        return $msg;
    }
    return null;
}

// Hilfsfunktionen zur Zeitkonvertierung
function convertTimeToSeconds($time) {
    $time = trim($time);
    if (preg_match('/^(\d{1,2}):(\d{2}),(\d{2})$/', $time, $m)) {
        $minutes = (int)$m[1];
        $seconds = (int)$m[2];
        $milliseconds = (int)$m[3];
        return ($minutes * 60) + $seconds + ($milliseconds / 100);
    }
    return null;
}
function formatSeconds($seconds) {
    $minutes = floor($seconds / 60);
    $rest = $seconds - ($minutes * 60);
    $sec = floor($rest);
    $ms = round(($rest - $sec)*100);
    return sprintf("%02d:%02d,%02d", $minutes, $sec, $ms);
}
function formatImprovement($diff) {
    if ($diff === null) return '-';
    $prefix = $diff < 0 ? '-' : '+';
    $abs = abs($diff);
    $minutes = floor($abs / 60);
    $rest = $abs - $minutes*60;
    $sec = floor($rest);
    $ms = round(($rest - $sec)*100);
    return $prefix . sprintf("%02d:%02d,%02d", $minutes, $sec, $ms);
}

// PDF-Erzeugung (Dompdf)
require_once('/kunden/homepages/4/d1007545866/htdocs/SLA-Projekt/vendor/autoload.php');
use Dompdf\Dompdf;
use Dompdf\Options;

// Aktionen vor HTML-Ausgabe verarbeiten
$action = $_GET['action'] ?? '';
$session_id = $_GET['session_id'] ?? null;
$session = null;
$times = [];
$sessions = [];

// Sitzung erstellen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_session'])) {
    $session_date = $_POST['session_date'];
    $description = $_POST['description'];
    $repeats = intval($_POST['repeats']);
    $swim_style_id = intval($_POST['swim_style_id']);
    $distance = intval($_POST['distance']);

    $stmt = $conn->prepare("INSERT INTO training_sessions (user_id, session_date, description) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $user_id, $session_date, $description);
    $stmt->execute();
    $session_id_new = $stmt->insert_id;
    $stmt->close();

    for ($i = 1; $i <= $repeats; $i++) {
        $stmt = $conn->prepare("INSERT INTO training_times (session_id, sequence, swim_style_id, distance, time) VALUES (?, ?, ?, ?, NULL)");
        $stmt->bind_param("iiii", $session_id_new, $i, $swim_style_id, $distance);
        $stmt->execute();
        $stmt->close();
    }

    header("Location: sprints_tests.php?session_id=$session_id_new");
    exit();
}

// Zeiten speichern
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_times'])) {
    $session_id = intval($_POST['session_id']);
    $times_input = $_POST['time'];

    foreach ($times_input as $id => $tval) {
        $stmt = $conn->prepare("UPDATE training_times SET time=? WHERE id=?");
        $stmt->bind_param("si", $tval, $id);
        $stmt->execute();
        $stmt->close();
    }

    header("Location: sprints_tests.php?session_id=$session_id&action=analysis");
    exit();
}

// PDF erstellen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_pdf']) && $session_id && $action==='analysis') {
    $session_id = intval($session_id);

    // Session laden
    $stmt = $conn->prepare("SELECT * FROM training_sessions WHERE id=? AND user_id=?");
    $stmt->bind_param("ii", $session_id, $user_id);
    $stmt->execute();
    $session = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$session) {
        setFlashMessage('error', 'Ungültige Sitzung für PDF.');
        header("Location: sprints_tests.php");
        exit();
    }

    // Zeiten abrufen
    $stmt = $conn->prepare("SELECT tt.*, ss.name AS swim_style_name FROM training_times tt INNER JOIN swim_styles ss ON tt.swim_style_id=ss.id WHERE tt.session_id=? ORDER BY tt.sequence ASC");
    $stmt->bind_param("i", $session_id);
    $stmt->execute();
    $times = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Daten aufbereiten
    $times_for_chart = [];
    foreach ($times as $time) {
        $current_seconds = convertTimeToSeconds($time['time']);
        $times_for_chart[] = ['sequence' => $time['sequence'], 'time' => $current_seconds];
    }

    // Bester Wettkampf-/ Letzter Testvergleich berechnen wir später im HTML
    // Jetzt Diagramm-URL erstellen
    $labels = array_column($times_for_chart, 'sequence');
    $data_values = array_column($times_for_chart, 'time');

    $chartConfig = [
        'type'=>'line',
        'data'=>[
            'labels'=>$labels,
            'datasets'=>[[
                'label'=>'Zeit (Sekunden)',
                'data'=>$data_values,
                'borderColor'=>'blue',
                'fill'=>false,
                'tension'=>0.1
            ]]
        ],
        'options'=>[
            'scales'=>[
                'x'=>['title'=>['display'=>true,'text'=>'Anzahl']],
                'y'=>['title'=>['display'=>true,'text'=>'Zeit (s)']]
            ]
        ]
    ];
    $chartUrl = 'https://quickchart.io/chart?c='.urlencode(json_encode($chartConfig));

    // HTML für PDF generieren
    // Verbesserungen berechnen
    $rows_html = '';
    foreach ($times as $time) {
        // Beste Wettkampfzeit
        $stmt = $conn->prepare("SELECT MIN(time) FROM times WHERE user_id=? AND swim_style_id=? AND distance=?");
        $stmt->bind_param("iii", $user_id, $time['swim_style_id'], $time['distance']);
        $stmt->execute();
        $stmt->bind_result($best_competition_time);
        $stmt->fetch();
        $stmt->close();

        $improvement_comp = '-';
        if ($best_competition_time) {
            $best_comp_sec = convertTimeToSeconds($best_competition_time);
            $current_sec = convertTimeToSeconds($time['time']);
            if ($current_sec !== null && $best_comp_sec !== null) {
                $diff = $current_sec - $best_comp_sec;
                $improvement_comp = formatImprovement($diff);
            }
        }

        // Letzter Test
        $stmt = $conn->prepare("SELECT tt.time FROM training_times tt 
                                INNER JOIN training_sessions ts ON tt.session_id=ts.id
                                WHERE ts.user_id=? AND tt.swim_style_id=? AND tt.distance=? AND ts.session_date < ?
                                ORDER BY ts.session_date DESC LIMIT 1");
        $stmt->bind_param("iiis", $user_id, $time['swim_style_id'], $time['distance'], $session['session_date']);
        $stmt->execute();
        $stmt->bind_result($last_test_time);
        $stmt->fetch();
        $stmt->close();

        $improvement_test = '-';
        if ($last_test_time) {
            $last_test_sec = convertTimeToSeconds($last_test_time);
            $current_sec = convertTimeToSeconds($time['time']);
            if ($current_sec!==null && $last_test_sec!==null) {
                $diff = $current_sec - $last_test_sec;
                $improvement_test = formatImprovement($diff);
            }
        }

        $rows_html .= '<tr>
            <td>'.$time['sequence'].'</td>
            <td>'.htmlspecialchars($time['swim_style_name']).'</td>
            <td>'.$time['distance'].' m</td>
            <td>'.htmlspecialchars($time['time']).'</td>
            <td>'.$improvement_comp.'</td>
            <td>'.$improvement_test.'</td>
        </tr>';
    }

    $session_date_disp = $session['session_date'] ? date('d.m.Y', strtotime($session['session_date'])) : 'Nicht festgelegt';
    $licensee = htmlspecialchars($user_name);

    $html = '
    <html>
    <head>
    <style>
    body { font-family: DejaVu Sans, sans-serif; font-size:12px; color:#333; }
    h1,h2,h3 { margin:0; padding:0; }
    h1 { font-size:18px; margin-bottom:10px; }
    h2 { font-size:16px; margin-bottom:8px; }
    .header { text-align:center; margin-bottom:20px; }
    table { width:100%; border-collapse: collapse; margin-bottom:20px; }
    th, td { border:1px solid #ccc; padding:5px; text-align:center; }
    th { background:#eee; }
    .footer { position: fixed; bottom:30px; left:0; right:0; text-align:center; font-size:10px; color:#555; }
    .chart { text-align:center; margin-bottom:20px; }
    .summary { margin-bottom:20px; }
    hr {border:none; border-top:1px solid #ccc; margin:20px 0; }
    </style>
    </head>
    <body>
    <div class="header">
    <h1>Auswertung Sprints & Tests</h1>
    <p>'.$session_date_disp.' - '.htmlspecialchars($session['description']).'</p>
    </div>
    <div class="chart">
    <img src="'.$chartUrl.'" alt="Diagramm" style="max-width:100%;">
    </div>
    <table>
    <thead>
    <tr>
    <th>Anzahl</th>
    <th>Schwimmart</th>
    <th>Distanz</th>
    <th>Zeit</th>
    <th>Verb. (WK)</th>
    <th>Vergl. (Test)</th>
    </tr>
    </thead>
    <tbody>
    '.$rows_html.'
    </tbody>
    </table>

    <div class="footer">
    <hr>
    Dieses Dokument wurde mit SLA-Schwimmen generiert. Lizenznehmer: '.$licensee.'
    </div>
    </body>
    </html>';

    // PDF erstellen
    $options = new Options();
    $options->set('defaultFont', 'DejaVu Sans');
    $options->setIsRemoteEnabled(true);
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4','portrait');
    $dompdf->render();
    $dompdf->stream('Auswertung_Sprints_Tests.pdf', ['Attachment'=>true]);
    exit();
}

// Wenn session_id vorhanden, Daten laden
if ($session_id) {
    $session_id = intval($session_id);
    $stmt = $conn->prepare("SELECT * FROM training_sessions WHERE id=? AND user_id=?");
    $stmt->bind_param("ii", $session_id, $user_id);
    $stmt->execute();
    $session = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($session) {
        $stmt = $conn->prepare("SELECT tt.*, ss.name AS swim_style_name FROM training_times tt INNER JOIN swim_styles ss ON ss.id=tt.swim_style_id WHERE tt.session_id=? ORDER BY tt.sequence ASC");
        $stmt->bind_param("i", $session_id);
        $stmt->execute();
        $times = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } else {
        setFlashMessage('error', 'Ungültige Sitzung.');
        header("Location: sprints_tests.php");
        exit();
}
}

// Bisherige Sitzungen abrufen
$stmt = $conn->prepare("SELECT * FROM training_sessions WHERE user_id=? ORDER BY session_date DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$sessions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$error_message = getFlashMessage('error');
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<title>Sprints & Tests</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<?php include '/kunden/homepages/4/d1007545866/htdocs/SLA-Projekt/menu.php'; ?>
<div class="container mt-5">
    <h2>Sprints &amp; Tests</h2>
    <?php if ($error_message): ?>
        <div class='alert alert-danger'><?php echo $error_message; ?></div>
    <?php endif; ?>

    <?php if (!$session_id): ?>
        <!-- Neue Sitzung erstellen -->
        <h3>Neue Trainingssitzung erstellen</h3>
        <form method="post">
            <div class="mb-3">
                <label for="session_date" class="form-label">Datum</label>
                <input type="date" name="session_date" id="session_date" class="form-control" required>
            </div>
            <div class="mb-3">
                <label for="description" class="form-label">Beschreibung</label>
                <input type="text" name="description" id="description" class="form-control">
            </div>
            <div class="mb-3">
                <label for="repeats" class="form-label">Anzahl Wiederholungen</label>
                <input type="number" name="repeats" id="repeats" class="form-control" min="1" value="1" required>
            </div>
            <div class="mb-3">
                <label for="swim_style_id" class="form-label">Schwimmart</label>
                <select name="swim_style_id" id="swim_style_id" class="form-select" required>
                    <?php
                    $stmt = $conn->prepare("SELECT id,name FROM swim_styles ORDER BY name ASC");
                    $stmt->execute();
                    $res=$stmt->get_result();
                    while($st=$res->fetch_assoc()){
                        echo '<option value="'.$st['id'].'">'.htmlspecialchars($st['name']).'</option>';
                    }
                    $stmt->close();
                    ?>
                </select>
            </div>
            <div class="mb-3">
                <label for="distance" class="form-label">Distanz (m)</label>
                <input type="number" name="distance" id="distance" class="form-control" value="50" required>
            </div>
            <button type="submit" name="create_session" class="btn btn-primary">Erstellen</button>
        </form>

        <h3 class="mt-5">Bisherige Trainingssitzungen</h3>
        <?php if (!empty($sessions)): ?>
            <ul class="list-group">
                <?php foreach ($sessions as $sess_item): ?>
                    <li class="list-group-item">
                        <a href="sprints_tests.php?session_id=<?php echo $sess_item['id']; ?>">
                            <?php echo date('d.m.Y', strtotime($sess_item['session_date'])).' - '.htmlspecialchars($sess_item['description']); ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p>Keine Sitzungen gefunden.</p>
        <?php endif; ?>

    <?php elseif ($session && $action==='analysis'): ?>
        <!-- Auswertung -->
        <h3>Auswertung der Trainingssitzung vom <?php echo $session['session_date']?date('d.m.Y',strtotime($session['session_date'])):'Nicht festgelegt'; ?></h3>
        <p><?php echo htmlspecialchars($session['description']); ?></p>
        <table class="table table-striped">
            <thead class="table-dark">
                <tr>
                    <th>Anzahl</th>
                    <th>Schwimmart</th>
                    <th>Distanz</th>
                    <th>Zeit</th>
                    <th>Verb. (WK)</th>
                    <th>Vergl. (Test)</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $times_for_chart=[];
            foreach($times as $t){
                $best_comp = '-';
                $stmt=$conn->prepare("SELECT MIN(time) FROM times WHERE user_id=? AND swim_style_id=? AND distance=?");
                $stmt->bind_param("iii",$user_id,$t['swim_style_id'],$t['distance']);
                $stmt->execute();
                $stmt->bind_result($best_comp_time);
                $stmt->fetch();
                $stmt->close();

                $comp_imp='-';
                if($best_comp_time) {
                    $bc=convertTimeToSeconds($best_comp_time);
                    $cc=convertTimeToSeconds($t['time']);
                    if($bc!==null && $cc!==null) {
                        $comp_imp = formatImprovement($cc-$bc);
                    }
                }

                $last_test='-';
                $stmt=$conn->prepare("SELECT tt.time FROM training_times tt
                                      INNER JOIN training_sessions ts ON tt.session_id=ts.id
                                      WHERE ts.user_id=? AND tt.swim_style_id=? AND tt.distance=?
                                      AND ts.session_date < ?
                                      ORDER BY ts.session_date DESC LIMIT 1");
                $stmt->bind_param("iiis",$user_id,$t['swim_style_id'],$t['distance'],$session['session_date']);
                $stmt->execute();
                $stmt->bind_result($ltime);
                $stmt->fetch();
                $stmt->close();

                $test_imp='-';
                if($ltime) {
                    $lt=convertTimeToSeconds($ltime);
                    $cc=convertTimeToSeconds($t['time']);
                    if($lt!==null && $cc!==null) {
                        $test_imp=formatImprovement($cc-$lt);
                    }
                }

                $times_for_chart[]=['sequence'=>$t['sequence'],'time'=>convertTimeToSeconds($t['time'])];

                echo '<tr>
                    <td>'.$t['sequence'].'</td>
                    <td>'.htmlspecialchars($t['swim_style_name']).'</td>
                    <td>'.$t['distance'].' m</td>
                    <td>'.htmlspecialchars($t['time']).'</td>
                    <td>'.$comp_imp.'</td>
                    <td>'.$test_imp.'</td>
                </tr>';
            }
            ?>
            </tbody>
        </table>
        <?php
        $labels=array_column($times_for_chart,'sequence');
        $vals=array_column($times_for_chart,'time');
        $chartConf=['type'=>'line','data'=>['labels'=>$labels,'datasets'=>[['label'=>'Zeit (Sek)','data'=>$vals,'borderColor'=>'blue','fill'=>false,'tension'=>0.1]]],'options'=>['scales'=>['x'=>['title'=>['display'=>true,'text'=>'Anzahl']],'y'=>['title'=>['display'=>true,'text'=>'Zeit (s)']]]]];
        $chartUrl='https://quickchart.io/chart?c='.urlencode(json_encode($chartConf));
        ?>
        <div class="mb-3 text-center">
            <img src="<?php echo $chartUrl; ?>" alt="Diagramm" style="max-width:100%;">
        </div>

        <form method="post" class="mb-5">
            <input type="hidden" name="session_id" value="<?php echo $session_id;?>">
            <button type="submit" name="generate_pdf" class="btn btn-secondary">PDF Auswertung herunterladen</button>
        </form>

    <?php else: ?>
        <!-- Zeiten eingeben -->
        <h3>Zeiteneingabe für Sitzung vom <?php echo $session['session_date']?date('d.m.Y',strtotime($session['session_date'])):'Nicht festgelegt'; ?></h3>
        <p><?php echo htmlspecialchars($session['description']); ?></p>
        <form method="post">
            <input type="hidden" name="session_id" value="<?php echo $session_id; ?>">
            <table class="table table-striped">
                <thead class="table-dark">
                    <tr>
                        <th>Anzahl</th>
                        <th>Schwimmart</th>
                        <th>Distanz</th>
                        <th>Zeit (MM:SS,MS)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($times as $tm): ?>
                    <tr>
                        <td><?php echo $tm['sequence']; ?></td>
                        <td><?php echo htmlspecialchars($tm['swim_style_name']); ?></td>
                        <td><?php echo $tm['distance'].' m'; ?></td>
                        <td><input type="text" name="time[<?php echo $tm['id']; ?>]" class="form-control" placeholder="MM:SS,MS" required></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <button type="submit" name="save_times" class="btn btn-success">Zeiten speichern</button>
        </form>
    <?php endif; ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
