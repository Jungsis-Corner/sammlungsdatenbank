<?php
require_once __DIR__ . '/inc_errors.php';
require_once __DIR__ . '/inc_icons.php';
require_once __DIR__ . '/inc_nav.php';

require __DIR__ . '/require_login.php';
require_once __DIR__ . '/inc_security.php';
require_once __DIR__ . '/inc_format.php';
require_once __DIR__ . '/inc_filter.php';
require_once __DIR__ . '/inc_image.php';
$is_guest = is_guest();

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/inc_guest.php';
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) fail_db('Datenbankfehler.', 'Verbindung: ' . $conn->connect_error);

// ---------------------- Lookups ----------------------
function get_lookup($table, $field) {
    global $conn;
    [$t, $f] = lookup_ident($table, $field);
    $res = $conn->query("SELECT ID, $f FROM $t ORDER BY $f");
    $lookup = [];
    while ($row = $res->fetch_assoc()) {
        $lookup[$row['ID']] = $row[$field];
    }
    return $lookup;
}

$kategorien     = get_lookup('Kategorie',    'Kategorie');
$katBaum        = kategorie_baum($conn);   // Hierarchie fürs Dropdown und die Badge
$zustand        = get_lookup('Zustand',      'Zustand');
$verpackung     = get_lookup('Verpackung',   'Verpackung');
$material       = get_lookup('Material',     'Material');
$standortList   = get_lookup('Standort',     'Standort');
/** Name eines Eintrags für das Filter-Badge (eine Einzelabfrage statt der ganzen Liste; Hersteller, Publisher und Verkäufer haben tausend Einträge). */
function lookup_name(string $table, string $field, int $id): string {
    global $conn;
    [$t, $f] = lookup_ident($table, $field);
    $stmt = $conn->prepare("SELECT $f FROM $t WHERE `ID` = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    return (string)($stmt->get_result()->fetch_row()[0] ?? '');
}

// Liste der tatsächlich verwendeten Box-Werte (fürs Dropdown)
$boxList = [];
$boxRes = $conn->query("SELECT b.ID, b.Box FROM Box b WHERE EXISTS (SELECT 1 FROM Sammlung s WHERE s.Box = b.ID) ORDER BY " . box_sort_sql('b'));
if ($boxRes) {
    while ($r = $boxRes->fetch_row()) {
        $boxList[(int)$r[0]] = $r[1];   // ID => Name
    }
}

// ---------------------- Parameter ----------------------
// Alte Links mit filter=<Kategorie-ID> (z. B. go.php, Blog) gelten als exakter Kategorie-Treffer: intern kat=<ID>
if (isset($_GET['filter']) && ctype_digit((string)$_GET['filter'])) {
    if (empty($_GET['kat'])) { $_GET['kat'] = $_GET['filter']; }
    unset($_GET['filter']);
}
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
$verkaufFilter   = ($_GET['verkauf'] ?? '') === '1';

// Einkaufsdatum-Suche (aus <input type="date">, liefert immer YYYY-MM-DD)
$einkaufsdatumFilter = trim((string)($_GET['einkaufsdatum'] ?? ''));
if ($einkaufsdatumFilter !== '' && !preg_match('~^\d{4}-\d{2}-\d{2}$~', $einkaufsdatumFilter)) {
    $einkaufsdatumFilter = '';
}

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
  'Box'                 => box_sort_sql('Box'),
  'Einkaufsdatum'       => 'Sammlung.Einkaufsdatum',
  'Wert'                => 'Sammlung.Wert',
  'Link zu YouTube'     => 'Sammlung.`Link zu YouTube`' // <- NEU
];


// $order_by bauen (Datum: NULLs ans Ende)
$order_by_core = $sort_map[$sort] . ' ' . strtoupper($dir);
if ($sort === 'Einkaufsdatum') {
    $order_by_core = 'Sammlung.Einkaufsdatum IS NULL, ' . $order_by_core;
}
// stabile Zweitsortierung
$order_by = $order_by_core . ', Sammlung.Bezeichnung ASC, Sammlung.ID ASC';   // ID als letzter Schlüssel: gleiche Titel bleiben in fester Reihenfolge (Liste, Detail, Bearbeiten, Export)

// Basis-Params für Links (ohne page)
$params = ['sort' => $sort, 'dir' => $dir];
foreach (['filter','kat','inkl','oh','material','q','hersteller','publisher','verkaeufer','standort','box','verkauf','einkaufsdatum'] as $p) {
    $val = $_GET[$p] ?? null;
    if ($val !== null && $val !== '') {
        $params[$p] = $val;
    }
}

// ---------------------- Aktive Badges (mit Icons) ----------------------
$activeBadges = [];

// Kategorie
if ($filter === 'spiel') {
    $activeBadges[] = ['label' => 'Spiel', 'remove' => ['filter' => '']];
} elseif ($filter === 'spiel_original') {
    $activeBadges[] = ['label' => 'Spiel (Original)', 'remove' => ['filter' => '']];
} elseif ($filter === 'spiel_homebrew') {
    $activeBadges[] = ['label' => 'Spiel (Homebrew)', 'remove' => ['filter' => '']];
}
$kaskade = kategorie_kaskade($katBaum, $kat, $inkl);
if ($kat > 0 && isset($kategorien[$kat])) {
    $katHatKinder = false;
    foreach ($katBaum as $kn) { if ($kn['id'] === $kat) { $katHatKinder = $kn['kinder'] > 0; break; } }
    $activeBadges[] = ['label' => implode(' › ', $kaskade['pfad']) . ($inkl && $katHatKinder ? ' (mit Unterkategorien)' : ''), 'remove' => ['kat' => '', 'inkl' => '']];
}

// Original/Homebrew
if ($oh === 'Original') {
    $activeBadges[] = ['label' => 'Original', 'remove' => ['oh' => '']];
} elseif ($oh === 'Homebrew') {
    $activeBadges[] = ['label' => 'Homebrew', 'remove' => ['oh' => '']];
}

// Material
if ($material_filter !== '') {
    $activeBadges[] = ['label' => 'Material: ' . preg_replace('/^Hardware\s*-\s*/u', '', $material_filter), 'remove' => ['material' => '']];
}

// Suche
if ($search !== '') {
    $activeBadges[] = ['label' => 'Suche: „' . $search . '“', 'remove' => ['q' => '']];
}

// Hersteller/Publisher/Verkäufer/Standort
$herstellerName = $herstellerId ? lookup_name('Hersteller', 'Hersteller', $herstellerId) : '';
$publisherName  = $publisherId  ? lookup_name('Publisher', 'Publisher', $publisherId) : '';
$verkaeuferName = $verkaeuferId ? lookup_name('Verkäufer', 'Verkäufer', $verkaeuferId) : '';
if ($herstellerId && $herstellerName !== '') {
    $activeBadges[] = ['label' => 'Hersteller: ' . $herstellerName, 'remove' => ['hersteller' => '']];
}
if ($publisherId && $publisherName !== '') {
    $activeBadges[] = ['label' => 'Publisher: ' . $publisherName, 'remove' => ['publisher' => '']];
}
if ($verkaeuferId && $verkaeuferName !== '') {
    $activeBadges[] = ['label' => 'Verkäufer: ' . $verkaeuferName, 'remove' => ['verkaeufer' => '']];
}
if ($standortId && isset($standortList[$standortId])) {
    $activeBadges[] = ['label' => 'Standort: ' . $standortList[$standortId], 'remove' => ['standort' => '']];
}
if ($boxId !== '') {
    $activeBadges[] = ['label' => '' . ($boxList[(int)$boxId] ?? ('Box ' . $boxId)), 'remove' => ['box' => '']];
}
if ($verkaufFilter) {
    $activeBadges[] = ['label' => 'Zum Verkauf', 'remove' => ['verkauf' => '']];
}
if ($einkaufsdatumFilter !== '') {
    $activeBadges[] = ['label' => 'Einkauf: ' . date('d.m.Y', strtotime($einkaufsdatumFilter)), 'remove' => ['einkaufsdatum' => '']];
}

// ---------------------- WHERE ----------------------
// gemeinsam in inc_filter.php (Prepared Statements); gleiche Werte für alle Abfragen unten
$flt = sammlung_filter_where([
    'filter' => $filter, 'kat' => $kat, 'inkl' => $inkl, 'oh' => $oh, 'material' => $material_filter, 'q' => $search,
    'hersteller' => $herstellerId, 'publisher' => $publisherId, 'verkaeufer' => $verkaeuferId,
    'standort' => $standortId, 'box' => $boxId, 'verkauf' => $verkaufFilter,
    'einkaufsdatum' => $einkaufsdatumFilter,
]);
$where_sql = $flt['sql'];

// ---------------------- Pagination ----------------------
$limit  = page_size();
if (isset($_GET['pp']) && is_string($_GET['pp']) && ctype_digit($_GET['pp']) && in_array((int)$_GET['pp'], PAGE_SIZES, true)
    && ($_COOKIE['pp'] ?? '') !== $_GET['pp']) {
    // Wahl merken (für alle Seiten und delete.php)
    setcookie('pp', $_GET['pp'], ['expires' => time() + 31536000, 'path' => '/sammlung/', 'secure' => !empty($_SERVER['HTTPS']), 'httponly' => true, 'samesite' => 'Lax']);
}
$page   = max(1, intval($_GET['page'] ?? 1));

// Zähler, Einträge mit Jahr und (nur Admin) Summe Wert in EINER Abfrage; nur die Joins, die der Filter braucht
$agg_sql = "
  SELECT COUNT(*), SUM(Sammlung.Jahr IS NOT NULL)" . ($is_guest ? '' : ', SUM(Sammlung.Wert)') . "
  FROM Sammlung
  LEFT JOIN Kategorie ON Sammlung.Kategorie = Kategorie.ID
  LEFT JOIN Material  ON Sammlung.Material  = Material.ID
  $where_sql
";
$agg = ($aggRes = db_select($conn, $agg_sql, $flt['types'], $flt['values'])) ? $aggRes->fetch_row() : [0, 0, 0];
$total      = (int)($agg[0] ?? 0);
$totalCount = $total;                                     // Einträge unter den aktuellen Filtern
$yearCount  = (int)($agg[1] ?? 0);                        // davon mit Jahr
$sumValue   = $is_guest ? null : (float)($agg[2] ?? 0.0); // Summe Wert (nur Admin)
$pages      = max(1, (int)ceil($total / $limit));
$page       = min($page, $pages);
$offset     = ($page - 1) * $limit;

// ---------------------- Daten ----------------------
// Nur die Spalten, die die Zeile anzeigt (kein Sammlung.*: die Beschreibungstexte werden hier nicht gebraucht)
$sql = "
  SELECT
    Sammlung.ID, Sammlung.SammlungBild1, Sammlung.Bezeichnung, Sammlung.`Zum Verkauf`, Sammlung.Jahr,
    Sammlung.`Original/Homebrew`, Sammlung.Zustand, Sammlung.Verpackung, Sammlung.Einkaufsdatum, Sammlung.Wert,
    Kategorie.Kategorie   AS KategorieName,
    Material.Material     AS MaterialName,
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
  ORDER BY $order_by
  LIMIT $limit OFFSET $offset
";
$result = db_select($conn, $sql, $flt['types'], $flt['values']);

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

/** Einheitliche, ellipsierte Seitenwechsel-Leiste (oben & unten). Unten ($mitOptionen) auf dem iPhone fest am Rand über der unteren Navigation. */
function render_pagination(int $page, int $pages, array $params, bool $mitOptionen = false): void {
  $window = 2;
  $href = fn(int $n) => '?' . htmlspecialchars(http_build_query(array_merge($params, ['page' => $n])));
  if ($pages > 1) {
  echo '<nav class="pagination' . ($mitOptionen ? ' pagination-unten' : '') . '" aria-label="Seitenwechsel' . ($mitOptionen ? ' unten' : '') . '">';
  if ($page > 1) {
      echo '<a class="pg-btn pg-erste" href="' . $href(1) . '">« Erste</a> ';
      echo '<a class="pg-btn pg-zurueck" rel="prev" href="' . $href($page - 1) . '">‹ Zurück</a> ';
  } else {
      echo '<span class="pg-btn pg-aus pg-zurueck" aria-disabled="true">‹ Zurück</span> ';
  }
  echo '<span class="pg-nummern">';
  if ($page - $window > 2) {
      echo '<a href="' . $href(1) . '">1</a> … ';
  }
  for ($i = max(1, $page-$window); $i <= min($pages, $page+$window); $i++) {
      if ($i === $page) {
          echo '<strong aria-current="page">' . $i . '</strong> ';
      } else {
          echo '<a href="' . $href($i) . '">' . $i . '</a> ';
      }
  }
  if ($page + $window < $pages-1) {
      echo '… <a href="' . $href($pages) . '">' . $pages . '</a> ';
  }
  echo '</span>';
  echo '<span class="pg-info">Seite ' . $page . ' von ' . $pages . '</span> ';
  if ($page < $pages) {
      echo '<a class="pg-btn pg-weiter" rel="next" href="' . $href($page + 1) . '">Weiter ›</a> ';
      echo '<a class="pg-btn pg-letzte" href="' . $href($pages) . '">Letzte »</a>';
  } else {
      echo '<span class="pg-btn pg-aus pg-weiter" aria-disabled="true">Weiter ›</span>';
  }
  echo '</nav>';
  }
  if ($mitOptionen) {
      // Seitensprung (GET mit allen aktiven Filtern) und Seitengröße (Cookie pp, gesetzt über ?pp=…)
      echo '<form method="get" class="pg-optionen">';
      foreach ($params as $k => $v) {
          if ($k === 'page' || $k === 'pp' || $v === '' || $v === null || is_array($v)) continue;
          echo '<input type="hidden" name="' . htmlspecialchars((string)$k) . '" value="' . htmlspecialchars((string)$v) . '">';
      }
      echo '<label>Seite <input type="number" name="page" min="1" max="' . $pages . '" value="' . $page . '"> von ' . $pages . '</label>';
      echo '<button type="submit" class="btn">Los</button>';
      echo '<span>pro Seite: ';
      $aktuell = page_size();
      foreach (PAGE_SIZES as $n) {
          // Seite so wählen, dass der erste sichtbare Eintrag sichtbar bleibt (Seite 30 bei 25 = Seite 15 bei 50)
          $neueSeite = intdiv(($page - 1) * $aktuell, $n) + 1;
          echo $n === $aktuell
              ? '<strong>' . $n . '</strong> '
              : '<a href="?' . htmlspecialchars(http_build_query(array_merge($params, ['pp' => $n, 'page' => $neueSeite]))) . '">' . $n . '</a> ';
      }
      echo '</span></form>';
  }
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
<script src="/sammlung/assets/theme-toggle.js?v=6"></script>
<script src="/sammlung/assets/typeahead.js?v=3" defer></script>

<?php
$isMuseum = !empty($_SESSION['museum_mode']);
?>

<title>

<?= $isMuseum
    ? htmlspecialchars(APP_TITLE_MUSEUM)
    : htmlspecialchars(APP_TITLE); ?>
</title>

<link rel="manifest" href="/sammlung/manifest.php">
<meta name="theme-color" content="#ffffff" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="#121418" media="(prefers-color-scheme: dark)">

<link rel="apple-touch-icon" href="/sammlung/icons/apple-touch-icon.png">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="<?= $isMuseum ? 'Museum' : 'Sammlung'; ?>">
  <link rel="stylesheet" href="/sammlung/assets/nav.css?v=2">
  <link rel="stylesheet" href="/sammlung/assets/liste.css?v=11">
  <script src="/sammlung/assets/liste.js?v=8" defer></script>
<link rel="stylesheet" href="/sammlung/assets/base.css?v=4">
<link rel="stylesheet" href="/sammlung/assets/print.css?v=2" media="print">
</head>
<body class="index-page <?= !empty($_SESSION['museum_mode']) ? 'museum' : '' ?>">
<a class="skip-link" href="#inhalt">Zum Inhalt springen</a>
<main id="inhalt" tabindex="-1">

<div id="top-marker" aria-hidden="true"></div>

<?php
$navOpt = [
    'is_guest' => $is_guest, 'is_admin' => is_admin(), 'museum' => $isMuseum,
    'export' => (!$isMuseum && (!$is_guest || guest_export_allowed())), 'params' => $params,
    'neu_href' => $is_guest ? '' : 'edit.php?' . http_build_query($params),
    'home_href' => 'index.php' . ($isMuseum ? '?museum=1' : ''), 'active' => 'sammlung',
];
?>
<!-- ===== Obere Leiste: Titel, Suche, Navigation, Theme-Umschalter ===== -->
<header class="appbar" id="appbar">
  <h1 class="appbar-titel"><a href="<?= htmlspecialchars($navOpt['home_href']) ?>"><?= $isMuseum ? htmlspecialchars(APP_TITLE_MUSEUM) : htmlspecialchars(APP_TITLE) ?></a></h1>
      <form class="searchbox" action="index.php" method="get" role="search">
    <?php
      // aktive Parameter erhalten
      foreach (['filter','kat','inkl','oh','material','hersteller','publisher','verkaeufer','standort','box','verkauf','sort','dir','einkaufsdatum'] as $keep) {
        if (!empty($_GET[$keep])) {
          echo '<input type="hidden" name="'.htmlspecialchars($keep).'" value="'.htmlspecialchars((string)$_GET[$keep]).'">';
        }
      }
      if ($isMuseum) { echo '<input type="hidden" name="museum" value="1">'; }
    ?>
    <label class="sr-only" for="suchfeld">Suchen</label>
    <input type="text" id="suchfeld" name="q" value="<?= htmlspecialchars((string)($search)) ?>" placeholder="Suchen…" enterkeyhint="search">
    <button type="submit"><?= icon('search') ?> Suchen</button>
  </form>
  <?= nav_appnav($navOpt) ?>
  <button type="button" id="themeToggle" class="btn theme-toggle appbar-icon" title="Hell/Dunkel umschalten"></button>
</header>

<?php $flash = flash_pull(); ?>
<?php if ($flash): ?>
  <div class="msg-box <?= htmlspecialchars($flash['type'], ENT_QUOTES, 'UTF-8') ?>" role="status">
    <?= htmlspecialchars($flash['text'], ENT_QUOTES, 'UTF-8') ?>
  </div>
<?php endif; ?>

<?php
// ----- Hilfen für den Filterbereich -----
$activeCount  = count($activeBadges);
$resetKeys    = ['filter','kat','inkl','oh','material','q','hersteller','publisher','verkaeufer','standort','box','verkauf','einkaufsdatum'];
$resetHref    = url_remove_only($resetKeys) . ($isMuseum ? (strpos($_SERVER['REQUEST_URI'],'?')===false ? '?museum=1' : '') : '');
/** Eine Filteroption (Link); aktiv = hervorgehoben, ein Klick auf die aktive Option hebt sie auf, wenn $abwahl gesetzt ist */
$opt = function (string $label, bool $aktiv, string $href) {
    return '<a class="fopt' . ($aktiv ? ' active' : '') . '" href="' . $href . '"' . ($aktiv ? ' aria-current="true"' : '') . '>' . htmlspecialchars($label) . '</a>';
};
$mu = $isMuseum ? ['museum' => '1'] : [];
$gruppeOffen = fn(bool $aktiv, bool $standard = false) => ($aktiv || $standard) ? ' open' : '';
?>

<!-- ===== Kennzahlen ===== -->
<div class="statleiste">
  <div class="stat">
    <span class="chip">Einträge: <strong><?= number_format($totalCount,0,',','.') ?></strong></span>
    <span class="chip">mit Jahr: <strong><?= number_format($yearCount,0,',','.') ?></strong></span>
    <?php if (!$is_guest && isset($sumValue) && !$isMuseum): ?>
      <span class="chip">Σ&nbsp;Wert: <strong><?= number_format($sumValue,2,',','.') ?> €</strong></span>
    <?php endif; ?>
  </div>
</div>

<div class="liste-layout<?= $filterPanelOpen ? '' : ' filter-collapsed' ?>" id="listeLayout">

  <!-- ===== Filterleiste über der Liste: Knopf, aktive Filter als Chips, Neuer Eintrag ===== -->
  <div class="liste-kopf">
    <button type="button" id="filterToggle" class="filter-toggle-btn" aria-controls="filterPanel" aria-expanded="<?= $filterPanelOpen ? 'true' : 'false' ?>">
      <?= icon('filter') ?> Filter<?php if ($activeCount): ?> <span class="count-badge"><?= (int)$activeCount ?></span><?php endif; ?>
    </button>

    <div class="chips" role="group" aria-label="Aktive Filter">
      <?php foreach ($activeBadges as $badge):
        $removeLink = url_remove_only(array_keys($badge['remove'])); ?>
        <a class="chip-filter" href="<?= $removeLink ?>" title="Diesen Filter entfernen">
          <?= htmlspecialchars($badge['label']) ?><?= icon('close', 'Filter entfernen') ?>
        </a>
      <?php endforeach; ?>
      <?php if ($activeCount > 1): ?>
        <a class="chip-filter chip-alle" href="<?= $resetHref ?>">Alle Filter löschen</a>
      <?php endif; ?>
    </div>

    <span class="treffer" role="status">
      <?= $activeCount ? 'Treffer: ' . (int)$total : 'Gesamt: ' . (int)$total . ' Einträge' ?>
    </span>
    <span class="spacer"></span>
    <?php if (!$is_guest): ?>
      <a class="btn-new" href="edit.php?<?= htmlspecialchars(http_build_query($params)) ?>"><?= icon('plus') ?> Neuer Eintrag</a>
    <?php endif; ?>
  </div>

  <div class="filter-backdrop" id="filterBackdrop" hidden></div>
  <!-- ===== Filter: Seitenleiste (ab 900 px) bzw. Bottom-Sheet ===== -->
  <aside class="filter-panel" id="filterPanel" aria-label="Filter">
    <div class="filter-panel-kopf">
      <span>Filter<?= $activeCount ? ' (' . (int)$activeCount . ')' : '' ?></span>
      <button type="button" class="btn" data-filter-close>Fertig</button>
    </div>

    <?php if (!$isMuseum): ?>
    <!-- Kategorie: Marke → Plattform → Typ (Parameter kat und inkl) -->
    <details class="fgroup"<?= $gruppeOffen($kat > 0, true) ?>>
      <summary>Kategorie</summary>
      <?php foreach (['s1' => 'Marke / Plattform', 's2' => 'Plattform', 's3' => 'Typ'] as $stufe => $stufeLabel):
        $st = $kaskade[$stufe]; if (!$st) continue; ?>
        <label class="flabel" for="kat-<?= $stufe ?>"><?= $stufeLabel ?></label>
        <select id="kat-<?= $stufe ?>" data-kat>
          <?php if ($stufe === 's1'): ?>
            <option value="">— alle Kategorien —</option>
          <?php elseif ($stufe === 's2'): ?>
            <option value="<?= htmlspecialchars($st['leer']) ?>">— alle Plattformen —</option>
          <?php elseif ($stufe === 's3' && $st['wahl'] === ''): ?>
            <option value="" selected>— Typ wählen —</option>
          <?php endif; ?>
          <?php foreach ($st['optionen'] as [$wert, $name]): ?>
            <option value="<?= htmlspecialchars($wert) ?>"<?= $wert === $st['wahl'] ? ' selected' : '' ?>><?= htmlspecialchars($name) ?></option>
          <?php endforeach; ?>
        </select>
      <?php endforeach; ?>
    </details>
    <?php endif; ?>

    <!-- Spiele und Herkunft -->
    <details class="fgroup" open>
      <summary>Spiele und Herkunft</summary>
      <div class="fopts">
        <?= $opt('Spiel', $filter === 'spiel', $build(['filter' => 'spiel'] + $mu)) ?>
        <?= $opt('Spiel + Original', $filter === 'spiel_original', $build(['filter' => 'spiel_original'] + $mu)) ?>
        <?= $opt('Spiel + Homebrew', $filter === 'spiel_homebrew', $build(['filter' => 'spiel_homebrew'] + $mu)) ?>
        <?= $opt('Kein Spielefilter', $filter === '', $build(['filter' => null] + $mu)) ?>
      </div>
      <div class="fopts">
        <?= $opt('Nur Original', $oh === 'Original', $build(['oh' => $oh === 'Original' ? null : 'Original'] + $mu)) ?>
        <?= $opt('Nur Homebrew', $oh === 'Homebrew', $build(['oh' => $oh === 'Homebrew' ? null : 'Homebrew'] + $mu)) ?>
        <a class="fopt<?= $verkaufFilter ? ' active' : '' ?>" href="<?= $build(['verkauf' => $verkaufFilter ? null : '1'] + $mu) ?>"<?= $verkaufFilter ? ' aria-current="true"' : '' ?>><?= icon('tag') ?> Zum Verkauf</a>
      </div>
    </details>

    <!-- Material -->
    <details class="fgroup"<?= $gruppeOffen($material_filter !== '', true) ?>>
      <summary>Material</summary>
      <div class="fopts">
        <?= $opt('Alle Materialien', $material_filter === '', $build(['material' => null] + $mu)) ?>
        <?php foreach ($material as $nameM):
          $labelM = preg_replace('/^Hardware\s*-\s*/u', '', (string)$nameM); ?>
          <?= $opt($labelM, $material_filter === $nameM, $build(['material' => $nameM] + $mu)) ?>
        <?php endforeach; ?>
      </div>
    </details>

    <?php if (!$isMuseum): ?>
    <!-- Hersteller, Publisher, Verkäufer: Suchfeld mit Vorschlägen (api_lookup.php) -->
    <details class="fgroup"<?= $gruppeOffen($herstellerId || $publisherId || $verkaeuferId) ?>>
      <summary>Hersteller und Publisher</summary>
      <?php foreach ([
          ['hersteller', 'Hersteller', $herstellerId, $herstellerName],
          ['publisher',  'Publisher',  $publisherId,  $publisherName],
          ['verkaeufer', 'Verkäufer',  $verkaeuferId, $verkaeuferName],
      ] as [$taKey, $taLabel, $taId, $taName]): ?>
        <?php if ($taKey === 'verkaeufer' && $is_guest && in_array('Verkäufer', guest_hidden_fields(), true)) continue; ?>
        <label class="flabel" for="ta-<?= $taKey ?>"><?= htmlspecialchars($taLabel) ?></label>
        <?php if ($taId): ?>
          <a class="fopt active" href="<?= url_remove_only([$taKey]) ?>" title="Filter entfernen"><?= htmlspecialchars($taName !== '' ? $taName : '#' . $taId) ?> <?= icon('close', 'Filter entfernen') ?></a>
        <?php else: ?>
          <input type="text" id="ta-<?= $taKey ?>" data-ta="<?= $taKey ?>" data-ta-nav="<?= $taKey ?>" placeholder="<?= htmlspecialchars($taLabel) ?> suchen…">
        <?php endif; ?>
      <?php endforeach; ?>
    </details>

    <?php if (!$is_guest): ?>
    <!-- Besitz: Standort, Box, Einkaufsdatum -->
    <details class="fgroup"<?= $gruppeOffen($standortId > 0 || $boxId !== '' || $einkaufsdatumFilter !== '') ?>>
      <summary>Standort und Einkauf</summary>
      <label class="flabel" for="f-standort">Standort</label>
      <select id="f-standort" data-nav="standort">
        <option value="">— alle —</option>
        <?php foreach ($standortList as $idS => $nameS): ?>
          <option value="<?= (int)$idS ?>"<?= $standortId === (int)$idS ? ' selected' : '' ?>><?= htmlspecialchars($nameS) ?></option>
        <?php endforeach; ?>
      </select>
      <label class="flabel" for="f-box">Box</label>
      <select id="f-box" data-nav="box">
        <option value="">— alle —</option>
        <?php foreach ($boxList as $bId => $bName): ?>
          <option value="<?= (int)$bId ?>"<?= ($boxId !== '' && (int)$boxId === (int)$bId) ? ' selected' : '' ?>><?= htmlspecialchars($bName) ?></option>
        <?php endforeach; ?>
      </select>
      <label class="flabel" for="f-datum">Einkaufsdatum</label>
      <input type="date" id="f-datum" data-nav="einkaufsdatum" value="<?= htmlspecialchars($einkaufsdatumFilter) ?>">
    </details>
    <?php endif; ?>
    <?php endif; ?>

    <a class="filter-reset btn-reset" href="<?= $resetHref ?>">Alle Filter zurücksetzen</a>

    <div class="filter-panel-fuss">
      <a class="btn-reset btn" href="<?= $resetHref ?>">Zurücksetzen</a>
      <button type="button" class="btn save" data-filter-close>Ergebnisse anzeigen (<?= (int)$total ?>)</button>
    </div>
  </aside>

  <div class="liste-ergebnis">

<?php
  // Kontext für Duplizieren/Löschen (Filter, Seite, Sortierung) einmal an der Tabelle statt in jeder Zeile
  $ctx = ['page' => $page, 'sort' => $sort, 'dir' => $dir];
  foreach (['filter','kat','inkl','oh','material','q','hersteller','publisher','verkaeufer','standort','box','verkauf','einkaufsdatum'] as $p) {
      if (!empty($_GET[$p])) $ctx[$p] = $_GET[$p];
  }
  // gewählte Darstellung (Cookie "la"): tabelle | raster | auto (Standard je nach Bildschirmbreite, siehe liste.css)
  $ansicht = in_array($_COOKIE['la'] ?? '', ['tabelle', 'raster'], true) ? $_COOKIE['la'] : 'auto';

  // Spalten: key => [Beschriftung, CSS-Klasse]; Reihenfolge = Reihenfolge in der Tabelle
  $cols = [
    'Bezeichnung'         => ['Bezeichnung',        'c-titel'],
    'Kategorie'           => ['Kategorie',          'c-kat'],
    'Zustand'             => ['Zustand',            'c-zust'],
    'Jahr'                => ['Jahr',               'c-jahr'],
  ];
  if (!$is_guest) { $cols['Wert'] = ['Wert', 'c-wert']; }
  $cols += [
    'Material'            => ['Material',           'c-mat'],
    '`Original/Homebrew`' => ['Original/Homebrew',  'c-oh'],
    'Verpackung'          => ['Verpackung',         'c-vp'],
  ];
  if (!$is_guest) {
    $cols += ['Standort' => ['Standort', 'c-loc'], 'Box' => ['Box', 'c-box'], 'Einkaufsdatum' => ['Einkaufsdatum', 'c-datum']];
  }
?>
  <div class="liste-werkzeug">
    <div class="ansicht-umschalter" role="group" aria-label="Darstellung">
      <button type="button" data-ansicht-wahl="tabelle" aria-pressed="<?= $ansicht === 'tabelle' ? 'true' : 'false' ?>"><?= icon('menu') ?> Tabelle</button>
      <button type="button" data-ansicht-wahl="raster" aria-pressed="<?= $ansicht === 'raster' ? 'true' : 'false' ?>"><?= icon('filter') ?> Raster</button>
    </div>
    <label class="sort-wahl">Sortieren
      <select data-sort aria-label="Sortierung">
        <?php foreach ($cols as $ck => [$cLabel]): foreach (['asc' => '↑', 'desc' => '↓'] as $cd => $pfeil): ?>
          <option value="<?= htmlspecialchars($ck . ':' . $cd) ?>"<?= ($sort === $ck && $dir === $cd) ? ' selected' : '' ?>><?= htmlspecialchars($cLabel) ?> <?= $pfeil ?></option>
        <?php endforeach; endforeach; ?>
      </select>
    </label>
  </div>

<?php if ($total === 0): ?>
  <div class="leer-zustand" role="status">
    <p class="leer-titel">Keine Treffer</p>
    <p><?= $activeCount ? 'Zu diesen Filtern gibt es keine Einträge. Entferne einen Filter oder setze alle zurück.' : 'In der Sammlung gibt es noch keine Einträge.' ?></p>
    <?php if ($activeCount): ?><p><a class="btn save" href="<?= $resetHref ?>">Filter zurücksetzen</a></p><?php endif; ?>
  </div>
<?php else: ?>

  <!-- Pagination OBERHALB der Tabelle -->
  <?php render_pagination($page, $pages, $params); ?>

<div class="liste-ergebnis-inhalt" data-ansicht="<?= $ansicht ?>">
<table class="list" role="table" data-ctx="<?= htmlspecialchars(json_encode($ctx), ENT_QUOTES, 'UTF-8') ?>" data-csrf="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
  <caption class="sr-only">Sammlung, Seite <?= (int)$page ?> von <?= (int)$pages ?>, <?= (int)$total ?> Einträge</caption>
  <thead>
    <tr role="row">
      <th scope="col" class="c-bild"><span class="sr-only">Bild</span></th>
      <?php foreach ($cols as $ck => [$cLabel, $cClass]):
        $aria = ($sort === $ck) ? ($dir === 'asc' ? ' aria-sort="ascending"' : ' aria-sort="descending"') : ''; ?>
        <th scope="col" class="<?= $cClass ?>"<?= $aria ?>><?= sort_link($ck, $cLabel, $sort, $dir, $params) ?></th>
      <?php endforeach; ?>
      <?php if (!$is_guest): ?><th scope="col" class="c-akt"><span class="sr-only">Aktionen</span></th><?php endif; ?>
    </tr>
  </thead>
  <tbody>
    <?php ob_start(); while($row = $result->fetch_assoc()):
      $rowP = ['id'=>$row['ID'],'page'=>$page,'sort'=>$sort,'dir'=>$dir];
      foreach(['filter','kat','inkl','oh','material','q','hersteller','publisher','verkaeufer','standort','box','verkauf','einkaufsdatum'] as $p){
        if (!empty($_GET[$p])) $rowP[$p]=$_GET[$p];
      }
      $paramsView = $rowP;
      if (!empty($_SESSION['museum_mode'])) { $paramsView['museum'] = '1'; }
      $viewHref = 'view.php?' . htmlspecialchars(http_build_query($paramsView));
      $bez = (string)($row['Bezeichnung'] ?? '');
    ?>
      <tr role="row" class="liste-zeile" data-href="<?= $viewHref ?>">
        <td role="cell" class="c-bild"><a class="img-link" href="<?= $viewHref ?>" tabindex="-1" aria-hidden="true"><?= image_img($row['SammlungBild1'] ?? '', $bez, ['widths' => [80, 160, 320], 'sizes' => '(max-width: 599px) 72px, (max-width: 899px) 25vw, 90px']) ?></a></td>
        <td role="cell" class="c-titel<?= ((int)($row['Zum Verkauf'] ?? 0) === 1) ? ' hat-tag' : '' ?>"><a class="titel-link" href="<?= $viewHref ?>"><?= htmlspecialchars($bez) ?></a><?= ((int)($row['Zum Verkauf'] ?? 0) === 1) ? ' <span class="verkauf-tag" title="Zum Verkauf">' . icon('tag', 'Zum Verkauf') . '</span>' : '' ?></td>
        <td role="cell" class="c-kat"><?= htmlspecialchars($row['KategorieName'] ?? '') ?></td>
        <td role="cell" class="c-zust"><?= htmlspecialchars($zustand[$row['Zustand'] ?? ''] ?? '') ?></td>
        <td role="cell" class="c-jahr"><?= htmlspecialchars((string)($row['Jahr'] ?? '')) ?></td>
        <?php if (!$is_guest): ?>
          <td role="cell" class="c-wert"><?= htmlspecialchars(fmt_dec($row['Wert'] ?? null)) ?></td>
        <?php endif; ?>
        <td role="cell" class="c-mat"><?= htmlspecialchars($row['MaterialName'] ?? '') ?></td>
        <td role="cell" class="c-oh"><?= htmlspecialchars($row['Original/Homebrew'] ?? '') ?></td>
        <td role="cell" class="c-vp"><?= htmlspecialchars($verpackung[$row['Verpackung'] ?? ''] ?? '') ?></td>
        <?php if (!$is_guest): ?>
          <td role="cell" class="c-loc"><?= htmlspecialchars($row['StandortName'] ?? '') ?></td>
          <td role="cell" class="c-box"><?= htmlspecialchars($row['BoxName'] ?? '') ?></td>
          <td role="cell" class="c-datum"><?php
            if (!empty($row['Einkaufsdatum']) && $row['Einkaufsdatum'] !== '0000-00-00') { echo date('d.m.Y', strtotime($row['Einkaufsdatum'])); }
          ?></td>
        <?php endif; ?>
        <?php if (!$is_guest): ?>
        <td role="cell" class="c-akt">
          <?php if (!$is_guest): ?>
            <a class="akt" href="edit.php?<?= htmlspecialchars(http_build_query($rowP)) ?>" title="Bearbeiten"><?= icon('edit', 'Bearbeiten') ?></a>
            <a class="akt row-action" href="#" data-action="delete.php" data-confirm="Wirklich löschen?" data-id="<?= (int)$row['ID'] ?>" title="Löschen"><?= icon('trash', 'Löschen') ?></a>
          <?php endif; ?>
        </td>
        <?php endif; ?>
      </tr>
    <?php endwhile; echo preg_replace('~>\s+<~', '><', preg_replace('~\s{2,}~', ' ', ob_get_clean())); /* Leerraum der Zeilen entfernen */ ?>
  </tbody>
</table>
</div>

  <!-- Pagination UNTERHALB der Tabelle -->
  <?php render_pagination($page, $pages, $params, true); ?>
<?php endif; /* Ende: Treffer vorhanden */ ?>

  <?php if (!$is_guest): ?>
    <p class="neu-unten"><a href="edit.php?<?= htmlspecialchars(http_build_query($params)) ?>"><?= icon('plus') ?> Neuer Eintrag</a></p>
  <?php endif; ?>

<a href="#top-marker" class="back-to-top" aria-label="Nach oben"><?= icon('up') ?> Nach oben</a>

  </div><!-- Ende .liste-ergebnis -->
</div><!-- Ende .liste-layout -->
<?php include __DIR__ . '/inc_pwa_footer.php'; ?>
</main>
<?= nav_bottom($navOpt) ?>
</body>
</html>
