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

/**
 * Wartungsmodus: Liegt die Datei maintenance.flag im App-Ordner, antworten alle Seiten mit 503 und
 * einer Hinweisseite. Ausnahmen: login.php/logout.php (damit man sich anmelden kann) und angemeldete
 * Admins. Geprüft wird die serverseitige Session (role = admin), nie ein Cookie-Wert oder URL-Parameter.
 */
function boot_maintenance(): void {
    if (!is_file(__DIR__ . '/maintenance.flag')) {
        return;
    }
    $script = basename($_SERVER['SCRIPT_NAME'] ?? '');
    if (in_array($script, ['login.php', 'logout.php'], true)) {
        return;
    }
    if (($_SESSION['role'] ?? '') === 'admin' && !empty($_SESSION['logged_in'])) {
        return;
    }
    $title = defined('APP_TITLE') ? (string)APP_TITLE : 'Sammlung';
    if (!headers_sent()) {
        http_response_code(503);
        header('Retry-After: 600');
        header('Cache-Control: no-store');
        header('X-Robots-Tag: noindex');
        header('Content-Type: text/html; charset=UTF-8');
    }
    echo '<!DOCTYPE html><html lang="de"><head><meta charset="UTF-8">'
       . '<meta name="viewport" content="width=device-width, initial-scale=1">'
       . '<meta name="robots" content="noindex">'
       . '<title>Wartungsarbeiten – ' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</title>'
       . '<style>body{font-family:system-ui,Arial,sans-serif;max-width:520px;margin:12vh auto 0;padding:0 16px;'
       . 'background:#fff;color:#222}@media (prefers-color-scheme:dark){body{background:#121418;color:#e6e6e6}}</style>'
       . '</head><body><h1>Wartungsarbeiten</h1>'
       . '<p>Die Sammlung wird gerade aktualisiert und ist in Kürze wieder erreichbar. Bitte versuche es in ein paar Minuten noch einmal.</p>'
       . '</body></html>';
    exit;
}

boot_session();
boot_maintenance();
