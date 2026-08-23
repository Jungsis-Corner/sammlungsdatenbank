<?php
// boot.php – zentrale Session-Einstellungen (Cookie-Domain/-Pfad, session_start)
// Wird von login.php, require_login.php, guest.php und go.php gemeinsam genutzt.
$domain = '.jungsi.de';     // www.jungsi.de / jungsi.de
$path   = '/sammlung/';     // App-Pfad

session_set_cookie_params([
  'lifetime' => 0,
  'path'     => $path,
  'domain'   => $domain,
  'secure'   => true,
  'httponly' => true,
  'samesite' => 'Lax'
]);
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
