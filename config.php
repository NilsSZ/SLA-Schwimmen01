<?php
// config.php

$servername = "db5016515798.hosting-data.io"; // oder die IP-Adresse Ihres Datenbankservers
$username = "dbu2387607"; // Ersetzen Sie dies mit Ihrem Datenbankbenutzername
$password = "Schip11911pi";     // Ersetzen Sie dies mit Ihrem Datenbankpasswort
$dbname = "dbs13406131";    // Ersetzen Sie dies mit Ihrem Datenbanknamen

// Erstellen der Verbindung
$conn = new mysqli($servername, $username, $password, $dbname);

// Überprüfen der Verbindung
if ($conn->connect_error) {
    die("Verbindung fehlgeschlagen: " . $conn->connect_error);
}
?>
