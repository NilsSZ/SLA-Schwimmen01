<?php
/********************************************************
 * UPLOAD MODULE PDF
 * PDF-Dateien mit weiteren Informationen zum Modul hochladen
 ********************************************************/

error_reporting(E_ALL);
ini_set('display_errors', 1);
ob_start();
session_start();

// DB-Verbindung einbinden
require_once 'dbconnection.php';

// Prüfen, ob Modul-ID und Datei übergeben wurden
if (!isset($_POST['module_id']) || !isset($_FILES['module_pdf'])) {
    die("Fehler: Kein Modul oder keine Datei angegeben.");
}

$module_id = (int) $_POST['module_id'];

if ($_FILES['module_pdf']['error'] !== UPLOAD_ERR_OK) {
    die("Fehler beim Hochladen der Datei.");
}

$fn  = $_FILES['module_pdf']['name'];
$tmp = $_FILES['module_pdf']['tmp_name'];
$ext = strtolower(pathinfo($fn, PATHINFO_EXTENSION));

if ($ext !== 'pdf') {
    die("Nur PDF-Dateien sind erlaubt.");
}

$uploadDir = __DIR__ . '/module_pdfs';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

$newFilename = 'module_' . $module_id . '_' . time() . '.pdf';
$dest = $uploadDir . '/' . $newFilename;

if (move_uploaded_file($tmp, $dest)) {
    // Aktualisiere die Spalte info_pdf in der Tabelle modules
    $relativePath = 'module_pdfs/' . $newFilename;
    $stmt = $conn->prepare("UPDATE modules SET info_pdf = ? WHERE id = ?");
    $stmt->bind_param("si", $relativePath, $module_id);
    $stmt->execute();
    $stmt->close();
    
    header("Location: produkt_details.php?id=" . $module_id);
    exit;
} else {
    die("Fehler beim Verschieben der Datei.");
}

ob_end_flush();
?>
