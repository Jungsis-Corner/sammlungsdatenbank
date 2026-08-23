<?php
// go.php – numerischer Code -> Ziel-URL
require __DIR__ . '/boot.php'; // falls vorhanden (Session-Cookies etc.)

header('X-Content-Type-Options: nosniff');

$code = $_GET['c'] ?? '';
if (!preg_match('/^\d{6,12}$/', $code)) { // 6-12 Ziffern zulassen
  http_response_code(400);
  exit('Ungültiger Code.');
}

// --- Mapping: Code -> Ziel (relativer Pfad innerhalb deiner App) ---
// Beispiel: Mega-Drive-Kategorie (ID 51)
$MAP = [
  '10005123' => '/sammlung/index.php?filter=51&sort=Bezeichnung',
  // weitere Codes hier ergänzen …
];

// Unbekannter Code?
if (!isset($MAP[$code])) {
  http_response_code(404);
  exit('Code nicht gefunden.');
}

// Gast-Autologin via login.php und Weiterleitung zum Ziel
$target = $MAP[$code];
$login  = '/sammlung/login.php?autologin=gast&to=' . rawurlencode($target);

header('Location: ' . $login, true, 302);
exit;
