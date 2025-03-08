<?php
// livetiming_start.php
// Wird von livetiming_zuschauer.php included, wenn status='before_start'
// Countdown bis zum Start, Auto-Reload alle 10 Sek.
header("Refresh:10");
$flash_error=getFlashMessage('error');
$flash_success=getFlashMessage('success');

$start=strtotime($session['start_datetime']);
$diff=$start-time();
if($diff<0) { header("Location: livetiming_zuschauer.php?session_id=$session_id"); exit(); }

$d=floor($diff/86400);
$h=floor(($diff%86400)/3600);
$m=floor(($diff%3600)/60);
$s=floor($diff%60);
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<title>Livetiming - Startet bald</title>
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
<h3><?=safeOutput($session['competition_name'])?> startet am <?=date('d.m.Y H:i',$start)?></h3>
<p><?=nl2br(safeOutput($session['welcome_message']))?></p>
<div class="countdown">In <?=$d?>T <?=$h?>h <?=$m?>m <?=$s?>s</div>
</div>
</body>
</html>
