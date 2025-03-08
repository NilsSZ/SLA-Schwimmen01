<?php
// header.php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Überprüfen, ob der Benutzer angemeldet ist
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Benutzername abrufen
$user_name = $_SESSION['name'] ?? 'Benutzer';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title><?php echo $page_title ?? 'SLA-Schwimmen'; ?></title>
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
                <a href="dashboard.php" class="nav-link<?php if ($page == 'dashboard') echo ' active'; ?>"><i class="bi bi-speedometer2"></i> Übersicht</a>
            </li>
            <li class="nav-item">
                <a href="bestzeiten.php" class="nav-link<?php if ($page == 'bestzeiten') echo ' active'; ?>"><i class="bi bi-stopwatch"></i> Bestzeiten</a>
            </li>
            <li class="nav-item">
                <a href="daten_hinzufuegen.php" class="nav-link<?php if ($page == 'daten_hinzufuegen') echo ' active'; ?>"><i class="bi bi-plus-circle"></i> Zeiten hinzufügen</a>
            </li>
            <!-- Weitere Links -->
        </ul>
    </div>

    <!-- Inhalt -->
    <div class="content" id="content">
        <div class="container-fluid mt-4">
