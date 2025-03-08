<?php
require __DIR__ . '/vendor/autoload.php';

use TCPDF;

// Neue PDF-Instanz erstellen
$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);

// Dokumentinformationen
$pdf->SetCreator('SLA-Schwimmen');
$pdf->SetAuthor('SLA-Schwimmen');
$pdf->SetTitle('Rechnung');
$pdf->SetSubject('Rechnung für Ihren Kauf');

// Standardkopf- und Fußzeilen deaktivieren
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

// Seite hinzufügen
$pdf->AddPage();

// Kopfbereich der Rechnung
$pdf->SetFont('dejavusans', '', 12);
$htmlHeader = <<<EOD
<h1 style="color: #007bff;">SLA-Schwimmen - Rechnung</h1>
<p><strong>Von:</strong> SLA-Schwimmen<br>
123 Schwimmstraße<br>
Berlin, Deutschland<br>
E-Mail: info@sla-schwimmen.de</p>
<hr>
<p><strong>An:</strong> Max Mustermann<br>
456 Kundenstraße<br>
Hamburg, Deutschland</p>
EOD;
$pdf->writeHTML($htmlHeader, true, false, true, false, '');

// Artikel-Tabelle
$htmlTable = <<<EOD
<table border="1" cellpadding="5">
    <thead>
        <tr>
            <th><strong>Artikel</strong></th>
            <th><strong>Beschreibung</strong></th>
            <th><strong>Menge</strong></th>
            <th><strong>Einzelpreis</strong></th>
            <th><strong>Gesamt</strong></th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>Diagrammmodul</td>
            <td>Das beste Diagrammmodul</td>
            <td>1</td>
            <td>2,50 €</td>
            <td>2,50 €</td>
        </tr>
        <tr>
            <td>Support-Modul</td>
            <td>Zusätzlicher Support</td>
            <td>2</td>
            <td>5,00 €</td>
            <td>10,00 €</td>
        </tr>
    </tbody>
</table>
EOD;
$pdf->writeHTML($htmlTable, true, false, true, false, '');

// Summenbereich
$pdf->Ln(5); // Zeilenumbruch
$htmlFooter = <<<EOD
<p><strong>Zwischensumme:</strong> 12,50 €<br>
<strong>Steuern (19%):</strong> 2,38 €<br>
<strong>Gesamtbetrag:</strong> 14,88 €</p>
<hr>
<p>Vielen Dank für Ihren Kauf bei SLA-Schwimmen!</p>
EOD;
$pdf->writeHTML($htmlFooter, true, false, true, false, '');

// PDF speichern oder ausgeben
$pdfOutput = __DIR__ . '/rechnungen/invoice.pdf';
$pdf->Output($pdfOutput, 'F'); // 'F' speichert die Datei auf dem Server

echo "Rechnung erfolgreich erstellt: {$pdfOutput}";
?>
