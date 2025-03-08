<?php
// livetiming_zuschauer.php
ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(E_ALL);
session_start();
require_once('../dbconnection.php');

function safeOutput($str){return htmlspecialchars($str??'',ENT_QUOTES,'UTF-8');}
function setFlashMessage($k,$m){$_SESSION['flash'][$k]=$m;}
function getFlashMessage($k){if(isset($_SESSION['flash'][$k])){$v=$_SESSION['flash'][$k];unset($_SESSION['flash'][$k]);return $v;}return null;}

$flash_error=getFlashMessage('error');
$flash_success=getFlashMessage('success');

$session_id=$_GET['session_id']??null;
$viewer_id=$_SESSION['viewer_id']??null;
$viewer_name=$_SESSION['viewer_name']??null;

if(!$session_id){
    echo "<p>Keine Session-ID angegeben.</p>";
    exit();
}

// Session laden
$stmt=$conn->prepare("SELECT ls.*, c.name AS competition_name, c.place, c.competition_date, u.name as athlete_name
FROM livetiming_sessions ls
INNER JOIN competitions c ON ls.competition_id=c.id
INNER JOIN users u ON ls.athlete_id=u.id
WHERE ls.id=?");
$stmt->bind_param("i",$session_id);
$stmt->execute();
$session=$stmt->get_result()->fetch_assoc();
$stmt->close();

if(!$session){
    echo "<p>Ungültige Session.</p>";
    exit();
}

// Behandlung der Kommentar-Absendung
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['post_comment'])){
    $comment=$_POST['comment']??'';
    $comment=trim($comment);
    if($viewer_id && $comment!==''){
        $stmt=$conn->prepare("INSERT INTO livetiming_comments (session_id,viewer_id,comment) VALUES (?,?,?)");
        $stmt->bind_param("iis",$session_id,$viewer_id,$comment);
        $stmt->execute();
        $stmt->close();
        setFlashMessage('success','Kommentar erfolgreich hinzugefügt.');
    } else {
        setFlashMessage('error','Bitte einen gültigen Kommentar eingeben.');
    }
    header("Location: livetiming_zuschauer.php?session_id=$session_id");
    exit();
}

// Anmeldung Zuschauer
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['join'])){
    $name=$_POST['name']??'';
    $code=$_POST['code']??'';
    if($session['join_method']=='code' && $session['join_code'] && $session['join_code']!=$code){
        setFlashMessage('error','Falscher Code.');
        header("Location: livetiming_zuschauer.php?session_id=$session_id");
        exit();
    }
    $stmt=$conn->prepare("INSERT INTO livetiming_viewers (session_id,name) VALUES (?,?)");
    $stmt->bind_param("is",$session_id,$name);
    $stmt->execute();
    $vid=$stmt->insert_id;
    $stmt->close();
    $_SESSION['viewer_id']=$vid;
    $_SESSION['viewer_name']=$name;
    setFlashMessage('success','Erfolgreich beigetreten.');
    header("Location: livetiming_zuschauer.php?session_id=$session_id");
    exit();
}

if(!$viewer_id){
    // Noch nicht eingeloggt -> Login-Seite
    ?>
    <!DOCTYPE html>
    <html lang="de">
    <head>
    <meta charset="UTF-8">
    <title>Livetiming Beitreten</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
    body{padding-top:56px;background:#e9ecef;}
    .login-card{
        max-width:400px;
        margin:auto;
        margin-top:50px;
    }
    </style>
    </head>
    <body>
    <div class="container">
    <?php if($flash_error): ?><div class='alert alert-danger mt-3'><?=safeOutput($flash_error)?></div><?php endif; ?>
    <?php if($flash_success): ?><div class='alert alert-success mt-3'><?=safeOutput($flash_success)?></div><?php endif; ?>
    <div class="card login-card shadow">
    <div class="card-header bg-primary text-white"><h4><?=safeOutput($session['competition_name'])?> am <?=($session['competition_date']?date('d.m.Y',strtotime($session['competition_date'])):'N/A')?></h4></div>
    <div class="card-body">
    <form method="post">
    <div class="mb-3">
    <label for="name" class="form-label">Dein Name</label>
    <input type="text" name="name" class="form-control" required>
    </div>
    <?php if($session['join_method']=='code' && $session['join_code']): ?>
    <div class="mb-3">
    <label for="code" class="form-label">Beitrittscode</label>
    <input type="text" name="code" class="form-control" required>
    </div>
    <?php endif; ?>
    <button type="submit" name="join" class="btn btn-primary w-100">Beitreten</button>
    </form>
    </div>
    </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    </body>
    </html>
    <?php
    exit();
}

// Status bestimmen
$now=time();
$start=strtotime($session['start_datetime']);
$pause_until=$session['pause_until']?strtotime($session['pause_until']):null;
$end=$session['end_datetime']?strtotime($session['end_datetime']):null;
if($end){
    $status='ended';
} else {
    if($now<$start)$status='before_start';
    elseif($pause_until && $now<$pause_until)$status='paused';
    else $status='running';
}

// Berechtigungen
$permissions=explode(',',$session['permissions']);

// Gemeinsame Daten laden
$stmt=$conn->prepare("SELECT cs.*, ss.name AS swim_style_name
                      FROM competition_starts cs
                      INNER JOIN swim_styles ss ON cs.swim_style_id=ss.id
                      WHERE cs.competition_id=?
                      ORDER BY cs.wk_nr ASC");
$stmt->bind_param("i",$session['competition_id']);
$stmt->execute();
$r_starts=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$stmt=$conn->prepare("SELECT lc.*, lv.name as viewer_name FROM livetiming_comments lc
                      INNER JOIN livetiming_viewers lv ON lc.viewer_id=lv.id
                      WHERE lc.session_id=?
                      ORDER BY lc.created_at ASC");
$stmt->bind_param("i",$session_id);
$stmt->execute();
$r_comments=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$stmt=$conn->prepare("SELECT * FROM livetiming_popups WHERE session_id=? ORDER BY created_at DESC");
$stmt->bind_param("i",$session_id);
$stmt->execute();
$r_popups=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Historische Zeiten für Diagramme
$athlete_id=$session['athlete_id'];

function convertTimeToSeconds($time) {
    $time=trim($time);
    if(preg_match('/^(\d{1,2}):(\d{2}),(\d{2})$/',$time,$m)){
        $minutes=(int)$m[1];
        $seconds=(int)$m[2];
        $ms=(int)$m[3];
        return $minutes*60+$seconds+$ms/100;
    }
    return null;
}

$chart_data_starts=[];
foreach($r_starts as $st){
    $swim_style_id=$st['swim_style_id'];
    $distance=$st['distance'];
    $stm=$conn->prepare("SELECT date,time FROM times WHERE user_id=? AND swim_style_id=? AND distance=? ORDER BY date ASC");
    $stm->bind_param("iii",$athlete_id,$swim_style_id,$distance);
    $stm->execute();
    $res=$stm->get_result()->fetch_all(MYSQLI_ASSOC);
    $stm->close();
    $labels=[];
    $data=[];
    foreach($res as $t){
        $labels[]=date('d.m.Y',strtotime($t['date']));
        $seconds=convertTimeToSeconds($t['time']);
        if($seconds!==null)$data[]=$seconds;else$data[]=null;
    }
    $chart_data_starts[]=[
       'start_id'=>$st['id'],
       'wk_nr'=>$st['wk_nr'],
       'style'=>$st['swim_style_name'],
       'distance'=>$distance,
       'labels'=>$labels,
       'data'=>$data
    ];
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<title>Livetiming Zuschauer</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
body { padding-top:56px; background:#f0f2f5; }
.hero {
    background: linear-gradient(to right, #007bff, #00aaff);
    color: white;
    padding: 30px;
    border-radius:8px;
    margin-bottom:20px;
}
.countdown {
    font-size:48px; 
    text-align:center; 
    margin:20px 0; 
    font-weight:bold; 
    color:#333;
}
.chart-container { position: relative; height:300px; margin-bottom:20px; }
.comment-list {
    max-height:200px; 
    overflow:auto; 
    background:#fff;
    padding:10px;
    border:1px solid #ddd;
    border-radius:5px;
}
.comment-list p {
    background: #f8f9fa; 
    border-radius:5px; 
    padding:8px; 
    margin-bottom:8px;
}
.comment-list p strong {
    color: #007bff;
}
</style>
</head>
<body>

<div class="container mt-4">
<div class="hero">
<h2><?=safeOutput($session['competition_name'])?></h2>
<p>Ort: <?=safeOutput($session['place'])?> | Datum: <?=($session['competition_date']?date('d.m.Y',strtotime($session['competition_date'])):'N/A')?><br>
Sportler: <?=safeOutput($session['athlete_name'])?></p>
</div>

<?php if($flash_error): ?><div class='alert alert-danger'><?=safeOutput($flash_error)?></div><?php endif; ?>
<?php if($flash_success): ?><div class='alert alert-success'><?=safeOutput($flash_success)?></div><?php endif; ?>

<?php
if($status=='before_start'):
$diff=$start-time(); ?>
<p><?=nl2br(safeOutput($session['welcome_message']))?></p>
<div class="countdown" id="countdown"></div>
<script>
let diff=<?=$diff?>;
function updateCountdown(){
    if(diff<=0){ location.reload();return; }
    let d=Math.floor(diff/86400);
    let h=Math.floor((diff%86400)/3600);
    let m=Math.floor((diff%3600)/60);
    let s=Math.floor(diff%60);
    document.getElementById('countdown').textContent=`${d}T ${h}h ${m}m ${s}s`;
    diff--;
}
updateCountdown();
setInterval(updateCountdown,1000);
</script>

<?php elseif($status=='paused'):
$diff=$pause_until-time(); ?>
<div class="card mb-4">
<div class="card-header bg-warning text-dark"><h5>Pause</h5></div>
<div class="card-body">
<p>Der Livetiming ist pausiert bis <?=date('d.m.Y H:i',$pause_until)?>.</p>
<div class="countdown" id="pause_countdown"></div>
</div>
</div>
<script>
let pdiff=<?=$diff?>;
function updatePauseCountdown(){
    if(pdiff<=0){ location.reload();return; }
    let d=Math.floor(pdiff/86400);
    let h=Math.floor((pdiff%86400)/3600);
    let m=Math.floor((pdiff%3600)/60);
    let s=Math.floor(pdiff%60);
    document.getElementById('pause_countdown').textContent=`${d}T ${h}h ${m}m ${s}s`;
    pdiff--;
}
updatePauseCountdown();
setInterval(updatePauseCountdown,1000);
</script>

<h4>Bisher geschwommene Starts:</h4>
<?php
$past_starts=array_filter($r_starts,function($x){return $x['swim_time']!=null;});
if(empty($past_starts)){echo"<p>Noch keine Endzeiten verfügbar.</p>";}
else {
    echo "<div class='table-responsive'><table class='table table-striped'><thead class='table-dark'><tr><th>WK-NR</th><th>Schwimmart</th><th>Distanz</th><th>Endzeit</th></tr></thead><tbody>";
    foreach($past_starts as $pst){
        echo "<tr><td>".safeOutput($pst['wk_nr'])."</td><td>".safeOutput($pst['swim_style_name'])."</td><td>".safeOutput($pst['distance'])." m</td><td>".safeOutput($pst['swim_time'])."</td></tr>";
    }
    echo "</tbody></table></div>";
}
?>

<?php elseif($status=='running'): ?>
<p><?=nl2br(safeOutput($session['welcome_message']))?></p>

<div class="card mb-4">
<div class="card-header bg-info text-white"><h5>Besondere Meldungen</h5></div>
<div class="card-body">
<?php if(empty($r_popups)){echo"<p>Keine Meldungen.</p>";}else{
echo "<ul class='mb-0'>";
foreach($r_popups as $p){
echo "<li>".safeOutput($p['content'])." <small>(".date('d.m.Y H:i',strtotime($p['created_at'])).")</small></li>";
}
echo "</ul>";
}?>
</div>
</div>

<div class="card mb-4">
<div class="card-header bg-secondary text-white"><h5>Starts</h5></div>
<div class="card-body table-responsive">
<table class="table table-striped">
<thead class="table-dark"><tr><th>WK-NR</th><th>Schwimmart</th><th>Distanz</th><th>Meldezeit</th><th>Endzeit</th></tr></thead>
<tbody>
<?php foreach($r_starts as $st): ?>
<tr><td><?=safeOutput($st['wk_nr'])?></td><td><?=safeOutput($st['swim_style_name'])?></td><td><?=safeOutput($st['distance'])?> m</td><td><?=safeOutput($st['entry_time'])?></td><td><?=safeOutput($st['swim_time']??'-')?></td></tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</div>

<?php if(in_array('comment',$permissions)): ?>
<div class="card mb-4">
<div class="card-header bg-light"><h5>Kommentare</h5></div>
<div class="card-body">
<div class="comment-list">
<?php if(empty($r_comments)){echo"<p>Keine Kommentare.</p>";}else{
foreach($r_comments as $cm){
echo "<p><strong>".safeOutput($cm['viewer_name']).":</strong><br>".nl2br(safeOutput($cm['comment']))."<br><small>".date('d.m.Y H:i',strtotime($cm['created_at']))."</small></p>";
}
}?>
</div>
</div>
<div class="card-footer">
<?php if($viewer_id): ?>
<form method="post" class="mb-0">
<div class="input-group">
<input type="text" name="comment" class="form-control" placeholder="Dein Kommentar...">
<button type="submit" name="post_comment" class="btn btn-primary">Senden</button>
</div>
</form>
<?php else: ?>
<p>Bitte erst beitreten, um zu kommentieren.</p>
<?php endif; ?>
</div>
</div>
<?php endif; ?>

<?php if($session['pdf_file'] && in_array('download',$permissions)): ?>
<div class="mb-4">
<a href="../<?=safeOutput($session['pdf_file'])?>" target="_blank" class="btn btn-outline-primary">Auswertung (PDF) herunterladen</a>
</div>
<?php endif; ?>

<h4>Historische Zeiten pro Start:</h4>
<p>Nutze die Pfeile zum Navigieren zwischen den Diagrammen.</p>
<div id="chartsCarousel" class="carousel slide" data-bs-ride="carousel">
<div class="carousel-inner">
<?php
$active=true;
foreach($chart_data_starts as $cds):
    $active_class=$active?' active':'';
    $active=false;
?>
<div class="carousel-item<?=$active_class?>">
<div class="card mb-4">
<div class="card-header bg-primary text-white">
<h5>WK-NR <?=$cds['wk_nr']?> - <?=safeOutput($cds['style'])?> (<?=$cds['distance']?> m)</h5>
</div>
<div class="card-body">
<?php if(empty($cds['data'])||count($cds['data'])==0){echo "<p>Keine historischen Daten vorhanden.</p>";}else{ ?>
<div class="chart-container">
<canvas id="chart_<?=$cds['start_id']?>"></canvas>
</div>
<script>
(function(){
  const ctx=document.getElementById('chart_<?=$cds['start_id']?>').getContext('2d');
  const labels=<?=json_encode($cds['labels'])?>;
  const data=<?=json_encode($cds['data'])?>;
  const chart=new Chart(ctx,{
    type:'line',
    data:{
      labels:labels,
      datasets:[{
        label:'Zeit (Sek.)',
        data:data,
        borderColor:'blue',
        backgroundColor:'rgba(0,0,255,0.1)',
        fill:true,
        tension:0.1,
        pointRadius:4,
        pointBackgroundColor:'blue'
      }]
    },
    options:{
      responsive:true,
      maintainAspectRatio:false,
      scales:{
        y:{title:{display:true,text:'Sekunden'}},
        x:{title:{display:true,text:'Datum'}}
      },
      plugins:{
        tooltip:{
          callbacks:{
            label:function(ctx){
              let val=ctx.parsed.y;
              return 'Zeit: '+val.toFixed(2)+' s';
            }
          }
        }
      }
    }
  });
})();
</script>
<?php } ?>
</div>
</div>
</div>
<?php endforeach; ?>
</div>
<button class="carousel-control-prev" type="button" data-bs-target="#chartsCarousel" data-bs-slide="prev">
<span class="carousel-control-prev-icon" aria-hidden="true"></span>
<span class="visually-hidden">Vorherige</span>
</button>
<button class="carousel-control-next" type="button" data-bs-target="#chartsCarousel" data-bs-slide="next">
<span class="carousel-control-next-icon" aria-hidden="true"></span>
<span class="visually-hidden">Nächste</span>
</button>
</div>

<?php elseif($status=='ended'): ?>
<p><?=nl2br(safeOutput($session['farewell_message']))?></p>

<div class="card mb-4">
<div class="card-header bg-info text-white"><h5>Finale Auswertung</h5></div>
<div class="card-body">
<?php if($session['pdf_file'] && in_array('download',$permissions)): ?>
<p><a href="../<?=safeOutput($session['pdf_file'])?>" target="_blank" class="btn btn-outline-primary">Abschließende Auswertung (PDF) herunterladen</a></p>
<?php else: ?>
<p>Keine Auswertung hochgeladen.</p>
<?php endif; ?>
</div>
</div>

<h4>Alle Starts:</h4>
<div class="table-responsive">
<table class="table table-striped">
<thead class="table-dark"><tr><th>WK-NR</th><th>Schwimmart</th><th>Distanz</th><th>Endzeit</th></tr></thead>
<tbody>
<?php foreach($r_starts as $st): ?>
<tr><td><?=safeOutput($st['wk_nr'])?></td><td><?=safeOutput($st['swim_style_name'])?></td><td><?=safeOutput($st['distance'])?>m</td><td><?=safeOutput($st['swim_time']??'-')?></td></tr>
<?php endforeach; ?>
</tbody>
</table>
</div>

<?php if(in_array('comment',$permissions)): ?>
<h4>Kommentare:</h4>
<div class="comment-list">
<?php if(empty($r_comments)){echo"<p>Keine Kommentare.</p>";}else{
foreach($r_comments as $cm){
echo "<p><strong>".safeOutput($cm['viewer_name']).":</strong><br>".nl2br(safeOutput($cm['comment']))."<br><small>".date('d.m.Y H:i',strtotime($cm['created_at']))."</small></p>";
}
}?>
</div>
<?php endif; ?>

<h4>Historische Zeiten pro Start (Endansicht):</h4>
<div id="chartsCarousel" class="carousel slide" data-bs-ride="carousel">
<div class="carousel-inner">
<?php
$active=true;
foreach($chart_data_starts as $cds):
    $active_class=$active?' active':'';
    $active=false;
?>
<div class="carousel-item<?=$active_class?>">
<div class="card mb-4">
<div class="card-header bg-primary text-white">
<h5>WK-NR <?=$cds['wk_nr']?> - <?=safeOutput($cds['style'])?> (<?=$cds['distance']?> m)</h5>
</div>
<div class="card-body">
<?php if(empty($cds['data'])||count($cds['data'])==0){echo "<p>Keine historischen Daten vorhanden.</p>";}else{ ?>
<div class="chart-container">
<canvas id="chart_end_<?=$cds['start_id']?>"></canvas>
</div>
<script>
(function(){
  const ctx=document.getElementById('chart_end_<?=$cds['start_id']?>').getContext('2d');
  const labels=<?=json_encode($cds['labels'])?>;
  const data=<?=json_encode($cds['data'])?>;
  const chart=new Chart(ctx,{
    type:'line',
    data:{
      labels:labels,
      datasets:[{
        label:'Zeit (Sek.)',
        data:data,
        borderColor:'blue',
        backgroundColor:'rgba(0,0,255,0.1)',
        fill:true,
        tension:0.1,
        pointRadius:4,
        pointBackgroundColor:'blue'
      }]
    },
    options:{
      responsive:true,
      maintainAspectRatio:false,
      scales:{
        y:{title:{display:true,text:'Sekunden'}},
        x:{title:{display:true,text:'Datum'}}
      },
      plugins:{
        tooltip:{
          callbacks:{
            label:function(ctx){
              let val=ctx.parsed.y;
              return 'Zeit: '+val.toFixed(2)+' s';
            }
          }
        }
      }
    }
  });
})();
</script>
<?php } ?>
</div>
</div>
</div>
<?php endforeach; ?>
</div>
<button class="carousel-control-prev" type="button" data-bs-target="#chartsCarousel" data-bs-slide="prev">
<span class="carousel-control-prev-icon" aria-hidden="true"></span>
<span class="visually-hidden">Vorherige</span>
</button>
<button class="carousel-control-next" type="button" data-bs-target="#chartsCarousel" data-bs-slide="next">
<span class="carousel-control-next-icon" aria-hidden="true"></span>
<span class="visually-hidden">Nächste</span>
</button>
</div>

<?php endif; ?>

</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
