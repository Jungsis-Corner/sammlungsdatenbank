<?php
require __DIR__ . '/require_login.php';
if (!is_admin()) { http_response_code(403); exit('Nur für Admins.'); }
require_once __DIR__ . '/config.php';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Einstellungen</title>
    <style>
        body { font-family: Arial; font-size: 10pt; padding: 20px; }
        h1 { text-align: center; color: #333; margin-bottom: 20px; }
        .button-center { text-align: center; margin-bottom: 20px; }
        .button-center a {
            display: inline-block;
            background: #007bff;
            color: #fff;
            padding: 8px 16px;
            text-decoration: none;
            border-radius: 4px;
            font-size: 10pt;
        }
        .button-center a:hover { background: #0056b3; }
        .nav { margin: 20px 0; text-align: center; }
        .nav a {
            display: inline-block;
            background: #007bff;
            color: #fff;
            padding: 8px 16px;
            margin: 0 10px;
            text-decoration: none;
            border-radius: 4px;
            font-size: 10pt;
        }
        .nav a:hover { background: #0056b3; }
    </style>
</head>
<body>
    <h1>Einstellungen</h1>
    <div class="button-center">
        <a href="index.php">← Zurück zur Sammlung</a>
    </div>
    <div class="nav">
        <a href="manage_hersteller.php">Hersteller verwalten</a>
        <a href="manage_publisher.php">Publisher verwalten</a>
        <a href="manage_verkaeufer.php">Verkäufer verwalten</a>
        <a href="manage_datentraeger.php">Datenträger verwalten</a>
        <a href="manage_kategorie.php">Kategorien verwalten</a>
        <a href="manage_material.php">Material verwalten</a>
        <a href="manage_standort.php">Standorte verwalten</a>
        <a href="manage_verpackung.php">Verpackung verwalten</a>
        <a href="manage_zustand.php">Zustände verwalten</a>
    </div>
</body>
</html>
