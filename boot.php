<?php
// boot.php – zentrale Session-Einstellungen (Cookie-Domain/-Pfad, session_start, Admin-Timeout)
// Wird von login.php, require_login.php, guest.php, go.php, logout.php und museum/start.php genutzt.
//
// Konfiguration (alle optional, siehe config.example.php):
//   SESSION_COOKIE_DOMAIN  Cookie-Domain, z. B. '.jungsi.de'. Leer = nur der eigene Host.
//   SESSION_ADMIN_IDLE     Sekunden Inaktivität bis zur Abmeldung von Admins (Standard 28800 = 8 h)
//   SESSION_COOKIE_SECURE  false nur für lokale Tests über http (Standard true)
require_once __DIR__ . '/config.php';

/** Startet die Session mit den App-Einstellungen und prüft das Idle-Timeout für Admins. */
function boot_session(): void {
    $path   = '/sammlung/';   // App-Pfad
    $domain = defined('SESSION_COOKIE_DOMAIN') ? (string)SESSION_COOKIE_DOMAIN : '';
    $secure = !(defined('SESSION_COOKIE_SECURE') && SESSION_COOKIE_SECURE === false);
    $idle   = (defined('SESSION_ADMIN_IDLE') && (int)SESSION_ADMIN_IDLE > 0)
        ? (int)SESSION_ADMIN_IDLE : 28800;

    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.use_strict_mode', '1');     // keine vom Client vorgegebenen Session-IDs
        ini_set('session.use_only_cookies', '1');
        ini_set('session.gc_maxlifetime', (string)$idle);
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => $path,
            'domain'   => $domain,
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
        session_start();
    }

    // Idle-Timeout nur für Admins (Gäste und Museum bleiben unberührt)
    if (($_SESSION['role'] ?? '') === 'admin') {
        $now = time();
        if (isset($_SESSION['last_activity']) && ($now - (int)$_SESSION['last_activity']) > $idle) {
            $_SESSION = [];
            if (!headers_sent()) {
                session_regenerate_id(true);
            }
        } else {
            $_SESSION['last_activity'] = $now;
        }
    }
}

boot_session();
