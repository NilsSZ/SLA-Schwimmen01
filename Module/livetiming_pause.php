<?php
// livetiming_pause.php
// Status paused
// Zeigt einen Pause-Screen mit Countdown bis `pause_until`
header("Refresh:10"); // alle 10 Sek neu laden
$flash_error=getFlashMessage('error');
$flash_success=getFlashMessage('success');

$pause_until=strtotime($session['pause_until']);
$diff=$pause_until-time();
if($diff<=0){
    header("Location: livetiming_zuschauer.php?session_id=$session_id");
    exit();
}

// Vergangene Starts (mit Endzeit)
$stmt=$conn->prepare("SELECT cs.*, ss.name AS swim_style_name
                      FROM competition_starts cs
                      INNER JOIN swim_styles ss ON cs.swim_style_id=ss.id
                      WHERE cs.competition_id=? AND cs.swim_time IS NOT NULL
                      ORDER BY cs.wk_nr ASC");
$stmt->bind_param("i",$session['competition_id']);
$stmt->execute();
$past_starts=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$d=floor($diff/86400);
$h=floor(($diff%86400)/3600);
$m=floor(($diff%3600)/60);
$s=floor($diff%60);
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<title>Livetiming - Pause</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
.countdown {
  font-size:32px;
  text-align:center;
  margin:20px 0;
}
</style>
</head>
<body style="padding-top:56px;background:#f0f2f5;">
<div class="container mt-4">
<?php if($flash_error): ?><div class="alert alert-danger"><?=safeOutput($flash_error)?></div><?php endif; ?>
<?php if($flash_success): ?><div class="alert alert-success"><?=safeOutput($flash_success)?></div><?php endif; ?>
<h3><?=safeOutput($session['competition_name'])?> - Pause</h3>
<p>Der Livetiming ist pausiert bis <?=date('d.m.Y H:i',$pause_until)?></p>
<div class="countdown">Noch <?=$d?>T <?=$h?>h <?=$m?>m <?=$s?>s</div>

<h4>Vergangene Starts:</h4>
<?php if(empty($past_starts)){echo "<p>Noch keine beendeten Starts.</p>";}else{ ?>
<table class="table table-striped">
<thead class="table-dark"><tr><th>WK-NR</th><th>Schwimmart</th><th>Distanz</th><th>Endzeit</th></tr></thead>
<tbody>
<?php foreach($past_starts as $pst): ?>
<tr>
<td><?=safeOutput($pst['wk_nr'])?></td>
<td><?=safeOutput($pst['swim_style_name'])?></td>
<td><?=safeOutput($pst['distance'])?> m</td>
<td><?=safeOutput($pst['swim_time'])?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<?php } ?>
</div>
</body>
</html>
