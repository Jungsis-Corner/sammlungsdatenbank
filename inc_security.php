<?php
/**
 * Gemeinsame Sicherheits-Helfer: CSRF-Token und Prüfung von Rücksprung-URLs.
 * Setzt eine laufende Session voraus (über require_login.php / boot.php).
 */

/** Liefert das CSRF-Token der Session (legt es bei Bedarf an). */
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return (string)$_SESSION['csrf'];
}

/** Hidden-Feld mit dem CSRF-Token für Formulare. */
function csrf_field(): string {
    return '<input type="hidden" name="csrf" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

/** Bricht ab, wenn die Anfrage kein POST mit gültigem CSRF-Token ist. */
function require_post_csrf(): void {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        http_response_code(405);
        header('Allow: POST');
        exit('Nur per POST erlaubt.');
    }
    if (!hash_equals($_SESSION['csrf'] ?? '', (string)($_POST['csrf'] ?? ''))) {
        http_response_code(400);
        exit('Ungültiges Formular (CSRF).');
    }
}

/**
 * Lässt für Rücksprünge nur relative App-Ziele zu (Dateiname.php mit optionaler Query).
 * Alles andere (javascript:, https://, //host, Backslash, Steuerzeichen) ergibt $fallback.
 */
function safe_back_url(?string $url, string $fallback): string {
    $url = (string)$url;
    if ($url !== '' && preg_match('~^[A-Za-z0-9_\-]+\.php(\?[^\\\\\x00-\x1F]*)?\z~', $url)) {
        return $url;
    }
    return $fallback;
}

/**
 * Whitelist für Lookup-Tabellen (Tabelle => Namensspalte), damit Tabellen-/Spaltennamen
 * nie ungeprüft in SQL landen. Liefert beide Namen in Backticks, z. B. ['`Material`', '`Material`'].
 * Bei unbekannten Namen wird abgebrochen (Programmierfehler, nicht Benutzereingabe).
 */
function lookup_ident(string $table, string $field): array {
    static $allowed = [
        'Kategorie'    => 'Kategorie',
        'Zustand'      => 'Zustand',
        'Verpackung'   => 'Verpackung',
        'Material'     => 'Material',
        'Standort'     => 'Standort',
        'Hersteller'   => 'Hersteller',
        'Publisher'    => 'Publisher',
        'Verkäufer'    => 'Verkäufer',
        'Datentraeger' => 'Datentrager',   // Spalte heißt in der DB ohne "ä"
    ];
    if (!isset($allowed[$table]) || $allowed[$table] !== $field) {
        if (function_exists('fail_db')) {
            fail_db('Interner Fehler.', 'Unerlaubter Lookup: ' . $table . '.' . $field);
        }
        http_response_code(500);
        exit('Interner Fehler.');
    }
    return ['`' . $table . '`', '`' . $field . '`'];
}
