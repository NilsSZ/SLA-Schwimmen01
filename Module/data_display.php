<?php
// Module/data_display.php

// Sitzung für 1 Tag einstellen
session_set_cookie_params(86400);
session_start();

// Überprüfen, ob der Benutzer angemeldet ist
if (!isset($_SESSION['name']) || !isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// Datenbankverbindung einbinden
include '../dbconnection.php';

// Aktuelle Benutzer-ID abrufen
$current_user_id = $_SESSION['user_id'];

// Schwimmarten abrufen
$swim_styles = [];
$result = $conn->query("SELECT * FROM swim_styles");
while ($row = $result->fetch_assoc()) {
    $swim_styles[] = $row;
}
$result->close();

// Schließen der Datenbankverbindung
$conn->close();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Daten anzeigen - SLA Schwimmen</title>
    <!-- Einbindung von CSS -->
    <link rel="stylesheet" type="text/css" href="../style.css">
    <!-- Font Awesome für Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- JavaScript-Dateien -->
    <script src="../script.js" defer></script>
    <!-- jQuery für AJAX -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Chart.js für Diagramme -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- jsPDF und html2canvas für PDF-Generierung -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://html2canvas.hertzen.com/dist/html2canvas.min.js"></script>
</head>
<body>
    <?php include '../menu.php'; ?>
    <div class="main-content">
        <header>
            <h1>Daten anzeigen</h1>
        </header>
        <div class="data-display-container">
            <!-- Formular zur Auswahl von Schwimmart und Distanz -->
            <h2>Daten auswählen</h2>
            <form id="data-form">
                <div class="form-group">
                    <label>Schwimmart:</label>
                    <select name="swim_style" id="swim_style" required>
                        <option value="">-- Wählen Sie eine Schwimmart --</option>
                        <?php foreach ($swim_styles as $style): ?>
                            <option value="<?php echo $style['id']; ?>"><?php echo htmlspecialchars($style['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Distanz:</label>
                    <select name="distance" id="distance" required>
                        <option value="">-- Wählen Sie zuerst eine Schwimmart --</option>
                    </select>
                </div>
                <button type="submit">Diagramm anzeigen</button>
            </form>

            <!-- Bereich für das Diagramm -->
            <div id="chart-container" style="display: none;">
                <h2>Diagramm</h2>
                <canvas id="timeChart"></canvas>
                <button id="download-pdf" style="margin-top: 20px;">Als PDF herunterladen</button>
            </div>

            <!-- Bereich für die Tabelle -->
            <div id="table-container" style="display: none;">
                <h2>Daten</h2>
                <table id="data-table">
                    <thead>
                        <tr>
                            <th>Datum</th>
                            <th>Zeit</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Dynamisch gefüllte Daten -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- JavaScript für die dynamische Distanz-Auswahl und Diagramm-Generierung -->
    <script>
        $(document).ready(function() {
            // Distanzen laden basierend auf der Schwimmart
            $('#swim_style').change(function() {
                var swimStyleId = $(this).val();
                if (swimStyleId) {
                    $.ajax({
                        url: 'get_distances.php',
                        type: 'POST',
                        data: {swim_style_id: swimStyleId},
                        success: function(data) {
                            $('#distance').html(data);
                        }
                    });
                } else {
                    $('#distance').html('<option value="">-- Wählen Sie zuerst eine Schwimmart --</option>');
                }
            });

            // Formular-Submit
            $('#data-form').submit(function(e) {
                e.preventDefault();
                var swimStyleId = $('#swim_style').val();
                var distance = $('#distance').val();

                if (swimStyleId && distance) {
                    // Daten abrufen und Diagramm generieren
                    $.ajax({
                        url: 'get_times.php',
                        type: 'POST',
                        data: {
                            swim_style_id: swimStyleId,
                            distance: distance
                        },
                        dataType: 'json',
                        success: function(response) {
                            if (response.status == 'success') {
                                generateChart(response.data);
                                populateTable(response.data);
                                $('#chart-container').show();
                                $('#table-container').show();
                            } else {
                                alert('Keine Daten verfügbar.');
                            }
                        }
                    });
                }
            });
        });

        var chart;

        function generateChart(data) {
            // Daten für das Diagramm vorbereiten
            var labels = [];
            var times = [];

            data.forEach(function(item) {
                labels.push(item.date);
                times.push(convertTimeToSeconds(item.time));
            });

            // Vorhandenes Diagramm zerstören
            if (chart) {
                chart.destroy();
            }

            // Diagramm erstellen
            var ctx = document.getElementById('timeChart').getContext('2d');
            chart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Zeit in Sekunden',
                        data: times,
                        borderColor: '#004080',
                        backgroundColor: 'rgba(0, 64, 128, 0.1)',
                        fill: true,
                        tension: 0.1
                    }]
                },
                options: {
                    scales: {
                        x: {
                            title: {
                                display: true,
                                text: 'Datum'
                            }
                        },
                        y: {
                            title: {
                                display: true,
                                text: 'Zeit (Sekunden)'
                            }
                        }
                    }
                }
            });

            // PDF-Download-Button aktivieren
            $('#download-pdf').off('click').on('click', function() {
                downloadPDF(data);
            });
        }

        function populateTable(data) {
            var tbody = $('#data-table tbody');
            tbody.empty();

            data.forEach(function(item) {
                var row = '<tr><td>' + item.date + '</td><td>' + item.time + '</td></tr>';
                tbody.append(row);
            });
        }

        function convertTimeToSeconds(timeStr) {
            // Erwartetes Format: MM:SS,MS
            var parts = timeStr.split(':');
            var minutes = parseInt(parts[0]);
            var secondsParts = parts[1].split(',');
            var seconds = parseInt(secondsParts[0]);
            var milliseconds = parseInt(secondsParts[1]);

            var totalSeconds = minutes * 60 + seconds + milliseconds / 100;
            return totalSeconds.toFixed(2);
        }

        async function downloadPDF(data) {
            const { jsPDF } = window.jspdf;

            // Canvas in Bild umwandeln
            const canvas = document.getElementById('timeChart');
            const canvasImage = canvas.toDataURL('image/png', 1.0);

            // PDF erstellen
            const pdf = new jsPDF('l', 'mm', 'a4');
            const pdfWidth = pdf.internal.pageSize.getWidth();
            const pdfHeight = pdf.internal.pageSize.getHeight();

            // Titel hinzufügen
            pdf.setFontSize(20);
            pdf.text('Schwimmzeiten-Diagramm', pdfWidth / 2, 20, { align: 'center' });

            // Datum hinzufügen
            var today = new Date();
            var dateStr = today.getDate() + '.' + (today.getMonth() + 1) + '.' + today.getFullYear();
            pdf.setFontSize(12);
            pdf.text('Datum: ' + dateStr, 10, 30);

            // Diagramm hinzufügen
            pdf.addImage(canvasImage, 'PNG', 10, 40, pdfWidth - 20, (pdfHeight / 2) - 30);

            // Tabelle hinzufügen
            pdf.text('Daten:', 10, (pdfHeight / 2) + 20);
            var rowHeight = 8;
            var startY = (pdfHeight / 2) + 30;

            // Tabellenkopf
            pdf.setFontSize(10);
            pdf.setFillColor(200, 200, 200);
            pdf.rect(10, startY, pdfWidth - 20, rowHeight, 'F');
            pdf.text('Datum', 12, startY + 6);
            pdf.text('Zeit', pdfWidth / 2, startY + 6);

            // Tabelleninhalt
            startY += rowHeight;
            data.forEach(function(item) {
                pdf.rect(10, startY, pdfWidth - 20, rowHeight);
                pdf.text(item.date, 12, startY + 6);
                pdf.text(item.time, pdfWidth / 2, startY + 6);
                startY += rowHeight;
            });

            // Merksatz hinzufügen
            pdf.setFontSize(10);
            pdf.text('Dieses Dokument wurde mit SLA-Schwimmen generiert. Lizenznehmer: <?php echo htmlspecialchars($_SESSION["name"]); ?>', 10, pdfHeight - 10);

            // PDF speichern
            pdf.save('Schwimmzeiten.pdf');
        }
    </script>
</body>
</html>
