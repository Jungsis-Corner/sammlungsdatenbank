<?php
// einkauf.php — Liste / Inbox für Tabelle `Einkauf`

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/require_login.php';
if (!is_admin()) { http_response_code(403); exit('Nur für Admins.'); }

require_once __DIR__ . '/config.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$conn->set_charset('utf8mb4');
date_default_timezone_set('Europe/Berlin');

// CSRF
if (empty($_SESSION['csrf'])) {
  $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

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
  if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
    http_response_code(400); exit('CSRF ungültig.');
  }

  $id    = (int)($_POST['id'] ?? 0);
  $field = (string)($_POST['field'] ?? '');
  $val   = (int)($_POST['val'] ?? 0);

  $allowed = ['Foto_erstellt','DB_Eintrag_erstellt','Foto_Website','Kickstarter'];
  if ($id <= 0 || !in_array($field, $allowed, true)) {
    http_response_code(400); exit('Ungültige Anfrage.');
  }

  $stmt = $conn->prepare("UPDATE `Einkauf` SET `$field` = ? WHERE `ID` = ?");
  $stmt->bind_param('ii', $val, $id);
  $stmt->execute();

  $back = $_POST['back'] ?? 'einkauf.php';
  header('Location: '.$back);
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
  $res = $conn->query("SELECT ID, `$field` AS label FROM `$table` ORDER BY `$field`");
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
  $href = 'einkauf.php?' . http_build_query($params);
  $arrow = ($sort === $key) ? ($dir === 'asc' ? ' ▲' : ' ▼') : '';
  return '<a href="'.htmlspecialchars($href, ENT_QUOTES, 'UTF-8').'">'.htmlspecialchars($label, ENT_QUOTES, 'UTF-8').$arrow.'</a>';
}

function format_price_de($v): string {
  $s = trim((string)$v);
  if ($s === '' || $s === '0' || $s === '0.00' || $s === '0,00') return $s;

  $s = str_replace([' ', "\t"], '', $s);
  $s = str_replace(',', '.', $s);

  if (!is_numeric($s)) return trim((string)$v);

  return number_format((float)$s, 2, ',', '.');
}

// Quickfilter Links
$now = new DateTime('now');
$last = (clone $now)->modify('first day of last month');
$lastY = (int)$last->format('Y');
$lastM = (int)$last->format('n');
$thisY = (int)$now->format('Y');

function build_href_with(array $set): string {
  $params = $_GET;
  foreach ($set as $k => $v) {
    if ($v === null) unset($params[$k]);
    else $params[$k] = $v;
  }
  return 'einkauf.php?' . http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <script src="/sammlung/assets/theme-toggle.js"></script>
  <link rel="stylesheet" href="/sammlung/assets/app.css?v=11">
  <title>Einkäufe – Sammlung</title>

  <style>
    .panel{
      border: 1px solid #cfcfcf;
      border-radius: 10px;
      padding: 12px;
      margin: 12px 0;
      background: #fff;
    }
    .filters{
      display:flex;
      gap:8px;
      flex-wrap:wrap;
      align-items:end;
    }
    .quickfilters{
      display:flex;
      gap:8px;
      flex-wrap:wrap;
      margin-top:10px;
    }

    table.list{
      border-collapse: collapse !important;
      width: 100%;
    }
    table.list th, table.list td{
      border: 1px solid #d6d6d6;
      padding: 8px 10px;
      vertical-align: top;
    }
    table.list thead th{
      background: #f7f7f7;
    }

    table.list tbody tr:nth-child(even) td{
      background: #fafafa;
    }
    table.list tbody tr:hover td{
      background: #f2f7ff;
    }

    th.col-date, td.col-date{ width:100px; min-width:100px; white-space:nowrap; text-align:center; }
    th.col-bez,  td.col-bez { width:32%; min-width:360px; white-space:normal; word-break:break-word; }
    th.col-cat,  td.col-cat { width:180px; min-width:180px; }
    th.col-seller, td.col-seller { width:180px; min-width:180px; }
    th.col-price, td.col-price { width:75px; min-width:75px; white-space:nowrap; text-align:right; }
    th.col-qty, td.col-qty { width:80px; min-width:80px; text-align:center; white-space:nowrap; }
    th.col-mini, td.col-mini { width:70px; min-width:70px; text-align:center; white-space:nowrap; }
    th.col-action, td.col-action { width:220px; min-width:220px; white-space:nowrap; text-align:center; }

    @media (max-width: 760px){
      th.col-bez, td.col-bez{ min-width:0; width:auto; }
      th.col-date, td.col-date,
      th.col-cat, td.col-cat,
      th.col-seller, td.col-seller,
      th.col-price, td.col-price,
      th.col-qty, td.col-qty,
      th.col-mini, td.col-mini,
      th.col-action, td.col-action{ min-width:0; width:auto; }
    }

    a.btn, button.btn{
      background: #2d6cdf;
      color: #fff !important;
      border: 1px solid #1f55b6;
    }

    a.btn:hover, button.btn:hover{
      filter: brightness(0.92);
    }

    a.btn.back, a.btn.reset, a.btn.secondary{
      background: #6c757d;
      border-color: #5c636a;
    }

    .quickfilters .btn{
      background: #0f766e;
      border-color: #0b5e57;
    }
    .quickfilters .btn:hover{
      filter: brightness(0.92);
    }

    .quickfilters .btn.off{
      background: #b42318;
      border-color: #8f1d14;
    }
  </style>
</head>
<body>

<h1>Einkäufe</h1>

<div class="buttons">
  <div class="row row-main">
    <a class="btn back" href="einstellungen.php" title="Zurück zu Einstellungen">← Zurück</a>
    <a class="btn new" href="einkauf_edit.php?id=0&back=<?= urlencode('einkauf.php') ?>" title="Neuen Einkauf anlegen">➕ Neuer Einkauf</a>
  </div>
</div>

<div class="panel">
  <form method="get" class="filters">
    <div>
      <label>Suche</label><br>
      <input type="text" name="q" value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>" placeholder="Bezeichnung…">
    </div>

    <div>
      <label>Status</label><br>
      <select name="status">
        <option value="all" <?= $status==='all'?'selected':'' ?>>alle</option>
        <option value="offen" <?= $status==='offen'?'selected':'' ?>>offen</option>
        <option value="geliefert" <?= $status==='geliefert'?'selected':'' ?>>geliefert</option>
      </select>
    </div>

    <div>
      <label>Kickstarter</label><br>
      <select name="kickstarter">
        <option value="all" <?= $kickstarter==='all'?'selected':'' ?>>alle</option>
        <option value="ja" <?= $kickstarter==='ja'?'selected':'' ?>>ja</option>
        <option value="nein" <?= $kickstarter==='nein'?'selected':'' ?>>nein</option>
      </select>
    </div>

    <div>
      <label>In Sammlung übernommen</label><br>
      <select name="db_status">
        <option value="all"     <?= $db_status==='all'?'selected':'' ?>>alle</option>
        <option value="offen"   <?= $db_status==='offen'?'selected':'' ?>>noch nicht übernommen</option>
        <option value="erfasst" <?= $db_status==='erfasst'?'selected':'' ?>>bereits übernommen</option>
      </select>
    </div>

    <div>
      <label>Kategorie</label><br>
      <select name="kategorie"><?= options_from_table($conn,'Kategorie','Kategorie',$kategorie) ?></select>
    </div>

    <div>
      <label>Verkäufer</label><br>
      <select name="verkaeufer"><?= options_from_table($conn,'Verkäufer','Verkäufer',$verkaeufer) ?></select>
    </div>

    <div>
      <label>Jahr (Lieferdatum)</label><br>
      <select name="jahr"><?= options_years_from_lieferdatum($conn, $lieferDateSql, $jahr) ?></select>
    </div>

    <div>
      <label>Monat</label><br>
      <select name="monat"><?= options_months($monat) ?></select>
    </div>

    <div>
      <button class="btn" type="submit">Filtern</button>
      <a class="btn" href="einkauf.php">Reset</a>
    </div>

    <div class="quickfilters">
      <a class="btn" href="<?= htmlspecialchars(build_href_with(['jahr'=>$lastY,'monat'=>$lastM]), ENT_QUOTES, 'UTF-8') ?>">Letzter Monat</a>
      <a class="btn" href="<?= htmlspecialchars(build_href_with(['jahr'=>$thisY,'monat'=>0]), ENT_QUOTES, 'UTF-8') ?>">Dieses Jahr</a>
      <a class="btn off" href="<?= htmlspecialchars(build_href_with(['jahr'=>null,'monat'=>null]), ENT_QUOTES, 'UTF-8') ?>">Datum-Filter aus</a>
    </div>
  </form>
</div>

<div class="panel">
  <div class="container">
    <table class="list">
      <thead>
        <tr>
          <th class="col-date"><?= sort_link('Bestellt','Bestelldatum',$sort,$dir) ?></th>
          <th class="col-bez"><?= sort_link('Bezeichnung','Bezeichnung',$sort,$dir) ?></th>
          <th class="col-cat"><?= sort_link('Kategorie','Kategorie',$sort,$dir) ?></th>
          <th class="col-seller"><?= sort_link('Verkäufer','Verkaeufer',$sort,$dir) ?></th>
          <th class="col-price"><?= sort_link('Preis','Preis',$sort,$dir) ?></th>
          <th class="col-date"><?= sort_link('Lieferung','Lieferdatum',$sort,$dir) ?></th>
          <th class="col-date"><?= sort_link('KS geplant','Kickstarter_Lieferdatum',$sort,$dir) ?></th>
          <th class="col-qty"><?= sort_link('Menge','Menge',$sort,$dir) ?></th>
          <th class="col-mini">Foto</th>
          <th class="col-mini">DB</th>
          <th class="col-mini">Web</th>
          <th class="col-mini"><?= sort_link('KS','Kickstarter',$sort,$dir) ?></th>
          <th class="col-action">Aktion</th>
        </tr>
      </thead>

      <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="13" style="padding:10px;">Keine Einträge.</td></tr>
      <?php endif; ?>

      <?php foreach ($rows as $r): ?>
        <?php
          $id = (int)($r['ID'] ?? 0);

          $mkToggle = function(string $field, int $cur) use ($id, $self) {
            $new = $cur ? 0 : 1;
            return '
              <form method="post" style="margin:0; display:inline;">
                <input type="hidden" name="csrf" value="'.htmlspecialchars($_SESSION['csrf'], ENT_QUOTES, 'UTF-8').'">
                <input type="hidden" name="action" value="toggle">
                <input type="hidden" name="id" value="'.$id.'">
                <input type="hidden" name="field" value="'.htmlspecialchars($field, ENT_QUOTES, 'UTF-8').'">
                <input type="hidden" name="val" value="'.$new.'">
                <input type="hidden" name="back" value="'.htmlspecialchars($self, ENT_QUOTES, 'UTF-8').'">
                <button type="submit" class="btn" style="padding:2px 6px;">'.($cur ? '✔' : '—').'</button>
              </form>';
          };

          $bestellt      = format_date_de($r['Bestelldatum'] ?? '');
          $liefer        = format_date_de($r['Lieferdatum'] ?? '');
          $ks_geplant    = format_date_de($r['Kickstarter_Lieferdatum'] ?? '');

          // ---- Übernahme nach edit.php (Sammlung) vorbereiten ----
          // Bestelldatum wird bewusst NICHT übernommen.
          // Lieferdatum -> Einkaufsdatum, Preis -> Wert (wie besprochen).
          $prefill = [];
          if (trim((string)($r['Bezeichnung'] ?? '')) !== '') {
              $prefill['pf_Bezeichnung'] = $r['Bezeichnung'];
          }
          if ((int)($r['Kategorie'] ?? 0) > 0) {
              $prefill['pf_Kategorie'] = (int)$r['Kategorie'];
          }
          if ((int)($r['Verkaeufer'] ?? 0) > 0) {
              $prefill['pf_Verkaeufer'] = (int)$r['Verkaeufer'];
          }
          if ((int)($r['Menge'] ?? 0) > 0) {
              $prefill['pf_Anzahl'] = (int)$r['Menge'];
          }
          $preisFmt = format_price_de($r['Preis'] ?? '');
          if ($preisFmt !== '' && $preisFmt !== '0,00') {
              $prefill['pf_Wert'] = $preisFmt;
          }
          $liefRaw = trim((string)($r['Lieferdatum'] ?? ''));
          if ($liefRaw !== '' && $liefRaw !== '0000-00-00') {
              $liefTs = strtotime($liefRaw);
              if ($liefTs) $prefill['pf_Einkaufsdatum'] = date('Y-m-d', $liefTs);
          }
          $transferHref = 'edit.php?id=0&' . http_build_query($prefill);
        ?>

        <tr>
          <td class="col-date"><?= htmlspecialchars($bestellt, ENT_QUOTES, 'UTF-8') ?></td>
          <td class="col-bez"><?= htmlspecialchars((string)($r['Bezeichnung'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
          <td class="col-cat"><?= htmlspecialchars((string)($r['KategorieName'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
          <td class="col-seller"><?= htmlspecialchars((string)($r['VerkaeuferName'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
          <td class="col-price"><?= htmlspecialchars(format_price_de($r['Preis'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
          <td class="col-date"><?= htmlspecialchars($liefer, ENT_QUOTES, 'UTF-8') ?></td>
          <td class="col-date"><?= htmlspecialchars($ks_geplant, ENT_QUOTES, 'UTF-8') ?></td>
          <td class="col-qty"><?= (int)($r['Menge'] ?? 1) ?></td>

          <td class="col-mini"><?= $mkToggle('Foto_erstellt', (int)($r['Foto_erstellt'] ?? 0)) ?></td>
          <td class="col-mini"><?= $mkToggle('DB_Eintrag_erstellt', (int)($r['DB_Eintrag_erstellt'] ?? 0)) ?></td>
          <td class="col-mini"><?= $mkToggle('Foto_Website', (int)($r['Foto_Website'] ?? 0)) ?></td>
          <td class="col-mini"><?= $mkToggle('Kickstarter', (int)($r['Kickstarter'] ?? 0)) ?></td>

          <td class="col-action">
            <a class="btn" href="einkauf_edit.php?id=<?= $id ?>&back=<?= urlencode($self) ?>">✏️</a>
            <a class="btn" href="einkauf_delete.php?id=<?= $id ?>&back=<?= urlencode($self) ?>"
               onclick="return confirm('Einkauf wirklich löschen?');">🗑️</a>
            <a class="btn" href="<?= htmlspecialchars($transferHref, ENT_QUOTES, 'UTF-8') ?>"
               target="_blank" rel="noopener"
               title="Als neuen Sammlungs-Eintrag übernehmen (Lieferung → Einkaufsdatum, Preis → Wert) – öffnet in neuem Tab">➡️ Sammlung</a>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

</body>
</html>