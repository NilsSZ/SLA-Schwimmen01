<?php
// livetiming_end.php
// Status ended - zeigt Abschieds-Nachricht, ggf. PDF-Download, alle Starts, Kommentare
header("Refresh:60"); // alle 60 Sek neu laden, falls was geändert
$flash_error=getFlashMessage('error');
$flash_success=getFlashMessage('success');

// Daten laden
$permissions=explode(',',$session['permissions']);
$stmt=$conn->prepare("SELECT cs.*, ss.name AS swim_style_name
                      FROM competition_starts cs
                      INNER JOIN swim_styles ss ON cs.swim_style_id=ss.id
                      WHERE cs.competition_id=?
                      ORDER BY cs.wk_nr ASC");
$stmt->bind_param("i",$session['competition_id']);
$stmt->execute();
$all_starts=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$stmt=$conn->prepare("SELECT lc.*, lv.name as viewer_name
                      FROM livetiming_comments lc
                      INNER JOIN livetiming_viewers lv ON lc.viewer_id=lv.id
                      WHERE lc.session_id=?
                      ORDER BY lc.created_at ASC");
$stmt->bind_param("i",$session_id);
$stmt->execute();
$all_comments=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<title>Livetiming - Beendet</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body style="padding-top:56px;background:#f0f2f5;">
<div class="container mt-4">
<?php if($flash_error): ?><div class="alert alert-danger"><?=safeOutput($flash_error)?></div><?php endif; ?>
<?php if($flash_success): ?><div class="alert alert-success"><?=safeOutput($flash_success)?></div><?php endif; ?>
<h3><?=safeOutput($session['competition_name'])?> - Beendet</h3>
<p><?=nl2br(safeOutput($session['farewell_message']))?></p>

<?php if($session['pdf_file'] && in_array('download',$permissions)): ?>
<p><a href="../<?=safeOutput($session['pdf_file'])?>" target="_blank">Auswertung (PDF) herunterladen</a></p>
<?php endif; ?>

<h4>Alle Starts:</h4>
<table class="table table-striped">
<thead class="table-dark"><tr><th>WK-NR</th><th>Schwimmart</th><th>Distanz</th><th>Endzeit</th></tr></thead>
<tbody>
<?php foreach($all_starts as $st): ?>
<tr>
<td><?=safeOutput($st['wk_nr'])?></td>
<td><?=safeOutput($st['swim_style_name'])?></td>
<td><?=safeOutput($st['distance'])?> m</td>
<td><?=safeOutput($st['swim_time']??'-')?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>

<h4>Kommentare:</h4>
<div style="max-height:300px;overflow:auto;">
<?php if(empty($all_comments)){echo"<p>Keine Kommentare.</p>";}else{
foreach($all_comments as $cm){
echo "<p><strong>".safeOutput($cm['viewer_name']).":</strong> ".nl2br(safeOutput($cm['comment']))."</p>";
}
}?>
</div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
