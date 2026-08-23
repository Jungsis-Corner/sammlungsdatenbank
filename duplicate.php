<?php
ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(E_ALL);
session_start();
require __DIR__ . '/require_login.php';
if (!is_admin()) { http_response_code(403); exit('Nur für Admins.'); }

require_once __DIR__ . '/config.php';
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) die("Verbindungsfehler: " . $conn->connect_error);

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { http_response_code(400); exit('Ungültige ID'); }

// Original laden
$res = $conn->query("SELECT * FROM Sammlung WHERE ID = $id");
$orig = $res ? $res->fetch_assoc() : null;
if (!$orig) { http_response_code(404); exit('Datensatz nicht gefunden'); }

// Titel automatisch hochzählen: "Name (Kopie)" / "Name (Kopie 2)" / ...
function next_copy_title(string $t): string {
    if (preg_match('/\s\(Kopie(?:\s(\d+))?\)$/u', $t, $m)) {
        $n = isset($m[1]) ? ((int)$m[1] + 1) : 2;
        return preg_replace('/\s\(Kopie(?:\s\d+)?\)$/u', ' (Kopie ' . $n . ')', $t);
    }
    return $t . ' (Kopie)';
}

// Felder, die wir übernehmen (nur wenn sie existieren)
// Bild, Wert, Einkaufsdatum werden bewusst geleert/neu gesetzt.
$maybeFields = [
  'Bezeichnung','Kategorie','Jahr','Original/Homebrew','Material','Zustand','Verpackung',
  'Standort','Box','Hersteller','Publisher','Verkäufer','Serie','Plattform','System',
  'SammlungBild1','Einkaufsdatum','Einkaufspreis','Wert','Bemerkung','Kommentar','Zubehör'
];

// Dynamisch nur vorhandene Felder aus obiger Liste nehmen:
$data = [];
foreach ($maybeFields as $f) {
    if (array_key_exists($f, $orig)) $data[$f] = $orig[$f];
}

// Anpassungen für die Kopie:
if (isset($data['Bezeichnung']))     $data['Bezeichnung']   = next_copy_title((string)$data['Bezeichnung']);
if (isset($data['SammlungBild1']))   $data['SammlungBild1'] = null;  // Bild weiterhin LEER lassen
// NICHT MEHR LEEREN:
// if (isset($data['Einkaufsdatum']))   $data['Einkaufsdatum'] = null;
// if (isset($data['Wert']))            $data['Wert']          = null;
// if (isset($data['Einkaufspreis']))   $data['Einkaufspreis'] = null;

// INSERT vorbereiten
$cols = array_keys($data);
$placeholders = implode(',', array_fill(0, count($cols), '?'));
$colList = '`' . implode('`,`', $cols) . '`';

$sql = "INSERT INTO Sammlung ($colList) VALUES ($placeholders)";
$stmt = $conn->prepare($sql);
if (!$stmt) die('Prepare fehlgeschlagen: '.$conn->error);

// Typen-String für bind_param bauen (i = int, d = double, s = string, b = blob)
// Wir behandeln hier pragmatisch alles als string, außer bekannten Zahlenspalten:
$intCols = ['Kategorie','Jahr','Material','Zustand','Verpackung','Standort','Hersteller','Publisher','Verkäufer'];
$doubleCols = ['Wert'];

$types = '';
$params = [];
foreach ($cols as $c) {
    if (in_array($c, $intCols, true)) {
        $types .= 'i';
        $params[] = is_null($data[$c]) || $data[$c]==='' ? null : (int)$data[$c];
    } elseif (in_array($c, $doubleCols, true)) {
        $types .= 'd';
        $params[] = is_null($data[$c]) || $data[$c]==='' ? null : (float)$data[$c];
    } else {
        $types .= 's';
        $params[] = $data[$c]; // string oder null (mysqli wandelt null korrekt)
    }
}

// Referenzen für bind_param bauen
$bind = [];
$bind[] = &$types;
for ($i=0; $i<count($params); $i++) { $bind[] = &$params[$i]; }

// Aufrufen
call_user_func_array([$stmt, 'bind_param'], $bind);
$stmt->execute();
if ($stmt->errno) die('Insert fehlgeschlagen: '.$stmt->error);

$newId = $stmt->insert_id;
$stmt->close();

// zurück zur Bearbeitung – Hinweisflag mitgeben
// Bestehende Filter/Sortierung/Seite weiterreichen
$backParams = $_GET;
$backParams['id'] = $newId;
$backParams['from'] = 'dup';

header('Location: edit.php?' . http_build_query($backParams));
exit;
