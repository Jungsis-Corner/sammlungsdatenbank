<?php
/**
 * Zentrale Fehlerbehandlung: alles ins Log, nichts mit internen Details an Besucher.
 *
 * Als allererstes in jeder Seite einbinden (vor require_login.php / config.php).
 * Das Log-Ziel steuert die .user.ini des Servers (error_log); hier wird es nicht gesetzt.
 *
 * Debug-Modus: in config.php `define('APP_DEBUG', true);` zeigt Fehler wieder im Browser.
 * Auf dem Server bleibt APP_DEBUG aus bzw. ungesetzt.
 */
if (defined('INC_ERRORS_LOADED')) { return; }
define('INC_ERRORS_LOADED', true);

error_reporting(E_ALL);
ini_set('log_errors', '1');
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');

/**
 * Gestaltete Fehlerseite (HTML als String). Ohne Datenbank, ohne Session, ohne Icons: funktioniert auch mitten im Fehlerfall.
 * Nur feste Texte übergeben, nie Fehlerdetails.
 */
function error_page_html(int $status, string $title, string $text, string $linkHref = '/sammlung/index.php', string $linkText = 'Zur Sammlung'): string {
    $e = fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
    $appTitle = (defined('APP_TITLE') && APP_TITLE !== '') ? (string)APP_TITLE : 'Sammlung';
    return '<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex">
<title>' . $e($title) . ' – ' . $e($appTitle) . '</title>
<script src="/sammlung/assets/theme-toggle.js?v=6"></script>
<link rel="stylesheet" href="/sammlung/assets/seite.css?v=1">
<link rel="stylesheet" href="/sammlung/assets/base.css?v=3">
</head>
<body class="seite">
<a class="skip-link" href="#inhalt">Zum Inhalt springen</a>
<main id="inhalt">
  <div class="karte">
    <p class="code">Fehler ' . $status . '</p>
    <h1>' . $e($title) . '</h1>
    <p>' . $e($text) . '</p>
    <div class="aktionen"><a class="btn save" href="' . $e($linkHref) . '">' . $e($linkText) . '</a></div>
  </div>
</main>
</body>
</html>';
}

/** Setzt den Statuscode, gibt die Fehlerseite aus und beendet die Anfrage. */
function error_page(int $status, string $title, string $text, string $linkHref = '/sammlung/index.php', string $linkText = 'Zur Sammlung'): void {
    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: text/html; charset=UTF-8');
        header('Cache-Control: no-store');
        header('X-Robots-Tag: noindex');
    }
    echo error_page_html($status, $title, $text, $linkHref, $linkText);
    exit;
}

/** APP_DEBUG steht erst nach dem Laden von config.php fest, daher erst bei Bedarf prüfen. */
function app_debug(): bool {
    return defined('APP_DEBUG') && APP_DEBUG === true;
}

// Warnungen/Hinweise: im Debug-Modus zusätzlich anzeigen, sonst nur loggen
set_error_handler(function (int $no, string $str, string $file, int $line): bool {
    if (app_debug()) { ini_set('display_errors', '1'); }
    return false;   // normale PHP-Behandlung (Logging) weiterlaufen lassen
});

// Fatale Fehler: Besucher sieht nur eine allgemeine Meldung, Details stehen im Log
register_shutdown_function(function (): void {
    $e = error_get_last();
    if (!$e || !($e['type'] & (E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR))) return;
    if (!headers_sent()) { http_response_code(500); }
    if (app_debug()) {
        echo "<pre style='background:#fee;border:1px solid #f00;padding:10px;white-space:pre-wrap'>";
        echo "🚨 PHP-Fatal in " . htmlspecialchars(basename($e['file'])) . " Zeile " . (int)$e['line'] . ":\n";
        echo htmlspecialchars($e['message'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        echo "</pre>";
    } else {
        if (ob_get_level() > 0 && !headers_sent()) { @ob_end_clean(); }   // halbfertige Seite verwerfen
        echo error_page_html(500, 'Ein Fehler ist aufgetreten', 'Die Seite konnte nicht angezeigt werden. Die Einzelheiten stehen im Log. Bitte versuche es später noch einmal.');
    }
});

/**
 * Datenbank- oder Statement-Fehler: Detail ins Log, Besucher bekommt nur $public (HTTP 500).
 */
function fail_db(string $public = 'Datenbankfehler.', ?string $detail = null) {
    if ($detail !== null && $detail !== '') {
        error_log('[' . basename($_SERVER['SCRIPT_NAME'] ?? '?') . '] ' . $detail);
    }
    error_page(500, 'Ein Fehler ist aufgetreten', $public);
}
