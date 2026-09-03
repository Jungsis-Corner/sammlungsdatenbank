<?php
ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(E_ALL);

require __DIR__ . '/require_login.php';
$is_guest = is_guest();

require_once __DIR__ . '/config.php';
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) die("Verbindungsfehler: " . $conn->connect_error);

// ---------------------- Lookups ----------------------
function get_lookup($table, $field) {
    global $conn;
    $res = $conn->query("SELECT ID, `$field` FROM `$table` ORDER BY `$field`");
    $lookup = [];
    while ($row = $res->fetch_assoc()) {
        $lookup[$row['ID']] = $row[$field];
    }
    return $lookup;
}

$kategorien     = get_lookup('Kategorie',    'Kategorie');
$zustand        = get_lookup('Zustand',      'Zustand');
$verpackung     = get_lookup('Verpackung',   'Verpackung');
$material       = get_lookup('Material',     'Material');
$standortList   = get_lookup('Standort',     'Standort');
$herstellerList = get_lookup('Hersteller',   'Hersteller');
$publisherList  = get_lookup('Publisher',    'Publisher');
$verkaeuferList = get_lookup('Verkäufer',    'Verkäufer');

// ---------------------- Parameter ----------------------
$filter          = $_GET['filter']     ?? '';
$oh              = $_GET['oh']         ?? '';
$material_filter = $_GET['material']   ?? '';
$search          = trim($_GET['q']      ?? '');
$herstellerId    = intval($_GET['hersteller'] ?? 0);
$publisherId     = intval($_GET['publisher']  ?? 0);
$verkaeuferId    = intval($_GET['verkaeufer'] ?? 0);
$standortId      = intval($_GET['standort']   ?? 0);
$verkaufFilter   = ($_GET['verkauf'] ?? '') === '1';

// Sortierspalte + Richtung
$sort = $_GET['sort'] ?? 'Bezeichnung';
$dir  = strtolower($_GET['dir'] ?? 'asc');
if (!in_array($dir, ['asc','desc'], true)) {
    $dir = 'asc';
}

// Erlaubte Sort-Felder (sichtbare Keys)
$validSorts = [
  'Bezeichnung','Kategorie','Jahr','`Original/Homebrew`',
  'Material','Zustand','Verpackung','Standort','Box','Einkaufsdatum',
  'Wert',
  'Link zu YouTube' // <- NEU
];
if (!in_array($sort, $validSorts, true)) {
    $sort = 'Bezeichnung';
}

// Mapping für ORDER BY auf echte Spalten inkl. Joins
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
  'Link zu YouTube'     => 'Sammlung.`Link zu YouTube`' // <- NEU
];


// $order_by bauen (Datum: NULLs ans Ende)
$order_by_core = $sort_map[$sort] . ' ' . strtoupper($dir);
if ($sort === 'Einkaufsdatum') {
    $order_by_core = 'Sammlung.Einkaufsdatum IS NULL, ' . $order_by_core;
}
// stabile Zweitsortierung
$order_by = $order_by_core . ', Sammlung.Bezeichnung ASC';

// Basis-Params für Links (ohne page)
$params = ['sort' => $sort, 'dir' => $dir];
foreach (['filter','oh','material','q','hersteller','publisher','verkaeufer','standort','verkauf'] as $p) {
    $val = $_GET[$p] ?? null;
    if ($val !== null && $val !== '') {
        $params[$p] = $val;
    }
}

// ---------------------- Aktive Badges (mit Icons) ----------------------
$activeBadges = [];

// Kategorie
if ($filter === 'spiel') {
    $activeBadges[] = ['label' => '🎮 Spiel', 'remove' => ['filter' => '']];
} elseif ($filter === 'spiel_original') {
    $activeBadges[] = ['label' => '✅ Spiel (Original)', 'remove' => ['filter' => '']];
} elseif ($filter === 'spiel_homebrew') {
    $activeBadges[] = ['label' => '🧪 Spiel (Homebrew)', 'remove' => ['filter' => '']];
} elseif (is_numeric($filter) && isset($kategorien[(int)$filter])) {
    $activeBadges[] = ['label' => '🗂️ ' . $kategorien[(int)$filter], 'remove' => ['filter' => '']];
}

// Original/Homebrew
if ($oh === 'Original') {
    $activeBadges[] = ['label' => '✅ Original', 'remove' => ['oh' => '']];
} elseif ($oh === 'Homebrew') {
    $activeBadges[] = ['label' => '🧪 Homebrew', 'remove' => ['oh' => '']];
}

// Material
if ($material_filter !== '') {
    $activeBadges[] = ['label' => '🧩 ' . $material_filter, 'remove' => ['material' => '']];
}

// Suche
if ($search !== '') {
    $activeBadges[] = ['label' => '🔎 „' . $search . '“', 'remove' => ['q' => '']];
}

// Hersteller/Publisher/Verkäufer/Standort
if ($herstellerId && isset($herstellerList[$herstellerId])) {
    $activeBadges[] = ['label' => '🏭 ' . $herstellerList[$herstellerId], 'remove' => ['hersteller' => '']];
}
if ($publisherId && isset($publisherList[$publisherId])) {
    $activeBadges[] = ['label' => '🏷️ ' . $publisherList[$publisherId], 'remove' => ['publisher' => '']];
}
if ($verkaeuferId && isset($verkaeuferList[$verkaeuferId])) {
    $activeBadges[] = ['label' => '🧑‍💼 ' . $verkaeuferList[$verkaeuferId], 'remove' => ['verkaeufer' => '']];
}
if ($standortId && isset($standortList[$standortId])) {
    $activeBadges[] = ['label' => '📍 ' . $standortList[$standortId], 'remove' => ['standort' => '']];
}
if ($verkaufFilter) {
    $activeBadges[] = ['label' => '🏷️ Zum Verkauf', 'remove' => ['verkauf' => '']];
}

// ---------------------- WHERE ----------------------
$where = [];
if ($filter==='spiel') {
    $where[] = "Kategorie.Kategorie LIKE '%Spiel%'";
} elseif ($filter==='spiel_original') {
    $where[] = "Kategorie.Kategorie LIKE '%Spiel%' AND Sammlung.`Original/Homebrew`='Original'";
} elseif ($filter==='spiel_homebrew') {
    $where[] = "Kategorie.Kategorie LIKE '%Spiel%' AND Sammlung.`Original/Homebrew`='Homebrew'";
} elseif (is_numeric($filter)) {
    $where[] = "Sammlung.Kategorie=" . intval($filter);
}
if ($oh==='Original') {
    $where[] = "Sammlung.`Original/Homebrew`='Original'";
} elseif ($oh==='Homebrew') {
    $where[] = "Sammlung.`Original/Homebrew`='Homebrew'";
}
if ($material_filter!=='') {
    $where[] = "Material.Material LIKE '" . $conn->real_escape_string($material_filter) . "%'";
}
if ($search!=='') {
    $esc = $conn->real_escape_string($search);
    $where[] = "(Sammlung.Bezeichnung LIKE '%$esc%' OR Sammlung.Barcode LIKE '%$esc%' OR Sammlung.Beschreibung LIKE '%$esc%')";
}
if ($herstellerId) {
    $where[] = "Sammlung.Hersteller=$herstellerId";
}
if ($publisherId) {
    $where[] = "Sammlung.Publisher=$publisherId";
}
if ($verkaeuferId) {
    $where[] = "Sammlung.Verkäufer=$verkaeuferId";
}
if ($standortId) {
    $where[] = "Sammlung.Standort=$standortId";
}
if ($verkaufFilter) {
    $where[] = "Sammlung.`Zum Verkauf` = '1'";
}
$where_sql = $where ? 'WHERE '.implode(' AND ',$where) : '';

// ---------------------- Pagination ----------------------
$limit  = 50;
$page   = max(1, intval($_GET['page'] ?? 1));
$offset = ($page-1)*$limit;

// Gesamtzahl
$count_sql = "
  SELECT COUNT(*) FROM Sammlung
    LEFT JOIN Kategorie  ON Sammlung.Kategorie  = Kategorie.ID
    LEFT JOIN Material   ON Sammlung.Material   = Material.ID
    LEFT JOIN Zustand    ON Sammlung.Zustand    = Zustand.ID
    LEFT JOIN Verpackung ON Sammlung.Verpackung = Verpackung.ID
    LEFT JOIN Standort   ON Sammlung.Standort   = Standort.ID
  $where_sql
";
$count_res = $conn->query($count_sql);
$total     = ($count_res && $r = $count_res->fetch_row()) ? intval($r[0]) : 0;
$pages     = max(1, ceil($total/$limit));

// ---------------------- Daten ----------------------
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
  LIMIT $limit OFFSET $offset
";
$result = $conn->query($sql);

// ---------------------- Mini-Statistik ----------------------
// Hinweis: totalCount = $total (bereits gefiltert)
$totalCount = (int)$total;

// Einträge mit Jahr (unter aktuellen Filtern)
$yearCount = 0;
$year_sql = "
  SELECT COUNT(*) FROM Sammlung
  LEFT JOIN Kategorie  ON Sammlung.Kategorie  = Kategorie.ID
  LEFT JOIN Material   ON Sammlung.Material   = Material.ID
  LEFT JOIN Zustand    ON Sammlung.Zustand    = Zustand.ID
  LEFT JOIN Verpackung ON Sammlung.Verpackung = Verpackung.ID
  LEFT JOIN Standort   ON Sammlung.Standort   = Standort.ID
  " . ($where_sql ? $where_sql . " AND " : "WHERE ") . " NULLIF(TRIM(Sammlung.Jahr),'') IS NOT NULL
";
if ($resY = $conn->query($year_sql)) {
    $rowY = $resY->fetch_row();
    $yearCount = (int)($rowY[0] ?? 0);
}

// Summe Wert (nur Admin, robust gegen Formatvarianten)
$sumValue = null;
if (!$is_guest) {
    $sum_sql = "
      SELECT SUM(
        CASE
          WHEN NULLIF(TRIM(Wert),'') IS NULL THEN 0
          WHEN REPLACE(REPLACE(Wert,'.',''),',','.') REGEXP '^[0-9]+(\\.[0-9]+)?$'
            THEN REPLACE(REPLACE(Wert,'.',''),',','.') + 0
          ELSE 0
        END
      ) AS s
      FROM Sammlung
      LEFT JOIN Kategorie  ON Sammlung.Kategorie  = Kategorie.ID
      LEFT JOIN Material   ON Sammlung.Material   = Material.ID
      LEFT JOIN Zustand    ON Sammlung.Zustand    = Zustand.ID
      LEFT JOIN Verpackung ON Sammlung.Verpackung = Verpackung.ID
      LEFT JOIN Standort   ON Sammlung.Standort   = Standort.ID
      $where_sql
    ";
    if ($resS = $conn->query($sum_sql)) {
        $rowS = $resS->fetch_assoc();
        $sumValue = (float)($rowS['s'] ?? 0.0);
    }
}

// ---------------------- Helfer für Links ----------------------
/** Sortierlink mit Toggle und Pfeil */
function sort_link(string $key, string $label, string $sort, string $dir, array $baseParams): string {
    $nextDir = ($sort === $key && $dir === 'asc') ? 'desc' : 'asc';
    $arrow   = '';
    if ($sort === $key) {
        $arrow = ($dir === 'asc') ? ' ▲' : ' ▼';
    }
    $params = array_merge($baseParams, ['sort'=>$key, 'dir'=>$nextDir, 'page'=>1]);
    $href   = '?' . htmlspecialchars(http_build_query($params));
    return '<a class="th-sort" href="'.$href.'" title="Sortieren nach '.$label.'">'.$label.$arrow.'</a>';
}

/** Aktuelle GET-Parameter (Kopie), ohne page */
function current_params(): array {
    $p = $_GET;
    unset($p['page']); // neue Navigation startet Seite 1
    return $p;
}

/** URL bauen, bei der NUR bestimmte Keys entfernt werden (alle anderen bleiben) */
function url_remove_only(array $keysToRemove, array $additions = []): string {
    $p = current_params();
    foreach ($keysToRemove as $k) unset($p[$k]);
    $p['page'] = 1;
    foreach ($additions as $k => $v) {
        if ($v === '' || $v === null) unset($p[$k]); else $p[$k] = $v;
    }
    return '?' . htmlspecialchars(http_build_query($p));
}

/** Einheitliche, ellipsierte Pagination (oben & unten) */
function render_pagination(int $page, int $pages, array $params): void {
  $window = 2;
  echo '<div class="pagination">';
  if ($page > 1) {
      echo '<a href="?'.http_build_query(array_merge($params,['page'=>1])).'">« Erste</a> ';
      echo '<a href="?'.http_build_query(array_merge($params,['page'=>$page-1])).'">‹ Zurück</a> ';
  }
  if ($page - $window > 2) {
      echo '<a href="?'.http_build_query(array_merge($params,['page'=>1])).'">1</a> … ';
  }
  for ($i = max(1, $page-$window); $i <= min($pages, $page+$window); $i++) {
      if ($i === $page) {
          echo "<strong>$i</strong> ";
      } else {
          echo '<a href="?'.http_build_query(array_merge($params,['page'=>$i])).'">'.$i.'</a> ';
      }
  }
  if ($page + $window < $pages-1) {
      echo '… <a href="?'.http_build_query(array_merge($params,['page'=>$pages])).'">'.$pages.'</a> ';
  }
  if ($page < $pages) {
      echo '<a href="?'.http_build_query(array_merge($params,['page'=>$page+1])).'">Weiter ›</a> ';
      echo '<a href="?'.http_build_query(array_merge($params,['page'=>$pages])).'">Letzte »</a>';
  }
  echo '</div>';
}

// -------- Toolbar Link-Builder --------
$baseParams = $_GET;
unset($baseParams['page']);
$build = function(array $overrides = []) use ($baseParams) {
  $p = array_merge($baseParams, $overrides);
  if (!isset($overrides['page'])) $p['page'] = 1;
  return 'index.php?' . htmlspecialchars(http_build_query($p));
};

// -------- Filterbereich ein-/ausklappbar (Zustand per Cookie gemerkt) --------
$filterPanelOpen = ($_COOKIE['filterPanelOpen'] ?? '1') !== '0';
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<script src="/sammlung/assets/theme-toggle.js"></script>

<?php
$isMuseum = !empty($_SESSION['museum_mode']);
?>

<title>

<?= $isMuseum
    ? htmlspecialchars(APP_TITLE_MUSEUM)
    : htmlspecialchars(APP_TITLE); ?>
</title>

<link rel="manifest" href="/sammlung/manifest.php">
<meta name="theme-color" content="<?= $isMuseum ? '#000000' : '#111111'; ?>">

<link rel="apple-touch-icon" href="/sammlung/icons/apple-touch-icon.png">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="<?= $isMuseum ? 'Museum' : 'Sammlung'; ?>">
  <style>
    body{font-family:Arial,Helvetica,sans-serif;font-size:10pt;padding:20px}
    .logout{float:right}
    h1{color:blue;text-align:center}

    /* ===== Toolbar (neu) ===== */
    .toolbar {
      position: sticky; top: 0;
      background: #fff;
      z-index: 5;
      padding: 12px 12px 10px;
      border: 1px solid #e5e5e5;
      border-radius: 8px;
      box-shadow: 0 2px 8px rgba(0,0,0,.06);
      margin: 12px 0 16px;
    }
    .toolbar-row1 {
      display: grid;
      grid-template-columns: minmax(180px, 300px) auto auto auto auto; /* Suche | Stat | Reset | Filter-Toggle | Statistik */
      gap: 8px;
      align-items: center;
      margin-bottom: 10px;
    }
    .toolbar-block {
      display: grid;
      grid-template-columns: repeat(8, minmax(0,1fr));
      gap: 6px;
      margin-bottom: 8px;
    }
    @media (max-width: 1100px) { .toolbar-block { grid-template-columns: repeat(5, 1fr); } }
    @media (max-width: 800px)  { .toolbar-block { grid-template-columns: repeat(3, 1fr); } }
    .toolbar-label { grid-column: 1 / -1; font-weight:700; color:#444; margin-top:4px; }

    /* Ein-/Ausklappbarer Filterbereich */
    .filter-panel { margin-top: 4px; }
    .filter-toggle-btn{
      display:inline-block; padding:8px 12px; border-radius:6px;
      background:#34495e; color:#fff; border:1px solid #2c3e50;
      cursor:pointer; font:inherit;
    }
    .filter-toggle-btn:hover{ filter:brightness(.96); }

    .searchbox { display:flex; gap:8px; align-items:center; }
    .searchbox input[type="text"]{
      width:100%; padding:8px 10px; border:1px solid #ccc; border-radius:6px; font:inherit;
    }
    .searchbox button{
      padding:8px 12px; border:1px solid #0b6d57; background:#16a085; color:#fff; border-radius:6px; cursor:pointer;
    }
    .searchbox button:hover{ filter:brightness(.95); }

    .stat { display:inline-flex; gap:10px; align-items:center; font-size:.95em; color:#333; }
    .stat .chip { display:inline-block; padding:6px 10px; border-radius:999px; background:#f5f5f5; border:1px solid #ddd; }

    .btn-reset {
      display:inline-block; padding:8px 12px; border-radius:6px;
      background:#e74c3c; color:#fff; border:1px solid #c0392b; text-decoration:none;
    }
    .btn-reset:hover { filter:brightness(.96); }

    .btn-stat {
      display:inline-block; padding:8px 12px; border-radius:6px;
      background:#34495e; color:#fff; border:1px solid #2c3e50; text-decoration:none;
    }
    .btn-stat:hover { filter:brightness(.96); }

    .btn-filter {
      display:block; text-align:center; padding:5px 8px; border-radius:5px;
      color:#fff; text-decoration:none; border:1px solid transparent; font-weight:600;
      font-size:0.85em;
    }
    .btn-filter:hover { filter:brightness(.96); }
    .btn-filter.active { outline:2px solid rgba(0,0,0,.15); }

    .btn-blue   { background:#3498db; border-color:#2980b9; }
    .btn-green  { background:#2ecc71; border-color:#27ae60; }
    .btn-purple { background:#8e44ad; border-color:#6f2f95; }
    .btn-teal   { background:#16a085; border-color:#0f6f5d; }
    .btn-orange { background:#f39c12; border-color:#d68910; }
    .btn-pink   { background:#e91e63; border-color:#ad1457; }
    .btn-gray   { background:#7f8c8d; border-color:#707b7c; }
    .btn-red    { background:#c0392b; border-color:#922b21; }

    /* Filter-Zusammenfassung */
    .filter-summary{
      display:flex; align-items:center; justify-content:space-between;
      flex-wrap:wrap; gap:8px;
      background:#f7f7ff; border:1px solid #cfd2ff; padding:8px 10px; margin:10px 0;
    }
    .filter-summary .badges { display:flex; flex-wrap:wrap; gap:8px; align-items:center; }
    .filter-summary strong{ margin-right:6px; }
    .filter-summary .badge{
      display:inline-block; padding:2px 8px; border-radius:12px;
      background:#e8ecff; border:1px solid #bfc7ff; font-size:0.9em;
      text-decoration:none; color:inherit;
    }
    .filter-summary .badge .x{ margin-right:4px; font-weight:bold; }
    .filter-summary .count{ font-weight:bold; margin-left:8px; }
    .filter-summary .actions { margin-left:auto; }
    .filter-summary .btn-new{
      display:inline-block; background:#6f42c1; color:#fff; padding:6px 10px;
      border-radius:4px; text-decoration:none; border:1px solid #5832a4;
    }
    .filter-summary .btn-new:hover{ filter:brightness(0.95); }

    /* Tabelle */
    table{ width:100%; table-layout: fixed; border-collapse:collapse; margin-top:10px }
    th,td{ border:1px solid #888; padding:4px; overflow:hidden; text-overflow: ellipsis; word-break: break-word; }
    th{ background:#eee; white-space:nowrap; }
    th a.th-sort{text-decoration:none;color:inherit}
    th a.th-sort:hover{text-decoration:underline}
	
	/* Bildzelle komplett klickbar */
table.list td.click-open { padding: 0; }
table.list td.click-open a.img-link{
  display: block;            /* füllt die ganze Zelle */
  padding: 4px;              /* entspricht deinen Zell-Paddings */
  height: 100%;
  text-decoration: none;
}
table.list td.click-open a.img-link:hover{ background: #f5faff; } /* dezentes Hover */
table.list td.click-open a.img-link:focus{
  outline: 2px solid #a6c8ff;
  outline-offset: -2px;
}


    /* Spaltenbreiten via colgroup */
    .col-icon   { width:36px;  }  /* 🔍/📝/📄/🗑 */
    .col-img    { width:90px;  }  /* Vorschaubild */
    .col-title  { width:24%;  }   /* Bezeichnung */
    .col-cat    { width:17%;  }   /* Kategorie */
    .col-year   { width:75px; }   /* Jahr */
    .col-oh     { width:15%;  }   /* Original/Homebrew */
    .col-mat    { width:12%;  }   /* Material */
    .col-zust   { width:10%;  }   /* Zustand */
    .col-vp     { width:12%;  }   /* Verpackung */
    .col-loc    { width:10%;  }   /* Standort */
    .col-box    { width:5%;  }    /* Box */
    .col-date   { width:100px; white-space:nowrap; } /* Einkaufsdatum */
    .col-wert   { width:80px; white-space:nowrap; }  /* Wert */

    td img { max-width: 80px; height: auto; display:block; }

    .pagination{text-align:center;margin:10px 0}
    .pagination a{
      display:inline-block;background:#ddd;padding:4px 10px;margin:3px;
      text-decoration:none;border:1px solid #aaa;border-radius:4px;
    }
	.toolbar-form select {
  width: 100%;
  padding: 8px 10px;
  border: 1px solid #ccc;
  border-radius: 6px;
  font: inherit;
  background: #fff;
}
@media (max-width: 1100px) { .toolbar-form { grid-template-columns: repeat(3, 1fr) !important; } }
@media (max-width: 800px)  { .toolbar-form { grid-template-columns: repeat(2, 1fr) !important; } }
/* iPad-Breiten: Einkaufsdatum-Spalte ausblenden */
@media (min-width:768px) and (max-width:1366px) {
  .index-page table.list th.col-einkaufsdatum,
  .index-page table.list td.col-einkaufsdatum {
    display: none;
  }
  /* optional: Col ebenfalls ausblenden (Kosmetik) */
  .index-page table.list col.col-einkaufsdatum { display: none; }
}
.back-to-top{
  position: fixed;
  right: 16px;
  bottom: 16px;
  z-index: 20;
  display: none;                /* wird per JS eingeblendet */
  padding: 10px 12px;
  background: #3498db;
  color:#fff; text-decoration:none;
  border:1px solid #2980b9; border-radius:8px;
  font-weight:600;
  box-shadow:0 2px 8px rgba(0,0,0,.12);
}
.back-to-top:hover{ filter:brightness(.95); }
.back-to-top.show{ display:inline-block; }

/* größerer Tap-Zielbereich mobil */
@media (max-width: 900px){
  .back-to-top{ padding: 14px 16px; right: 12px; bottom: 12px; }
}
html { scroll-behavior: smooth; } /* iPadOS 15+ kann das */
.back-to-top { cursor: pointer; }
/* Button nur auf Desktop zeigen – iPad/Tablet & Phone ausblenden */
.desktop-only { display:inline-block; }
@media (max-width: 1366px){ .desktop-only { display:none !important; } }

/* =========================================================
   DARK MODE (echte Farben statt Invertierungs-Trick - zuverlässiger)
   ========================================================= */
html[data-theme="dark"]{
  background: #121212 !important;
}
html[data-theme="dark"] body{
  background: #121212 !important;
  color: #e8e8e8 !important;
}
html[data-theme="dark"] h1{
  color: #a9b8ff !important;
}
html[data-theme="dark"] .logout a,
html[data-theme="dark"] .logout{
  color: #e8e8e8 !important;
}
html[data-theme="dark"] .toolbar{
  background: #1e1e1e !important;
  border-color: #333 !important;
}
html[data-theme="dark"] .toolbar-label{
  color: #ccc !important;
}
html[data-theme="dark"] .searchbox input[type="text"]{
  background: #2a2a2a !important;
  color: #e8e8e8 !important;
  border-color: #444 !important;
}
html[data-theme="dark"] .stat{
  color: #e8e8e8 !important;
}
html[data-theme="dark"] .stat .chip{
  background: #2a2a2a !important;
  border-color: #444 !important;
  color: #e8e8e8 !important;
}
html[data-theme="dark"] .filter-summary{
  background: #1a1a2e !important;
  border-color: #33335a !important;
  color: #e8e8e8 !important;
}
html[data-theme="dark"] .filter-summary .badge{
  background: #2a2a45 !important;
  border-color: #44447a !important;
  color: #e8e8e8 !important;
}
html[data-theme="dark"] table,
html[data-theme="dark"] th,
html[data-theme="dark"] td{
  background: #1a1a1a !important;
  color: #e8e8e8 !important;
  border-color: #444 !important;
}
html[data-theme="dark"] th{
  background: #2a2a2a !important;
}
html[data-theme="dark"] th a.th-sort{
  color: #e8e8e8 !important;
}
html[data-theme="dark"] table.list td.click-open a.img-link:hover{
  background: #2a2a3a !important;
}
html[data-theme="dark"] .pagination a{
  background: #2a2a2a !important;
  border-color: #555 !important;
  color: #e8e8e8 !important;
}
html[data-theme="dark"] .pagination strong{
  background: #3a3a3a !important;
  border-color: #666 !important;
  color: #fff !important;
}
html[data-theme="dark"] .toolbar-form select{
  background: #2a2a2a !important;
  color: #e8e8e8 !important;
  border-color: #444 !important;
}
.theme-toggle{
  background: #555 !important;
  border-color: #333 !important;
  color: #fff !important;
}
.theme-toggle-floating{
position: fixed;
bottom: 76px;
right: 16px;
z-index: 9999;
border-radius: 50%;
width: 44px;
height: 44px;
padding: 0;
font-size: 20px;
line-height: 42px;
text-align: center;
box-shadow: 0 2px 8px rgba(0,0,0,.25);
border: 1px solid #333;
cursor: pointer;
}

  </style>
</head>
<body class="index-page <?= !empty($_SESSION['museum_mode']) ? 'museum' : '' ?>">

<div id="offlineBanner" style="display:none; padding:10px; text-align:center;">
  Offline – es wird ggf. die zuletzt gespeicherte Ansicht angezeigt.
</div>

<div id="top-marker" aria-hidden="true"></div>

<?php if (!empty($_SESSION['museum_mode'])): ?>
  <div class="logout">
    <a href="/sammlung/museum/exit.php">⏹ Museum beenden</a>
  </div>
<?php else: ?>
  <div class="logout"><a href="logout.php">🚪 Logout</a></div>

  <?php if (is_admin()): ?>
    <a href="einstellungen.php" style="margin-left:20px;font-size:0.8em;text-decoration:none;">⚙️ Einstellungen</a>
  <?php endif; ?>
<?php endif; ?>

<h1>
  <?= !empty($_SESSION['museum_mode'])
      ? htmlspecialchars(APP_TITLE_MUSEUM)
      : htmlspecialchars(APP_TITLE); ?>
</h1>

<?php if (($_GET['msg'] ?? '') === 'deleted'): ?>
  <div style="background:#eaf9ea;border:1px solid #9cd49c;padding:8px;margin:10px 0;text-align:center;">
    Eintrag wurde gelöscht.
  </div>
<?php endif; ?>
 <!-- ===== Toolbar ===== -->
<div class="toolbar <?= $isMuseum ? 'toolbar-museum' : '' ?>">

  <!-- Zeile 1: Suche | Chips | Reset | (optional Statistik/Export nur außerhalb Museum) -->
  <div class="toolbar-row1">
    <form class="searchbox" action="index.php" method="get">
      <?php
        // aktive Parameter erhalten
        foreach (['filter','oh','material','hersteller','publisher','verkaeufer','standort','sort','dir'] as $keep) {
          if (!empty($_GET[$keep])) {
            echo '<input type="hidden" name="'.htmlspecialchars($keep).'" value="'.htmlspecialchars((string)$_GET[$keep]).'">';
          }
        }
        // Museum-Flag erhalten
        if ($isMuseum) {
          echo '<input type="hidden" name="museum" value="1">';
        }
      ?>
      <input type="text" name="q" value="<?= htmlspecialchars((string)($search)) ?>" placeholder="Suchen…">
      <button type="submit">Suchen</button>
    </form>

    <div class="stat">
      <span class="chip">Einträge: <strong><?= number_format($totalCount,0,',','.') ?></strong></span>
      <span class="chip">mit Jahr: <strong><?= number_format($yearCount,0,',','.') ?></strong></span>

      <?php if (!$is_guest && isset($sumValue) && !$isMuseum): ?>
        <span class="chip">Σ&nbsp;Wert: <strong><?= number_format($sumValue,2,',','.') ?> €</strong></span>
      <?php endif; ?>

      <?php if (!$isMuseum): ?>
        <a class="btn-stat desktop-only" href="export.php?<?= htmlspecialchars(http_build_query($params)) ?>">
          ⬇️ Excel (aktuelle Liste)
        </a>
      <?php endif; ?>
    </div>

    <a class="btn-reset" href="<?= url_remove_only(['filter','oh','material','q','hersteller','publisher','verkaeufer','standort','verkauf']) ?><?= $isMuseum ? (strpos($_SERVER['REQUEST_URI'],'?')===false ? '?museum=1' : '') : '' ?>">
      Reset alle Filter
    </a>

    <button type="button" id="filterToggleBtn" class="filter-toggle-btn">
      <?= $filterPanelOpen ? '🔼 Schnellfilter ausblenden' : '🔽 Schnellfilter anzeigen' ?>
    </button>

    <?php if (!$isMuseum): ?>
      <a class="btn-stat" href="statistik.php">📊 Statistik</a>
    <?php endif; ?>
  </div>

  <div id="filterPanel" class="filter-panel"<?= $filterPanelOpen ? '' : ' style="display:none;"' ?>>

  <!-- Zeile 2: Schnellfilter (im Museum lassen wir das drin, aber kompakter) -->
  <div class="toolbar-block">
    <div class="toolbar-label">Schnellfilter</div>

    <a class="btn-filter <?= ($filter==='spiel'?'active ':'') ?>btn-blue"   href="<?= $build(['filter'=>'spiel'] + ($isMuseum?['museum'=>'1']:[])) ?>">Spiel</a>
    <a class="btn-filter <?= ($filter==='spiel_original'?'active ':'') ?>btn-green"  href="<?= $build(['filter'=>'spiel_original'] + ($isMuseum?['museum'=>'1']:[])) ?>">Spiel + Original</a>
    <a class="btn-filter <?= ($filter==='spiel_homebrew'?'active ':'') ?>btn-purple" href="<?= $build(['filter'=>'spiel_homebrew'] + ($isMuseum?['museum'=>'1']:[])) ?>">Spiel + Homebrew</a>
    <a class="btn-filter <?= ($filter===''?'active ':'') ?>btn-gray" href="<?= $build(['filter'=>null] + ($isMuseum?['museum'=>'1']:[])) ?>">Kein Spielefilter</a>

    <a class="btn-filter <?= ($oh==='Original'?'active ':'') ?>btn-teal"   href="<?= $build(['oh'=>'Original'] + ($isMuseum?['museum'=>'1']:[])) ?>">Nur Original</a>
    <a class="btn-filter <?= ($oh==='Homebrew'?'active ':'') ?>btn-orange" href="<?= $build(['oh'=>'Homebrew'] + ($isMuseum?['museum'=>'1']:[])) ?>">Nur Homebrew</a>

    <a class="btn-filter <?= ($verkaufFilter?'active ':'') ?>btn-red" href="<?= $build(['verkauf'=> $verkaufFilter ? null : '1'] + ($isMuseum?['museum'=>'1']:[])) ?>">🏷️ Zum Verkauf</a>
  </div>

  <!-- Zeile 3: Material-Buttons (im Museum ok) -->
  <div class="toolbar-block">
    <div class="toolbar-label">Material</div>
    <?php
      $matButtons = [
        'Hardware - FPGA-Gaming-Handheld','Hardware - Heimcomputer','Hardware - Handheld',
        'Hardware - IBM kompatibel','Hardware - Konsole','Hardware - Retro-Gaming-Computer',
        'Hardware - Retro-Gaming-Handheld','Hardware - Retro-Gaming-Konsolen','Hardware - Tragbare Computer',
        'Literatur'
      ];
      $palette = ['btn-blue','btn-green','btn-purple','btn-teal','btn-orange','btn-pink','btn-gray','btn-blue','btn-green','btn-purple'];
      foreach ($matButtons as $i => $m) {
        $isActive = ($material_filter === $m);
        $btnClass = $palette[$i % count($palette)];
        $label    = preg_replace('/^Hardware\s*-\s*/u','',$m);
        echo '<a class="btn-filter '.($isActive?'active ':'').$btnClass.'" href="'.$build(['material'=>$m] + ($isMuseum?['museum'=>'1']:[])).'">'
            .htmlspecialchars($label).'</a>';
      }
    ?>
    <a class="btn-filter btn-gray <?= ($material_filter===''?'active':'') ?>" href="<?= $build(['material'=>null] + ($isMuseum?['museum'=>'1']:[])) ?>">Alle Materialien</a>
  </div>

  </div><!-- Ende #filterPanel -->

  <!-- Zeile 4: Dropdown-Filter nur außerhalb Museum -->
  <?php if (!$isMuseum): ?>
    <div class="toolbar-block">
      <div class="toolbar-label">Weitere Filter (Dropdown)</div>

      <form class="toolbar-form" method="get" style="grid-column: 1 / -1; display:grid; grid-template-columns: repeat(6, minmax(0,1fr)); gap:8px; align-items:center;">
        <?php
          foreach (['q','sort','dir','filter','oh','material','hersteller','publisher','verkaeufer','standort'] as $keep) {
            if (isset($_GET[$keep]) && $_GET[$keep] !== '') {
              echo '<input type="hidden" name="'.htmlspecialchars($keep).'" value="'.htmlspecialchars((string)$_GET[$keep]).'">';
            }
          }
        ?>

        <!-- Kategorie -->
        <select name="filter" onchange="this.form.submit()">
          <option value="">— alle Kategorien —</option>
          <?php foreach ($kategorien as $id_cat=>$name_cat): ?>
            <option value="<?= $id_cat ?>" <?= ($filter==$id_cat?'selected':'') ?>>
              <?= htmlspecialchars($name_cat) ?>
            </option>
          <?php endforeach; ?>
        </select>

        <!-- Original/Homebrew -->
        <select name="oh" onchange="this.form.submit()">
          <option value="">— Original/Homebrew —</option>
          <option value="Original" <?= $oh==='Original'?'selected':'' ?>>Original</option>
          <option value="Homebrew" <?= $oh==='Homebrew'?'selected':'' ?>>Homebrew</option>
        </select>

        <!-- Material -->
        <select name="material" onchange="this.form.submit()">
          <option value="">— Material —</option>
          <?php foreach($material as $idM=>$nameM): ?>
            <option value="<?= htmlspecialchars($nameM) ?>" <?= ($material_filter===$nameM?'selected':'') ?>>
              <?= htmlspecialchars($nameM) ?>
            </option>
          <?php endforeach; ?>
        </select>

        <!-- Hersteller -->
        <select name="hersteller" onchange="this.form.submit()">
          <option value="">— Hersteller —</option>
          <?php foreach($herstellerList as $idH=>$nameH): ?>
            <option value="<?= $idH ?>" <?= ($herstellerId===$idH?'selected':'') ?>>
              <?= htmlspecialchars($nameH) ?>
            </option>
          <?php endforeach; ?>
        </select>

        <!-- Publisher -->
        <select name="publisher" onchange="this.form.submit()">
          <option value="">— Publisher —</option>
          <?php foreach($publisherList as $idP=>$nameP): ?>
            <option value="<?= $idP ?>" <?= ($publisherId===$idP?'selected':'') ?>>
              <?= htmlspecialchars($nameP) ?>
            </option>
          <?php endforeach; ?>
        </select>

        <!-- Verkäufer -->
        <select name="verkaeufer" onchange="this.form.submit()">
          <option value="">— Verkäufer —</option>
          <?php foreach($verkaeuferList as $idV=>$nameV): ?>
            <option value="<?= $idV ?>" <?= ($verkaeuferId===$idV?'selected':'') ?>>
              <?= htmlspecialchars($nameV) ?>
            </option>
          <?php endforeach; ?>
        </select>

        <?php if (!$is_guest): ?>
          <!-- Standort -->
          <?php if ($standortId): ?>
            <button onclick="location.href='<?= url_remove_only(['standort']) ?>'">✖ Standort entfernen</button>
          <?php else: ?>
            <select onchange="if(this.value) location.href='?<?= http_build_query($params) ?>&standort='+this.value+'&page=1'">
              <option value="">Standort filtern</option>
              <?php foreach($standortList as $idS=>$nameS): ?>
                <option value="<?= $idS ?>"><?= htmlspecialchars($nameS) ?></option>
              <?php endforeach; ?>
            </select>
          <?php endif; ?>
        <?php endif; ?>

        <a class="btn-reset" href="<?= url_remove_only(['filter','oh','material','hersteller','publisher','verkaeufer','standort']) ?>" style="text-align:center;">
          Reset Dropdowns
        </a>
      </form>
    </div>
  <?php endif; ?>

</div>
<!-- ===== Ende Toolbar ===== -->

<?php
// ----- Filterzusammenfassung + Zählung (bereinigt, benutzt $activeBadges) -----
$visibleCount = $result ? $result->num_rows : 0;
$activeCount  = count($activeBadges);
?>
<div class="filter-summary">
  <div class="badges">
    <?php if ($activeCount): ?>
      <strong>Aktive Filter (<?= (int)$activeCount ?>):</strong>
      <?php foreach ($activeBadges as $badge):
        $removeKeys = array_keys($badge['remove']); // z. B. ['oh'] oder ['filter']
        $removeLink = url_remove_only($removeKeys);
      ?>
        <a class="badge" href="<?= $removeLink ?>" title="Diesen Filter entfernen">
          <span class="x">×</span> <?= htmlspecialchars($badge['label']) ?>
        </a>
      <?php endforeach; ?>
      <span class="count">
        Treffer gesamt: <?= (int)$total ?> • Diese Seite: <?= (int)$visibleCount ?>
      </span>
      <a class="badge" href="<?= url_remove_only(['filter','oh','material','q','hersteller','publisher','verkaeufer','standort','verkauf']) ?>" title="Alle Filter löschen">Alle Filter löschen</a>
    <?php else: ?>
      <strong>Keine aktiven Filter.</strong>
      <span class="count">Gesamt: <?= (int)$total ?> Einträge</span>
    <?php endif; ?>
  </div>

  <?php if (!$is_guest): ?>
    <div class="actions">
      <a class="btn-new" href="edit.php?<?= htmlspecialchars(http_build_query($params)) ?>">➕ Neuer Eintrag</a>
    </div>
  <?php endif; ?>
</div>

  <!-- Pagination OBERHALB der Tabelle -->
  <?php render_pagination($page, $pages, $params); ?>

  <!-- Tabelle -->
<table class="list">
    <colgroup>
  <col class="col-icon"><!-- 🔍 -->
  <?php if (!$is_guest): ?>
    <col class="col-icon"><!-- 📝 -->
    <col class="col-icon"><!-- 📄 -->
  <?php endif; ?>
  <col class="col-img"><!-- Bild -->
  <col class="col-title"><!-- Bezeichnung -->
  <col class="col-cat"><!-- Kategorie -->
  <col class="col-year"><!-- Jahr -->
  <col class="col-oh"><!-- Original/Homebrew -->
  <col class="col-mat"><!-- Material -->
  <col class="col-zust"><!-- Zustand -->
  <col class="col-vp"><!-- Verpackung -->
  <?php if (!$is_guest): ?>
    <col class="col-loc"><!-- Standort -->
    <col class="col-box"><!-- Box -->
    <col class="col-date col-einkaufsdatum"><!-- Einkaufsdatum -->
  <?php endif; ?>
  <?php if (!$is_guest): ?>
    <col class="col-wert"><!-- Wert -->
    <col class="col-icon"><!-- 🗑️ -->
  <?php endif; ?>
</colgroup>

    <tr>
      <th>🔍</th>
      <?php if (!$is_guest): ?>
        <th>📝</th>
        <th>📄</th>
      <?php endif; ?>
      <th>Bild</th>
      <?php
  // Basis-Spalten (für alle)
  $cols_base = [
    'Bezeichnung' => 'Bezeichnung',
    'Kategorie'   => 'Kategorie',
    'Jahr'        => 'Jahr',
    '`Original/Homebrew`' => 'Original/Homebrew',
    'Material'    => 'Material',
    'Zustand'     => 'Zustand',
    'Verpackung'  => 'Verpackung',
  ];

  // Zusatzspalten nur für Admin
  $cols_admin_extra = [
    'Standort'      => 'Standort',
    'Box'           => 'Box',
    'Einkaufsdatum' => 'Einkaufsdatum',
    'Wert'          => 'Wert', // ← jetzt in der Sort-Loop
  ];

  $cols = $is_guest ? $cols_base : ($cols_base + $cols_admin_extra);

  foreach ($cols as $key => $label) {
    $thClass = ($key === 'Einkaufsdatum') ? ' class="col-einkaufsdatum"' : '';
    echo '<th'.$thClass.'>'.sort_link($key, $label, $sort, $dir, $params).'</th>';
  }
  if (!$is_guest) {
    echo '<th>🗑️</th>';
  }
?>

    </tr>
    <?php while($row = $result->fetch_assoc()):
      $rowP = ['id'=>$row['ID'],'page'=>$page,'sort'=>$sort,'dir'=>$dir];
      foreach(['filter','oh','material','q','hersteller','publisher','verkaeufer','standort','verkauf'] as $p){
        if (!empty($_GET[$p])) $rowP[$p]=$_GET[$p];
      }
    ?>
      <tr>
        <?php
$paramsView = $rowP;

if (!empty($_SESSION['museum_mode'])) {
    $paramsView['museum'] = '1';
}
?>

<td>
  <a href="view.php?<?= http_build_query($paramsView) ?>">🔍</a>
</td>
        <?php if (!$is_guest): ?>
          <td><a href="edit.php?<?= http_build_query($rowP) ?>">📝</a></td>
          <td><a href="duplicate.php?<?= http_build_query($rowP) ?>" title="Datensatz duplizieren">📄</a></td>
        <?php endif; ?>
        <td class="click-open">
  <a class="img-link" href="view.php?<?= http_build_query($rowP) ?>" title="Öffnen">
    <img src="<?= htmlspecialchars($row['SammlungBild1']) ?>" height="60" alt="">
  </a>
</td>
        <td><?= htmlspecialchars($row['Bezeichnung']) ?><?= (($row['Zum Verkauf'] ?? '') === '1') ? ' <span title="Zum Verkauf">🏷️</span>' : '' ?></td>
        <td><?= htmlspecialchars($row['KategorieName']) ?></td>
        <td><?= htmlspecialchars($row['Jahr']) ?></td>
        <td><?= htmlspecialchars($row['Original/Homebrew']) ?></td>
        <td><?= htmlspecialchars($row['MaterialName'] ?? '') ?></td>
<td><?= htmlspecialchars($zustand[$row['Zustand']] ?? '') ?></td>
<td><?= htmlspecialchars($verpackung[$row['Verpackung']] ?? '') ?></td>

<?php if (!$is_guest): ?>
  <td><?= htmlspecialchars($row['StandortName'] ?? '') ?></td>
  <td><?= htmlspecialchars($row['Box'] ?? '') ?></td>
<td class="col-einkaufsdatum">
    <?php
      if (!empty($row['Einkaufsdatum']) && $row['Einkaufsdatum']!=='0000-00-00') {
        echo date('d.m.Y', strtotime($row['Einkaufsdatum']));
      }
    ?>
	</td>
<?php endif; ?>

<?php if (!$is_guest): ?>
  <td><?= htmlspecialchars($row['Wert'] ?? '') ?></td>
  <td><a href="delete.php?<?= http_build_query($rowP) ?>" onclick="return confirm('Wirklich löschen?')">🗑️</a></td>
<?php endif; ?>

      </tr>
    <?php endwhile; ?>
  </table>

  <!-- Pagination UNTERHALB der Tabelle -->
  <?php render_pagination($page, $pages, $params); ?>

  <?php if (!$is_guest): ?>
    <p><a href="edit.php?<?= htmlspecialchars(http_build_query($params)) ?>">➕ Neuer Eintrag</a></p>
  <?php endif; ?>

<a href="#top-marker" class="back-to-top" aria-label="Nach oben">↑ Nach oben</a>

<script>
(function(){
  var btn   = document.getElementById('filterToggleBtn');
  var panel = document.getElementById('filterPanel');
  if (!btn || !panel) return;

  btn.addEventListener('click', function(){
    var isOpen = panel.style.display !== 'none';
    var next   = !isOpen;
    panel.style.display = next ? '' : 'none';
    btn.textContent = next ? '🔼 Schnellfilter ausblenden' : '🔽 Schnellfilter anzeigen';
    document.cookie = 'filterPanelOpen=' + (next ? '1' : '0') + ';path=/;max-age=' + (60*60*24*365) + ';SameSite=Lax';
  });
})();
</script>

<script>
(function(){
  const btn = document.querySelector('.back-to-top');
  if(!btn) return;

  const rootEl = document.documentElement; // <html>

  function toggle(){
    if ((window.scrollY || rootEl.scrollTop || document.body.scrollTop) > 400) {
      btn.classList.add('show');
    } else {
      btn.classList.remove('show');
    }
  }
  window.addEventListener('scroll', toggle, {passive:true});
  toggle();

  btn.addEventListener('click', function(e){
    e.preventDefault();

    // 1) iOS/Allgemein: direkt das Fenster scrollen
    if (typeof window.scrollTo === 'function') {
      try {
        window.scrollTo({ top: 0, behavior: 'smooth' });
      } catch(_) {
        window.scrollTo(0, 0);
      }
    }

    // 2) Zusätzliche Absicherung für Safari
    rootEl.scrollTop = 0;
    document.body.scrollTop = 0;

    // 3) Ultimativer Fallback: Anker setzen
    // (hilft, falls JS unterdrückt/fehlschlägt)
    if (location.hash !== '#top-marker') {
      setTimeout(() => { location.hash = '#top-marker'; }, 60);
    }
  });
})();
</script>
<?php include __DIR__ . '/inc_pwa_footer.php'; ?>
</body>
</html>
