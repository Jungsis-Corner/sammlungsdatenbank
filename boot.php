<?php
// boot.php – gemeinsame Session-Settings
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
