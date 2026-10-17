<?php
// 404.php – Fehlerseite für unbekannte Pfade (per ErrorDocument in .htaccess).
// Bewusst ohne Login und ohne Datenbank: auch Besucher ohne Session sehen sie.
require_once __DIR__ . '/inc_errors.php';

if (is_file(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
}
error_page(404, 'Seite nicht gefunden', 'Diese Adresse gibt es nicht (mehr). Vielleicht hat sich der Link geändert oder ein Tippfehler eingeschlichen.');
