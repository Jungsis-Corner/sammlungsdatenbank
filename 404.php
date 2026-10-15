<?php
// 404.php – Fehlerseite für unbekannte Pfade (per ErrorDocument in .htaccess).
// Bewusst ohne Login und ohne Datenbank: auch Besucher ohne Session sehen sie.
require_once __DIR__ . '/inc_errors.php';
require_once __DIR__ . '/inc_icons.php';

http_response_code(404);
header('X-Robots-Tag: noindex');
header('Cache-Control: no-store');

if (is_file(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
}
$title = defined('APP_TITLE') ? (string)APP_TITLE : 'Sammlung';
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex">
<title>Seite nicht gefunden – <?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></title>
<script src="/sammlung/assets/theme-toggle.js?v=5"></script>
<link rel="stylesheet" href="/sammlung/assets/app.css?v=13">
<link rel="stylesheet" href="/sammlung/assets/base.css?v=3">
</head>
<body>
<a class="skip-link" href="#inhalt">Zum Inhalt springen</a>
  <main id="inhalt" style="max-width:560px; margin:8vh auto 0;">
    <h1>Seite nicht gefunden</h1>
    <p>Diese Adresse gibt es nicht (mehr). Vielleicht hat sich der Link geändert oder ein Tippfehler eingeschlichen.</p>
    <p><a class="btn btn-blue" href="/sammlung/index.php"><?= icon('back') ?> Zur Sammlung</a></p>
  </main>
</body>
</html>
