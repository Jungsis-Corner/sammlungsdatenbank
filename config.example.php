<?php
// config.example.php
// Kopiere diese Datei zu "config.php" und trage deine eigenen Werte ein.
// config.php selbst wird per .gitignore NICHT versioniert.

// --- Datenbank-Zugangsdaten ---
if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('DB_USER')) define('DB_USER', 'dein_db_user');
if (!defined('DB_PASS')) define('DB_PASS', 'dein_db_passwort');
if (!defined('DB_NAME')) define('DB_NAME', 'deine_datenbank');

// --- Optional: IGDB-Import (Spielbeschreibungen automatisch nachladen) ---
// Kostenloser Twitch-Developer-Account nötig: https://dev.twitch.tv/console/apps
if (!defined('TWITCH_CLIENT_ID_CONST'))     define('TWITCH_CLIENT_ID_CONST',     '');
if (!defined('TWITCH_CLIENT_SECRET_CONST')) define('TWITCH_CLIENT_SECRET_CONST', '');

// --- Optional: automatische Übersetzung (DeepL) ---
// Kostenloser API-Key: https://www.deepl.com/de/pro-api
if (!defined('DEEPL_API_KEY_CONST')) define('DEEPL_API_KEY_CONST', '');

// --- Admin-Login ---
// Passwort-Hash selbst erzeugen (z.B. lokal per PHP CLI):
//   php -r "echo password_hash('DEIN-PASSWORT', PASSWORD_DEFAULT);"
// Ergebnis unten einfügen. NIEMALS das Klartext-Passwort hier eintragen.
if (!defined('ADMIN_USERNAME'))      define('ADMIN_USERNAME', 'admin');
if (!defined('ADMIN_PASSWORD_HASH')) define('ADMIN_PASSWORD_HASH', '');

// --- Host der App für absolute Weiterleitungen (z. B. in guest.php) ---
// Nur der Hostname, ohne https:// und ohne Pfad. Leer = relative Weiterleitung auf dem aktuellen Host.
if (!defined('APP_HOST')) define('APP_HOST', '');

// --- Session (boot.php) ---
// Cookie-Domain der Session. Leer = nur der eigene Host (richtig, wenn die App ohne Subdomain läuft).
// Für Jungsis Installation: '.jungsi.de', damit www.jungsi.de und jungsi.de dieselbe Session nutzen.
if (!defined('SESSION_COOKIE_DOMAIN')) define('SESSION_COOKIE_DOMAIN', '');
// Automatische Abmeldung von Admins nach so vielen Sekunden Inaktivität (Standard 28800 = 8 Stunden).
if (!defined('SESSION_ADMIN_IDLE'))    define('SESSION_ADMIN_IDLE', 28800);
// Nur für lokale Tests über http auf false setzen. Auf dem Server immer true lassen.
if (!defined('SESSION_COOKIE_SECURE')) define('SESSION_COOKIE_SECURE', true);

// --- Gastzugang ---
// Der Gast-Login gast/gast ist bewusst öffentlich (nur Lesezugriff, siehe README).
// Dürfen Gäste die Liste exportieren (CSV/Excel)? Standard: nein.
if (!defined('GUEST_EXPORT_ENABLED'))  define('GUEST_EXPORT_ENABLED', false);
// Zeilenlimit für den Gast-Export (nur wirksam, wenn GUEST_EXPORT_ENABLED true ist).
if (!defined('GUEST_EXPORT_MAX_ROWS')) define('GUEST_EXPORT_MAX_ROWS', 200);
// Felder, die Gäste in der Detailansicht (view.php) nicht sehen. Weitere Kandidaten:
// 'Seriennummer', 'Verkäufer', 'Einkaufsdatum'. Bezeichnungen wie in der Detailansicht.
if (!defined('GUEST_HIDDEN_FIELDS')) define('GUEST_HIDDEN_FIELDS', [
  'Standort', 'Box', 'Einkaufspreis', 'Wert', 'Getestet', 'Erstellt am', 'Geändert am',
]);

// --- Login-Drosselung: Salt für die Hashes von IP und Benutzername (Tabelle Login_Versuche) ---
// Zufallswert selbst erzeugen (lokal per PHP CLI) und unten eintragen:
//   php -r "echo bin2hex(random_bytes(32));"
// Ohne Eintrag wird ein Schlüssel aus ADMIN_PASSWORD_HASH abgeleitet (funktioniert, ist aber weniger sauber).
if (!defined('LOGIN_HASH_SALT')) define('LOGIN_HASH_SALT', '');

// --- Pfad zur Error-Logdatei (für logview.php) ---
// Falls leer/nicht gesetzt, nutzt logview.php ersatzweise php-error.log im Projektordner.
if (!defined('ERROR_LOG_PATH')) define('ERROR_LOG_PATH', '');

// --- Debug-Modus: true zeigt PHP-Fehler im Browser (nur lokal, nie auf dem Server!) ---
// Ohne Eintrag gilt false: Fehler stehen nur im Log (siehe inc_errors.php).
if (!defined('APP_DEBUG')) define('APP_DEBUG', false);

// --- Bilder (optional: ohne Eintrag gelten die Standardwerte aus inc_image.php) ---
// Der gespeicherte Wert in Sammlung.SammlungBild1 darf eine volle WordPress-URL oder ein relativer Pfad sein.
//   IMAGE_SOURCE    'wordpress' (Standard) | 'local' (Ordner medien/) | 'auto' (erst lokal, dann WordPress)
//   IMAGE_WP_BASE   URL-Pfad der WordPress-Uploads, z. B. '/blog/wp-content/uploads/' (oder volle URL)
//   IMAGE_WP_DIR    Dateisystem-Pfad dazu; Standard: DOCUMENT_ROOT + IMAGE_WP_BASE-Pfad '/blog/wp-content/uploads/'.
//                   Nur zum Prüfen, ob eine Datei vorhanden ist; ist der Ordner nicht lesbar, wird angenommen, dass sie da ist.
// if (!defined('IMAGE_SOURCE'))   define('IMAGE_SOURCE', 'wordpress');
// if (!defined('IMAGE_WP_BASE'))  define('IMAGE_WP_BASE', '/blog/wp-content/uploads/');
// if (!defined('IMAGE_WP_DIR'))   define('IMAGE_WP_DIR', '/pfad/zum/webroot/blog/wp-content/uploads/');

// --- App-Titel (zentral an EINER Stelle änderbar) ---
if (!defined('APP_TITLE'))          define('APP_TITLE', 'Meine Sammlung');
if (!defined('APP_TITLE_MUSEUM'))   define('APP_TITLE_MUSEUM', 'Meine Sammlung (Gast-Ansicht)');
if (!defined('APP_PWA_NAME'))       define('APP_PWA_NAME', 'Meine Sammlung');
if (!defined('APP_PWA_SHORT_NAME')) define('APP_PWA_SHORT_NAME', 'Sammlung');
?>
