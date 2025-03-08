<nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
    <div class="container-fluid">
        <a class="navbar-brand" href="#">SLA-Schwimmen</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Navigation umschalten">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav">
                <!-- Startseite -->
                <li class="nav-item">
                    <a class="nav-link" href="/sla-projekt/dashboard.php"><i class="bi bi-house"></i> Startseite</a>
                </li>
                <!-- Statistik & Analyse -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="statistikDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-bar-chart"></i> Statistik & Analyse
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="statistikDropdown">
                        <li><a class="dropdown-item" href="analyse.php">Analyse</a></li>
                        <li><a class="dropdown-item" href="bestzeiten.php">Bestzeiten</a></li>
                        <li><a class="dropdown-item" href="wettkampfstatistik.php">Wettkampfstatistik</a></li>
                        <li><a class="dropdown-item" href="import_export.php">Import / Export</a></li>
                        <li><a class="dropdown-item" href="durchschnittszeiten.php">Durchschnittszeiten</a></li>
                    </ul>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="organisationDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-gear"></i> Organisation
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="organisationDropdown">
                        <li><a class="dropdown-item" href="kalender.php">Kalender</a></li>
                        <li><a class="dropdown-item" href="lizenzen.php">Meine Lizenzen</a></li>
                        <li><a class="dropdown-item" href="online-shop.php">Module kaufen</a></li>
                        <li><a class="dropdown-item" href="settings.php">Account-Einstellungen</a></li>
                    </ul>
                </li>
                <!-- Meine Zeiten -->
                <li class="nav-item">
                    <a class="nav-link" href="meine_zeiten.php"><i class="bi bi-clock-history"></i> Meine Zeiten</a>
                </li>
                <!-- Wettkampf erstellen -->
                <li class="nav-item">
                    <a class="nav-link" href="wettkampf_erstellen.php"><i class="bi bi-trophy"></i> Wettkampf erstellen</a>
                </li>
                <!-- Livetiming -->
                <li class="nav-item">
                    <a class="nav-link" href="livetiming.php"><i class="bi bi-stopwatch"></i> Livetiming</a>
                </li>
                <!-- Training Kategorie -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="trainingDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-activity"></i> Training
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="trainingDropdown">
                        <li><a class="dropdown-item" href="trainingsplan.php">Trainingsplan</a></li>
                        <li><a class="dropdown-item" href="sprints_tests.php">Sprints &amp; Tests</a></li>
                        <!-- Weitere Module können hier hinzugefügt werden -->
                    </ul>
                </li>
                <!-- Neuer Menüpunkt: Daten hinzufügen -->
                <li class="nav-item">
                    <a class="nav-link" href="daten_hinzufuegen.php"><i class="bi bi-plus-circle"></i> Daten hinzufügen</a>
                </li>
            </ul>
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <span class="navbar-text">
                        Angemeldet als <?php echo htmlspecialchars($_SESSION['name'] ?? 'Benutzer', ENT_QUOTES, 'UTF-8'); ?>
                    </span>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="logout.php"><i class="bi bi-box-arrow-right"></i> Abmelden</a>
                </li>
            </ul>
        </div>
    </div>
</nav>
