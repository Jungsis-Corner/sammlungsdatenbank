<?php
/**
 * Gastzugang (S4): gemeinsame Gast-Session, Zähler und Konfigurationswerte.
 * Erwartet eine laufende Session (boot.php) und geladene config.php.
 *
 * Optionale Konstanten in config.php (Standardwerte stehen hier):
 *   GUEST_EXPORT_ENABLED   false   Gäste dürfen exportieren (E1)
 *   GUEST_EXPORT_MAX_ROWS  200     Zeilenlimit für den Gast-Export
 *   GUEST_HIDDEN_FIELDS    Liste   für Gäste ausgeblendete Felder in view.php (E2)
 */

/** Felder, die Gäste in view.php nicht sehen (Bezeichnungen wie in tr_meta()). */
function guest_hidden_fields(): array {
    if (defined('GUEST_HIDDEN_FIELDS') && is_array(GUEST_HIDDEN_FIELDS)) {
        return GUEST_HIDDEN_FIELDS;
    }
    return ['Standort', 'Box', 'Einkaufspreis', 'Wert', 'Getestet', 'Erstellt am', 'Geändert am'];
}

/** Dürfen Gäste exportieren? Standard: nein. */
function guest_export_allowed(): bool {
    return defined('GUEST_EXPORT_ENABLED') && GUEST_EXPORT_ENABLED === true;
}

/** Zeilenlimit für den Gast-Export. */
function guest_export_max_rows(): int {
    return (defined('GUEST_EXPORT_MAX_ROWS') && (int)GUEST_EXPORT_MAX_ROWS > 0)
        ? (int)GUEST_EXPORT_MAX_ROWS : 200;
}

/**
 * Gast-Login protokollieren (Statistik-Seite). Darf den Login nie blockieren,
 * daher robust und still.
 */
function log_guest_login(string $quelle): void {
    try {
        $conn = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if (!$conn || $conn->connect_errno) return;
        $stmt = $conn->prepare("INSERT INTO Login_Log (Quelle) VALUES (?)");
        if ($stmt) {
            $stmt->bind_param('s', $quelle);
            $stmt->execute();
            $stmt->close();
        }
        $conn->close();
    } catch (\Throwable $e) {
        // bewusst ignorieren
    }
}

/**
 * Startet eine Gast-Session. Gezählt wird nur, wenn die Session vorher noch kein
 * Gast war (neue Gast-Session). Eine Admin-Session wird nie zum Gast herabgestuft.
 * Gibt true zurück, wenn jetzt eine Gast-Session besteht.
 */
function start_guest_session(string $quelle): bool {
    if (($_SESSION['role'] ?? '') === 'admin') {
        return false;
    }
    $neu = empty($_SESSION['is_guest']);

    session_regenerate_id(true);
    // neu + alt-kompatible Flags
    $_SESSION['logged_in']    = true;
    $_SESSION['loggedin']     = true;   // alt
    $_SESSION['user_id']      = 0;
    $_SESSION['username']     = 'Gast';
    $_SESSION['role']         = 'guest';
    $_SESSION['is_guest']     = true;
    $_SESSION['login_method'] = 'guest';

    if ($neu) {
        log_guest_login($quelle);
    }
    return true;
}
