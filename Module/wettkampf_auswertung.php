<?php
/********************************************************
 * wettkampf_auswertung.php
 * Erzeugt eine PDF-Auswertung in modernem Stil.
 * 
 * Spalten:
 *   WK-Nr | Schwimmart | Länge | Meldezeit | Endzeit | Verbesserung %
 * Alle Zeilen, in denen improvement>0 => hellgrün
 * improvement<0 => hellrot
 * 
 * Am Ende: "BenutzerXYZ hat sich bei X Starts Y Mal verbessert."
 ********************************************************/
ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(E_ALL);

session_start();
if(!isset($_SESSION['user_id'])){
    die("Bitte einloggen.");
}
$user_id   = $_SESSION['user_id'];
$user_name = $_SESSION['name'] ?? 'Benutzer';

require_once '../dbconnection.php';

function convertTimeToSeconds($time){
    $parts=explode(":",$time);
    if(count($parts)<2)return 0;
    $minutes=intval($parts[0]);
    $secParts=explode(",",$parts[1]);
    $seconds=intval($secParts[0]);
    $ms=(isset($secParts[1])) ? floatval("0.".$secParts[1]) :0;
    return $minutes*60 + $seconds +$ms;
}
function improvementPercent($entry, $end){
    $e=convertTimeToSeconds($entry);
    $d=convertTimeToSeconds($end);
    if($e<=0)return 0;
    return round((($e-$d)/$e)*100,2);
}

if(!isset($_GET['competition_id'])){
    die("Ungültiger Aufruf.");
}
$cid=intval($_GET['competition_id']);

// Wettkampf
$stmt=$conn->prepare("SELECT c.name, c.competition_date, c.user_id
                      FROM competitions c
                      WHERE c.id=?");
$stmt->bind_param("i",$cid);
$stmt->execute();
$comp=$stmt->get_result()->fetch_assoc();
$stmt->close();
if(!$comp){
    die("Wettkampf nicht gefunden.");
}
if($comp['user_id']!=$user_id){
    die("Keine Berechtigung.");
}
$compName=$comp['name'];
$compDate=$comp['competition_date'];

// Starts
$sql="SELECT cs.wk_nr, cs.distance, cs.entry_time, cs.swim_time, ss.name AS swim_style
      FROM competition_starts cs
      JOIN competitions co ON co.id=cs.competition_id
      JOIN swim_styles ss ON ss.id=cs.swim_style_id
      WHERE cs.competition_id=?
      ORDER BY cs.wk_nr ASC";
$stmt=$conn->prepare($sql);
$stmt->bind_param("i",$cid);
$stmt->execute();
$starts=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();

// PDF
require_once '../TCPDF-main/tcpdf.php'; // Pfad anpassen

class MyPDF extends TCPDF {
    public $footerNote="";
    // Override Footer
    public function Footer(){
        $this->SetY(-15);
        $this->SetFont('dejavusans','',9);
        $this->SetDrawColor(0,0,0);
        $this->SetLineWidth(0.3);
        $this->Line($this->GetX(),$this->GetY(), $this->getPageWidth()-$this->getMargins()['right'], $this->GetY());
        $this->Ln(2);
        $this->Cell(0,10, "Seite ".$this->getAliasNumPage()."/".$this->getAliasNbPages()." | ".$this->footerNote,0,0,"C");
    }
}

$pdf=new MyPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
$pdf->SetCreator("SLA-Schwimmen");
$pdf->SetAuthor($user_name);
$pdf->SetTitle("Auswertung ".$compName);
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(true);
$pdf->SetMargins(15,20,15);
$pdf->SetAutoPageBreak(true,15);

$pdf->footerNote="Dieses Dokument wurde mit SLA-Schwimmen generiert. Lizenznehmer: ".$user_name;

$pdf->AddPage();
$pdf->SetFont('dejavusans','B',16);
$pdf->Cell(0,0, $compName,0,1,'C');
$pdf->Ln(1);
$pdf->SetFont('dejavusans','',12);
$pdf->Cell(0,0, "Datum: ".date('d.m.Y',strtotime($compDate)),0,1,'C');
$pdf->Ln(10);

// Tabelle-Header
$pdf->SetFont('dejavusans','B',11);
$pdf->SetFillColor(220,220,220);
$pdf->Cell(15,8,"WK",1,0,'C',true);
$pdf->Cell(40,8,"Schwimmart",1,0,'C',true);
$pdf->Cell(20,8,"Länge",1,0,'C',true);
$pdf->Cell(30,8,"Meldezeit",1,0,'C',true);
$pdf->Cell(30,8,"Endzeit",1,0,'C',true);
$pdf->Cell(45,8,"Verbesserung (%)",1,1,'C',true);

$pdf->SetFont('dejavusans','',10);

$improvedCount=0;    // Anzahl der Starts mit Verbesserung
$completedCount=0;   // Anzahl der Starts mit Endzeit
foreach($starts as $st){
    $wk   = $st['wk_nr'];
    $style= $st['swim_style'];
    $dist = $st['distance']." m";
    $entry= $st['entry_time'] ?: '-';
    $end  = $st['swim_time']  ?: '-';
    
    $improvement= '';
    $rowColor=[255,255,255]; // weiß
    if($st['swim_time']){
        $completedCount++;
        $perc= improvementPercent($st['entry_time'],$st['swim_time']);
        if($perc>0){
            $improvement="+" . $perc . " %";
            $rowColor=[212,237,218]; // hellgrün
            $improvedCount++;
        } elseif($perc<0){
            $improvement= $perc." %";
            $rowColor=[248,215,218]; // hellrot
        } else {
            $improvement= "0 %";
        }
    }
    
    $pdf->SetFillColor($rowColor[0],$rowColor[1],$rowColor[2]);
    $pdf->Cell(15,7,$wk,1,0,'C',true);
    $pdf->Cell(40,7,$style,1,0,'C',true);
    $pdf->Cell(20,7,$dist,1,0,'C',true);
    $pdf->Cell(30,7,$entry,1,0,'C',true);
    $pdf->Cell(30,7,$end,1,0,'C',true);
    $pdf->Cell(45,7,$improvement,1,1,'C',true);
}

// Abschließende Info
$pdf->Ln(5);
$pdf->SetFont('dejavusans','',11);
$pdf->MultiCell(0,6,
    $user_name." hat sich bei ".$completedCount." Starts ".$improvedCount." mal verbessert.",
    0,'C',false,1);

$pdf->Output("Auswertung_".$compName.".pdf","I");
exit;
