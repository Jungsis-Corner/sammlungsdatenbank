<?php
ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(E_ALL);

register_shutdown_function(function () {
    $e = error_get_last();
    if (!$e) return;
    echo "<pre style='background:#fee;border:1px solid #f00;padding:10px;white-space:pre-wrap'>";
    echo "🚨 PHP‐Fatal in view.php on line {$e['line']}:\n";
    echo htmlspecialchars($e['message'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    echo "</pre>";
});

date_default_timezone_set('Europe/Berlin');

// ---- Login prüfen (Gäste erlaubt) ----
require_once __DIR__ . '/require_login.php';
if (!function_exists('is_guest')) { function is_guest(): bool { return isset($_SESSION['role']) && $_SESSION['role'] === 'guest'; } }
if (!function_exists('is_admin')) { function is_admin(): bool { return isset($_SESSION['role']) && $_SESSION['role'] === 'admin'; } }
$is_guest = is_guest();

// ---- DB-Verbindung ----
require __DIR__ . '/config.php';
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
$oh             = $_GET['oh']               ?? '';
$material       = $_GET['material']         ?? '';
$q              = trim($_GET['q']           ?? '');
$hersteller_id  = intval($_GET['hersteller'] ?? 0);
$publisher_id   = intval($_GET['publisher']  ?? 0);
$verkaeufer_id  = intval($_GET['verkaeufer'] ?? 0);
$standort_id    = intval($_GET['standort']   ?? 0);
$backUrl        = $_GET['back']             ?? null;

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
  'Box'                 => 'S.Box',
  'Einkaufsdatum'       => 'S.Einkaufsdatum',
  'Link zu YouTube' => 'S.`Link zu YouTube`',
  'Getestet am'         => 'S.`Getestet am`',
  'Getestet Status'     => 'S.`Getestet Status`',
];
$order_by_core = ($sort_map[$sort] ?? 'S.Bezeichnung') . ' ' . strtoupper($dir);
if ($sort === 'Einkaufsdatum' || $sort === 'Getestet am') $order_by_core = "S.`$sort` IS NULL, " . $order_by_core;
$order_by = $order_by_core . ', S.Bezeichnung ASC';

// ---- WHERE nach aktiven Filtern (EXAKT wie index.php) ----
$where = [];
if ($filter==='spiel')              $where[] = "K.Kategorie LIKE '%Spiel%'";
elseif ($filter==='spiel_original') $where[] = "K.Kategorie LIKE '%Spiel%' AND S.`Original/Homebrew`='Original'";
elseif ($filter==='spiel_homebrew') $where[] = "K.Kategorie LIKE '%Spiel%' AND S.`Original/Homebrew`='Homebrew'";
elseif (is_numeric($filter))        $where[] = "S.Kategorie=" . (int)$filter;

if     ($oh==='Original')           $where[] = "S.`Original/Homebrew`='Original'";
elseif ($oh==='Homebrew')           $where[] = "S.`Original/Homebrew`='Homebrew'";

// WICHTIG: wie index.php -> LIKE '…%'
if ($material!=='')                 $where[] = "M.Material LIKE '" . $conn->real_escape_string($material) . "%'";

if ($q!=='')                        $where[] = "S.Bezeichnung LIKE '%" . $conn->real_escape_string($q) . "%'";
if ($hersteller_id)                 $where[] = "S.Hersteller=$hersteller_id";
if ($publisher_id)                  $where[] = "S.Publisher=$publisher_id";
if ($verkaeufer_id)                 $where[] = "S.`Verkäufer`=$verkaeufer_id";
if ($standort_id)                   $where[] = "S.Standort=$standort_id";
$where_sql = $where ? 'WHERE '.implode(' AND ',$where) : '';

// ---- Einheitliche FROM+JOINs (einmal definieren, überall nutzen) ----
$from_join = "
  FROM Sammlung S
  LEFT JOIN Kategorie   K  ON S.Kategorie   = K.ID
  LEFT JOIN Material    M  ON S.Material    = M.ID
  LEFT JOIN Zustand     Z  ON S.Zustand     = Z.ID
  LEFT JOIN Verpackung  VP ON S.Verpackung  = VP.ID
  LEFT JOIN Standort    ST ON S.Standort    = ST.ID
";

// ---- Basis-Params für Links (FRÜH bauen, da evtl. beim Redirect genutzt) ----
$params = ['page'=>$page,'sort'=>$sort,'dir'=>$dir];
foreach (['filter','oh','material','q','hersteller','publisher','verkaeufer','standort'] as $k) {
    if (isset($_GET[$k]) && $_GET[$k] !== '') $params[$k] = $_GET[$k];
}
$listUrl = 'index.php?' . http_build_query($params);

// ---- Fallback: Wenn id fehlt, zur ersten passenden ID der aktuellen Liste weiterleiten ----
if ($id <= 0) {
    $firstRow = $conn->query("
      SELECT S.ID
      $from_join
      $where_sql
      ORDER BY $order_by
      LIMIT 1
    ")->fetch_row();
    if ($firstRow) {
        $first_id = (int)$firstRow[0];
        header('Location: view.php?' . http_build_query(array_merge($params,['id'=>$first_id])));
        exit;
    } else {
        http_response_code(404);
        exit('Keine passenden Datensätze.');
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
    DT.Datentrager     AS DatentraegerName
  $from_join
  LEFT JOIN Hersteller  H  ON S.Hersteller   = H.ID
  LEFT JOIN Publisher   P  ON S.Publisher    = P.ID
  LEFT JOIN `Verkäufer` VK ON S.`Verkäufer`  = VK.ID
  LEFT JOIN Datentraeger DT ON S.Datentraeger= DT.ID
  WHERE S.ID = $id
  LIMIT 1
";
$data = $conn->query($sql)->fetch_assoc();
if (!$data) { http_response_code(404); exit('Datensatz nicht gefunden.'); }

// ---- VerbautIn: Name des übergeordneten Geräts ----
$verbautInId   = (int)($data['VerbautIn'] ?? 0);
$verbautInName = '';
if ($verbautInId > 0) {
    $vbRow = $conn->query("SELECT Bezeichnung FROM Sammlung WHERE ID = {$verbautInId} LIMIT 1")->fetch_row();
    $verbautInName = $vbRow ? $vbRow[0] : '';
}

// ---- Rückwärts: alle Objekte, die dieses Gerät als VerbautIn haben ----
$verbaut_liste = $conn->query("
    SELECT ID, Bezeichnung FROM Sammlung
    WHERE VerbautIn = {$id}
    ORDER BY Bezeichnung
")->fetch_all(MYSQLI_ASSOC);

$bez = trim((string)($data['Bezeichnung'] ?? ''));
$pageTitle = $bez !== '' ? $bez.' – Sammlung' : 'Sammlung';

$standortName = trim((string)($data['StandortName'] ?? ''));

// ---- IDs der aktuellen Liste (für Prev/Nächster/Erster/Letzter) ----
$idres = $conn->query("
  SELECT S.ID
  $from_join
  $where_sql
  ORDER BY $order_by
");
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

// Welche Felder sollen Gäste NICHT sehen?
$HIDE_FOR_GUEST = ['Standort','Box','Einkaufspreis','Wert','Getestet'];

// Gruppierung der Felder für die neue Karten-Ansicht (analog zu edit.php)
$VIEW_FIELD_GROUPS = [
  'Bezeichnung' => 'Grunddaten', 'Kategorie' => 'Grunddaten', 'Jahr' => 'Grunddaten',
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

    $group = $VIEW_FIELD_GROUPS[$label] ?? 'Weitere Angaben';
    if ($group !== $VIEW_CURRENT_GROUP) {
        if ($VIEW_CURRENT_GROUP !== null) echo '</div></div>'; // .form-grid + .form-group schließen
        echo '<div class="form-group"><h3>'.htmlspecialchars($group).'</h3><div class="form-grid">';
        $VIEW_CURRENT_GROUP = $group;
    }

    $isWide = in_array($label, $VIEW_WIDE_FIELDS, true);
    echo '<div class="field'.($isWide ? ' wide' : '').'">';
    echo '<span class="label">'.htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE).'</span>';
    echo '<span class="value">';
    echo $rawHtml ? $value : htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE);
    echo '</span></div>';
}

// Muss nach dem letzten tr_meta()-Aufruf einmal ausgeführt werden,
// um die letzte offene Gruppe zu schließen.
function tr_meta_close(): void {
    global $VIEW_CURRENT_GROUP;
    if ($VIEW_CURRENT_GROUP !== null) echo '</div></div>';
    $VIEW_CURRENT_GROUP = null;
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="format-detection" content="telephone=no">
<title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
<link rel="stylesheet" href="/sammlung/assets/app.css?v=9">
</head>
<body class="view-page">
  <h1><?= htmlspecialchars($data['Bezeichnung'] ?? '') ?></h1>

  <!-- Toolbar oben -->
  <div class="toolbar">
    <a class="btn-back" href="<?= htmlspecialchars($backUrl ?: $listUrl) ?>">← Zurück zur Liste</a>
    <a class="btn-edit" href="edit.php?<?= htmlspecialchars(http_build_query(array_merge($params,['id'=>$id]))) ?>">📝 Bearbeiten</a>

    <?php
      $selfHref  = link_to_view($params, (int)$id);
      $firstHref = $first_id ? link_to_view($params, (int)$first_id) : $selfHref;
      $prevHref  = $prev_id  ? link_to_view($params, (int)$prev_id)  : $selfHref;
      $nextHref  = $next_id  ? link_to_view($params, (int)$next_id)  : $selfHref;
      $lastHref  = $last_id  ? link_to_view($params, (int)$last_id)  : $selfHref;
    ?>

    <a class="btn-nav<?= $first_id && $id!=$first_id ? '' : ' disabled' ?>"
       href="<?= htmlspecialchars($firstHref) ?>"
       <?= ($first_id && $id!=$first_id) ? '' : 'aria-disabled="true" tabindex="-1" onclick="return false;"' ?>
       title="<?= ($first_id && $id!=$first_id) ? 'Erster Datensatz' : 'Kein erster Datensatz' ?>">« Erste</a>

    <a class="btn-nav<?= $prev_id ? '' : ' disabled' ?>"
       href="<?= htmlspecialchars($prevHref) ?>"
       <?= $prev_id ? '' : 'aria-disabled="true" tabindex="-1" onclick="return false;"' ?>
       title="<?= $prev_id ? 'Vorheriger Datensatz' : 'Kein vorheriger Datensatz' ?>">◀️ Vorheriger</a>

    <a class="btn-nav<?= $next_id ? '' : ' disabled' ?>"
       href="<?= htmlspecialchars($nextHref) ?>"
       <?= $next_id ? '' : 'aria-disabled="true" tabindex="-1" onclick="return false;"' ?>
       title="<?= $next_id ? 'Nächster Datensatz' : 'Kein nächster Datensatz' ?>">Nächster ▶️</a>

    <a class="btn-nav<?= $last_id && $id!=$last_id ? '' : ' disabled' ?>"
       href="<?= htmlspecialchars($lastHref) ?>"
       <?= ($last_id && $id!=$last_id) ? '' : 'aria-disabled="true" tabindex="-1" onclick="return false;"' ?>
       title="<?= ($last_id && $id!=$last_id) ? 'Letzter Datensatz' : 'Kein letzter Datensatz' ?>">Letzte »</a>

    <?php if (!empty($data['Hersteller']) && !empty($data['HerstellerName'])): ?>
      <a class="btn-search" href="<?= htmlspecialchars(link_to_index_with($params, ['hersteller'=>(int)$data['Hersteller']])) ?>">🔎 Alle von Hersteller: <?= htmlspecialchars($data['HerstellerName']) ?></a>
    <?php endif; ?>
    <?php if (!empty($data['Publisher']) && !empty($data['PublisherName'])): ?>
      <a class="btn-search" href="<?= htmlspecialchars(link_to_index_with($params, ['publisher'=>(int)$data['Publisher']])) ?>">🔎 Alle vom Publisher: <?= htmlspecialchars($data['PublisherName']) ?></a>
    <?php endif; ?>
    <?php if (!empty($data['Verkäufer']) && !empty($data['VerkaeuferName'])): ?>
      <a class="btn-search" href="<?= htmlspecialchars(link_to_index_with($params, ['verkaeufer'=>(int)$data['Verkäufer']])) ?>">🔎 Alle vom Verkäufer: <?= htmlspecialchars($data['VerkaeuferName']) ?></a>
    <?php endif; ?>
  </div>

  <!-- Layout -->
  <div class="layout">
    <div class="meta">
      <div class="form-groups">
        <?php
          tr_meta('Bezeichnung',        $data['Bezeichnung'] ?? '');
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
          $sonstLabel  = (($data['Sonstiges'] ?? '') === '1') ? 'Ja' : 'Nein';
          if ($sonstBeschr !== '') $sonstLabel .= ' (' . $sonstBeschr . ')';
          tr_meta('Sonstiges dabei', $sonstLabel);

          tr_meta('Material',           $data['MaterialName'] ?? '');

          tr_meta('Standort', $data['StandortName'] ?? '');
          tr_meta('Zum Verkauf', ($data['Zum Verkauf'] ?? '') === '1' ? 'Ja' : 'Nein');

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
              tr_meta('Box', $data['Box'] ?? '');
          }

          $einkaufsdatum = (!empty($data['Einkaufsdatum']) && $data['Einkaufsdatum']!=='0000-00-00')
                           ? date('d.m.Y', strtotime($data['Einkaufsdatum'])) : '';
          tr_meta('Einkaufsdatum', $einkaufsdatum);
          tr_meta('Verkäufer',     $data['VerkaeuferName'] ?? '');

          tr_meta('Einkaufspreis', $data['Einkaufspreis'] ?? '');
          tr_meta('Wert',          $data['Wert'] ?? '');

          // Getestet: Status + Datum kombiniert anzeigen
          $gStatus = $data['Getestet Status'] ?? '';
          $gDatum  = (!empty($data['Getestet am']) && $data['Getestet am'] !== '0000-00-00')
                     ? date('d.m.Y', strtotime($data['Getestet am'])) : '';
          $gLabel  = '';
          if ($gStatus === 'OK')          $gLabel = '✅ OK';
          elseif ($gStatus === 'Defekt')  $gLabel = '❌ Defekt';
          if ($gDatum) $gLabel .= ($gLabel ? ' (' . $gDatum . ')' : $gDatum);
          tr_meta('Getestet', $gLabel);

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
    </div>

    <div class="pic">
      <?php if ($bild): ?>
        <img id="mainPic" src="<?= htmlspecialchars($bild) ?>" alt="Bild" loading="lazy">
      <?php endif; ?>
    </div>
  </div>

  <!-- Lightbox -->
  <div id="lb" class="lb" hidden>
    <button class="lb-close" aria-label="Schließen">×</button>
    <img id="lbImg" alt="">
  </div>
</body>
</html>