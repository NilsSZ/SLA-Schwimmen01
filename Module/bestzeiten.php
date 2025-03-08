<?php
// bestzeiten.php
// Fehleranzeige aktivieren (nur für Entwicklungszwecke)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


// Start der Session
session_start();

// Überprüfen, ob der Benutzer angemeldet ist
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Datenbankverbindung einbinden
require_once('config.php');

// Benutzerdaten
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['name'] ?? 'Benutzer';

// Titel der Seite
$page_title = 'Persönliche Bestzeiten';

// Bestzeiten abrufen
$best_times = [];
$stmt = $conn->prepare("
    SELECT 
        t.swim_style_id, 
        t.distance, 
        MIN(t.time) as best_time, 
        ss.name as swim_style_name
    FROM times t
    INNER JOIN swim_styles ss ON t.swim_style_id = ss.id
    WHERE t.user_id = ?
    GROUP BY t.swim_style_id, t.distance
    ORDER BY ss.name, t.distance ASC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($swim_style_id, $distance, $best_time, $swim_style_name);

while ($stmt->fetch()) {
    $best_times[] = [
        'swim_style_id' => $swim_style_id,
        'distance' => $distance,
        'best_time' => $best_time,
        'swim_style_name' => $swim_style_name
    ];
}
$stmt->close();

// Funktion zum Konvertieren von MM:SS.MS in Sekunden
function timeToSeconds($time) {
    $time = str_replace(',', '.', $time);
    list($minutes, $rest) = explode(':', $time);
    list($seconds, $milliseconds) = explode('.', $rest);
    $total_seconds = ($minutes * 60) + $seconds + ($milliseconds / 100);
    return $total_seconds;
}

// Export als CSV
if (isset($_POST['export_csv'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=bestzeiten.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Schwimmart', 'Distanz', 'Beste Zeit']);

    foreach ($best_times as $row) {
        fputcsv($output, [
            $row['swim_style_name'],
            $row['distance'],
            $row['best_time']
        ]);
    }
    fclose($output);
    exit();
}

// Export als PDF
if (isset($_POST['export_pdf'])) {
    // Output Buffer leeren
    if (ob_get_length()) {
        ob_end_clean();
    }

    // PDF erstellen
    require_once('tcpdf/tcpdf.php');

    $pdf = new TCPDF('P', PDF_UNIT, 'A4', true, 'UTF-8', false);
    $pdf->SetCreator('SLA-Schwimmen');
    $pdf->SetAuthor($user_name);
    $pdf->SetTitle('Persönliche Bestzeiten');
    $pdf->SetSubject('Bestzeiten');
    $pdf->SetKeywords('Schwimmen, Bestzeiten, Statistiken');

    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(15, 20, 15);
    $pdf->SetFooterMargin(15);
    $pdf->SetFont('dejavusans', '', 10);

    $pdf->AddPage();

    $pdf->SetFont('dejavusans', 'B', 16);
    $pdf->Cell(0, 10, 'Persönliche Bestzeiten', 0, 1, 'C');
    $pdf->Ln(10);

    // Tabelle erstellen
    $tbl_header = '<style>
                    th {
                        background-color: #f2f2f2;
                        font-weight: bold;
                        text-align: center;
                    }
                    td {
                        text-align: center;
                    }
                   </style>
                   <table cellspacing="0" cellpadding="5" border="1">
                   <tr>
                       <th>Schwimmart</th>
                       <th>Distanz (m)</th>
                       <th>Beste Zeit</th>
                   </tr>';

    $tbl_footer = '</table>';
    $tbl = '';

    foreach ($best_times as $row) {
        $tbl .= '<tr>
                    <td>' . htmlspecialchars($row['swim_style_name']) . '</td>
                    <td>' . htmlspecialchars($row['distance']) . '</td>
                    <td>' . htmlspecialchars($row['best_time']) . '</td>
                 </tr>';
    }

    $pdf->writeHTML($tbl_header . $tbl . $tbl_footer, true, false, false, false, '');

    // Fußzeile hinzufügen
    $page_count = $pdf->getAliasNbPages();
    for ($i = 1; $i <= $page_count; $i++) {
        $pdf->setPage($i);
        $pdf->SetY(-15);
        $pdf->SetFont('dejavusans', '', 8);
        // Schwarzer Strich
        $pdf->SetDrawColor(0, 0, 0);
        $pdf->SetLineWidth(0.5);
        $pdf->Line($pdf->GetX(), $pdf->GetY(), $pdf->GetPageWidth() - $pdf->GetX(), $pdf->GetY());
        // Merksatz und Seitenzahl
        $pdf->Cell(0, 10, 'Dieses Dokument wurde mit SLA-Schwimmen generiert. Lizenznehmer: ' . $user_name . '.', 0, 0, 'L');
        $pdf->Cell(0, 10, 'Seite ' . $i . '/' . $page_count, 0, 0, 'R');
    }

    $pdf->Output('Bestzeiten.pdf', 'I');
    exit();
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Persönliche Bestzeiten</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.10.5/font/bootstrap-icons.min.css">
    <!-- Custom CSS -->
    <style>
        body {
            background-color: #f0f2f5;
            overflow-x: hidden;
        }
        /* Sidebar */
        .sidebar {
            height: 100vh;
            background-color: #343a40;
            color: #fff;
            transition: all 0.3s;
            position: fixed;
            width: 250px;
            left: 0;
            top: 0;
            z-index: 1000;
        }
        .sidebar .nav-link {
            color: #fff;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            background-color: #495057;
        }
        .sidebar.collapsed {
            width: 0;
            overflow: hidden;
        }
        /* Content */
        .content {
            margin-left: 250px;
            transition: all 0.3s;
        }
        .content.expanded {
            margin-left: 0;
        }
        /* Toggle Button */
        #sidebarToggle {
            display: none;
        }
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .sidebar {
                left: -250px;
            }
            .sidebar.show {
                left: 0;
            }
            .content {
                margin-left: 0;
            }
            #sidebarToggle {
                display: inline-block;
            }
            /* Overlay für die Sidebar auf Mobilgeräten */
            #sidebarOverlay {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                height: 100vh;
                width: 100vw;
                background-color: rgba(0, 0, 0, 0.5);
                z-index: 999;
            }
            #sidebarOverlay.show {
                display: block;
            }
        }
        /* Additional styles */
        .navbar {
            position: fixed;
            width: 100%;
            z-index: 1000;
        }
        .container-fluid {
            padding-top: 60px;
        }
        .chart-container {
            position: relative;
            height: 500px;
        }
    </style>
</head>
<body>
    <!-- Overlay für die Sidebar auf Mobilgeräten -->
    <div id="sidebarOverlay"></div>

    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <button type="button" id="sidebarToggle" class="btn btn-dark me-2">
                <i class="bi bi-list"></i>
            </button>
            <a class="navbar-brand" href="#">SLA-Schwimmen</a>
            <!-- Navigationslinks -->
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <!-- Profile and logout links -->
                    <li class="nav-item">
                        <a class="nav-link" href="profile.php"><i class="bi bi-person-circle"></i> Profil</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php"><i class="bi bi-box-arrow-right"></i> Abmelden</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <ul class="nav flex-column mt-3">
            <li class="nav-item">
                <a href="dashboard.php" class="nav-link"><i class="bi bi-speedometer2"></i> Übersicht</a>
            </li>
            <li class="nav-item">
                <a href="bestzeiten.php" class="nav-link active"><i class="bi bi-stopwatch"></i> Bestzeiten</a>
            </li>
            <li class="nav-item">
                <a href="daten_hinzufuegen.php" class="nav-link"><i class="bi bi-plus-circle"></i> Zeiten hinzufügen</a>
            </li>
            <!-- Weitere Links -->
        </ul>
    </div>

    <!-- Inhalt -->
    <div class="content" id="content">
        <div class="container-fluid mt-4">
            <!-- Inhalt der Seite -->
            <h2>Persönliche Bestzeiten</h2>

            <div class="mb-3">
                <form method="post" class="d-inline">
                    <button type="submit" name="export_pdf" class="btn btn-primary">Als PDF exportieren</button>
                </form>
                <form method="post" class="d-inline">
                    <button type="submit" name="export_csv" class="btn btn-success">Als CSV exportieren</button>
                </form>
            </div>

            <?php if (!empty($best_times)): ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Schwimmart</th>
                                <th>Distanz (m)</th>
                                <th>Beste Zeit</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($best_times as $row): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['swim_style_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['distance']); ?></td>
                                    <td><?php echo htmlspecialchars($row['best_time']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Diagramm -->
                <div class="chart-container mt-5">
                    <canvas id="bestzeitenChart"></canvas>
                </div>
            <?php else: ?>
                <p>Keine Bestzeiten gefunden.</p>
            <?php endif; ?>
        </div> <!-- Ende von container-fluid -->
    </div> <!-- Ende von content -->

    <!-- Bootstrap JS und optional Popper.js -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Chart.js einbinden -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Custom JS -->
    <script>
        // Sidebar ein- und ausklappen
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebar = document.getElementById('sidebar');
        const content = document.getElementById('content');
        const sidebarOverlay = document.getElementById('sidebarOverlay');

        sidebarToggle.addEventListener('click', function () {
            if (window.innerWidth < 768) {
                sidebar.classList.toggle('show');
                sidebarOverlay.classList.toggle('show');
            } else {
                sidebar.classList.toggle('collapsed');
                content.classList.toggle('expanded');
            }
        });

        // Klick auf Overlay schließt die Sidebar
        sidebarOverlay.addEventListener('click', function() {
            sidebar.classList.remove('show');
            sidebarOverlay.classList.remove('show');
        });

        <?php if (!empty($best_times)): ?>
            // Diagramm erstellen
            const ctx = document.getElementById('bestzeitenChart').getContext('2d');
            const labels = <?php echo json_encode(array_map(function($row) {
                return $row['swim_style_name'] . ' ' . $row['distance'] . 'm';
            }, $best_times)); ?>;
            const data = <?php echo json_encode(array_map(function($row) {
                // Konvertiere Zeit in Sekunden für das Diagramm
                $time_parts = explode(':', $row['best_time']);
                $minutes = intval($time_parts[0]);
                $seconds_parts = explode('.', $time_parts[1]);
                $seconds = intval($seconds_parts[0]);
                $milliseconds = intval($seconds_parts[1]);
                $total_seconds = ($minutes * 60) + $seconds + ($milliseconds / 100);
                return $total_seconds;
            }, $best_times)); ?>;

            function formatTime(seconds) {
                const minutes = Math.floor(seconds / 60);
                const remainingSeconds = seconds - minutes * 60;
                const wholeSeconds = Math.floor(remainingSeconds);
                const milliseconds = Math.round((remainingSeconds - wholeSeconds) * 100);
                return `${minutes.toString().padStart(2, '0')}:${wholeSeconds.toString().padStart(2, '0')}.${milliseconds.toString().padStart(2, '0')}`;
            }

            const bestzeitenChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Beste Zeit',
                        data: data,
                        backgroundColor: 'rgba(54, 162, 235, 0.6)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    indexAxis: 'y',
                    scales: {
                        x: {
                            ticks: {
                                callback: function(value) {
                                    return formatTime(value);
                                }
                            },
                            title: {
                                display: true,
                                text: 'Zeit'
                            }
                        },
                        y: {
                            title: {
                                display: true,
                                text: 'Schwimmart und Distanz'
                            }
                        }
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return 'Beste Zeit: ' + formatTime(context.parsed.x);
                                }
                            }
                        }
                    }
                }
            });
        <?php endif; ?>
    </script>
</body>
</html>
