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
    $sess = (string)($_SESSION['csrf'] ?? '');
    $post = (string)($_POST['csrf'] ?? '');
    // Leere Tokens nie akzeptieren (hash_equals('', '') wäre sonst true)
    if ($sess === '' || $post === '' || !hash_equals($sess, $post)) {
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
 * Schützt eine CSV-Zelle vor Formel-Injection (Excel/LibreOffice): Beginnt der Wert mit
 * =, +, -, @, Tab oder Wagenrücklauf, wird ein ' vorangestellt, damit er als Text gilt.
 */
function csv_safe_cell(string $v): string {
    if ($v !== '' && strpbrk($v[0], "=+-@\t\r") !== false) {
        return "'" . $v;
    }
    return $v;
}

/** Einmalige Meldung für die nächste Seite (statt ?msg= in der URL). $type: info|success|error */
function flash_set(string $text, string $type = 'info'): void {
    $_SESSION['flash'] = ['text' => $text, 'type' => in_array($type, ['info', 'success', 'error'], true) ? $type : 'info'];
}

/** Holt die einmalige Meldung und löscht sie. Liefert ['text' => …, 'type' => …] oder null. */
function flash_pull(): ?array {
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return (is_array($f) && isset($f['text'])) ? $f : null;
}

/**
 * Lässt nur lokale Pfade unterhalb von /sammlung/ zu (z. B. Login-Ziel).
 * Abgelehnt werden //, Backslash, ://, .. und Steuerzeichen, auch in URL-kodierter Form.
 * Alles andere ergibt $fallback.
 */
function safe_local_path(?string $p, string $fallback): string {
    $p = (string)$p;
    if ($p === '' || strpos($p, '/sammlung/') !== 0) {
        return $fallback;
    }
    foreach ([$p, rawurldecode($p)] as $v) {
        if (preg_match('~[\x00-\x1F\x7F\\\\]~', $v)
            || strpos($v, '//') !== false
            || strpos($v, '://') !== false
            || strpos($v, '..') !== false
            || strpos($v, '/sammlung/') !== 0) {
            return $fallback;
        }
    }
    return $p;
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
        'Box'          => 'Box',
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

/**
 * Verweise auf einen Lookup-Eintrag: Liste von [Tabelle, Spalte] (jeweils in Backticks), die dessen ID speichern.
 * Fest hinterlegt, nie aus Benutzereingaben. Tabellen ohne Eintrag haben (noch) keine Verweise.
 */
function lookup_refs(string $table): array {
    static $refs = [
        'Kategorie'    => [['Sammlung', 'Kategorie'], ['Einkauf', 'Kategorie']],
        'Zustand'      => [['Sammlung', 'Zustand']],
        'Verpackung'   => [['Sammlung', 'Verpackung']],
        'Material'     => [['Sammlung', 'Material']],
        'Standort'     => [['Sammlung', 'Standort']],
        'Hersteller'   => [['Sammlung', 'Hersteller']],
        'Publisher'    => [['Sammlung', 'Publisher']],
        'Verkäufer'    => [['Sammlung', 'Verkäufer'], ['Einkauf', 'Verkaeufer']],
        'Datentraeger' => [['Sammlung', 'Datentraeger']],
        'Box'          => [['Sammlung', 'Box']],
    ];
    $out = [];
    foreach ($refs[$table] ?? [] as [$t, $c]) { $out[] = ['`' . $t . '`', '`' . $c . '`']; }
    return $out;
}
