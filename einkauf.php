<?php
// einkauf.php — Liste / Inbox für Tabelle `Einkauf`

require_once __DIR__ . '/inc_errors.php';
require_once __DIR__ . '/inc_icons.php';

require_once __DIR__ . '/require_login.php';
require_once __DIR__ . '/inc_security.php';
require_once __DIR__ . '/inc_format.php';
require_once __DIR__ . '/inc_admin_ui.php';
if (!is_admin()) { error_page(403, 'Kein Zugriff', 'Dieser Bereich ist nur für Administratoren.'); }

require_once __DIR__ . '/config.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$conn->set_charset('utf8mb4');
date_default_timezone_set('Europe/Berlin');

/* =================== Helper: Datum DE anzeigen =================== */
function format_date_de(?string $s): string {
  $s = trim((string)$s);
  if ($s === '') return '';
  if (preg_match('~^\d{4}-\d{2}-\d{2}~', $s)) {
    $dt = DateTime::createFromFormat('Y-m-d', substr($s, 0, 10));
    return $dt ? $dt->format('d.m.Y') : $s;
  }
  if (preg_match('~^\d{2}\.\d{2}\.\d{4}$~', $s)) {
    $dt = DateTime::createFromFormat('d.m.Y', $s);
    return $dt ? $dt->format('d.m.Y') : $s;
  }
  $ts = strtotime($s);
  return $ts ? date('d.m.Y', $ts) : $s;
}

/* =================== Helper: Statement als Array holen (mit/ohne mysqlnd) =================== */
function stmt_fetch_all_assoc(mysqli_stmt $stmt): array {
  $res = $stmt->get_result();
  if ($res instanceof mysqli_result) {
    return $res->fetch_all(MYSQLI_ASSOC);
  }

  $rows = [];
  $meta = $stmt->result_metadata();
  if (!$meta) return $rows;

  $fields = [];
  $row = [];
  while ($field = $meta->fetch_field()) {
    $fields[] = $field->name;
    $row[$field->name] = null;
  }

  $refs = [];
  foreach ($fields as $f) $refs[] = &$row[$f];
  call_user_func_array([$stmt, 'bind_result'], $refs);

  while ($stmt->fetch()) {
    $rows[] = $row;
    $row = array_fill_keys($fields, null);
    $refs = [];
    foreach ($fields as $f) $refs[] = &$row[$f];
    call_user_func_array([$stmt, 'bind_result'], $refs);
  }

  return $rows;
}

/* =================== Quick Toggle (POST) =================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle') {
  require_post_csrf();

  $id    = (int)($_POST['id'] ?? 0);
  $field = (string)($_POST['field'] ?? '');
  $val   = (int)($_POST['val'] ?? 0);
  if (isset($_POST['toggle']) && preg_match('/^([A-Za-z_]+):([01]):(\d+)$/', (string)$_POST['toggle'], $tm)) {   // ein Formular für die Liste, der Knopf trägt Feld, neuen Wert und ID
    $field = $tm[1];
    $val   = (int)$tm[2];
    $id    = (int)$tm[3];
  }

  $allowed = ['Foto_erstellt','DB_Eintrag_erstellt','Foto_Website','Kickstarter'];
  if ($id <= 0 || !in_array($field, $allowed, true)) {
    http_response_code(400); exit('Ungültige Anfrage.');
  }

  $stmt = $conn->prepare("UPDATE `Einkauf` SET `$field` = ? WHERE `ID` = ?");
  $stmt->bind_param('ii', $val, $id);
  $stmt->execute();

  // Rückmeldung (role="status" in der Liste): „Foto: ja – Bezeichnung“
  $labels = ['Foto_erstellt' => 'Foto', 'DB_Eintrag_erstellt' => 'In Sammlung übernommen', 'Foto_Website' => 'Foto auf Website', 'Kickstarter' => 'Kickstarter'];
  $bezRow = $conn->prepare("SELECT `Bezeichnung` FROM `Einkauf` WHERE `ID` = ?");
  $bezRow->bind_param('i', $id);
  $bezRow->execute();
  $bezTxt = (string)(stmt_fetch_all_assoc($bezRow)[0]['Bezeichnung'] ?? '');
  flash_set(($labels[$field] ?? $field) . ': ' . ($val ? 'ja' : 'nein') . ($bezTxt !== '' ? ' – ' . $bezTxt : ''), 'success');

  $back = safe_back_url($_POST['back'] ?? null, 'einkauf.php');
  header('Location: ' . $back . '#e' . $id);   // zurück zur Zeile
  exit;
}

/* =================== Filter / Sort =================== */
$q            = trim((string)($_GET['q'] ?? ''));
$status       = (string)($_GET['status'] ?? 'all');
$kategorie    = (int)($_GET['kategorie'] ?? 0);
$verkaeufer   = (int)($_GET['verkaeufer'] ?? 0);
$kickstarter  = (string)($_GET['kickstarter'] ?? 'all'); // all | ja | nein
$db_status    = (string)($_GET['db_status'] ?? 'all'); // all | offen | erfasst

$jahr  = (int)($_GET['jahr'] ?? 0);
$monat = (int)($_GET['monat'] ?? 0);

// Lieferdatum robust als Datum interpretieren
$lieferDateSql = "
  CASE
    WHEN E.Lieferdatum REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}' THEN DATE(E.Lieferdatum)
    WHEN E.Lieferdatum REGEXP '^[0-9]{2}\\.[0-9]{2}\\.[0-9]{4}$' THEN STR_TO_DATE(E.Lieferdatum,'%d.%m.%Y')
    ELSE NULL
  END
";

// Kickstarter-Lieferdatum robust als Datum interpretieren
$kickLieferDateSql = "
  CASE
    WHEN E.Kickstarter_Lieferdatum REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}' THEN DATE(E.Kickstarter_Lieferdatum)
    WHEN E.Kickstarter_Lieferdatum REGEXP '^[0-9]{2}\\.[0-9]{2}\\.[0-9]{4}$' THEN STR_TO_DATE(E.Kickstarter_Lieferdatum,'%d.%m.%Y')
    ELSE NULL
  END
";

$sort = (string)($_GET['sort'] ?? 'Lieferdatum');
$dir  = strtolower((string)($_GET['dir'] ?? 'asc'));
if (!in_array($dir, ['asc','desc'], true)) $dir = 'desc';

$sort_map = [
  'ID'                       => 'E.ID',
  'Bestelldatum'             => 'E.Bestelldatum',
  'Lieferdatum'              => "($lieferDateSql)",
  'Kickstarter_Lieferdatum'  => "($kickLieferDateSql)",
  'Bezeichnung'              => 'E.Bezeichnung',
  'Kategorie'                => 'K.Kategorie',
  'Verkaeufer'               => 'V.`Verkäufer`',
  'Preis'                    => 'E.Preis',
  'Menge'                    => 'E.Menge',
  'Kickstarter'              => 'E.Kickstarter',
];

$order = ($sort_map[$sort] ?? "($lieferDateSql)") . ' ' . strtoupper($dir) . ', E.ID DESC';

// WHERE
$where = [];
$args  = [];
$types = '';

if ($q !== '') {
  $where[] = "E.Bezeichnung LIKE ?";
  $args[]  = "%$q%";
  $types  .= 's';
}

if ($status === 'offen') {
  $where[] = "(E.Lieferdatum IS NULL OR E.Lieferdatum = '0000-00-00')";
} elseif ($status === 'geliefert') {
  $where[] = "(E.Lieferdatum IS NOT NULL AND E.Lieferdatum <> '0000-00-00')";
}

if ($kategorie > 0) {
  $where[] = "E.Kategorie = ?";
  $args[]  = $kategorie;
  $types  .= 'i';
}
if ($verkaeufer > 0) {
  $where[] = "E.Verkaeufer = ?";
  $args[]  = $verkaeufer;
  $types  .= 'i';
}

if ($kickstarter === 'ja') {
  $where[] = "E.Kickstarter = 1";
} elseif ($kickstarter === 'nein') {
  $where[] = "(E.Kickstarter = 0 OR E.Kickstarter IS NULL)";
}

if ($db_status === 'offen') {
  $where[] = "(E.DB_Eintrag_erstellt = 0 OR E.DB_Eintrag_erstellt IS NULL)";
} elseif ($db_status === 'erfasst') {
  $where[] = "E.DB_Eintrag_erstellt = 1";
}

// Datumsbereich für Lieferdatum
if ($jahr > 0) {
  if ($monat < 1 || $monat > 12) {
    $start = sprintf('%04d-01-01', $jahr);
    $end = (new DateTime($start))->modify('+1 year')->format('Y-m-d');
  } else {
    $start = sprintf('%04d-%02d-01', $jahr, $monat);
    $end = (new DateTime($start))->modify('+1 month')->format('Y-m-d');
  }

  $where[] = "($lieferDateSql IS NOT NULL AND $lieferDateSql >= ? AND $lieferDateSql < ?)";
  $args[]  = $start;
  $args[]  = $end;
  $types  .= 'ss';
}

$where_sql = $where ? ('WHERE '.implode(' AND ', $where)) : '';

/* =================== Dropdown Optionen =================== */
function options_from_table(mysqli $conn, string $table, string $field, int $selected = 0): string {
  $out = "<option value=\"0\">— alle —</option>\n";
  [$t, $f] = lookup_ident($table, $field);
  $res = $conn->query("SELECT ID, $f AS label FROM $t ORDER BY $f");
  while ($r = $res->fetch_assoc()) {
    $id = (int)$r['ID'];
    $sel = ($id === $selected) ? ' selected' : '';
    $out .= "<option value=\"$id\"$sel>".htmlspecialchars((string)$r['label'], ENT_QUOTES, 'UTF-8')."</option>\n";
  }
  return $out;
}

function options_years_from_lieferdatum(mysqli $conn, string $lieferDateSql, int $selected = 0): string {
  $out = "<option value=\"0\">— alle —</option>\n";
  $sql = "
    SELECT DISTINCT YEAR($lieferDateSql) AS y
    FROM Einkauf E
    WHERE $lieferDateSql IS NOT NULL
    ORDER BY y DESC
  ";
  $res = $conn->query($sql);
  while ($r = $res->fetch_assoc()) {
    $y = (int)($r['y'] ?? 0);
    if ($y <= 0) continue;
    $sel = ($y === $selected) ? ' selected' : '';
    $out .= "<option value=\"$y\"$sel>$y</option>\n";
  }
  return $out;
}

function options_months(int $selected = 0): string {
  $out = "<option value=\"0\">— alle —</option>\n";
  for ($m = 1; $m <= 12; $m++) {
    $sel = ($m === $selected) ? ' selected' : '';
    $out .= "<option value=\"$m\"$sel>".sprintf('%02d', $m)."</option>\n";
  }
  return $out;
}

/* =================== Query =================== */
$sql = "
SELECT
  E.*,
  K.Kategorie     AS KategorieName,
  V.`Verkäufer`   AS VerkaeuferName
FROM Einkauf E
LEFT JOIN Kategorie   K ON E.Kategorie   = K.ID
LEFT JOIN `Verkäufer` V ON E.Verkaeufer  = V.ID
$where_sql
ORDER BY $order
";

// Seitenwechsel (50 pro Seite)
const EINKAUF_PRO_SEITE = 50;
$stmt = $conn->prepare("SELECT COUNT(*) FROM Einkauf E LEFT JOIN Kategorie K ON E.Kategorie = K.ID LEFT JOIN `Verkäufer` V ON E.Verkaeufer = V.ID $where_sql");
if ($types !== '') { $stmt->bind_param($types, ...$args); }
$stmt->execute();
$total = (int)(stmt_fetch_all_assoc($stmt)[0]['COUNT(*)'] ?? 0);
$pages = max(1, (int)ceil($total / EINKAUF_PRO_SEITE));
$page  = min(max(1, (int)($_GET['page'] ?? 1)), $pages);
$sql  .= ' LIMIT ' . EINKAUF_PRO_SEITE . ' OFFSET ' . (($page - 1) * EINKAUF_PRO_SEITE);   // nur feste Zahlen

$stmt = $conn->prepare($sql);
if ($types !== '') {
  $stmt->bind_param($types, ...$args);
}
$stmt->execute();
$rows = stmt_fetch_all_assoc($stmt);

// Back-URL
$self = 'einkauf.php?' . http_build_query($_GET);

function sort_link(string $label, string $key, string $sort, string $dir): string {
  $newDir = ($sort === $key && $dir === 'asc') ? 'desc' : 'asc';
  $params = $_GET;
  $params['sort'] = $key;
  $params['dir']  = $newDir;
  unset($params['page']);
  $href = 'einkauf.php?' . http_build_query($params);
  $arrow = ($sort === $key) ? ($dir === 'asc' ? ' ▲' : ' ▼') : '';
  $aria  = ($sort === $key) ? ($dir === 'asc' ? ' aria-sort="ascending"' : ' aria-sort="descending"') : '';
  return '<a href="'.htmlspecialchars($href, ENT_QUOTES, 'UTF-8').'">'.htmlspecialchars($label, ENT_QUOTES, 'UTF-8').$arrow.'</a>';
}

function format_price_de($v): string {
  return fmt_dec($v);
}

// Quickfilter Links
$now = new DateTime('now');
$last = (clone $now)->modify('first day of last month');
$lastY = (int)$last->format('Y');
$lastM = (int)$last->format('n');
$thisY = (int)$now->format('Y');

function build_href_with(array $set): string {
  $params = $_GET;
  unset($params['page']);
  foreach ($set as $k => $v) {
    if ($v === null) unset($params[$k]);
    else $params[$k] = $v;
  }
  return 'einkauf.php?' . http_build_query($params);
}
?>
<?php
$e = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

// Namen der gewählten Filter (Kategorie/Verkäufer) für Eingabefeld und Chips
$katName = ''; $vkName = '';
if ($kategorie > 0) {
  $r = $conn->query('SELECT Kategorie FROM Kategorie WHERE ID = ' . (int)$kategorie)->fetch_row();
  $katName = $r ? (string)$r[0] : '';
}
if ($verkaeufer > 0) {
  $r = $conn->query('SELECT `Verkäufer` FROM `Verkäufer` WHERE ID = ' . (int)$verkaeufer)->fetch_row();
  $vkName = $r ? (string)$r[0] : '';
}
$monatsNamen = [1=>'Januar','Februar','März','April','Mai','Juni','Juli','August','September','Oktober','November','Dezember'];

// Aktive Filter als Chips (Link entfernt den Filter)
$chips = [];
if ($q !== '')                 $chips[] = ['Suche: ' . $q, ['q' => null]];
if ($status !== 'all')         $chips[] = ['Status: ' . ($status === 'offen' ? 'offen' : 'geliefert'), ['status' => null]];
if ($kickstarter !== 'all')    $chips[] = ['Kickstarter: ' . ($kickstarter === 'ja' ? 'ja' : 'nein'), ['kickstarter' => null]];
if ($db_status !== 'all')      $chips[] = [$db_status === 'offen' ? 'Noch nicht übernommen' : 'Bereits übernommen', ['db_status' => null]];
if ($kategorie > 0)            $chips[] = ['Kategorie: ' . ($katName !== '' ? $katName : '#' . $kategorie), ['kategorie' => null]];
if ($verkaeufer > 0)           $chips[] = ['Verkäufer: ' . ($vkName !== '' ? $vkName : '#' . $verkaeufer), ['verkaeufer' => null]];
if ($jahr > 0)                 $chips[] = ['Lieferung: ' . ($monat >= 1 && $monat <= 12 ? $monatsNamen[$monat] . ' ' : '') . $jahr, ['jahr' => null, 'monat' => null]];

$listParams = $_GET; unset($listParams['page']);
$sortSpalten = ['Lieferdatum' => 'Lieferung', 'Bestelldatum' => 'Bestellt', 'Bezeichnung' => 'Bezeichnung', 'Kategorie' => 'Kategorie',
                'Verkaeufer' => 'Verkäufer', 'Preis' => 'Preis', 'Menge' => 'Menge', 'Kickstarter_Lieferdatum' => 'Kickstarter geplant', 'ID' => 'Eingabe'];
$flashHtml = admin_flash();
$toggleNamen = ['Foto_erstellt' => 'Foto', 'DB_Eintrag_erstellt' => 'DB', 'Foto_Website' => 'Web', 'Kickstarter' => 'KS'];
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <?php $page_title = 'Einkäufe'; include __DIR__ . '/inc_head_admin.php'; ?>
  <script src="/sammlung/assets/typeahead.js?v=3" defer></script>
  <script src="/sammlung/assets/einkauf.js?v=3" defer></script>
  <link rel="stylesheet" href="/sammlung/assets/einkauf.css?v=2">
</head>
<body class="admin-page einkauf-page">
<?= admin_header([['Einkäufe', '']]) ?>

<main id="inhalt" tabindex="-1">
  <h1>Einkäufe</h1>

  <?= $flashHtml ?>

  <div class="v-knoepfe ek-kopf">
    <a class="btn save" href="einkauf_edit.php?id=0&amp;back=<?= urlencode('einkauf.php') ?>"><?= icon('plus') ?> Neuer Einkauf</a>
    <span class="v-unter" aria-live="polite"><?= $total ?> <?= $total === 1 ? 'Einkauf' : 'Einkäufe' ?><?= $chips ? ' (gefiltert)' : '' ?></span>
  </div>

  <!-- Filter -->
  <details class="filterbox" id="filterBox"<?= $chips ? ' data-aktiv="1"' : '' ?>>
    <summary>Filter<?= $chips ? ' (' . count($chips) . ')' : '' ?></summary>
    <form method="get" class="ek-filter" action="einkauf.php">
      <input type="hidden" name="sort" value="<?= $e($sort) ?>">
      <input type="hidden" name="dir" value="<?= $e($dir) ?>">
      <div class="v-feld ek-breit">
        <label for="f_q">Suche</label>
        <input type="search" id="f_q" name="q" value="<?= $e($q) ?>" placeholder="Bezeichnung…" autocomplete="off">
      </div>
      <div class="v-feld">
        <label for="f_status">Status</label>
        <select id="f_status" name="status">
          <option value="all" <?= $status==='all'?'selected':'' ?>>alle</option>
          <option value="offen" <?= $status==='offen'?'selected':'' ?>>offen</option>
          <option value="geliefert" <?= $status==='geliefert'?'selected':'' ?>>geliefert</option>
        </select>
      </div>
      <div class="v-feld">
        <label for="f_ks">Kickstarter</label>
        <select id="f_ks" name="kickstarter">
          <option value="all" <?= $kickstarter==='all'?'selected':'' ?>>alle</option>
          <option value="ja" <?= $kickstarter==='ja'?'selected':'' ?>>ja</option>
          <option value="nein" <?= $kickstarter==='nein'?'selected':'' ?>>nein</option>
        </select>
      </div>
      <div class="v-feld">
        <label for="f_db">In Sammlung übernommen</label>
        <select id="f_db" name="db_status">
          <option value="all"     <?= $db_status==='all'?'selected':'' ?>>alle</option>
          <option value="offen"   <?= $db_status==='offen'?'selected':'' ?>>noch nicht übernommen</option>
          <option value="erfasst" <?= $db_status==='erfasst'?'selected':'' ?>>bereits übernommen</option>
        </select>
      </div>
      <div class="v-feld">
        <label for="f_kat">Kategorie</label>
        <input type="text" id="f_kat" value="<?= $e($katName) ?>" placeholder="Tippen zum Suchen…" autocomplete="off" data-ta="kategorie" data-ta-hidden="f_kat_id">
        <input type="hidden" id="f_kat_id" name="kategorie" value="<?= $kategorie > 0 ? (int)$kategorie : '' ?>">
      </div>
      <div class="v-feld">
        <label for="f_vk">Verkäufer</label>
        <input type="text" id="f_vk" value="<?= $e($vkName) ?>" placeholder="Tippen zum Suchen…" autocomplete="off" data-ta="verkaeufer" data-ta-hidden="f_vk_id">
        <input type="hidden" id="f_vk_id" name="verkaeufer" value="<?= $verkaeufer > 0 ? (int)$verkaeufer : '' ?>">
      </div>
      <div class="v-feld">
        <label for="f_jahr">Jahr (Lieferdatum)</label>
        <select id="f_jahr" name="jahr"><?= options_years_from_lieferdatum($conn, $lieferDateSql, $jahr) ?></select>
      </div>
      <div class="v-feld">
        <label for="f_monat">Monat</label>
        <select id="f_monat" name="monat"><?= options_months($monat) ?></select>
      </div>
      <div class="v-knoepfe ek-breit">
        <button class="btn save" type="submit"><?= icon('filter') ?> Filtern</button>
        <a class="btn" href="einkauf.php">Zurücksetzen</a>
      </div>
      <div class="v-knoepfe ek-breit" role="group" aria-label="Schnellfilter Lieferdatum">
        <a class="btn" href="<?= $e(build_href_with(['jahr'=>$lastY,'monat'=>$lastM])) ?>">Letzter Monat</a>
        <a class="btn" href="<?= $e(build_href_with(['jahr'=>$thisY,'monat'=>0])) ?>">Dieses Jahr</a>
        <a class="btn" href="<?= $e(build_href_with(['jahr'=>null,'monat'=>null])) ?>">Datum-Filter aus</a>
      </div>
    </form>
  </details>

  <?php if ($chips): ?>
    <ul class="ek-chips" aria-label="Aktive Filter">
      <?php foreach ($chips as [$text, $weg]): ?>
        <li><a class="chip" href="<?= $e(build_href_with($weg)) ?>" title="Filter entfernen"><?= $e($text) ?> <?= icon('close', 'entfernen') ?></a></li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>

  <!-- Sortierung (unter 900 px; darüber sind die Spaltenköpfe Links) -->
  <div class="ek-sortwahl">
    <label for="f_sort">Sortieren nach</label>
    <select id="f_sort" data-sort>
      <?php foreach ($sortSpalten as $k => $lbl): foreach (['asc' => 'aufsteigend', 'desc' => 'absteigend'] as $d => $dl): ?>
        <option value="<?= $e($k . ':' . $d) ?>" <?= ($sort === $k && $dir === $d) ? 'selected' : '' ?>><?= $e($lbl . ' (' . $dl . ')') ?></option>
      <?php endforeach; endforeach; ?>
    </select>
  </div>

  <!-- Liste -->
  <form method="post" class="ek-liste-form">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="toggle">
    <input type="hidden" name="back" value="<?= $e($self) ?>">
    <table class="ek-tabelle">
      <caption class="sr-only">Einkäufe (<?= $total ?>), Seite <?= $page ?> von <?= $pages ?></caption>
      <thead>
        <tr>
          <th scope="col" class="c-datum"><?= sort_link('Bestellt','Bestelldatum',$sort,$dir) ?></th>
          <th scope="col" class="c-titel"><?= sort_link('Bezeichnung','Bezeichnung',$sort,$dir) ?></th>
          <th scope="col" class="c-kat"><?= sort_link('Kategorie','Kategorie',$sort,$dir) ?></th>
          <th scope="col" class="c-vk"><?= sort_link('Verkäufer','Verkaeufer',$sort,$dir) ?></th>
          <th scope="col" class="c-preis"><?= sort_link('Preis','Preis',$sort,$dir) ?></th>
          <th scope="col" class="c-liefer"><?= sort_link('Lieferung','Lieferdatum',$sort,$dir) ?></th>
          <th scope="col" class="c-menge"><?= sort_link('Menge','Menge',$sort,$dir) ?></th>
          <th scope="col" class="c-status">Stand</th>
          <th scope="col" class="c-akt"><span class="sr-only">Aktionen</span></th>
        </tr>
      </thead>
      <tbody>
      <?php if (!$rows): ?>
        <tr class="leer"><td colspan="9" class="v-leer">Keine Einträge<?= $chips ? ' zu diesen Filtern. <a href="einkauf.php">Filter zurücksetzen</a>' : '.' ?></td></tr>
      <?php endif; ?>

      <?php foreach ($rows as $r):
          $id = (int)($r['ID'] ?? 0);
          $bez = (string)($r['Bezeichnung'] ?? '');
          $editHref = 'einkauf_edit.php?id=' . $id . '&back=' . urlencode($self);
          $bestellt = format_date_de($r['Bestelldatum'] ?? '');
          $lieferRaw = trim((string)($r['Lieferdatum'] ?? ''));
          $liefer = ($lieferRaw === '0000-00-00') ? '' : format_date_de($lieferRaw);
          $ks = (int)($r['Kickstarter'] ?? 0) === 1;
          $ksGeplant = format_date_de($r['Kickstarter_Lieferdatum'] ?? '');
          if ($ksGeplant === '00.00.0000' || $ksGeplant === '30.11.-0001') $ksGeplant = '';
          $preis = format_price_de($r['Preis'] ?? '');

          // ---- Übernahme nach edit.php (Sammlung) vorbereiten (unverändert) ----
          // Bestelldatum wird bewusst NICHT übernommen.
          // Lieferdatum -> Einkaufsdatum, Preis -> Einkaufspreis und Wert (beide gleich, später im Formular anpassbar).
          $prefill = [];
          if (trim($bez) !== '') { $prefill['pf_Bezeichnung'] = $r['Bezeichnung']; }
          if ((int)($r['Kategorie'] ?? 0) > 0) { $prefill['pf_Kategorie'] = (int)$r['Kategorie']; }
          if ((int)($r['Verkaeufer'] ?? 0) > 0) { $prefill['pf_Verkaeufer'] = (int)$r['Verkaeufer']; }
          if ((int)($r['Menge'] ?? 0) > 0) { $prefill['pf_Anzahl'] = (int)$r['Menge']; }
          if ($preis !== '' && $preis !== '0,00') {
              $prefill['pf_Einkaufspreis'] = $preis;
              $prefill['pf_Wert'] = $preis;
          }
          if ($lieferRaw !== '' && $lieferRaw !== '0000-00-00') {
              $liefTs = strtotime($lieferRaw);
              if ($liefTs) $prefill['pf_Einkaufsdatum'] = date('Y-m-d', $liefTs);
          }
          $transferHref = 'edit.php?id=0&' . http_build_query($prefill);
      ?>
        <tr class="zeile-klick" id="e<?= $id ?>" data-href="<?= $e($editHref) ?>">
          <td class="c-datum" data-l="Bestellt"><?= $e($bestellt) ?></td>
          <td class="c-titel">
            <a class="zeilenlink" href="<?= $e($editHref) ?>"><?= $e($bez) ?></a>
            <?php if ($ks): ?><span class="ek-ks">Kickstarter<?= $ksGeplant !== '' ? ' · geplant ' . $e($ksGeplant) : '' ?></span><?php endif; ?>
          </td>
          <td class="c-kat"><?= $e($r['KategorieName'] ?? '') ?></td>
          <td class="c-vk"><?= $e($r['VerkaeuferName'] ?? '') ?></td>
          <td class="c-preis"><?= $preis !== '' ? $e($preis) . ' €' : '' ?></td>
          <td class="c-liefer"><?= $liefer !== '' ? $e($liefer) : '<span class="v-unter">offen</span>' ?></td>
          <td class="c-menge"><?= (int)($r['Menge'] ?? 1) ?><span class="nur-karte">×</span></td>
          <td class="c-status">
            <div class="ek-toggles" role="group" aria-label="Stand von <?= $e($bez) ?>">
              <?php foreach ($toggleNamen as $feld => $kurz):
                  $an = (int)($r[$feld] ?? 0) === 1; ?>
                <button type="submit" name="toggle" value="<?= $e($feld . ':' . ($an ? 0 : 1) . ':' . $id) ?>"
                        class="tgl<?= $an ? ' an' : '' ?>" aria-pressed="<?= $an ? 'true' : 'false' ?>"
                        title="<?= $e($kurz . ': ' . ($an ? 'ja' : 'nein') . ' – antippen zum Ändern') ?>"><?= $an ? icon('check') : '' ?><?= $e($kurz) ?></button>
              <?php endforeach; ?>
            </div>
          </td>
          <td class="c-akt">
            <a class="btn" href="<?= $e($transferHref) ?>" target="_blank" rel="noopener"
               title="Als neuen Sammlungs-Eintrag übernehmen (Lieferung → Einkaufsdatum, Preis → Einkaufspreis und Wert) – öffnet in neuem Tab"><?= icon('forward') ?> In Sammlung</a>
            <button type="button" class="btn delete" data-loeschen data-id="<?= $id ?>" data-name="<?= $e($bez) ?>" data-sperre="" data-hinweis=""><?= icon('trash', 'Löschen') ?></button>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </form>

  <?= admin_pagination($page, $pages, $listParams) ?>
</main>

<!-- Löschen bestätigen (POST mit CSRF an einkauf_delete.php) -->
<dialog id="loeschDialog" class="v-dialog" aria-labelledby="loeschTitel">
  <form method="post" action="einkauf_delete.php">
    <?= csrf_field() ?>
    <input type="hidden" name="id" id="loeschId" value="">
    <input type="hidden" name="back" value="<?= $e($self) ?>">
    <h2 id="loeschTitel">Einkauf löschen?</h2>
    <p id="loeschText"></p>
    <div class="v-dialog-knoepfe">
      <button type="button" class="btn" data-dialog-zu>Abbrechen</button>
      <button type="submit" class="btn delete" id="loeschOk"><?= icon('trash') ?> Löschen</button>
    </div>
  </form>
</dialog>
</body>
</html>
