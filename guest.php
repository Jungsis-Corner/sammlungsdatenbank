<?php
// guest.php
// Gemeinsame Session-Konfiguration (Cookie-Domain/-Pfad, session_start)
require_once __DIR__ . '/boot.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/inc_security.php';
require_once __DIR__ . '/inc_guest.php';

// --- base64url-decode ---
function b64url_decode(string $s): string {
    $pad = strlen($s) % 4;
    if ($pad) $s .= str_repeat('=', 4 - $pad);
    $out = base64_decode(strtr($s, '-_', '+/'), true);
    return $out === false ? '' : $out;
}

$to = $_GET['to'] ?? '';
$decoded = b64url_decode($to);

// Falls du deine App unter /sammlung/ betreibst, Basis hart verdrahten:
$base = '/sammlung/';
if ($decoded !== '' && $decoded[0] !== '/') {
    $decoded = $base . ltrim($decoded, '/');
}

// Sicherheit: nur lokale Ziele unterhalb von /sammlung/ erlauben
if (safe_local_path($decoded, '') === '') {
    http_response_code(400);
    exit('Ungültiger Link.');
}

// --- Gast-Session starten (zählt nur bei einer neuen Gast-Session) ---
start_guest_session('guest_link');

// --- Weiterleitung: mit APP_HOST absolut, sonst relativ zum aktuellen Host ---
if (defined('APP_HOST') && APP_HOST !== '') {
    $location = 'https://' . APP_HOST . $decoded;
} else {
    $location = $decoded;
}

header('Location: ' . $location, true, 302);
exit;
