<?php
// einkauf_edit.php — Neu/Bearbeiten für Tabelle `Einkauf` + Blättern (vor/zurück) im Kontext von einkauf.php

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

$id      = (int)($_GET['id'] ?? 0);
$backUrl = (string)($_GET['back'] ?? 'einkauf.php');

/* =================== Helper: Statement als Array holen (mit/ohne mysqlnd) =================== */
function stmt_fetch_all_assoc(mysqli_stmt $stmt): array {
  $res = $stmt->get_result();
  if ($res instanceof mysqli_result) {
    return $res->fetch_all(MYSQLI_ASSOC);
  }

  // Fallback ohne mysqlnd
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
  call_user_func_array([$stmt,'bind_result'], $refs);

  while ($stmt->fetch()) {
    $rows[] = $row;
    // Re-init damit Referenzen nicht “mitwandern”
    $row = array_fill_keys($fields, null);
    $refs = [];
    foreach ($fields as $f) $refs[] = &$row[$f];
    call_user_func_array([$stmt,'bind_result'], $refs);
  }
  return $rows;
}

/* =================== Helper: get_result Fallback (single row) =================== */
function stmt_fetch_one_assoc(mysqli_stmt $stmt): ?array {
  $res = $stmt->get_result();
  if ($res instanceof mysqli_result) {
    $r = $res->fetch_assoc();
    return $r ?: null;
  }

  $meta = $stmt->result_metadata();
  if (!$meta) return null;

  $fields = [];
  $row = [];
  while ($field = $meta->fetch_field()) {
    $fields[] = $field->name;
    $row[$field->name] = null;
  }
  $refs = [];
  foreach ($fields as $f) $refs[] = &$row[$f];
  call_user_func_array([$stmt,'bind_result'], $refs);

  if ($stmt->fetch()) return $row;
  return null;
}

/** Dropdown-Optionen aus Lookup-Tabellen */
function get_options(mysqli $conn, string $table, string $field, int $selected = 0): string {
  // value="" statt 0 -> damit später NULL gespeichert werden kann
  $out = "<option value=\"\">— unbekannt —</option>\n";
  $res = $conn->query("SELECT ID, `$field` AS label FROM `$table` ORDER BY `$field`");
  while ($r = $res->fetch_assoc()) {
    $rid = (int)$r['ID'];
    $sel = ($rid === $selected) ? ' selected' : '';
    $out .= "<option value=\"$rid\"$sel>" . htmlspecialchars((string)$r['label'], ENT_QUOTES, 'UTF-8') . "</option>\n";
  }
  return $out;
}

/**
 * INSERT oder UPDATE.
 * Robust gegen leere Eingaben:
 * - FK-Spalten Kategorie/Verkaeufer: NULLIF(?,0)
 * - DATE/DECIMAL/Text: NULLIF(?, '')
 */
function insert_or_update(mysqli $conn, int $id, array $v): int {
  $kat   = (int)($v['Kategorie'] ?? 0);
  $verk  = (int)($v['Verkaeufer'] ?? 0);
  $menge = max(1, (int)($v['Menge'] ?? 1));

  $bestell = trim((string)($v['Bestelldatum'] ?? ''));
  $liefer  = trim((string)($v['Lieferdatum'] ?? ''));
  $bez     = trim((string)($v['Bezeichnung'] ?? ''));
  $notiz   = trim((string)($v['Notizen'] ?? ''));

  $preisRaw = trim((string)($v['Preis'] ?? ''));
  $preisRaw = str_replace([' ', "\t"], '', $preisRaw);
  $preisRaw = str_replace(',', '.', $preisRaw);

  $foto = (int)($v['Foto_erstellt'] ?? 0);
  $db   = (int)($v['DB_Eintrag_erstellt'] ?? 0);
  $web  = (int)($v['Foto_Website'] ?? 0);

  $kickstarter = (int)($v['Kickstarter'] ?? 0);
  $kick_liefer = trim((string)($v['Kickstarter_Lieferdatum'] ?? ''));

  if (!$kickstarter) {
    $kick_liefer = '';
  }

  if ($id === 0) {
    $stmt = $conn->prepare("
      INSERT INTO Einkauf
        (Bestelldatum, Bezeichnung, Kategorie, Verkaeufer, Preis, Lieferdatum, Menge,
         Foto_erstellt, DB_Eintrag_erstellt, Foto_Website, Kickstarter, Kickstarter_Lieferdatum, Notizen)
      VALUES
        (NULLIF(?,''), ?, NULLIF(?,0), NULLIF(?,0), NULLIF(?,''), NULLIF(?,''), ?,
         ?, ?, ?, ?, NULLIF(?, ''), NULLIF(?, ''))
    ");
 $stmt->bind_param(
  'ssiissiiiiiss',
  $bestell, $bez, $kat, $verk, $preisRaw, $liefer, $menge,
  $foto, $db, $web, $kickstarter, $kick_liefer, $notiz
);
    $stmt->execute();
    return (int)$conn->insert_id;

  } else {
    $stmt = $conn->prepare("
      UPDATE Einkauf SET
        Bestelldatum = NULLIF(?, ''),
        Bezeichnung  = ?,
        Kategorie    = NULLIF(?, 0),
        Verkaeufer   = NULLIF(?, 0),
        Preis        = NULLIF(?, ''),
        Lieferdatum  = NULLIF(?, ''),
        Menge        = ?,
        Foto_erstellt       = ?,
        DB_Eintrag_erstellt = ?,
        Foto_Website        = ?,
        Kickstarter         = ?,
        Kickstarter_Lieferdatum = NULLIF(?, ''),
        Notizen      = NULLIF(?, '')
      WHERE ID = ?
    ");
    $stmt->bind_param(
  'ssiissiiiiissi',
  $bestell, $bez, $kat, $verk, $preisRaw, $liefer, $menge,
  $foto, $db, $web, $kickstarter, $kick_liefer, $notiz, $id
);
    $stmt->execute();
    return $id;
  }
}

$msg = '';

/* =================== POST: speichern =================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
    http_response_code(400);
    exit('CSRF ungültig.');
  }

  $id      = (int)($_POST['id'] ?? 0);
  $backUrl = (string)($_POST['back'] ?? 'einkauf.php');

  $v = [
    'Bestelldatum'            => (string)($_POST['Bestelldatum'] ?? ''),
    'Bezeichnung'             => (string)($_POST['Bezeichnung'] ?? ''),
    'Kategorie'               => (string)($_POST['Kategorie'] ?? ''),
    'Verkaeufer'              => (string)($_POST['Verkaeufer'] ?? ''),
    'Preis'                   => (string)($_POST['Preis'] ?? ''),
    'Lieferdatum'             => (string)($_POST['Lieferdatum'] ?? ''),
    'Menge'                   => (string)($_POST['Menge'] ?? '1'),
    'Foto_erstellt'           => isset($_POST['Foto_erstellt']) ? 1 : 0,
    'DB_Eintrag_erstellt'     => isset($_POST['DB_Eintrag_erstellt']) ? 1 : 0,
    'Foto_Website'            => isset($_POST['Foto_Website']) ? 1 : 0,
    'Kickstarter'             => isset($_POST['Kickstarter']) ? 1 : 0,
    'Kickstarter_Lieferdatum' => (string)($_POST['Kickstarter_Lieferdatum'] ?? ''),
    'Notizen'                 => (string)($_POST['Notizen'] ?? ''),
  ];

  if (trim((string)$v['Bezeichnung']) === '') {
    $msg = 'Bezeichnung darf nicht leer sein.';
  } else {
    try {
      $id = insert_or_update($conn, $id, $v);
      header('Location: einkauf_edit.php?id='.$id.'&back='.urlencode($backUrl).'&msg='.urlencode('Gespeichert.'));
      exit;
    } catch (mysqli_sql_exception $e) {
      $msg = 'Fehler: nicht gespeichert (DB): ' . $e->getMessage();
    }
  }
}

/* =================== GET: Datensatz laden =================== */
$data = [
  'Bestelldatum'            => '',
  'Bezeichnung'             => '',
  'Kategorie'               => null,
  'Verkaeufer'              => null,
  'Preis'                   => '',
  'Lieferdatum'             => '',
  'Menge'                   => 1,
  'Foto_erstellt'           => 0,
  'DB_Eintrag_erstellt'     => 0,
  'Foto_Website'            => 0,
  'Kickstarter'             => 0,
  'Kickstarter_Lieferdatum' => '',
  'Notizen'                 => '',
  'Erstellt_am'             => null,
  'Geaendert_am'            => null,
];

if ($id > 0) {
  $stmt = $conn->prepare("SELECT * FROM Einkauf WHERE ID=?");
  $stmt->bind_param('i', $id);
  $stmt->execute();
  $row = stmt_fetch_one_assoc($stmt);
  if ($row) $data = array_merge($data, $row);
}

/* =================== Prev/Next (Blättern) anhand backUrl-Kontext =================== */
$prevId = 0;
$nextId = 0;

if ($id > 0) {
  $backParts = parse_url($backUrl);
  $backQuery = [];
  if (!empty($backParts['query'])) parse_str($backParts['query'], $backQuery);

  $q          = trim((string)($backQuery['q'] ?? ''));
  $status     = (string)($backQuery['status'] ?? 'all');
  $kategorie  = (int)($backQuery['kategorie'] ?? 0);
  $verkaeufer = (int)($backQuery['verkaeufer'] ?? 0);
  $jahr       = (int)($backQuery['jahr'] ?? 0);
  $monat      = (int)($backQuery['monat'] ?? 0);

  $sort = (string)($backQuery['sort'] ?? 'Lieferdatum');
  $dir  = strtolower((string)($backQuery['dir'] ?? 'desc'));
  if (!in_array($dir, ['asc','desc'], true)) $dir = 'desc';

  $lieferDateSql = "
    CASE
      WHEN E.Lieferdatum REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}' THEN DATE(E.Lieferdatum)
      WHEN E.Lieferdatum REGEXP '^[0-9]{2}\\.[0-9]{2}\\.[0-9]{4}$' THEN STR_TO_DATE(E.Lieferdatum,'%d.%m.%Y')
      ELSE NULL
    END
  ";

  $sort_map = [
    'ID'           => 'E.ID',
    'Bestelldatum' => 'E.Bestelldatum',
    'Lieferdatum'  => "($lieferDateSql)",
    'Bezeichnung'  => 'E.Bezeichnung',
    'Kategorie'    => 'K.Kategorie',
    'Verkaeufer'   => 'V.`Verkäufer`',
    'Preis'        => 'E.Preis',
    'Menge'        => 'E.Menge',
  ];
  $order = ($sort_map[$sort] ?? "($lieferDateSql)") . ' ' . strtoupper($dir) . ', E.ID DESC';

  $where = [];
  $args  = [];
  $types = '';

  if ($q !== '') { $where[] = "E.Bezeichnung LIKE ?"; $args[] = "%$q%"; $types .= 's'; }

  // Status wie einkauf.php (inkl. '0000-00-00')
  if ($status === 'offen') {
    $where[] = "(E.Lieferdatum IS NULL OR E.Lieferdatum = '' OR E.Lieferdatum = '0000-00-00')";
  } elseif ($status === 'geliefert') {
    $where[] = "(E.Lieferdatum IS NOT NULL AND E.Lieferdatum <> '' AND E.Lieferdatum <> '0000-00-00')";
  }

  if ($kategorie > 0)  { $where[] = "E.Kategorie = ?";  $args[] = $kategorie;  $types .= 'i'; }
  if ($verkaeufer > 0) { $where[] = "E.Verkaeufer = ?"; $args[] = $verkaeufer; $types .= 'i'; }

  if ($jahr > 0) {
    if ($monat < 1 || $monat > 12) {
      $start = sprintf('%04d-01-01', $jahr);
      $end   = (new DateTime($start))->modify('+1 year')->format('Y-m-d');
    } else {
      $start = sprintf('%04d-%02d-01', $jahr, $monat);
      $end   = (new DateTime($start))->modify('+1 month')->format('Y-m-d');
    }
    $where[] = "($lieferDateSql IS NOT NULL AND $lieferDateSql >= ? AND $lieferDateSql < ?)";
    $args[]  = $start;
    $args[]  = $end;
    $types  .= 'ss';
  }

  $where_sql = $where ? ('WHERE '.implode(' AND ', $where)) : '';

  // IDs im aktuellen Kontext holen
  $sqlIds = "
    SELECT E.ID
    FROM Einkauf E
    LEFT JOIN Kategorie   K ON E.Kategorie  = K.ID
    LEFT JOIN `Verkäufer` V ON E.Verkaeufer = V.ID
    $where_sql
    ORDER BY $order
  ";
  $st = $conn->prepare($sqlIds);
  if ($types !== '') $st->bind_param($types, ...$args);
  $st->execute();
  $idRows = stmt_fetch_all_assoc($st);
  $ids = array_map(fn($r) => (int)$r['ID'], $idRows);

  $pos = array_search($id, $ids, true);
  if ($pos !== false) {
    if ($pos > 0) $prevId = $ids[$pos - 1];
    if ($pos < count($ids) - 1) $nextId = $ids[$pos + 1];
  }
}

$pageTitle = $id ? 'Einkauf bearbeiten' : 'Neuer Einkauf';

$erstellt  = !empty($data['Erstellt_am'])  ? date('d.m.Y H:i:s', strtotime((string)$data['Erstellt_am']))   : '';
$geaendert = !empty($data['Geaendert_am']) ? date('d.m.Y H:i:s', strtotime((string)$data['Geaendert_am'])) : '';

$selKat  = (int)($data['Kategorie'] ?? 0);
$selV    = (int)($data['Verkaeufer'] ?? 0);
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <script src="/sammlung/assets/theme-toggle.js"></script>
  <link rel="stylesheet" href="/sammlung/assets/app.css?v=10">
  <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?> – Sammlung</title>

  <style>
    .btn.nav-disabled{ opacity:.35; pointer-events:none; }

    a.btn.nav{
      background:#0f766e;
      border:1px solid #0b5e57;
      color:#fff !important;
    }

    a.btn.nav:hover{
      filter:brightness(0.92);
    }

    span.btn.nav-disabled{
      background:#e9ecef;
      border:1px solid #cfd4da;
      color:#6c757d;
    }
  </style>
</head>
<body class="edit-page">

<h1><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></h1>

<?php if (!empty($_GET['msg'])): ?>
  <div style="background:#eef;border:1px solid #99f;padding:8px;margin:10px 0;">
    <?= htmlspecialchars((string)$_GET['msg'], ENT_QUOTES, 'UTF-8') ?>
  </div>
<?php endif; ?>

<?php if ($msg): ?>
  <div style="background:#fee;border:1px solid #f99;padding:8px;margin:10px 0;">
    <?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?>
  </div>
<?php endif; ?>

<div class="buttons">
  <div class="row row-main">
    <a class="btn back" href="<?= htmlspecialchars($backUrl, ENT_QUOTES, 'UTF-8') ?>">← Zurück</a>

    <?php if ($id > 0): ?>
      <?php if ($prevId > 0): ?>
        <a class="btn nav" href="einkauf_edit.php?id=<?= (int)$prevId ?>&back=<?= urlencode($backUrl) ?>">‹ Vorheriger</a>
      <?php else: ?>
        <span class="btn nav-disabled">‹ Vorheriger</span>
      <?php endif; ?>

      <?php if ($nextId > 0): ?>
        <a class="btn nav" href="einkauf_edit.php?id=<?= (int)$nextId ?>&back=<?= urlencode($backUrl) ?>">Nächster ›</a>
      <?php else: ?>
        <span class="btn nav-disabled">Nächster ›</span>
      <?php endif; ?>
    <?php endif; ?>

    <button type="submit" class="btn save" form="f">💾 Speichern</button>
  </div>
</div>

<form id="f" method="post">
  <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf'], ENT_QUOTES, 'UTF-8') ?>">
  <input type="hidden" name="id" value="<?= (int)$id ?>">
  <input type="hidden" name="back" value="<?= htmlspecialchars($backUrl, ENT_QUOTES, 'UTF-8') ?>">

  <div class="container">
    <div class="fields">
      <div class="form-table">

        <div class="field">
          <span class="label">Bestelldatum</span>
          <input type="date" name="Bestelldatum" value="<?= htmlspecialchars((string)($data['Bestelldatum'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        </div>

        <div class="field">
          <span class="label">Bezeichnung</span>
          <input type="text" name="Bezeichnung" value="<?= htmlspecialchars((string)($data['Bezeichnung'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required>
        </div>

        <div class="field">
          <span class="label">Kategorie</span>
          <select name="Kategorie"><?= get_options($conn, 'Kategorie', 'Kategorie', $selKat) ?></select>
        </div>

        <div class="field">
          <span class="label">Verkäufer</span>
          <select name="Verkaeufer"><?= get_options($conn, 'Verkäufer', 'Verkäufer', $selV) ?></select>
        </div>

        <div class="field">
          <span class="label">Preis</span>
          <input type="text" name="Preis"
                 value="<?= htmlspecialchars((string)($data['Preis'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                 placeholder="z.B. 12,50">
        </div>

        <div class="field">
          <span class="label">Lieferdatum</span>
          <input type="date" name="Lieferdatum" value="<?= htmlspecialchars((string)($data['Lieferdatum'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        </div>

        <div class="field">
          <span class="label">Menge</span>
          <input type="number" name="Menge" min="1" value="<?= (int)($data['Menge'] ?? 1) ?>">
        </div>

        <div class="field">
          <span class="label">Foto erstellt</span>
          <input type="checkbox" name="Foto_erstellt" value="1" <?= !empty($data['Foto_erstellt']) ? 'checked' : '' ?>>
        </div>

        <div class="field">
          <span class="label">DB-Eintrag erstellt</span>
          <input type="checkbox" name="DB_Eintrag_erstellt" value="1" <?= !empty($data['DB_Eintrag_erstellt']) ? 'checked' : '' ?>>
        </div>

        <div class="field">
          <span class="label">Foto auf Website</span>
          <input type="checkbox" name="Foto_Website" value="1" <?= !empty($data['Foto_Website']) ? 'checked' : '' ?>>
        </div>

        <div class="field">
          <span class="label">Kickstarter</span>
          <input type="checkbox" name="Kickstarter" value="1" <?= !empty($data['Kickstarter']) ? 'checked' : '' ?>>
        </div>

        <div class="field">
          <span class="label">Geplantes Lieferdatum Kickstarter</span>
          <input type="date" name="Kickstarter_Lieferdatum" value="<?= htmlspecialchars((string)($data['Kickstarter_Lieferdatum'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        </div>

        <div class="field">
          <span class="label">Notizen</span>
          <textarea name="Notizen" rows="4"><?= htmlspecialchars((string)($data['Notizen'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
        </div>

        <div class="field readonly">
          <span class="label">Erstellt am</span>
          <input type="text" value="<?= htmlspecialchars($erstellt, ENT_QUOTES, 'UTF-8') ?>" readonly tabindex="-1">
        </div>

        <div class="field readonly">
          <span class="label">Geändert am</span>
          <input type="text" value="<?= htmlspecialchars($geaendert, ENT_QUOTES, 'UTF-8') ?>" readonly tabindex="-1">
        </div>

      </div>
    </div>
  </div>
</form>

</body>
</html>