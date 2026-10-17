<?php
require_once __DIR__ . '/inc_errors.php';

require __DIR__ . '/require_login.php';
require_once __DIR__ . '/inc_security.php';
require_once __DIR__ . '/inc_format.php';
require_once __DIR__ . '/inc_filter.php';
require_once __DIR__ . '/inc_icons.php';
require_once __DIR__ . '/inc_admin_ui.php';
$is_guest = is_guest();

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/inc_guest.php';

// Gäste dürfen nur exportieren, wenn GUEST_EXPORT_ENABLED gesetzt ist (E1)
if ($is_guest && !guest_export_allowed()) {
    error_page(403, 'Export nicht verfügbar', 'Der Export ist für Gäste nicht verfügbar.');
}
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$conn->set_charset('utf8mb4');

// ---------- Parameter wie index.php ----------
$filter          = $_GET['filter']     ?? '';
$kat             = intval($_GET['kat'] ?? 0);              // Kategorie-Knoten
$inkl            = ($_GET['inkl'] ?? '') === '1';          // inkl. Unterkategorien
$oh              = $_GET['oh']         ?? '';
$material_filter = $_GET['material']   ?? '';
$search          = trim($_GET['q']      ?? '');
$herstellerId    = intval($_GET['hersteller'] ?? 0);
$publisherId     = intval($_GET['publisher']  ?? 0);
$verkaeuferId    = intval($_GET['verkaeufer'] ?? 0);
$standortId      = intval($_GET['standort']   ?? 0);
$boxId           = trim((string)($_GET['box'] ?? ''));

// Einkaufsdatum-Suche (aus <input type="date">, liefert immer YYYY-MM-DD)
$einkaufsdatumFilter = trim((string)($_GET['einkaufsdatum'] ?? ''));
if ($einkaufsdatumFilter !== '' && !preg_match('~^\d{4}-\d{2}-\d{2}$~', $einkaufsdatumFilter)) {
    $einkaufsdatumFilter = '';
}

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
  'Box'                 => box_sort_sql('Box'),
  'Einkaufsdatum'       => 'Sammlung.Einkaufsdatum',
  'Wert'                => 'Sammlung.Wert',
  'Getestet am'         => 'Sammlung.`Getestet am`',
  'Getestet Status'     => 'Sammlung.`Getestet Status`'
];

$order_by_core = $sort_map[$sort] . ' ' . strtoupper($dir);
if ($sort === 'Einkaufsdatum' || $sort === 'Getestet am') {
    // NULL-Daten ans Ende
    $order_by_core = "Sammlung.`$sort` IS NULL, " . $order_by_core;
}
$order_by = $order_by_core . ', Sammlung.Bezeichnung ASC, Sammlung.ID ASC';   // ID als letzter Schlüssel: gleiche Titel bleiben in fester Reihenfolge (Liste, Detail, Bearbeiten, Export)

// ---------- WHERE wie index.php (gemeinsam in inc_filter.php, mit Prepared Statements) ----------
$flt = sammlung_filter_where([
    'filter' => $filter, 'kat' => $kat, 'inkl' => $inkl, 'oh' => $oh, 'material' => $material_filter, 'q' => $search,
    'hersteller' => $herstellerId, 'publisher' => $publisherId, 'verkaeufer' => $verkaeuferId,
    'standort' => $standortId, 'box' => $boxId, 'verkauf' => ($_GET['verkauf'] ?? '') === '1',
    'einkaufsdatum' => $einkaufsdatumFilter,
]);
$where_sql = $flt['sql'];

// Gast-Export ist auf GUEST_EXPORT_MAX_ROWS Zeilen begrenzt (int aus Konfiguration, kein Benutzerwert)
$limit_sql = $is_guest ? ' LIMIT ' . guest_export_max_rows() : '';

// ---------- Exportseite (ohne download=1): zeigt Umfang und aktive Filter, der Download steht hinter dem Knopf ----------
if (($_GET['download'] ?? '') !== '1') {
    $e = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    $cnt = (int)db_select($conn, "SELECT COUNT(*) FROM Sammlung LEFT JOIN Kategorie ON Sammlung.Kategorie = Kategorie.ID LEFT JOIN Material ON Sammlung.Material = Material.ID $where_sql", $flt['types'], $flt['values'])->fetch_row()[0];
    $gastGrenze = $is_guest ? guest_export_max_rows() : 0;
    $zeilen = ($gastGrenze > 0) ? min($cnt, $gastGrenze) : $cnt;
    $name = function (string $tabelle, string $spalte, int $id) use ($conn): string {
        [$t, $c] = lookup_ident($tabelle, $spalte);
        $r = db_select($conn, "SELECT $c FROM $t WHERE ID = ?", 'i', [$id])->fetch_row();
        return $r ? (string)$r[0] : '#' . $id;
    };
    $aktiv = [];   // [Bezeichnung, Wert]
    $fl = (string)$filter;
    if ($fl === 'spiel')               $aktiv[] = ['Typ', 'Spiele'];
    elseif ($fl === 'spiel_original')  $aktiv[] = ['Typ', 'Spiele (Original)'];
    elseif ($fl === 'spiel_homebrew')  $aktiv[] = ['Typ', 'Spiele (Homebrew)'];
    elseif (is_numeric($fl))           $aktiv[] = ['Kategorie', $name('Kategorie', 'Kategorie', (int)$fl)];
    if ($kat > 0)                      $aktiv[] = ['Kategorie', $name('Kategorie', 'Kategorie', $kat) . ($inkl ? ' (mit Unterkategorien)' : '')];
    if ($oh === 'Original' || $oh === 'Homebrew') $aktiv[] = ['Original/Homebrew', $oh];
    if ($material_filter !== '')       $aktiv[] = ['Material', $material_filter];
    if ($search !== '')                $aktiv[] = ['Suche', $search];
    if ($herstellerId > 0)             $aktiv[] = ['Hersteller', $name('Hersteller', 'Hersteller', $herstellerId)];
    if ($publisherId > 0)              $aktiv[] = ['Publisher', $name('Publisher', 'Publisher', $publisherId)];
    if (!$is_guest) {
        if ($verkaeuferId > 0)         $aktiv[] = ['Verkäufer', $name('Verkäufer', 'Verkäufer', $verkaeuferId)];
        if ($standortId > 0)           $aktiv[] = ['Standort', $name('Standort', 'Standort', $standortId)];
        if ($boxId !== '' && ctype_digit($boxId)) $aktiv[] = ['Box', $name('Box', 'Box', (int)$boxId)];
        if ($einkaufsdatumFilter !== '') $aktiv[] = ['Einkaufsdatum', date('d.m.Y', strtotime($einkaufsdatumFilter))];
    }
    if (($_GET['verkauf'] ?? '') === '1') $aktiv[] = ['Zum Verkauf', 'ja'];

    $backParams = $_GET; unset($backParams['download']);
    $dlParams = $_GET; $dlParams['download'] = '1';
    $isMuseum = !empty($_SESSION['museum_mode']);
    $navOpt = ['is_guest' => $is_guest, 'is_admin' => is_admin(), 'museum' => $isMuseum, 'export' => !$isMuseum, 'params' => $backParams];
    $spalten = ['Bezeichnung', 'Kategorie', 'Jahr', 'Original/Homebrew', 'Material', 'Zustand', 'Verpackung'];
    if (!$is_guest) { array_push($spalten, 'Standort', 'Box', 'Einkaufsdatum', 'Getestet am', 'Getestet Status', 'Wert'); }
    ?>
<!DOCTYPE html>
<html lang="de">
<head>
  <?php $page_title = 'Export'; include __DIR__ . '/inc_head_admin.php'; ?>
</head>
<body class="admin-page">
<?= admin_header([['Export', '']], $navOpt, false) ?>

<main id="inhalt">
  <h1>Export</h1>

  <section class="v-karte" aria-labelledby="h_umfang">
    <h2 id="h_umfang">Das wird exportiert</h2>
    <p><strong><?= number_format($zeilen, 0, ',', '.') ?></strong> <?= $zeilen === 1 ? 'Eintrag' : 'Einträge' ?>
      <?php if ($gastGrenze > 0 && $cnt > $gastGrenze): ?> (von <?= number_format($cnt, 0, ',', '.') ?>; für Gäste auf <?= number_format($gastGrenze, 0, ',', '.') ?> begrenzt)<?php endif; ?></p>

    <h3 class="ex-h3">Filter</h3>
    <?php if ($aktiv): ?>
      <ul class="ex-filter">
        <?php foreach ($aktiv as [$bez, $wert]): ?><li><span class="ex-bez"><?= $e($bez) ?></span> <?= $e($wert) ?></li><?php endforeach; ?>
      </ul>
      <p class="v-unter">Nur die gefilterten Einträge werden exportiert, in der Sortierung der Liste.</p>
    <?php else: ?>
      <p>Kein Filter aktiv: Es wird die <strong>gesamte Sammlung</strong> exportiert.</p>
    <?php endif; ?>

    <h3 class="ex-h3">Format</h3>
    <p>CSV (Semikolon, UTF-8) – öffnet direkt in Excel.</p>
    <p class="v-unter">Spalten: <?= $e(implode(', ', $spalten)) ?>.</p>

    <div class="v-knoepfe ex-knoepfe">
      <a class="btn save" href="export.php?<?= $e(http_build_query($dlParams)) ?>"><?= icon('export') ?> Herunterladen</a>
      <a class="btn" href="index.php?<?= $e(http_build_query($backParams)) ?>"><?= icon('back') ?> Zurück zur Liste</a>
    </div>
  </section>
</main>
</body>
</html>
<?php
    exit;
}

// ---------- Daten (wie index.php; gleiche Joins für „sprechende“ Namen) ----------
$sql = "
  SELECT
    Sammlung.*,
    Kategorie.Kategorie   AS KategorieName,
    Material.Material     AS MaterialName,
    Zustand.Zustand       AS ZustandName,
    Verpackung.Verpackung AS VerpackungName,
    Standort.Standort     AS StandortName,
    Box.Box               AS BoxName
  FROM Sammlung
  LEFT JOIN Kategorie  ON Sammlung.Kategorie  = Kategorie.ID
  LEFT JOIN Material   ON Sammlung.Material   = Material.ID
  LEFT JOIN Zustand    ON Sammlung.Zustand    = Zustand.ID
  LEFT JOIN Verpackung ON Sammlung.Verpackung = Verpackung.ID
  LEFT JOIN Standort   ON Sammlung.Standort   = Standort.ID
  LEFT JOIN Box        ON Sammlung.Box        = Box.ID
  $where_sql
  ORDER BY $order_by$limit_sql
";
$res = db_select($conn, $sql, $flt['types'], $flt['values']);

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
$add('kat',        $kat > 0 ? (string)$kat : null);
$add('oh',         $_GET['oh']         ?? null);
$add('material',   $_GET['material']   ?? null);
$add('hersteller', $_GET['hersteller'] ?? null);
$add('publisher',  $_GET['publisher']  ?? null);
$add('verkaeufer', $_GET['verkaeufer'] ?? null);
$add('standort',   $_GET['standort']   ?? null);
$add('box',         $_GET['box']         ?? null);
$add('einkaufsdatum', $_GET['einkaufsdatum'] ?? null);
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
    $line[] = $row['BoxName'] ?? '';
    $ed = (!empty($row['Einkaufsdatum']) && $row['Einkaufsdatum']!=='0000-00-00')
            ? date('d.m.Y', strtotime($row['Einkaufsdatum'])) : '';
    $line[] = $ed;
    $gd = (!empty($row['Getestet am']) && $row['Getestet am']!=='0000-00-00')
            ? date('d.m.Y', strtotime($row['Getestet am'])) : '';
    $line[] = $gd;
    $line[] = $row['Getestet Status'] ?? '';
    $line[] = fmt_dec($row['Wert'] ?? null);
  }

  // Zeilenumbrüche für CSV/Excel glätten und Formel-Injection verhindern (=, +, -, @ am Anfang)
  foreach ($line as &$v) {
    $v = csv_safe_cell(str_replace(["\r\n","\r","\n"], ' / ', (string)$v));
  }
  unset($v);

  fputcsv($out, $line, $delimiter, '"', '');
}

fclose($out);
exit;