<?php
// attest.php
ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(E_ALL);

session_start();
require_once('../dbconnection.php');
require_once 'license_helper.php';

$user_id = $_SESSION['user_id'] ?? 0;
$module_id = 3; // Beispiel: Die Modul-ID dieses Moduls

$license = checkLicense($module_id, $user_id);
if (!$license) {
    // Falls keine Lizenz gefunden wurde, zeige einen Hinweis und/oder leite zum Shop weiter
    echo "<div class='alert alert-warning'>Du besitzt diese Lizenz noch nicht. Bitte kaufe sie im <a href='online-shop.php'>Shop</a>.</div>";
    // Optional: Den Rest des Codes (z.B. Modulfunktionen) nicht ausführen
    exit();
}



// -------------------------------------------------------------------
// KURZES LIZENZCHECK-SNIPPET (SPÄTER NUTZBAR)
// -------------------------------------------------------------------
// function checkModuleLicense($moduleName){
//     // Hier später echte Prüfung, ob der User dieses Modul lizenziert hat
//     return true; // Demo: immer true
// }
// if(!checkModuleLicense('attests')){
//     echo "<h2>Keine Lizenz für dieses Modul.</h2>";
//     exit();
// }
// -------------------------------------------------------------------

if(!isset($_SESSION['user_id'])){
    header('Location: ../login.php');
    exit();
}
$user_id   = $_SESSION['user_id'];
$user_name = $_SESSION['name'] ?? 'Benutzer';

// Flash-Funktionen
function setFlash($k,$m){ $_SESSION['flash_'.$k]=$m; }
function getFlash($k){
    if(isset($_SESSION['flash_'.$k])){
        $v=$_SESSION['flash_'.$k];
        unset($_SESSION['flash_'.$k]);
        return $v;
    }
    return null;
}
$flash_error   = getFlash('error');
$flash_success = getFlash('success');

// Upload-Verzeichnis
$uploadDir = '../uploads_attests';
if(!is_dir($uploadDir)){ mkdir($uploadDir,0777,true); }

// Ärztetabelle auslesen (DropDown & Ajax-Suche)
$doctorsList = [];
$stmt = $conn->prepare("SELECT id, practice_name, zip, city FROM doctors WHERE deleted_at IS NULL ORDER BY practice_name ASC");
$stmt->execute();
$res = $stmt->get_result();
while($row = $res->fetch_assoc()){
    $doctorsList[] = $row;
}
$stmt->close();

// Ärztetabelle: neues Einfügen (falls man direkt in der Maske "Neuen Arzt hinzufügen" nutzt)
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['new_doctor'])){
    $pName = trim($_POST['practice_name']??'');
    $pZip  = trim($_POST['zip']??'');
    $pCity = trim($_POST['city']??'');
    if($pName===''){
        setFlash('error','Praxisname darf nicht leer sein.');
        header('Location: attest.php');
        exit();
    }
    $ins=$conn->prepare("INSERT INTO doctors (practice_name, zip, city) VALUES (?,?,?)");
    $ins->bind_param("sss",$pName,$pZip,$pCity);
    if($ins->execute()){
        setFlash('success','Neue Arztpraxis erfolgreich angelegt.');
    } else {
        setFlash('error','Fehler beim Hinzufügen der Arztpraxis: '.$conn->error);
    }
    $ins->close();
    header('Location: attest.php');
    exit();
}

// Ajax-Arztsuche
if(isset($_GET['ajax_doctor_search'])){
    $term = trim($_GET['ajax_doctor_search']);
    $st = $conn->prepare("SELECT id, practice_name, zip, city FROM doctors 
                          WHERE deleted_at IS NULL 
                            AND practice_name LIKE CONCAT('%',?,'%')
                          ORDER BY practice_name ASC LIMIT 10");
    $st->bind_param("s",$term);
    $st->execute();
    $result = $st->get_result();
    $data = [];
    while($r=$result->fetch_assoc()) $data[]=$r;
    $st->close();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit();
}

// Hilfsfunktion: Calendar-Event anlegen / aktualisieren
function updateCalendarForAttest($conn,$attest_id,$user_id,$valid_until,$doctor_id,$doctor_name){
    $del = $conn->prepare("
      DELETE FROM calendar_events
      WHERE user_id=? AND title='Ablauf Sportattest'
      AND date=(SELECT valid_until FROM attests WHERE id=?)
    ");
    $del->bind_param("ii",$user_id,$attest_id);
    $del->execute();
    $del->close();
    $desc = 'Attest läuft ab. Arzt: ';
    if($doctor_id){
        $dq=$conn->prepare("SELECT practice_name FROM doctors WHERE id=?");
        $dq->bind_param("i",$doctor_id);
        $dq->execute();
        $dq->bind_result($pn);
        if($dq->fetch()){ $desc.=$pn; }
        $dq->close();
    } else {
        $desc.=($doctor_name ?: 'Unbekannt');
    }
    $title='Ablauf Sportattest';
    $typ='Sonstiges';
    $in=$conn->prepare("INSERT INTO calendar_events (user_id,title,type,date,description) VALUES (?,?,?,?,?)");
    $in->bind_param("issss",$user_id,$title,$typ,$valid_until,$desc);
    $in->execute();
    $in->close();
}

// Automatische Gültigkeit in Tagen (Beispielwerte)
function getDefaultValidityDays($exam_type){
    // Du kannst hier beliebig anpassen
    switch($exam_type){
        case 'Sport-Tauglichkeitsuntersuchung': return 365;
        case 'Reha-Freigabe':                   return 180;
        case 'Ärztliche Unbedenklichkeit':      return 730;
        default:                                return 365; 
    }
}

// Attest-Typen-Liste
$attestTypes = [
    'Sport-Tauglichkeitsuntersuchung',
    'Reha-Freigabe',
    'Ärztliche Unbedenklichkeit',
    'Sonstiges'
];

// Code-Generator
function generateRandomCode($length=8){
    $chars='ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $code='';
    for($i=0;$i<$length;$i++){
        $code.=$chars[rand(0,strlen($chars)-1)];
    }
    return $code;
}

// Neues Attest
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['create_attest'])){
    $exam_date       = $_POST['exam_date'] ?? '';
    $exam_type       = $_POST['exam_type'] ?? 'Sonstiges';
    $auto_days       = getDefaultValidityDays($exam_type);
    // Gültig-bis auto berechnen
    $tsExam = strtotime($exam_date);
    $validDateCalc = date('Y-m-d', $tsExam + $auto_days*86400);

    // Der User darf aber auch manuell eingreifen
    $user_valid_until = trim($_POST['valid_until']??'');
    if($user_valid_until!==''){
        $valid_until = $user_valid_until;
    } else {
        $valid_until = $validDateCalc;
    }

    $doctor_id       = (int)($_POST['doctor_id']??0);
    $doctor_manual   = trim($_POST['doctor_name_manual']??'');
    $notes           = trim($_POST['notes']??'');
    $signature       = $_POST['signature_data']??'';
    $needs_followup  = isset($_POST['needs_followup'])?1:0;

    // Upload
    $imagePath=null;
    if(isset($_FILES['attest_image']) && $_FILES['attest_image']['error']===0){
        $ext = strtolower(pathinfo($_FILES['attest_image']['name'],PATHINFO_EXTENSION));
        $allowed=['jpg','jpeg','png','gif'];
        if(in_array($ext,$allowed)){
            $newName='attest_'.time().'_'.rand(1000,9999).'.'.$ext;
            $dest=$uploadDir.'/'.$newName;
            if(move_uploaded_file($_FILES['attest_image']['tmp_name'],$dest)){
                $imagePath='uploads_attests/'.$newName;
            }
        }
    }

    if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$exam_date)){
        setFlash('error','Ungültiges Untersuchungs-Datum.');
        header('Location: attest.php');
        exit();
    }
    if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$valid_until)){
        setFlash('error','Ungültiges Gültig-bis-Datum.');
        header('Location: attest.php');
        exit();
    }
    $reference_code=generateRandomCode(10);
    $stmt=$conn->prepare("
      INSERT INTO attests
      (user_id, exam_date, valid_until, doctor_id, doctor_name_manual,
       notes, image_path, signature_data, exam_type, status, needs_followup,
       reference_code)
      VALUES
      (?,?,?,?,?,?,?,?,?,?,?,?)
    ");
    $status='wartend';
    $stmt->bind_param("ississssssss",
        $user_id,$exam_date,$valid_until,
        $doctor_id,$doctor_manual,$notes,$imagePath,$signature,
        $exam_type,$status,$needs_followup,$reference_code
    );
    if($stmt->execute()){
        $newId=$stmt->insert_id;
        updateCalendarForAttest($conn,$newId,$user_id,$valid_until,$doctor_id,$doctor_manual);
        setFlash('success','Attest erfolgreich angelegt.');
    } else {
        setFlash('error','Fehler beim Anlegen: '.$conn->error);
    }
    $stmt->close();
    header('Location: attest.php');
    exit();
}

// Edit
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['update_attest'])){
    $attest_id = (int)$_POST['attest_id'];
    // Neue Felder
    $exam_date       = $_POST['exam_date'] ?? '';
    $exam_type       = $_POST['exam_type'] ?? 'Sonstiges';
    $auto_days       = getDefaultValidityDays($exam_type);
    $tsExam          = strtotime($exam_date);
    $def_valid_until = date('Y-m-d',$tsExam + $auto_days*86400);
    $user_valid_until= trim($_POST['valid_until']??'');
    if($user_valid_until!==''){
        $valid_until=$user_valid_until;
    } else {
        $valid_until=$def_valid_until;
    }
    $doctor_id       = (int)($_POST['doctor_id']??0);
    $doctor_manual   = trim($_POST['doctor_name_manual']??'');
    $notes           = trim($_POST['notes']??'');
    $signature       = $_POST['signature_data']??'';
    $needs_followup  = isset($_POST['needs_followup'])?1:0;
    $status          = $_POST['status']??'wartend';

    $imagePath=null;
    if(isset($_FILES['attest_image_edit']) && $_FILES['attest_image_edit']['error']===0){
        $ext=strtolower(pathinfo($_FILES['attest_image_edit']['name'],PATHINFO_EXTENSION));
        $allowed=['jpg','jpeg','png','gif'];
        if(in_array($ext,$allowed)){
            $newName='attest_'.time().'_'.rand(1000,9999).'.'.$ext;
            $dest=$uploadDir.'/'.$newName;
            if(move_uploaded_file($_FILES['attest_image_edit']['tmp_name'],$dest)){
                $imagePath='uploads_attests/'.$newName;
            }
        }
    }
    if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$exam_date)){
        setFlash('error','Ungültiges Untersuchungs-Datum.');
        header('Location: attest.php');
        exit();
    }
    if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$valid_until)){
        setFlash('error','Ungültiges Gültig-bis-Datum.');
        header('Location: attest.php');
        exit();
    }

    $sql="UPDATE attests SET exam_date=?, valid_until=?, exam_type=?, doctor_id=?, doctor_name_manual=?, notes=?, needs_followup=?, status=?";
    $bindTypes="sssissis";
    $bindParams=[$exam_date,$valid_until,$exam_type,$doctor_id,$doctor_manual,$notes,$needs_followup,$status];

    if($imagePath!==null){
        $sql.=", image_path=?";
        $bindTypes.="s";
        $bindParams[]=$imagePath;
    }
    if($signature!==''){
        $sql.=", signature_data=?";
        $bindTypes.="s";
        $bindParams[]=$signature;
    }
    $sql.=", updated_at=NOW() WHERE id=? AND user_id=? AND deleted_at IS NULL";
    $bindTypes.="ii";
    $bindParams[]=$attest_id;
    $bindParams[]=$user_id;

    $stmt=$conn->prepare($sql);
    $stmt->bind_param($bindTypes, ...$bindParams);
    if($stmt->execute()){
        updateCalendarForAttest($conn,$attest_id,$user_id,$valid_until,$doctor_id,$doctor_manual);
        setFlash('success','Attest aktualisiert.');
    } else {
        setFlash('error','Fehler beim Update: '.$conn->error);
    }
    $stmt->close();
    header('Location: attest.php');
    exit();
}

// Löschen
if(isset($_GET['delete_id'])){
    $del=(int)$_GET['delete_id'];
    $st=$conn->prepare("UPDATE attests SET deleted_at=NOW() WHERE id=? AND user_id=?");
    $st->bind_param("ii",$del,$user_id);
    $st->execute();
    $st->close();
    // Kalender-Eintrag entfernen
    $cd=$conn->prepare("
       DELETE FROM calendar_events
       WHERE user_id=? AND title='Ablauf Sportattest'
       AND date=(SELECT valid_until FROM attests WHERE id=?)
    ");
    $cd->bind_param("ii",$user_id,$del);
    $cd->execute();
    $cd->close();
    header('Location: attest.php');
    exit();
}

// Liste
$allAttests=[];
$stmt=$conn->prepare("
  SELECT a.*, d.practice_name
  FROM attests a
  LEFT JOIN doctors d ON a.doctor_id=d.id
  WHERE a.user_id=? AND a.deleted_at IS NULL
  ORDER BY a.valid_until ASC
");
$stmt->bind_param("i",$user_id);
$stmt->execute();
$allAttests=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// CSV/PDF Exporte - optional
if(isset($_GET['export_csv'])){
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="attests_export.csv"');
    echo "Untersuchungsdatum;Gueltig_bis;Arzt;Typ;Status;Notizen\n";
    foreach($allAttests as $row){
        $dat= date('d.m.Y',strtotime($row['exam_date']));
        $val= date('d.m.Y',strtotime($row['valid_until']));
        $doc= $row['practice_name']?$row['practice_name']:$row['doctor_name_manual'];
        $typ= $row['exam_type']??'---';
        $sts= $row['status']??'wartend';
        $nts= str_replace(["\r","\n",";"],[" "," ","."],$row['notes']??'');
        echo "$dat;$val;$doc;$typ;$sts;$nts\n";
    }
    exit();
}
if(isset($_GET['export_pdf'])){
    require_once('../TCPDF-main/tcpdf.php');
    $pdf = new TCPDF('P','mm','A4',true,'UTF-8',false);
    $pdf->SetCreator('SLA-Schwimmen');
    $pdf->SetAuthor($user_name);
    $pdf->SetTitle('Attests Export');
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(true);
    $pdf->SetFooterMargin(15);
    $pdf->AddPage();
    $pdf->SetFont('dejavusans','B',14);
    $pdf->Cell(0,10,'Attest-Übersicht',0,1,'C');
    $pdf->SetFont('dejavusans','',10);
    foreach($allAttests as $row){
        $pdf->Ln(3);
        $pdf->MultiCell(0,6,"Untersuchungsdatum: ".date('d.m.Y',strtotime($row['exam_date'])),0,'L');
        $pdf->MultiCell(0,6,"Gültig bis: ".date('d.m.Y',strtotime($row['valid_until'])),0,'L');
        $doc=$row['practice_name']?$row['practice_name']:$row['doctor_name_manual'];
        $pdf->MultiCell(0,6,"Arzt: ".$doc,0,'L');
        $pdf->MultiCell(0,6,"Typ: ".($row['exam_type']??'---'),0,'L');
        $pdf->MultiCell(0,6,"Status: ".($row['status']??'wartend'),0,'L');
        $pdf->MultiCell(0,6,"Notizen: ".($row['notes']??''),0,'L');
        $pdf->Ln(3);
        $pdf->Cell(0,0,'-----------------------------------',0,1,'L');
    }
    $pdf->SetY(-30);
    $pdf->SetFont('dejavusans','',10);
    $pdf->SetDrawColor(0,0,0);
    $pdf->SetLineWidth(0.5);
    $pdf->Line($pdf->GetX(),$pdf->GetY(),$pdf->getPageWidth()-$pdf->GetX(),$pdf->GetY());
    $pdf->Ln(5);
    $merksatz="Dieses Dokument wurde mit SLA-Schwimmen generiert. Lizenznehmer: $user_name.";
    $pdf->Cell(0,0,$merksatz,0,1,'C');
    $pdf->Output('Attests_Export.pdf','I');
    exit();
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Attest-Modul</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.10.5/font/bootstrap-icons.min.css">
    <style>
      body { padding-top:56px; background:#f8f9fa; }
      .card { margin-bottom:20px; }
      .signature-pad {border:1px solid #ccc;width:100%;height:180px;background:#fff;}
      .search-results {position:absolute;background:#fff;width:100%;z-index:9999;border:1px solid #ccc;display:none;max-height:200px;overflow:auto;}
      .search-results div {padding:5px;cursor:pointer;}
      .search-results div:hover {background:#eee;}
      .table-hover tbody tr:hover {background:#fafafa;}
    </style>
</head>
<body>
<?php include '../menu.php'; ?>
<div class="container mt-4">

<?php if($flash_error): ?><div class="alert alert-danger"><?= $flash_error ?></div><?php endif; ?>
<?php if($flash_success): ?><div class="alert alert-success"><?= $flash_success ?></div><?php endif; ?>

<h2>Attest-Modul</h2>

<div class="row">
  <div class="col-md-8">
    <!-- Attestliste -->
    <div class="card mb-4">
      <div class="card-header">
        <h5 class="m-0">Meine Atteste</h5>
      </div>
      <div class="card-body p-2">
        <?php if(empty($allAttests)): ?>
          <p>Keine Atteste angelegt.</p>
        <?php else: ?>
          <table class="table table-bordered table-hover">
            <thead class="table-light">
              <tr>
                <th>Untersuchungsdatum</th>
                <th>Gültig bis</th>
                <th>Arzt</th>
                <th>Typ</th>
                <th>Status</th>
                <th>Aktion</th>
              </tr>
            </thead>
            <tbody>
            <?php foreach($allAttests as $at):
              $expiresIn = strtotime($at['valid_until']) - time();
              $rowClass=($expiresIn<30*86400)?'table-warning':'';
            ?>
              <tr class="<?= $rowClass ?>">
                <td><?= date('d.m.Y',strtotime($at['exam_date'])) ?></td>
                <td><?= date('d.m.Y',strtotime($at['valid_until'])) ?></td>
                <td><?php
                  if(!empty($at['practice_name'])) echo htmlspecialchars($at['practice_name']);
                  else echo htmlspecialchars($at['doctor_name_manual']??'Unbekannt');
                ?></td>
                <td><?= htmlspecialchars($at['exam_type']??'Sonstiges') ?></td>
                <td><?= htmlspecialchars($at['status']??'wartend') ?></td>
                <td>
                  <button class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#editModal<?= $at['id']?>">
                    <i class="bi bi-pencil-square"></i>
                  </button>
                  <a href="?delete_id=<?= $at['id']?>" class="btn btn-sm btn-danger"
                     onclick="return confirm('Dieses Attest wirklich löschen?');">
                    <i class="bi bi-trash"></i>
                  </a>
                </td>
              </tr>

              <!-- Edit Modal -->
              <div class="modal fade" id="editModal<?= $at['id']?>" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                  <div class="modal-content">
                    <form method="post" enctype="multipart/form-data">
                      <input type="hidden" name="update_attest" value="1">
                      <input type="hidden" name="attest_id" value="<?= $at['id']?>">
                      <div class="modal-header">
                        <h5 class="modal-title">Attest bearbeiten</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                      </div>
                      <div class="modal-body">
                        <div class="mb-3">
                          <label class="form-label">Untersuchungsdatum</label>
                          <input type="date" name="exam_date" class="form-control" value="<?= $at['exam_date']?>" required>
                        </div>
                        <div class="mb-3">
                          <label class="form-label">Attest-Typ</label>
                          <select name="exam_type" class="form-select">
                            <?php foreach($attestTypes as $typ): ?>
                              <option value="<?=$typ?>" <?=$typ==$at['exam_type']?'selected':''?>><?=$typ?></option>
                            <?php endforeach; ?>
                          </select>
                          <small class="text-muted">Bei Änderung wird die Gültigkeit beim Speichern neu berechnet, falls kein manuelles Gültig-bis-Datum angegeben wird.</small>
                        </div>
                        <div class="mb-3">
                          <label class="form-label">Gültig bis (leer lassen, um aut. zu berechnen)</label>
                          <input type="date" name="valid_until" class="form-control" placeholder="z.B. leer">
                        </div>
                        <div class="mb-3 position-relative">
                          <label class="form-label">Arztpraxis (Suche)</label>
                          <input type="hidden" name="doctor_id" id="docIdEdit<?= $at['id']?>" value="<?= (int)$at['doctor_id']?>">
                          <input type="text" class="form-control docSearchEdit" id="docSearchEdit<?= $at['id']?>"
                                 data-result-div="searchResEdit<?= $at['id']?>" placeholder="Arztpraxis..."
                                 value="<?php if(!empty($at['practice_name'])) echo $at['practice_name']; ?>">
                          <div class="search-results" id="searchResEdit<?= $at['id']?>"></div>
                        </div>
                        <div class="mb-3">
                          <label class="form-label">Arztpraxis (manuell)</label>
                          <input type="text" name="doctor_name_manual" class="form-control" 
                                 value="<?=htmlspecialchars($at['doctor_name_manual']??'')?>">
                        </div>
                        <div class="mb-3">
                          <label class="form-label">Status</label>
                          <select name="status" class="form-select">
                            <option value="wartend"    <?=$at['status']=='wartend'?'selected':''?>>wartend</option>
                            <option value="gueltig"    <?=$at['status']=='gueltig'?'selected':''?>>gültig</option>
                            <option value="abgelaufen" <?=$at['status']=='abgelaufen'?'selected':''?>>abgelaufen</option>
                            <option value="ungueltig"  <?=$at['status']=='ungueltig'?'selected':''?>>ungültig</option>
                          </select>
                        </div>
                        <div class="mb-3">
                          <label class="form-label">Notizen</label>
                          <textarea name="notes" class="form-control"><?=htmlspecialchars($at['notes']??'')?></textarea>
                        </div>
                        <div class="mb-3 form-check">
                          <input class="form-check-input" type="checkbox" name="needs_followup" id="nfEdit<?=$at['id']?>"
                                 <?=$at['needs_followup']?'checked':''?>>
                          <label class="form-check-label" for="nfEdit<?=$at['id']?>">Benötigt Nachuntersuchung?</label>
                        </div>
                        <?php if(!empty($at['image_path'])):?>
                          <p>Aktuelles Bild:<br>
                            <img src="../<?=htmlspecialchars($at['image_path'])?>" alt="Attest" style="max-width:100px;">
                          </p>
                        <?php endif;?>
                        <div class="mb-3">
                          <label class="form-label">Neues Bild (optional)</label>
                          <input type="file" name="attest_image_edit" class="form-control" accept="image/*">
                        </div>
                        <?php if(!empty($at['signature_data'])):?>
                          <p>Aktuelle Unterschrift:<br>
                            <img src="data:image/png;base64,<?=htmlspecialchars($at['signature_data'])?>" 
                                 alt="Sign" style="max-width:150px;">
                          </p>
                        <?php endif;?>
                        <label class="form-label">Neue Unterschrift</label>
                        <div class="signature-pad" id="sigPadEdit<?= $at['id']?>"></div>
                        <input type="hidden" name="signature_data" id="sigDataEdit<?= $at['id']?>">
                        <button type="button" class="btn btn-sm btn-secondary mt-2"
                                onclick="clearSignature('sigPadEdit<?= $at['id']?>','sigDataEdit<?= $at['id']?>')">
                          Löschen
                        </button>
                      </div>
                      <div class="modal-footer">
                        <button type="submit" class="btn btn-primary"
                                onclick="captureSignature('sigPadEdit<?= $at['id']?>','sigDataEdit<?= $at['id']?>')">
                          Speichern
                        </button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                      </div>
                    </form>
                  </div>
                </div>
              </div>
              <!-- /Edit Modal -->
            <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
      <div class="card-footer">
        <a href="?export_csv=1" class="btn btn-sm btn-outline-info me-2">CSV-Export</a>
        <a href="?export_pdf=1" class="btn btn-sm btn-outline-primary">PDF-Export</a>
      </div>
    </div>
  </div>

  <div class="col-md-4">
    <!-- Neues Attest anlegen -->
    <div class="card mb-4">
      <div class="card-header">Neues Attest anlegen</div>
      <div class="card-body">
        <form method="post" enctype="multipart/form-data">
          <input type="hidden" name="create_attest" value="1">
          <div class="mb-3">
            <label class="form-label">Untersuchungsdatum</label>
            <input type="date" name="exam_date" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Attest-Typ</label>
            <select name="exam_type" class="form-select">
              <?php foreach($attestTypes as $typ): ?>
                <option value="<?=$typ?>"><?=$typ?></option>
              <?php endforeach; ?>
            </select>
            <small class="text-muted">Die Gültigkeit wird anhand des Typs vordefiniert (kann aber unten manuell geändert werden).</small>
          </div>
          <div class="mb-3">
            <label class="form-label">Gültig bis (optional)</label>
            <input type="date" name="valid_until" class="form-control" placeholder="leer für auto-Berechnung">
          </div>
          <div class="mb-3 position-relative">
            <label class="form-label">Arztpraxis (Suche)</label>
            <input type="hidden" name="doctor_id" id="docIdCreate">
            <input type="text" class="form-control" id="docSearchCreate" placeholder="Arztpraxis eingeben...">
            <div class="search-results" id="searchResCreate"></div>
          </div>
          <div class="mb-3">
            <label class="form-label">Arztpraxis (manuell)</label>
            <input type="text" name="doctor_name_manual" class="form-control">
          </div>
          <div class="mb-3 form-check">
            <input class="form-check-input" type="checkbox" name="needs_followup" id="nfCreate">
            <label class="form-check-label" for="nfCreate">Nachuntersuchung nötig?</label>
          </div>
          <div class="mb-3">
            <label class="form-label">Notizen</label>
            <textarea name="notes" class="form-control"></textarea>
          </div>
          <div class="mb-3">
            <label class="form-label">Bild (optional)</label>
            <input type="file" name="attest_image" class="form-control" accept="image/*">
          </div>
          <div class="mb-3">
            <label class="form-label">Unterschrift</label>
            <div class="signature-pad" id="sigPadCreate"></div>
            <input type="hidden" name="signature_data" id="sigDataCreate">
            <button type="button" class="btn btn-sm btn-secondary mt-2"
                    onclick="clearSignature('sigPadCreate','sigDataCreate')">Löschen</button>
          </div>
          <button type="submit" class="btn btn-success" onclick="captureSignature('sigPadCreate','sigDataCreate')">
            Speichern
          </button>
        </form>
      </div>
    </div>

    <!-- Neue Arztpraxis anlegen -->
    <div class="card mb-4">
      <div class="card-header">Neue Arztpraxis</div>
      <div class="card-body">
        <form method="post">
          <input type="hidden" name="new_doctor" value="1">
          <div class="mb-3">
            <label class="form-label">Name der Praxis</label>
            <input type="text" name="practice_name" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">PLZ</label>
            <input type="text" name="zip" class="form-control">
          </div>
          <div class="mb-3">
            <label class="form-label">Stadt</label>
            <input type="text" name="city" class="form-control">
          </div>
          <button type="submit" class="btn btn-info">Speichern</button>
        </form>
      </div>
    </div>
  </div>
</div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
let signaturePads={};

function initCanvas(containerId){
  const container=document.getElementById(containerId);
  const canvas=document.createElement('canvas');
  canvas.width=container.offsetWidth;
  canvas.height=container.offsetHeight;
  canvas.style.backgroundColor='#fff';
  container.innerHTML='';
  container.appendChild(canvas);
  const ctx=canvas.getContext('2d');
  let drawing=false;
  canvas.addEventListener('mousedown',(e)=>{drawing=true;ctx.beginPath();ctx.moveTo(e.offsetX,e.offsetY);});
  canvas.addEventListener('mousemove',(e)=>{if(drawing){ctx.lineTo(e.offsetX,e.offsetY);ctx.stroke();}});
  canvas.addEventListener('mouseup',()=>{drawing=false;});
  return canvas;
}

function captureSignature(canvasId, hiddenFieldId){
  if(!signaturePads[canvasId]) return;
  const dataUrl=signaturePads[canvasId].toDataURL('image/png');
  document.getElementById(hiddenFieldId).value=dataUrl.replace(/^data:image\/png;base64,/,'');
}

function clearSignature(canvasId, hiddenFieldId){
  if(!signaturePads[canvasId]) return;
  const ctx=signaturePads[canvasId].getContext('2d');
  ctx.clearRect(0,0,signaturePads[canvasId].width, signaturePads[canvasId].height);
  document.getElementById(hiddenFieldId).value='';
}

document.addEventListener('DOMContentLoaded',function(){
  signaturePads['sigPadCreate']=initCanvas('sigPadCreate');

  // Bei allen Edit-Modals erst beim Öffnen initCanvas
  document.querySelectorAll('[id^="editModal"]').forEach(modal=>{
    modal.addEventListener('shown.bs.modal',function(){
      let sigPadDiv=modal.querySelector('.signature-pad');
      if(sigPadDiv && !signaturePads[sigPadDiv.id]){
        signaturePads[sigPadDiv.id]=initCanvas(sigPadDiv.id);
      }
    });
  });

  // Arzt-Suche (Neu-Anlegen)
  const docSearchC=document.getElementById('docSearchCreate');
  const docResC=document.getElementById('searchResCreate');
  docSearchC.addEventListener('input',function(){
    const val=this.value.trim();
    if(val.length<2){ docResC.style.display='none';return;}
    fetch('attest.php?ajax_doctor_search='+encodeURIComponent(val))
    .then(r=>r.json())
    .then(d=>{
      if(d.length>0){
        let html='';
        d.forEach(it=>{
          html+=`<div data-doc-id="${it.id}">${it.practice_name} (${it.zip||''} ${it.city||''})</div>`;
        });
        docResC.innerHTML=html;
        docResC.style.display='block';
      } else {
        docResC.style.display='none';
      }
    });
  });
  docResC.addEventListener('click',(e)=>{
    if(e.target && e.target.hasAttribute('data-doc-id')){
      let did=e.target.getAttribute('data-doc-id');
      let txt=e.target.textContent;
      docSearchC.value=txt;
      document.getElementById('docIdCreate').value=did;
      docResC.style.display='none';
    }
  });

  // Arzt-Suche (Bearbeiten)
  document.querySelectorAll('.docSearchEdit').forEach(inp=>{
    inp.addEventListener('input',function(){
      const val=this.value.trim();
      let resDivId=this.getAttribute('data-result-div');
      let resultDiv=document.getElementById(resDivId);
      if(val.length<2){resultDiv.style.display='none';return;}
      fetch('attest.php?ajax_doctor_search='+encodeURIComponent(val))
        .then(r=>r.json())
        .then(dat=>{
          if(dat.length>0){
            let h='';
            dat.forEach(i=>{
              h+=`<div data-doc-id="${i.id}">${i.practice_name} (${i.zip||''} ${i.city||''})</div>`;
            });
            resultDiv.innerHTML=h;
            resultDiv.style.display='block';
          } else {
            resultDiv.style.display='none';
          }
        });
    });
  });
  document.querySelectorAll('.search-results').forEach(sr=>{
    sr.addEventListener('click',function(e){
      if(e.target && e.target.hasAttribute('data-doc-id')){
        let id=e.target.getAttribute('data-doc-id');
        let txt=e.target.textContent;
        let srId=sr.getAttribute('id');
        let base=srId.replace('searchResEdit','');
        let inputId='docSearchEdit'+base;
        let hiddenId='docIdEdit'+base;
        if(document.getElementById(inputId)) document.getElementById(inputId).value=txt;
        if(document.getElementById(hiddenId)) document.getElementById(hiddenId).value=id;
        sr.style.display='none';
      }
    });
  });
});
</script>
<?php $conn->close(); ?>
</body>
</html>
