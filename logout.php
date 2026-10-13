<?php
require_once __DIR__ . '/inc_errors.php';
// Gleiche Cookie-Parameter wie die App (boot.php), damit das Cookie wirklich gelöscht wird
require_once __DIR__ . '/boot.php';
$_SESSION = [];
if (ini_get('session.use_cookies')) {
  $params = session_get_cookie_params();
  setcookie(session_name(), '', time()-42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}
session_destroy();
header('Location: /sammlung/login.php');
exit;
