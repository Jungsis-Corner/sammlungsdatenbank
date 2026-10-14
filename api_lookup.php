<?php
/**
 * api_lookup.php?t=<Schlüssel>&q=<Text>[&x=<ID>]  (Typeahead, nur lesend)
 *
 * Liefert höchstens 20 Treffer als JSON: {"items":[{"id":12,"label":"Atari"}, …]}
 *  - t: hersteller | publisher | verkaeufer | kategorie | material | standort | zustand | verpackung | datentraeger | box
 *       | sammlung (Einträge der Sammlung für "Gehört zu", nur Admin; x = ID, die ausgeschlossen wird)
 *  - q: Suchtext (höchstens 80 Zeichen); Treffer, die damit beginnen, stehen vor Treffern, die ihn nur enthalten.
 *       Ohne Text kommen die ersten 20 alphabetisch.
 * Tabellen und Spalten stammen ausschließlich aus der festen Zuordnung unten (lookup_ident), nie aus der Anfrage.
 * Nur für angemeldete Besucher; Gäste bekommen nicht, was ihnen die Seiten ausblenden (Standort, Box, Sammlung und die
 * Felder aus GUEST_HIDDEN_FIELDS). Es wird nichts geändert, deshalb GET ohne CSRF-Token.
 */
require_once __DIR__ . '/inc_errors.php';
require_once __DIR__ . '/boot.php';              // Session, Wartungsmodus (kein Redirect: für Skripte gibt es JSON-Fehler)
require_once __DIR__ . '/inc_security.php';
require_once __DIR__ . '/inc_filter.php';        // like_escape()
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/inc_guest.php';

function api_json(int $code, array $data): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$angemeldet = !empty($_SESSION['logged_in']) || !empty($_SESSION['loggedin']) || !empty($_SESSION['is_guest']);
$admin = (($_SESSION['role'] ?? '') === 'admin');
$gast  = !$admin && (!empty($_SESSION['is_guest']) || (($_SESSION['role'] ?? '') === 'guest'));
if (session_status() === PHP_SESSION_ACTIVE) { session_write_close(); }
if (!$angemeldet) { api_json(401, ['error' => 'Nicht angemeldet.']); }
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') { api_json(405, ['error' => 'Nur GET.']); }

// Schlüssel => [Tabelle, Spalte, Feldname für GUEST_HIDDEN_FIELDS (null = Gäste nie)]
$karte = [
    'hersteller'   => ['Hersteller',   'Hersteller',  ''],
    'publisher'    => ['Publisher',    'Publisher',   ''],
    'verkaeufer'   => ['Verkäufer',    'Verkäufer',   'Verkäufer'],
    'kategorie'    => ['Kategorie',    'Kategorie',   ''],
    'material'     => ['Material',     'Material',    ''],
    'zustand'      => ['Zustand',      'Zustand',     ''],
    'verpackung'   => ['Verpackung',   'Verpackung',  ''],
    'datentraeger' => ['Datentraeger', 'Datentrager', ''],
    'standort'     => ['Standort',     'Standort',    null],
    'box'          => ['Box',          'Box',         null],
];
$t = $_GET['t'] ?? '';
$q = $_GET['q'] ?? '';
if (!is_string($t) || !is_string($q)) { api_json(400, ['error' => 'Ungültige Anfrage.']); }
$q = trim(mb_substr($q, 0, 80));
$limit = 20;

if ($t === 'sammlung') {
    if (!$admin) { api_json(403, ['error' => 'Nur für Admins.']); }
    $x = isset($_GET['x']) && ctype_digit((string)$_GET['x']) ? (int)$_GET['x'] : 0;
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) { error_log('api_lookup: Verbindung fehlgeschlagen'); api_json(500, ['error' => 'Datenbankfehler.']); }
    $conn->set_charset('utf8mb4');
    $like = '%' . like_escape($q) . '%';
    $pre  = like_escape($q) . '%';
    $id   = ctype_digit($q) ? (int)$q : 0;
    $stmt = $conn->prepare(
        "SELECT `ID`, `Bezeichnung`, `Seriennummer` FROM `Sammlung`
          WHERE `ID` <> ? AND (? = '' OR `Bezeichnung` LIKE ? OR `Seriennummer` LIKE ? OR `ID` = ?)
          ORDER BY (`ID` = ?) DESC, (`Bezeichnung` LIKE ?) DESC, `Bezeichnung`, `ID` LIMIT $limit");
    $stmt->bind_param('isssiis', $x, $q, $like, $like, $id, $id, $pre);
    $stmt->execute();
    $items = [];
    foreach ($stmt->get_result() as $r) {
        $sn = trim((string)($r['Seriennummer'] ?? ''));
        $items[] = ['id' => (int)$r['ID'], 'label' => (string)$r['Bezeichnung'] . ' (#' . (int)$r['ID'] . ')' . ($sn !== '' ? ' – SN: ' . $sn : '')];
    }
    api_json(200, ['items' => $items]);
}

if (!isset($karte[$t])) { api_json(400, ['error' => 'Unbekannte Liste.']); }
[$tabelle, $spalte, $gastFeld] = $karte[$t];
if ($gast) {
    if ($gastFeld === null || ($gastFeld !== '' && in_array($gastFeld, guest_hidden_fields(), true))) {
        api_json(403, ['error' => 'Nicht erlaubt.']);
    }
}
[$tb, $sp] = lookup_ident($tabelle, $spalte);   // feste Whitelist, nie aus der Anfrage

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) { error_log('api_lookup: Verbindung fehlgeschlagen'); api_json(500, ['error' => 'Datenbankfehler.']); }
$conn->set_charset('utf8mb4');
$like = '%' . like_escape($q) . '%';
$pre  = like_escape($q) . '%';
$stmt = $conn->prepare(
    "SELECT `ID`, $sp AS `Name` FROM $tb
      WHERE (? = '' OR $sp LIKE ?)
      ORDER BY ($sp LIKE ?) DESC, $sp LIMIT $limit");
$stmt->bind_param('sss', $q, $like, $pre);
$stmt->execute();
$items = [];
foreach ($stmt->get_result() as $r) { $items[] = ['id' => (int)$r['ID'], 'label' => (string)$r['Name']]; }
api_json(200, ['items' => $items]);
