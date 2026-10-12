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
        echo '<p style="font-family:sans-serif;padding:1em">Ein Fehler ist aufgetreten. Details stehen im Log.</p>';
    }
});

/**
 * Datenbank- oder Statement-Fehler: Detail ins Log, Besucher bekommt nur $public (HTTP 500).
 */
function fail_db(string $public = 'Datenbankfehler.', ?string $detail = null) {
    if ($detail !== null && $detail !== '') {
        error_log('[' . basename($_SERVER['SCRIPT_NAME'] ?? '?') . '] ' . $detail);
    }
    if (!headers_sent()) { http_response_code(500); }
    exit(htmlspecialchars($public, ENT_QUOTES, 'UTF-8'));
}
