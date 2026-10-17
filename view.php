<?php
require_once __DIR__ . '/inc_errors.php';
require_once __DIR__ . '/inc_icons.php';
require_once __DIR__ . '/inc_nav.php';


date_default_timezone_set('Europe/Berlin');

// ---- Login prüfen (Gäste erlaubt) ----
require_once __DIR__ . '/require_login.php';
require_once __DIR__ . '/inc_security.php';
require_once __DIR__ . '/inc_format.php';
require_once __DIR__ . '/inc_image.php';
require_once __DIR__ . '/inc_filter.php';
if (!function_exists('is_guest')) { function is_guest(): bool { return isset($_SESSION['role']) && $_SESSION['role'] === 'guest'; } }
if (!function_exists('is_admin')) { function is_admin(): bool { return isset($_SESSION['role']) && $_SESSION['role'] === 'admin'; } }
$is_guest = is_guest();

// ---- DB-Verbindung ----
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/inc_guest.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$conn->set_charset('utf8mb4');
@$conn->query("SET time_zone = 'Europe/Berlin'");

// ---- GET-Parameter (ALLE, wie index.php) ----
$id             = intval($_GET['id']        ?? 0);
$page           = max(1, intval($_GET['page'] ?? 1));
$sort           = $_GET['sort']             ?? 'Bezeichnung';
$dir            = strtolower($_GET['dir']   ?? 'asc'); if (!in_array($dir, ['asc','desc'], true)) $dir = 'asc';
$filter         = $_GET['filter']           ?? '';
$kat             = intval($_GET['kat'] ?? 0);              // Kategorie-Knoten
$inkl            = ($_GET['inkl'] ?? '') === '1';          // inkl. Unterkategorien
$oh             = $_GET['oh']               ?? '';
$material       = $_GET['material']         ?? '';
$q              = trim($_GET['q']           ?? '');
$hersteller_id  = intval($_GET['hersteller'] ?? 0);
$publisher_id   = intval($_GET['publisher']  ?? 0);
$verkaeufer_id  = intval($_GET['verkaeufer'] ?? 0);
$standort_id    = intval($_GET['standort']   ?? 0);
$box_id         = trim((string)($_GET['box'] ?? ''));
$verkauf_filter = ($_GET['verkauf'] ?? '') === '1';
$einkaufsdatum_filter = trim((string)($_GET['einkaufsdatum'] ?? ''));
if ($einkaufsdatum_filter !== '' && !preg_match('~^\d{4}-\d{2}-\d{2}$~', $einkaufsdatum_filter)) {
    $einkaufsdatum_filter = '';
}
$backUrl        = safe_back_url($_GET['back'] ?? null, '');  // nur relative App-Ziele

// ---- Sortierung wie index.php ----
$sort_map = [
  'Bezeichnung'         => 'S.Bezeichnung',
  'Kategorie'           => 'K.Kategorie',
  'Jahr'                => 'S.Jahr',
  '`Original/Homebrew`' => 'S.`Original/Homebrew`',
  'Material'            => 'M.Material',
  'Zustand'             => 'Z.Zustand',
  'Verpackung'          => 'VP.Verpackung',
  'Standort'            => 'ST.Standort',
  'Box'                 => box_sort_sql('BX'),
  'Einkaufsdatum'       => 'S.Einkaufsdatum',
  'Link zu YouTube' => 'S.`Link zu YouTube`',
  'Getestet am'         => 'S.`Getestet am`',
  'Getestet Status'     => 'S.`Getestet Status`',
];
$order_by_core = ($sort_map[$sort] ?? 'S.Bezeichnung') . ' ' . strtoupper($dir);
if ($sort === 'Einkaufsdatum' || $sort === 'Getestet am') $order_by_core = "S.`$sort` IS NULL, " . $order_by_core;
$order_by = $order_by_core . ', S.Bezeichnung ASC, S.ID ASC';   // ID als letzter Schlüssel: gleiche Titel bleiben in fester Reihenfolge (Liste, Detail, Bearbeiten, Export)

// ---- WHERE nach aktiven Filtern (EXAKT wie index.php) ----
// Filter wie index.php (gemeinsam in inc_filter.php, mit Prepared Statements)
$flt = sammlung_filter_where([
    'filter' => $filter, 'kat' => $kat, 'inkl' => $inkl, 'oh' => $oh, 'material' => $material, 'q' => $q,
    'hersteller' => $hersteller_id, 'publisher' => $publisher_id, 'verkaeufer' => $verkaeufer_id,
    'standort' => $standort_id, 'box' => $box_id, 'verkauf' => $verkauf_filter,
    'einkaufsdatum' => $einkaufsdatum_filter,
], ['s' => 'S', 'k' => 'K', 'm' => 'M']);
$where_sql = $flt['sql'];

// ---- Einheitliche FROM+JOINs (einmal definieren, überall nutzen) ----
$from_join = "
  FROM Sammlung S
  LEFT JOIN Kategorie   K  ON S.Kategorie   = K.ID
  LEFT JOIN Material    M  ON S.Material    = M.ID
  LEFT JOIN Zustand     Z  ON S.Zustand     = Z.ID
  LEFT JOIN Verpackung  VP ON S.Verpackung  = VP.ID
  LEFT JOIN Standort    ST ON S.Standort    = ST.ID
  LEFT JOIN Box         BX ON S.Box         = BX.ID
";

// ---- Basis-Params für Links (FRÜH bauen, da evtl. beim Redirect genutzt) ----
$params = ['page'=>$page,'sort'=>$sort,'dir'=>$dir];
foreach (['filter','kat','inkl','oh','material','q','hersteller','publisher','verkaeufer','standort','box','verkauf','einkaufsdatum'] as $k) {
    if (isset($_GET[$k]) && $_GET[$k] !== '') $params[$k] = $_GET[$k];
}
$listUrl = 'index.php?' . http_build_query($params);

// ---- Fallback: Wenn id fehlt, zur ersten passenden ID der aktuellen Liste weiterleiten ----
if ($id <= 0) {
    $firstRow = db_select($conn, "
      SELECT S.ID
      $from_join
      $where_sql
      ORDER BY $order_by
      LIMIT 1
    ", $flt['types'], $flt['values'])->fetch_row();
    if ($firstRow) {
        $first_id = (int)$firstRow[0];
        header('Location: view.php?' . http_build_query(array_merge($params,['id'=>$first_id])));
        exit;
    } else {
        error_page(404, 'Keine Treffer', 'Zu dieser Auswahl gibt es keine Einträge.', 'index.php', 'Zur Liste');
    }
}

// ---- Datensatz laden (inkl. sprechender Namen) ----
$sql = "
  SELECT
    S.*,
    K.Kategorie        AS KategorieName,
    M.Material         AS MaterialName,
    Z.Zustand          AS ZustandName,
    VP.Verpackung      AS VerpackungName,
    ST.Standort        AS StandortName,
    H.Hersteller       AS HerstellerName,
    P.Publisher        AS PublisherName,
    VK.`Verkäufer`     AS VerkaeuferName,
    DT.Datentrager     AS DatentraegerName,
    BX.Box             AS BoxName
  $from_join
  LEFT JOIN Hersteller  H  ON S.Hersteller   = H.ID
  LEFT JOIN Publisher   P  ON S.Publisher    = P.ID
  LEFT JOIN `Verkäufer` VK ON S.`Verkäufer`  = VK.ID
  LEFT JOIN Datentraeger DT ON S.Datentraeger= DT.ID
  WHERE S.ID = ?
  LIMIT 1
";
$data = db_select($conn, $sql, 'i', [$id])->fetch_assoc();
if (!$data) { error_page(404, 'Eintrag nicht gefunden', 'Diesen Eintrag gibt es nicht (mehr). Vielleicht wurde er gelöscht.'); }

// ---- VerbautIn: Name des übergeordneten Geräts ----
$verbautInId   = (int)($data['VerbautIn'] ?? 0);
$verbautInName = '';
if ($verbautInId > 0) {
    $vbRow = db_select($conn, "SELECT Bezeichnung FROM Sammlung WHERE ID = ? LIMIT 1", 'i', [$verbautInId])->fetch_row();
    $verbautInName = $vbRow ? $vbRow[0] : '';
}

// ---- Rückwärts: alle Objekte, die dieses Gerät als VerbautIn haben ----
$verbaut_liste = db_select($conn, "
    SELECT ID, Bezeichnung FROM Sammlung
    WHERE VerbautIn = ?
    ORDER BY Bezeichnung
", 'i', [$id])->fetch_all(MYSQLI_ASSOC);

$bez = trim((string)($data['Bezeichnung'] ?? ''));
$pageTitle = $bez !== '' ? $bez.' – Sammlung' : 'Sammlung';

$standortName = trim((string)($data['StandortName'] ?? ''));

// ---- IDs der aktuellen Liste (für Prev/Nächster/Erster/Letzter) ----
$idres = db_select($conn, "
  SELECT S.ID
  $from_join
  $where_sql
  ORDER BY $order_by
", $flt['types'], $flt['values']);
$ids = array_column($idres->fetch_all(MYSQLI_ASSOC), 'ID');

$prev_id = $next_id = $first_id = $last_id = null;
if ($ids) {
    $first_id = $ids[0];
    $last_id  = $ids[count($ids)-1];
    $pos = array_search($id, $ids); // nicht strict
    if ($pos !== false) {
        $prev_id = $ids[$pos-1] ?? null;
        $next_id = $ids[$pos+1] ?? null;
    }
}

// ---- Link-Helfer ----
function link_to_view(array $params, int $targetId): string {
    $p = array_merge($params, ['id'=>$targetId]);
    return 'view.php?' . http_build_query($p);
}
function link_to_index_with(array $params, array $override): string {
    $p = array_merge($params, $override);
    $p['page'] = 1;
    unset($p['id']);
    return 'index.php?' . http_build_query($p);
}

// ---- Anzeige-Aufbereitung ----
$bild = trim((string)($data['SammlungBild1'] ?? ''));
$created  = !empty($data['Erstellt_am'])  ? date('d.m.Y H:i:s', strtotime($data['Erstellt_am']))   : '';
$updated  = !empty($data['Geaendert_am']) ? date('d.m.Y H:i:s', strtotime($data['Geaendert_am'])) : '';

$beschreibung_raw = (string)($data['Beschreibung'] ?? '');
$beschreibung_trimmed = preg_replace("/^\h*\v+/u", '', $beschreibung_raw);
$beschreibung_trimmed = preg_replace("/^\h{1,3}/um", '', $beschreibung_trimmed);
$beschreibung_html = nl2br(htmlspecialchars($beschreibung_trimmed, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));

// Welche Felder sollen Gäste NICHT sehen? (Liste aus config.php, siehe inc_guest.php)
$HIDE_FOR_GUEST = guest_hidden_fields();

// Gruppierung der Felder für die neue Karten-Ansicht (analog zu edit.php)
$VIEW_FIELD_GROUPS = [
  'Kategorie' => 'Grunddaten', 'Jahr' => 'Grunddaten',
  'Original/Homebrew' => 'Grunddaten', 'Hersteller' => 'Grunddaten', 'Publisher' => 'Grunddaten',
  'Verkäufer' => 'Kauf & Test', 'Seriennummer' => 'Grunddaten', 'Anzahl' => 'Grunddaten',

  'Zustand' => 'Zustand & Vollständigkeit', 'Originalverpackung dabei' => 'Zustand & Vollständigkeit',
  'Verpackung' => 'Zustand & Vollständigkeit', 'Datenträger vorhanden' => 'Zustand & Vollständigkeit',
  'Datenträger' => 'Zustand & Vollständigkeit', 'Anleitung dabei' => 'Zustand & Vollständigkeit',
  'Sonstiges dabei' => 'Zustand & Vollständigkeit', 'Material' => 'Zustand & Vollständigkeit',

  'Standort' => 'Standort & Kennung', 'Zum Verkauf' => 'Standort & Kennung', 'Gehört zu' => 'Standort & Kennung',
  'Zugehörige Objekte' => 'Standort & Kennung', 'ISBN' => 'Standort & Kennung',
  'Barcode / EAN' => 'Standort & Kennung', 'Box' => 'Standort & Kennung',

  'Einkaufsdatum' => 'Kauf & Test', 'Einkaufspreis' => 'Kauf & Test', 'Wert' => 'Kauf & Test',
  'Getestet' => 'Kauf & Test',

  'Link zum Blog' => 'Links & Notizen', 'Link zu YouTube' => 'Links & Notizen', 'Bemerkung' => 'Links & Notizen',

  'Erstellt am' => 'Verwaltung', 'Geändert am' => 'Verwaltung',
];
$VIEW_WIDE_FIELDS = ['Bezeichnung','Zustand','Gehört zu','Zugehörige Objekte','Link zum Blog','Link zu YouTube','Bemerkung'];
$VIEW_CURRENT_GROUP = null;

function tr_meta(string $label, string $value = '', bool $rawHtml = false): void {
    global $is_guest, $HIDE_FOR_GUEST, $VIEW_FIELD_GROUPS, $VIEW_WIDE_FIELDS, $VIEW_CURRENT_GROUP;
    if ($is_guest && in_array($label, $HIDE_FOR_GUEST, true)) return;
    if (trim(strip_tags($value)) === '' && stripos($value, '<svg') === false) return;   // leere Felder nicht anzeigen

    $group = $VIEW_FIELD_GROUPS[$label] ?? 'Weitere Angaben';
    if ($group !== $VIEW_CURRENT_GROUP) {
        if ($VIEW_CURRENT_GROUP !== null) echo '</dl></section>';
        echo '<section class="dgruppe"><h2>' . htmlspecialchars($group) . '</h2><dl>';
        $VIEW_CURRENT_GROUP = $group;
    }
    $isWide = in_array($label, $VIEW_WIDE_FIELDS, true);
    echo '<div class="zeile' . ($isWide ? ' breit' : '') . '"><dt>' . htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE) . '</dt><dd>';
    echo $rawHtml ? $value : htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE);
    echo '</dd></div>';
}

// Muss nach dem letzten tr_meta()-Aufruf einmal ausgeführt werden,
// um die letzte offene Gruppe zu schließen.
function tr_meta_close(): void {
    global $VIEW_CURRENT_GROUP;
    if ($VIEW_CURRENT_GROUP !== null) echo '</dl></section>';
    $VIEW_CURRENT_GROUP = null;
}
?>
<?php
// ---- Navigation, Chips und Aktionen für die Darstellung ----
$isMuseum = !empty($_SESSION['museum_mode']);
$navOpt = [
    'is_guest' => $is_guest, 'is_admin' => is_admin(), 'museum' => $isMuseum,
    'export' => (!$isMuseum && (!$is_guest || guest_export_allowed())), 'params' => $params,
];
$selfHref  = link_to_view($params, (int)$id);
$firstHref = $first_id ? link_to_view($params, (int)$first_id) : $selfHref;
$prevHref  = $prev_id  ? link_to_view($params, (int)$prev_id)  : $selfHref;
$nextHref  = $next_id  ? link_to_view($params, (int)$next_id)  : $selfHref;
$lastHref  = $last_id  ? link_to_view($params, (int)$last_id)  : $selfHref;
$backHref  = $backUrl ?: $listUrl;
$editHref  = 'edit.php?' . http_build_query(array_merge($params, ['id' => $id]));
$hatErste  = (bool)($first_id && $id != $first_id);
$hatLetzte = (bool)($last_id && $id != $last_id);
$e = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
/** Navigationsknopf (Erste/Vorheriger/…): ohne Ziel als deaktiviert markiert (kein Inline-Handler) */
$navBtn = function (string $cls, bool $aktiv, string $href, string $titel, string $inhalt) use ($e) {
    return '<a class="' . $cls . ($aktiv ? '' : ' disabled') . '" href="' . $e($href) . '"'
         . ($aktiv ? '' : ' aria-disabled="true" tabindex="-1"') . ' title="' . $e($titel) . '">' . $inhalt . '</a>';
};
$verwandt = [];
if (!empty($data['Hersteller']) && !empty($data['HerstellerName'])) $verwandt[] = ['Alle von Hersteller', $data['HerstellerName'], link_to_index_with($params, ['hersteller' => (int)$data['Hersteller']])];
if (!empty($data['Publisher']) && !empty($data['PublisherName'])) $verwandt[] = ['Alle vom Publisher', $data['PublisherName'], link_to_index_with($params, ['publisher' => (int)$data['Publisher']])];
if (!empty($data['Verkäufer']) && !empty($data['VerkaeuferName']) && !($is_guest && in_array('Verkäufer', $HIDE_FOR_GUEST, true))) $verwandt[] = ['Alle vom Verkäufer', $data['VerkaeuferName'], link_to_index_with($params, ['verkaeufer' => (int)$data['Verkäufer']])];
$hatBild = (bool)image_parse($bild);
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="format-detection" content="telephone=no">
<title><?= $e($pageTitle) ?></title>
<script src="/sammlung/assets/theme-toggle.js?v=6"></script>
<link rel="stylesheet" href="/sammlung/assets/nav.css?v=1">
<link rel="stylesheet" href="/sammlung/assets/detail.css?v=2">
<link rel="stylesheet" href="/sammlung/assets/base.css?v=3">
<link rel="stylesheet" href="/sammlung/assets/print.css?v=2" media="print">
<script src="/sammlung/assets/detail.js?v=2" defer></script>
</head>
<body class="view-page">
<a class="skip-link" href="#inhalt">Zum Inhalt springen</a>

<header class="appbar">
  <p class="appbar-titel"><a href="index.php<?= $isMuseum ? '?museum=1' : '' ?>"><?= $isMuseum ? $e(APP_TITLE_MUSEUM) : $e(APP_TITLE) ?></a></p>
  <?= nav_appnav($navOpt) ?>
  <button type="button" id="themeToggle" class="btn theme-toggle appbar-icon" title="Hell/Dunkel umschalten"></button>
</header>

<main id="inhalt" data-prev="<?= $prev_id ? $e($prevHref) : '' ?>" data-next="<?= $next_id ? $e($nextHref) : '' ?>">

  <!-- Werkzeugleiste (ab 600 px; auf dem iPhone steht die Aktionsleiste unten) -->
  <div class="detail-werkzeug">
    <a class="btn btn-back" href="<?= $e($backHref) ?>"><?= icon('back') ?> Zurück zur Liste</a>
    <?= $navBtn('btn btn-nav', $hatErste, $firstHref, $hatErste ? 'Erster Datensatz' : 'Kein erster Datensatz', icon('back') . ' Erste') ?>
    <?= $navBtn('btn btn-nav', (bool)$prev_id, $prevHref, $prev_id ? 'Vorheriger Datensatz' : 'Kein vorheriger Datensatz', icon('back') . ' Vorheriger') ?>
    <?= $navBtn('btn btn-nav', (bool)$next_id, $nextHref, $next_id ? 'Nächster Datensatz' : 'Kein nächster Datensatz', 'Nächster ' . icon('forward')) ?>
    <?= $navBtn('btn btn-nav', $hatLetzte, $lastHref, $hatLetzte ? 'Letzter Datensatz' : 'Kein letzter Datensatz', 'Letzte ' . icon('forward')) ?>
    <button type="button" class="btn" data-drucken><?= icon('print') ?> Drucken</button>
    <?php if (!$is_guest): ?>
      <span class="spacer"></span>
      <a class="btn btn-edit" href="<?= $e($editHref) ?>"><?= icon('edit') ?> Bearbeiten</a>
      <button type="button" class="btn delete" data-loeschen><?= icon('trash') ?> Löschen</button>
    <?php endif; ?>
  </div>

  <article class="detail<?= $hatBild ? '' : ' ohne-bild' ?>">
    <?php if ($hatBild): ?>
    <div class="detail-bild">
      <button type="button" class="bild-knopf" data-lightbox data-gross="<?= $e(image_url($bild, 1200)) ?>" aria-label="Bild vergrößern">
        <?= image_img($bild, $bez, ['id' => 'mainPic', 'priority' => 'high', 'widths' => [320, 640, 1200], 'sizes' => '(max-width: 899px) 100vw, 480px']) ?>
      </button>
    </div>
    <?php endif; ?>

    <div class="detail-info">
      <h1><?= $e($data['Bezeichnung'] ?? '') ?></h1>
      <div class="chips-detail">
        <?php if (!empty($data['KategorieName'])): ?>
          <a class="chip-d" href="<?= $e(link_to_index_with($params, ['kat' => (int)$data['Kategorie'], 'inkl' => ''])) ?>"><?= $e($data['KategorieName']) ?></a>
        <?php endif; ?>
        <?php if (!empty($data['ZustandName'])): ?><span class="chip-d"><?= $e($data['ZustandName']) ?></span><?php endif; ?>
        <?php if ((int)($data['Zum Verkauf'] ?? 0) === 1): ?><span class="chip-d chip-verkauf"><?= icon('tag') ?> Zum Verkauf</span><?php endif; ?>
      </div>

      <div class="detail-gruppen">
        <?php
          tr_meta('Kategorie',          $data['KategorieName'] ?? '');
          tr_meta('Jahr',               $data['Jahr'] ?? '');
          tr_meta('Original/Homebrew',  $data['Original/Homebrew'] ?? '');
          tr_meta('Hersteller',         $data['HerstellerName'] ?? '');
          tr_meta('Publisher',          $data['PublisherName'] ?? '');
          tr_meta('Seriennummer',       $data['Seriennummer'] ?? '');
          tr_meta('Anzahl',             (string)($data['Anzahl'] ?? ''));
          tr_meta('Zustand',            $data['ZustandName'] ?? '');

          // Original/Nachdruck-Status hübsch formatieren
          $fmtStatus = function(string $v): string {
              if ($v === 'Original')  return 'Original';
              if ($v === 'Nachdruck') return 'Nachdruck / Repro';
              return 'Nein';
          };

          // Vollständigkeit: Status-Feld + zugehöriges Typ-/Detailfeld direkt nebeneinander
          tr_meta('Originalverpackung dabei', $fmtStatus((string)($data['Verpackung Status'] ?? '')));
          tr_meta('Verpackung',         $data['VerpackungName'] ?? '');

          tr_meta('Datenträger vorhanden',    $fmtStatus((string)($data['Datenträger Status'] ?? '')));
          tr_meta('Datenträger',        $data['DatentraegerName'] ?? '');

          tr_meta('Anleitung dabei',          $fmtStatus((string)($data['Anleitung Status'] ?? '')));

          $sonstBeschr = trim((string)($data['Sonstiges Beschreibung'] ?? ''));
          $sonstLabel  = ((int)($data['Sonstiges'] ?? 0) === 1) ? 'Ja' : 'Nein';
          if ($sonstBeschr !== '') $sonstLabel .= ' (' . $sonstBeschr . ')';
          tr_meta('Sonstiges dabei', $sonstLabel);

          tr_meta('Material',           $data['MaterialName'] ?? '');

          tr_meta('Standort', $data['StandortName'] ?? '');
          tr_meta('Zum Verkauf', (int)($data['Zum Verkauf'] ?? 0) === 1 ? 'Ja' : 'Nein');

          // Gehört zu (übergeordnetes Gerät / verbaut in oder zugehörig gelagert)
          if ($verbautInId > 0) {
              $vbLink = '<a href="' . htmlspecialchars(link_to_view($params, $verbautInId)) . '">'
                      . htmlspecialchars($verbautInName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                      . '</a>';
              tr_meta('Gehört zu', $vbLink, true);
          }

          // Zugehörige Objekte (Rückwärtsverknüpfung: alles, was auf dieses Objekt verweist)
          if ($verbaut_liste) {
              $links = array_map(function($r) use ($params) {
                  return '<a href="' . htmlspecialchars(link_to_view($params, (int)$r['ID'])) . '">'
                       . htmlspecialchars($r['Bezeichnung'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                       . '</a>';
              }, $verbaut_liste);
              tr_meta('Zugehörige Objekte', implode('<br>', $links), true);
          }

		  tr_meta('ISBN', $data['ISBN'] ?? '');
		  tr_meta('Barcode / EAN', (string)($data['Barcode'] ?? ''));
          if (!$is_guest && strcasecmp((string)$data['StandortName'], 'Lager') === 0) {
              tr_meta('Box', $data['BoxName'] ?? '');
          }

          $einkaufsdatum = (!empty($data['Einkaufsdatum']) && $data['Einkaufsdatum']!=='0000-00-00')
                           ? date('d.m.Y', strtotime($data['Einkaufsdatum'])) : '';
          tr_meta('Einkaufsdatum', $einkaufsdatum);
          tr_meta('Verkäufer',     $data['VerkaeuferName'] ?? '');

          tr_meta('Einkaufspreis', fmt_eur($data['Einkaufspreis'] ?? null));
          tr_meta('Wert',          fmt_eur($data['Wert'] ?? null));

          // Getestet: Status + Datum kombiniert anzeigen
          $gStatus = $data['Getestet Status'] ?? '';
          $gDatum  = (!empty($data['Getestet am']) && $data['Getestet am'] !== '0000-00-00')
                     ? date('d.m.Y', strtotime($data['Getestet am'])) : '';
          $gLabel  = '';
          if ($gStatus === 'OK')          $gLabel = icon('check') . ' OK';
          elseif ($gStatus === 'Defekt')  $gLabel = icon('close') . ' Defekt';
          if ($gDatum) $gLabel .= ($gLabel ? ' (' . htmlspecialchars($gDatum) . ')' : htmlspecialchars($gDatum));
          tr_meta('Getestet', $gLabel, true);

          $blog = trim((string)($data['Link zum Blog'] ?? ''));
          $blogHtml = $blog
            ? '<a href="'.htmlspecialchars($blog, ENT_QUOTES | ENT_SUBSTITUTE).'" target="_blank" rel="noopener noreferrer">'
               .htmlspecialchars($blog, ENT_QUOTES | ENT_SUBSTITUTE).'</a>'
            : '';
          tr_meta('Link zum Blog', $blogHtml, true);
		  
		  // NEU: Link zu YouTube
$yt = trim((string)($data['Link zu YouTube'] ?? ''));
$ytHtml = $yt
  ? '<a href="'.htmlspecialchars($yt, ENT_QUOTES | ENT_SUBSTITUTE).'" target="_blank" rel="noopener noreferrer">'
     .htmlspecialchars($yt, ENT_QUOTES | ENT_SUBSTITUTE).'</a>'
  : '';
tr_meta('Link zu YouTube', $ytHtml, true);

          if ($beschreibung_trimmed !== '') tr_meta('Bemerkung', $beschreibung_html, true);

          tr_meta('Erstellt am',  $created);
          tr_meta('Geändert am',  $updated);
          tr_meta_close();
        ?>
      </div>

      <div class="detail-drucken-unten"><button type="button" class="btn" data-drucken><?= icon('print') ?> Seite drucken</button></div>

      <?php if ($verwandt): ?>
      <section class="dgruppe verwandt">
        <h2>Mehr aus der Sammlung</h2>
        <div class="verwandt-liste">
          <?php foreach ($verwandt as [$vl, $vn, $vh]): ?>
            <a class="btn btn-search" href="<?= $e($vh) ?>"><?= icon('search') ?> <?= $e($vl) ?>: <?= $e($vn) ?></a>
          <?php endforeach; ?>
        </div>
      </section>
      <?php endif; ?>
    </div>
  </article>
</main>

<!-- Aktionsleiste (nur iPhone, unter 600 px) -->
<nav class="aktionsleiste" aria-label="Aktionen">
  <a href="<?= $e($backHref) ?>"><?= icon('menu') ?><span>Liste</span></a>
  <?= $navBtn('', (bool)$prev_id, $prevHref, 'Vorheriger Datensatz', icon('back') . '<span>Zurück</span>') ?>
  <?= $navBtn('', (bool)$next_id, $nextHref, 'Nächster Datensatz', icon('forward') . '<span>Weiter</span>') ?>
  <?php if (!$is_guest): ?>
    <a href="<?= $e($editHref) ?>"><?= icon('edit') ?><span>Bearbeiten</span></a>
    <button type="button" data-loeschen><?= icon('trash') ?><span>Löschen</span></button>
  <?php endif; ?>
</nav>

<?php if ($hatBild): ?>
<!-- Bild groß -->
<dialog id="lb" class="lb" aria-label="Bild vergrößert">
  <button type="button" class="lb-close" data-lb-zu aria-label="Schließen"><?= icon('close') ?></button>
  <img id="lbImg" alt="<?= $e($bez) ?>">
</dialog>
<?php endif; ?>

<?php if (!$is_guest): ?>
<!-- Löschen bestätigen (POST mit CSRF an delete.php, Filter und Seite werden mitgegeben) -->
<dialog id="loeschDialog" class="loesch-dialog" aria-labelledby="loeschTitel">
  <form method="post" action="delete.php">
    <?= csrf_field() ?>
    <?php foreach (array_merge($params, ['id' => $id]) as $dk => $dv): ?>
      <input type="hidden" name="<?= $e($dk) ?>" value="<?= $e($dv) ?>">
    <?php endforeach; ?>
    <h2 id="loeschTitel">Eintrag löschen?</h2>
    <p>„<?= $e($bez) ?>“ wird unwiderruflich gelöscht.</p>
    <div class="loesch-knoepfe">
      <button type="button" class="btn" data-loeschen-zu>Abbrechen</button>
      <button type="submit" class="btn delete"><?= icon('trash') ?> Löschen</button>
    </div>
  </form>
</dialog>
<?php endif; ?>
<?php include __DIR__ . '/inc_pwa_footer.php'; ?>
</body>
</html>
