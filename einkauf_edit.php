<?php
// einkauf_edit.php — Neu/Bearbeiten für Tabelle `Einkauf` + Blättern (vor/zurück) im Kontext von einkauf.php

require_once __DIR__ . '/inc_errors.php';
require_once __DIR__ . '/inc_icons.php';
require_once __DIR__ . '/inc_nav.php';

require_once __DIR__ . '/require_login.php';
require_once __DIR__ . '/inc_security.php';
require_once __DIR__ . '/inc_format.php';
require_once __DIR__ . '/inc_filter.php';   // db_select()
if (!is_admin()) { error_page(403, 'Kein Zugriff', 'Dieser Bereich ist nur für Administratoren.'); }

require_once __DIR__ . '/config.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$conn->set_charset('utf8mb4');
date_default_timezone_set('Europe/Berlin');

$id      = (int)($_GET['id'] ?? 0);
$backUrl = safe_back_url($_GET['back'] ?? null, 'einkauf.php');

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
  [$t, $f] = lookup_ident($table, $field);
  $res = $conn->query("SELECT ID, $f AS label FROM $t ORDER BY $f");
  while ($r = $res->fetch_assoc()) {
    $rid = (int)$r['ID'];
    $sel = ($rid === $selected) ? ' selected' : '';
    $out .= "<option value=\"$rid\"$sel>" . htmlspecialchars((string)$r['label'], ENT_QUOTES, 'UTF-8') . "</option>\n";
  }
  return $out;
}

/**
 * ID aus Such-Textfeld (Kategorie/Verkäufer) ermitteln, falls das versteckte
 * ID-Feld leer ankommt (z.B. veraltetes JavaScript im Browser-Cache).
 * Reihenfolge: 1) Hidden-ID, 2) "(#ID)" im Text, 3) exakter Name in der Lookup-Tabelle.
 */
function resolve_lookup_id(mysqli $conn, string $table, string $field, string $idRaw, string $textRaw): string {
  $id = (int)$idRaw;
  if ($id > 0) return (string)$id;

  $text = trim($textRaw);
  if ($text === '') return '';

  if (preg_match('/\(#(\d+)\)\s*$/', $text, $m)) return $m[1];

  [$t, $f] = lookup_ident($table, $field);
  $stmt = $conn->prepare("SELECT ID FROM $t WHERE $f = ? LIMIT 1");
  $stmt->bind_param('s', $text);
  $stmt->execute();
  $row = stmt_fetch_one_assoc($stmt);
  return $row ? (string)(int)$row['ID'] : '';
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

  $preisRaw = parse_decimal($v['Preis'] ?? '');   // "12,50" -> "12.50", leer/ungültig -> NULL

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
  require_post_csrf();

  $id      = (int)($_POST['id'] ?? 0);
  $backUrl = safe_back_url($_POST['back'] ?? null, 'einkauf.php');

  $v = [
    'Bestelldatum'            => (string)($_POST['Bestelldatum'] ?? ''),
    'Bezeichnung'             => (string)($_POST['Bezeichnung'] ?? ''),
    'Kategorie'               => resolve_lookup_id($conn, 'Kategorie', 'Kategorie', (string)($_POST['Kategorie'] ?? ''), (string)($_POST['Kategorie_text'] ?? '')),
    'Verkaeufer'              => resolve_lookup_id($conn, 'Verkäufer', 'Verkäufer', (string)($_POST['Verkaeufer'] ?? ''), (string)($_POST['Verkaeufer_text'] ?? '')),
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
      // „Als NEU speichern“: aktuelle Formularwerte (auch ungespeicherte Änderungen) als neuen Eintrag anlegen
      $dup = (($_POST['action'] ?? '') === 'dup') && $id > 0;
      if ($dup) {
        $id = 0;
        $v['Bezeichnung'] = trim((string)$v['Bezeichnung']) . ' (Kopie)';
      }
      $id = insert_or_update($conn, $id, $v);
      flash_set($dup ? 'Als neuer Eintrag gespeichert (Kopie).' : 'Gespeichert.', 'success');
      header('Location: einkauf_edit.php?id='.$id.'&back='.urlencode($backUrl));
      exit;
    } catch (mysqli_sql_exception $e) {
      error_log('[einkauf_edit.php] Speichern: ' . $e->getMessage());
      $msg = 'Fehler: nicht gespeichert (Datenbankfehler, Details im Log).';
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

// Anzeige-Label für die Such-Textfelder (Kategorie/Verkäufer) ermitteln
$selKatLabel = '';
if ($selKat > 0) {
    $r = db_select($conn, "SELECT Kategorie FROM Kategorie WHERE ID = ?", 'i', [$selKat])->fetch_row();
    if ($r) $selKatLabel = (string)$r[0];
}
$selVLabel = '';
if ($selV > 0) {
    $r = db_select($conn, "SELECT `Verkäufer` FROM `Verkäufer` WHERE ID = ?", 'i', [$selV])->fetch_row();
    if ($r) $selVLabel = (string)$r[0];
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?> – Sammlung</title>
  <script src="/sammlung/assets/theme-toggle.js?v=6"></script>
  <script src="/sammlung/assets/typeahead.js?v=1" defer></script>
  <script src="/sammlung/assets/edit.js?v=2" defer></script>
  <link rel="stylesheet" href="/sammlung/assets/nav.css?v=2">
  <link rel="stylesheet" href="/sammlung/assets/edit.css?v=3">
  <link rel="stylesheet" href="/sammlung/assets/base.css?v=4">
</head>
<body class="edit-page">
<a class="skip-link" href="#inhalt">Zum Inhalt springen</a>

<header class="appbar">
  <p class="appbar-titel"><a href="index.php"><?= htmlspecialchars(APP_TITLE, ENT_QUOTES, 'UTF-8') ?></a></p>
  <?= nav_appnav(['is_guest' => false, 'is_admin' => true, 'museum' => false, 'export' => true, 'params' => []]) ?>
  <button type="button" id="themeToggle" class="btn theme-toggle appbar-icon" title="Hell/Dunkel umschalten"></button>
</header>

<main id="inhalt" tabindex="-1">

<h1><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></h1>

<?php $flash = flash_pull(); ?>
<?php if ($flash): ?>
  <div class="msg-box <?= htmlspecialchars($flash['type'], ENT_QUOTES, 'UTF-8') ?>" role="status">
    <?= htmlspecialchars($flash['text'], ENT_QUOTES, 'UTF-8') ?>
  </div>
<?php endif; ?>

<?php if ($msg): ?>
  <div class="msg-box error" role="alert" id="formFehler">
    <?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?>
  </div>
<?php endif; ?>

<?php
  $e = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
  $navLink = function (bool $aktiv, string $href, string $titel, string $inhalt) use ($e): string {
      return '<a class="btn nav' . ($aktiv ? '' : ' disabled') . '" href="' . $e($href) . '"'
           . ($aktiv ? '' : ' aria-disabled="true" tabindex="-1"') . ' title="' . $e($titel) . '">' . $inhalt . '</a>';
  };
?>

<!-- Speichern / Abbrechen (auf dem iPhone fest unten) -->
<div class="edit-leiste edit-speichern">
  <a class="btn back" href="<?= $e($backUrl) ?>" title="Zurück zur Liste"><?= icon('back') ?> <span class="nur-gross">Zurück</span><span class="nur-klein">Abbrechen</span></a>
  <button type="submit" class="btn save" form="f" title="Speichern"><?= icon('save') ?> Speichern</button>
</div>

<?php if ($id > 0): ?>
<div class="edit-leiste edit-nav">
  <?= $navLink($prevId > 0, 'einkauf_edit.php?id=' . (int)$prevId . '&back=' . urlencode($backUrl), 'Vorheriger Eintrag', icon('back') . ' Vorheriger') ?>
  <?= $navLink($nextId > 0, 'einkauf_edit.php?id=' . (int)$nextId . '&back=' . urlencode($backUrl), 'Nächster Eintrag', 'Nächster ' . icon('forward')) ?>
  <button type="submit" form="f" name="action" value="dup" class="btn dup"
          title="Aktuelle Angaben (auch ungespeicherte Änderungen) als neuen Eintrag speichern"><?= icon('copy') ?> Als NEU speichern</button>
</div>
<?php endif; ?>

<form id="f" method="post">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int)$id ?>">
  <input type="hidden" name="back" value="<?= $e($backUrl) ?>">

  <div class="container">
    <div class="fields">
      <div class="form-groups">

        <details class="form-group" open>
          <summary>Bestellung</summary>
          <div class="form-grid">
            <div class="field">
              <label class="label" for="f_Bestelldatum">Bestelldatum</label>
              <input type="date" id="f_Bestelldatum" name="Bestelldatum" value="<?= $e($data['Bestelldatum'] ?? '') ?>">
            </div>
            <div class="field wide">
              <label class="label" for="f_Bezeichnung">Bezeichnung</label>
              <input type="text" id="f_Bezeichnung" name="Bezeichnung" value="<?= $e($data['Bezeichnung'] ?? '') ?>" required aria-required="true"<?= $msg ? ' aria-describedby="formFehler"' : '' ?>>
            </div>
            <div class="field">
              <label class="label" for="f_Kategorie">Kategorie</label>
              <input type="text" id="f_Kategorie" name="Kategorie_text" class="lookup-search ta-input" autocomplete="off"
                     placeholder="Tippen zum Suchen…" value="<?= $e($selKatLabel) ?>"
                     data-ta="kategorie" data-ta-hidden="Kategorie_hidden">
              <input type="hidden" name="Kategorie" id="Kategorie_hidden" value="<?= $selKat ?>">
            </div>
            <div class="field">
              <label class="label" for="f_Verkaeufer">Verkäufer</label>
              <input type="text" id="f_Verkaeufer" name="Verkaeufer_text" class="lookup-search ta-input" autocomplete="off"
                     placeholder="Tippen zum Suchen…" value="<?= $e($selVLabel) ?>"
                     data-ta="verkaeufer" data-ta-hidden="Verkaeufer_hidden">
              <input type="hidden" name="Verkaeufer" id="Verkaeufer_hidden" value="<?= $selV ?>">
            </div>
            <div class="field">
              <label class="label" for="f_Preis">Preis</label>
              <input type="text" id="f_Preis" name="Preis"
                     value="<?php $pv = parse_decimal($data['Preis'] ?? ''); echo $e($pv !== null ? fmt_dec($pv) : (string)($data['Preis'] ?? '')); ?>"
                     inputmode="decimal" autocomplete="off" placeholder="z. B. 12,50">
            </div>
            <div class="field">
              <label class="label" for="f_Menge">Menge</label>
              <input type="number" id="f_Menge" name="Menge" min="1" inputmode="numeric" value="<?= (int)($data['Menge'] ?? 1) ?>">
            </div>
          </div>
        </details>

        <details class="form-group" open>
          <summary>Lieferung</summary>
          <div class="form-grid">
            <div class="field">
              <label class="label" for="f_Lieferdatum">Lieferdatum</label>
              <input type="date" id="f_Lieferdatum" name="Lieferdatum" value="<?= $e($data['Lieferdatum'] ?? '') ?>">
            </div>
            <div class="field checkbox-field">
              <label class="label" for="f_Kickstarter">Kickstarter</label>
              <input type="checkbox" id="f_Kickstarter" name="Kickstarter" value="1" <?= !empty($data['Kickstarter']) ? 'checked' : '' ?>>
            </div>
            <div class="field">
              <label class="label" for="f_Kickstarter_Lieferdatum">Geplantes Lieferdatum Kickstarter</label>
              <input type="date" id="f_Kickstarter_Lieferdatum" name="Kickstarter_Lieferdatum" value="<?= $e($data['Kickstarter_Lieferdatum'] ?? '') ?>">
            </div>
          </div>
        </details>

        <details class="form-group" open>
          <summary>Fortschritt</summary>
          <div class="form-grid">
            <div class="field checkbox-field">
              <label class="label" for="f_Foto_erstellt">Foto erstellt</label>
              <input type="checkbox" id="f_Foto_erstellt" name="Foto_erstellt" value="1" <?= !empty($data['Foto_erstellt']) ? 'checked' : '' ?>>
            </div>
            <div class="field checkbox-field">
              <label class="label" for="f_DB_Eintrag_erstellt">DB-Eintrag erstellt</label>
              <input type="checkbox" id="f_DB_Eintrag_erstellt" name="DB_Eintrag_erstellt" value="1" <?= !empty($data['DB_Eintrag_erstellt']) ? 'checked' : '' ?>>
            </div>
            <div class="field checkbox-field">
              <label class="label" for="f_Foto_Website">Foto auf Website</label>
              <input type="checkbox" id="f_Foto_Website" name="Foto_Website" value="1" <?= !empty($data['Foto_Website']) ? 'checked' : '' ?>>
            </div>
          </div>
        </details>

        <details class="form-group" open>
          <summary>Notizen</summary>
          <div class="form-grid">
            <div class="field wide">
              <label class="label" for="f_Notizen">Notizen</label>
              <textarea id="f_Notizen" name="Notizen" rows="4"><?= $e($data['Notizen'] ?? '') ?></textarea>
            </div>
          </div>
        </details>

        <details class="form-group" open>
          <summary>Verwaltung</summary>
          <div class="form-grid">
            <div class="field readonly">
              <label class="label" for="f_erstellt">Erstellt am</label>
              <input type="text" id="f_erstellt" value="<?= $e($erstellt) ?>" readonly tabindex="-1">
            </div>
            <div class="field readonly">
              <label class="label" for="f_geaendert">Geändert am</label>
              <input type="text" id="f_geaendert" value="<?= $e($geaendert) ?>" readonly tabindex="-1">
            </div>
          </div>
        </details>

      </div>
    </div>
  </div>
</form>

</main>
</body>
</html>