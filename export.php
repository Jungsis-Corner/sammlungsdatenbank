<?php
ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(E_ALL);

require __DIR__ . '/require_login.php';
$is_guest = is_guest();

require_once __DIR__ . '/config.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$conn->set_charset('utf8mb4');

// ---------- Parameter wie index.php ----------
$filter          = $_GET['filter']     ?? '';
$oh              = $_GET['oh']         ?? '';
$material_filter = $_GET['material']   ?? '';
$search          = trim($_GET['q']      ?? '');
$herstellerId    = intval($_GET['hersteller'] ?? 0);
$publisherId     = intval($_GET['publisher']  ?? 0);
$verkaeuferId    = intval($_GET['verkaeufer'] ?? 0);
$standortId      = intval($_GET['standort']   ?? 0);

$sort = $_GET['sort'] ?? 'Bezeichnung';
$dir  = strtolower($_GET['dir'] ?? 'asc');
if (!in_array($dir, ['asc','desc'], true)) $dir = 'asc';

$validSorts = [
  'Bezeichnung','Kategorie','Jahr','`Original/Homebrew`',
  'Material','Zustand','Verpackung','Standort','Box','Einkaufsdatum','Wert',
  'Getestet am','Getestet Status'
];
if (!in_array($sort, $validSorts, true)) $sort = 'Bezeichnung';

$sort_map = [
  'Bezeichnung'         => 'Sammlung.Bezeichnung',
  'Kategorie'           => 'Kategorie.Kategorie',
  'Jahr'                => 'Sammlung.Jahr',
  '`Original/Homebrew`' => 'Sammlung.`Original/Homebrew`',
  'Material'            => 'Material.Material',
  'Zustand'             => 'Zustand.Zustand',
  'Verpackung'          => 'Verpackung.Verpackung',
  'Standort'            => 'Standort.Standort',
  'Box'                 => 'Sammlung.Box',
  'Einkaufsdatum'       => 'Sammlung.Einkaufsdatum',
  'Wert'                => 'CAST(REPLACE(REPLACE(Sammlung.Wert, ".", ""), ",", ".") AS DECIMAL(10,2))',
  'Getestet am'         => 'Sammlung.`Getestet am`',
  'Getestet Status'     => 'Sammlung.`Getestet Status`'
];

$order_by_core = $sort_map[$sort] . ' ' . strtoupper($dir);
if ($sort === 'Einkaufsdatum' || $sort === 'Getestet am') {
    // NULL-Daten ans Ende
    $order_by_core = "Sammlung.`$sort` IS NULL, " . $order_by_core;
}
$order_by = $order_by_core . ', Sammlung.Bezeichnung ASC';

// ---------- WHERE wie index.php ----------
$where = [];
if     ($filter==='spiel')          $where[] = "Kategorie.Kategorie LIKE '%Spiel%'";
elseif ($filter==='spiel_original') $where[] = "Kategorie.Kategorie LIKE '%Spiel%' AND Sammlung.`Original/Homebrew`='Original'";
elseif ($filter==='spiel_homebrew') $where[] = "Kategorie.Kategorie LIKE '%Spiel%' AND Sammlung.`Original/Homebrew`='Homebrew'";
elseif (is_numeric($filter))        $where[] = "Sammlung.Kategorie=" . intval($filter);

if     ($oh==='Original')           $where[] = "Sammlung.`Original/Homebrew`='Original'";
elseif ($oh==='Homebrew')           $where[] = "Sammlung.`Original/Homebrew`='Homebrew'";

if ($material_filter!=='')          $where[] = "Material.Material = '" . $conn->real_escape_string($material_filter) . "'";
if ($search!=='')                   $where[] = "Sammlung.Bezeichnung LIKE '%" . $conn->real_escape_string($search) . "%'";
if ($herstellerId)                  $where[] = "Sammlung.Hersteller=$herstellerId";
if ($publisherId)                   $where[] = "Sammlung.Publisher=$publisherId";
if ($verkaeuferId)                  $where[] = "Sammlung.`Verkäufer`=$verkaeuferId";
if ($standortId)                    $where[] = "Sammlung.Standort=$standortId";

$where_sql = $where ? 'WHERE '.implode(' AND ',$where) : '';

// ---------- Daten (wie index.php; gleiche Joins für „sprechende“ Namen) ----------
$sql = "
  SELECT
    Sammlung.*,
    Kategorie.Kategorie   AS KategorieName,
    Material.Material     AS MaterialName,
    Zustand.Zustand       AS ZustandName,
    Verpackung.Verpackung AS VerpackungName,
    Standort.Standort     AS StandortName
  FROM Sammlung
  LEFT JOIN Kategorie  ON Sammlung.Kategorie  = Kategorie.ID
  LEFT JOIN Material   ON Sammlung.Material   = Material.ID
  LEFT JOIN Zustand    ON Sammlung.Zustand    = Zustand.ID
  LEFT JOIN Verpackung ON Sammlung.Verpackung = Verpackung.ID
  LEFT JOIN Standort   ON Sammlung.Standort   = Standort.ID
  $where_sql
  ORDER BY $order_by
";
$res = $conn->query($sql);

// ---------- CSV-Header je nach Rolle (wie Tabellenliste; Bildspalte lassen wir weg) ----------
$headers = [
  'Bezeichnung','Kategorie','Jahr','Original/Homebrew','Material','Zustand','Verpackung'
];
if (!$is_guest){
  $headers[] = 'Standort';
  $headers[] = 'Box';
  $headers[] = 'Einkaufsdatum';
  $headers[] = 'Getestet am';
  $headers[] = 'Getestet Status';
  $headers[] = 'Wert';
}

// ---------- Dynamischen Dateinamen bauen ----------
$parts = [];
$parts[] = date('Y-m-d'); // Datum vorn

$add = function(string $label, $value) use (&$parts) {
    if ($value === null || $value === '') return;
    $v = (string)$value;
    // kurz halten & dateisystem-sicher normalisieren
    $v = mb_substr($v, 0, 40);
    $v = preg_replace('/[^\p{L}\p{N}\-_.]+/u', '-', $v); // nur Buchst/Ziffern/- _ .
    $v = trim($v, '-_.');
    if ($v !== '') $parts[] = $label . '-' . $v;
};

$add('q',          $_GET['q']          ?? null);
$add('filter',     $_GET['filter']     ?? null);
$add('oh',         $_GET['oh']         ?? null);
$add('material',   $_GET['material']   ?? null);
$add('hersteller', $_GET['hersteller'] ?? null);
$add('publisher',  $_GET['publisher']  ?? null);
$add('verkaeufer', $_GET['verkaeufer'] ?? null);
$add('standort',   $_GET['standort']   ?? null);
$add('sort',       ($sort ?? null) . '-' . ($dir ?? 'asc'));

$suffix   = implode('_', $parts);
$suffix   = mb_substr($suffix, 0, 200);
$filename = "sammlung_{$suffix}.csv";

// ---------- Ausgabe vorbereiten: CSV (UTF-8 BOM + Semikolon) ----------
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="'.$filename.'"');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

// BOM für Excel (muss vor jeglicher Ausgabe kommen)
echo "\xEF\xBB\xBF";

$out = fopen('php://output','w');
$delimiter = ';'; // DE-Excel

// Kopfzeile
fputcsv($out, $headers, $delimiter, '"', '');

// Zeilen
while ($row = $res->fetch_assoc()){
  $line = [
    $row['Bezeichnung']     ?? '',
    $row['KategorieName']   ?? '',
    $row['Jahr']            ?? '',
    $row['Original/Homebrew'] ?? '',
    $row['MaterialName']    ?? '',
    $row['ZustandName']     ?? '',
    $row['VerpackungName']  ?? '',
  ];

  if (!$is_guest){
    $line[] = $row['StandortName'] ?? '';
    $line[] = $row['Box'] ?? '';
    $ed = (!empty($row['Einkaufsdatum']) && $row['Einkaufsdatum']!=='0000-00-00')
            ? date('d.m.Y', strtotime($row['Einkaufsdatum'])) : '';
    $line[] = $ed;
    $gd = (!empty($row['Getestet am']) && $row['Getestet am']!=='0000-00-00')
            ? date('d.m.Y', strtotime($row['Getestet am'])) : '';
    $line[] = $gd;
    $line[] = $row['Getestet Status'] ?? '';
    $line[] = $row['Wert'] ?? '';
  }

  // Zeilenumbrüche für CSV/Excel glätten
  foreach ($line as &$v) {
    $v = str_replace(["\r\n","\r","\n"], ' / ', (string)$v);
  }
  unset($v);

  fputcsv($out, $line, $delimiter, '"', '');
}

fclose($out);
exit;