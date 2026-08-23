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
?>
