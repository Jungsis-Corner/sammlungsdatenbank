<?php
// offline.php – wird vom Service Worker angezeigt, wenn keine Verbindung besteht (sw.js, im Cache vorgehalten).
// Bewusst ohne Login, Datenbank und Skripte.
header('Cache-Control: no-cache');
?><!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex">
<title>Keine Verbindung – Sammlung</title>
<script src="/sammlung/assets/theme-toggle.js?v=6"></script>
<link rel="stylesheet" href="/sammlung/assets/seite.css?v=1">
<link rel="stylesheet" href="/sammlung/assets/base.css?v=3">
</head>
<body class="seite">
<main id="inhalt">
  <div class="karte">
    <h1>Keine Verbindung</h1>
    <p>Du bist gerade offline. Die Sammlung braucht eine Internetverbindung. Bitte versuche es erneut, sobald wieder eine besteht.</p>
    <div class="aktionen"><a class="btn save" href="/sammlung/index.php">Erneut versuchen</a></div>
  </div>
</main>
</body>
</html>
