<?php
// ==================== require_login.php ====================
// Gemeinsame Session-Konfiguration (Cookie-Domain/-Pfad, session_start)
require_once __DIR__ . '/boot.php';

// ---- Hilfsfunktionen (werden von view.php / edit.php genutzt) ----
if (!function_exists('is_logged_in')) {
  function is_logged_in(): bool {
    return !empty($_SESSION['logged_in']) || !empty($_SESSION['loggedin']) || !empty($_SESSION['is_guest']);
  }
}
if (!function_exists('is_admin')) {
  function is_admin(): bool {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
  }
}
if (!function_exists('is_guest')) {
  function is_guest(): bool {
    // kompatibel zu login.php
    if (!empty($_SESSION['is_guest'])) return true;
    return (isset($_SESSION['role']) && $_SESSION['role'] === 'guest');
  }
}

// ---- Not-Logged-In → Redirect auf login.php (mit Rücksprungziel) ----
// Wichtig: NICHT in login.php selbst ausführen
$script = basename($_SERVER['SCRIPT_NAME'] ?? '');
if ($script !== 'login.php' && !is_logged_in()) {
    // aktuelles Ziel sicher ableiten
    $uri = $_SERVER['REQUEST_URI'] ?? '/sammlung/index.php';
    // niemals auf login.php zurückschicken
    if (stripos($uri, '/sammlung/login.php') !== false) {
        $uri = '/sammlung/index.php';
    }
    header('Location: /sammlung/login.php?to=' . rawurlencode($uri));
    exit;
}
