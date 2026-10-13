<?php
/**
 * Login-Drosselung (S3). Erwartet geladene config.php (DB_*, optional LOGIN_HASH_SALT).
 *
 * - Gesperrt wird pro IP-Hash ab LOGIN_MAX_IP oder pro Benutzername-Hash ab LOGIN_MAX_USER
 *   Fehlversuchen innerhalb von LOGIN_WINDOW_MIN Minuten.
 * - Gespeichert werden nur HMAC-Hashes, keine Klartext-IP und kein Klartext-Benutzername.
 * - Alle Funktionen sind fehlertolerant: Fehlt die Tabelle (Migration noch nicht eingespielt)
 *   oder die Datenbank, wird geloggt und NICHT gesperrt, damit niemand ausgesperrt wird.
 */

const LOGIN_WINDOW_MIN = 15;      // Zeitfenster in Minuten
const LOGIN_MAX_IP     = 5;       // Fehlversuche pro IP-Hash im Fenster
const LOGIN_MAX_USER   = 20;      // Fehlversuche pro Benutzername-Hash im Fenster
const LOGIN_KEEP_DAYS  = 30;      // Aufbewahrung der Einträge
const LOGIN_DELAY_US   = 300000;  // 300 ms Verzögerung nach jedem Fehlversuch

/** HMAC-SHA256 mit Salt aus config.php; Fallback: aus ADMIN_PASSWORD_HASH abgeleiteter Schlüssel. */
function login_hash(string $value): string {
    if (defined('LOGIN_HASH_SALT') && LOGIN_HASH_SALT !== '') {
        $key = (string)LOGIN_HASH_SALT;
    } else {
        $key = hash('sha256', 'login-throttle|' . (defined('ADMIN_PASSWORD_HASH') ? ADMIN_PASSWORD_HASH : ''));
    }
    return hash_hmac('sha256', $value, $key);
}

/** Hash der Client-IP. Nur REMOTE_ADDR, nie X-Forwarded-For (fälschbar). */
function login_ip_hash(): string {
    return login_hash((string)($_SERVER['REMOTE_ADDR'] ?? ''));
}

/** Hash des Benutzernamens (Groß-/Kleinschreibung egal, auf 100 Zeichen begrenzt). */
function login_user_hash(string $username): string {
    return login_hash(strtolower(substr(trim($username), 0, 100)));
}

/** Eigene DB-Verbindung; null bei Fehler (dann keine Drosselung). */
function login_throttle_open(): ?mysqli {
    try {
        $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($db->connect_errno) {
            error_log('login_throttle: Verbindung fehlgeschlagen');
            return null;
        }
        $db->set_charset('utf8mb4');
        return $db;
    } catch (\Throwable $e) {
        error_log('login_throttle: ' . $e->getMessage());
        return null;
    }
}

/** Löscht Einträge, die älter als LOGIN_KEEP_DAYS sind. */
function login_throttle_cleanup(?mysqli $db): void {
    if (!$db) return;
    try {
        $db->query('DELETE FROM `Login_Versuche` WHERE `Zeitpunkt` < (NOW() - INTERVAL ' . (int)LOGIN_KEEP_DAYS . ' DAY)');
    } catch (\Throwable $e) {
        error_log('login_throttle cleanup: ' . $e->getMessage());
    }
}

/** true, wenn für diese IP oder diesen Benutzernamen die Sperre gilt. */
function login_is_blocked(?mysqli $db, string $ipHash, string $userHash): bool {
    if (!$db) return false;
    try {
        $sql = 'SELECT COALESCE(SUM(`IP_Hash` = ?), 0), COALESCE(SUM(`User_Hash` = ?), 0)
                FROM `Login_Versuche`
                WHERE `Erfolg` = 0
                  AND `Zeitpunkt` > (NOW() - INTERVAL ' . (int)LOGIN_WINDOW_MIN . ' MINUTE)
                  AND (`IP_Hash` = ? OR `User_Hash` = ?)';
        $stmt = $db->prepare($sql);
        if (!$stmt) { error_log('login_throttle: prepare fehlgeschlagen'); return false; }
        $stmt->bind_param('ssss', $ipHash, $userHash, $ipHash, $userHash);
        $stmt->execute();
        $stmt->bind_result($byIp, $byUser);
        $stmt->fetch();
        $stmt->close();
        return ((int)$byIp >= LOGIN_MAX_IP) || ((int)$byUser >= LOGIN_MAX_USER);
    } catch (\Throwable $e) {
        error_log('login_throttle check: ' . $e->getMessage());
        return false;
    }
}

/** Protokolliert einen Versuch (Erfolg 1/0). */
function login_record(?mysqli $db, string $ipHash, string $userHash, bool $success): void {
    if (!$db) return;
    try {
        $stmt = $db->prepare('INSERT INTO `Login_Versuche` (`IP_Hash`, `User_Hash`, `Erfolg`) VALUES (?, ?, ?)');
        if (!$stmt) return;
        $ok = $success ? 1 : 0;
        $stmt->bind_param('ssi', $ipHash, $userHash, $ok);
        $stmt->execute();
        $stmt->close();
    } catch (\Throwable $e) {
        error_log('login_throttle record: ' . $e->getMessage());
    }
}

/** Setzt nach erfolgreicher Anmeldung die Fehlversuche dieser IP und dieses Benutzers zurück. */
function login_reset(?mysqli $db, string $ipHash, string $userHash): void {
    if (!$db) return;
    try {
        $stmt = $db->prepare('DELETE FROM `Login_Versuche` WHERE `Erfolg` = 0 AND (`IP_Hash` = ? OR `User_Hash` = ?)');
        if (!$stmt) return;
        $stmt->bind_param('ss', $ipHash, $userHash);
        $stmt->execute();
        $stmt->close();
    } catch (\Throwable $e) {
        error_log('login_throttle reset: ' . $e->getMessage());
    }
}
