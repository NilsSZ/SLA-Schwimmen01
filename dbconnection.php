<?php
$servername = "db5016515798.hosting-data.io";
$username = "dbu2387607";
$password = "Schip11911pi";
$dbname = "dbs13406131";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Verbindung fehlgeschlagen: " . $conn->connect_error);
}
?>