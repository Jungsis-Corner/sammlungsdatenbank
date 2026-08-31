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
    <script src="/sammlung/assets/theme-toggle.js"></script>
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

        /* =========================================================
           DARK MODE
           ========================================================= */
        html[data-theme="dark"] body{
          filter: invert(1) hue-rotate(180deg);
          background: #fff;
        }
        html[data-theme="dark"] img,
        html[data-theme="dark"] video{
          filter: invert(1) hue-rotate(180deg);
        }
        .theme-toggle{
          background: #555 !important;
          border-color: #333 !important;
          color: #fff !important;
        }
        .theme-toggle-floating{
          position: fixed;
          bottom: 16px;
          right: 16px;
          z-index: 9999;
          border-radius: 50%;
          width: 44px;
          height: 44px;
          padding: 0;
          font-size: 20px;
          line-height: 42px;
          text-align: center;
          box-shadow: 0 2px 8px rgba(0,0,0,.25);
          border: 1px solid #333;
          cursor: pointer;
        }
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
