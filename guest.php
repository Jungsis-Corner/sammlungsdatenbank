<?php
// guest.php
// --- Session Cookie-Parameter VOR session_start() setzen ---
$cookieParams = session_get_cookie_params();
// Passe Domain/Path an deine tatsächliche Live-Domain an:
$domain = 'www.jungsi.de';     // erlaubt www.jungsi.de und jungsi.de
$path   = '/sammlung/';              // oder '/sammlung/' wenn deine App ausschließlich dort lebt
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => $path,
    'domain'   => $domain,
    'secure'   => true,      // bei https
    'httponly' => true,
    'samesite' => 'Lax'
]);
session_start();

// --- base64url-decode ---
function b64url_decode(string $s): string {
    $pad = strlen($s) % 4;
    if ($pad) $s .= str_repeat('=', 4 - $pad);
    $out = base64_decode(strtr($s, '-_', '+/'), true);
    return $out === false ? '' : $out;
}

$to = $_GET['to'] ?? '';
$decoded = b64url_decode($to);

// Sicherheit: nur relative Ziele innerhalb der Site erlauben
if ($decoded === '' || strpos($decoded, '://') !== false) {
    http_response_code(400);
    exit('Ungültiger Link.');
}

// Falls du deine App unter /sammlung/ betreibst, Basis hart verdrahten:
$base = '/sammlung/';
if ($decoded[0] !== '/') {
    $decoded = $base . ltrim($decoded, '/');
}

// --- "Gast-Login" setzen: auf *deine* Auth-Keys achten! ---
session_regenerate_id(true);
$_SESSION['logged_in'] = true;      // <- viele Guards prüfen das
$_SESSION['user_id']   = 0;         // <- harmlose ID für Gast
$_SESSION['username']  = 'Gast';
$_SESSION['role']      = 'guest';
$_SESSION['is_guest']  = true;

// Optional: Merker, dass der Login via Gast kam (für UI)
$_SESSION['login_method'] = 'guest';

// --- Absolute Weiterleitung ---
$scheme = 'https';
$host   = 'www.jungsi.de';          // hier exakt die Domain benutzen, die auch im Browser öffnet
$location = $scheme . '://' . $host . $decoded;

header('Location: ' . $location, true, 302);
exit;
