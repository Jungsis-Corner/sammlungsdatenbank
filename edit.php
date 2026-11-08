<?php
require_once __DIR__ . '/inc_errors.php';
require_once __DIR__ . '/inc_icons.php';
require_once __DIR__ . '/inc_nav.php';

// ---- (Optional) PHP-Zeitzone setzen ----
date_default_timezone_set('Europe/Berlin');

// ---- Login prüfen & nur Admin zulassen ----
require_once __DIR__ . '/require_login.php';
require_once __DIR__ . '/inc_security.php';
require_once __DIR__ . '/inc_format.php';
require_once __DIR__ . '/inc_image.php';
require_once __DIR__ . '/inc_filter.php';
require_once __DIR__ . '/inc_igdb.php';
require_once __DIR__ . '/inc_edit.php';
if (!is_admin()) { error_page(403, 'Kein Zugriff', 'Dieser Bereich ist nur für Administratoren.'); }

// --- CSRF-Token initialisieren ---
if (empty($_SESSION['csrf'])) {
  $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
$is_guest = false;                        // rein informativ für Anzeige/Buttons

// ---- DB-Verbindung ----
require_once __DIR__ . '/config.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$conn->set_charset('utf8mb4');
if ($conn->connect_errno) {
    fail_db('Datenbankfehler.', 'Verbindung: ' . $conn->connect_error);
}
// (Optional) MySQL-Session-Zeitzone setzen – wenn dein Hoster es unterstützt
@$conn->query("SET time_zone = 'Europe/Berlin'");


// GET-Parameter
$id             = intval($_GET['id']        ?? 0);
$page           = intval($_GET['page']      ?? 1);
$sort           = $_GET['sort']             ?? 'Bezeichnung';
$dir            = strtolower($_GET['dir']   ?? 'asc');          // NEU: Richtung übernehmen
if (!in_array($dir, ['asc','desc'], true)) { $dir = 'asc'; }

$filter         = $_GET['filter']           ?? '';
$kat             = intval($_GET['kat'] ?? 0);              // Kategorie-Knoten
$inkl            = ($_GET['inkl'] ?? '') === '1';          // inkl. Unterkategorien
$oh             = $_GET['oh']               ?? '';
$material       = $_GET['material']         ?? '';
$q              = $_GET['q']                ?? '';
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

// damit die Nav-Buttons nicht undefiniert sind, auch bei $id==0
$prev_id = null;
$next_id = null;

// Erlaube id=0 für „Neuer Eintrag“
if ($id < 0) die('Ungültige ID.');

// Basis-Parameter für Prev/Next & Zurück-Link
$params = ['page'=>$page,'sort'=>$sort,'dir'=>$dir]; // NEU: dir aufnehmen
if ($filter        !== '') $params['filter']     = $filter;
if ($kat           >   0) $params['kat']        = $kat;
if ($inkl                 ) $params['inkl']       = '1';
if ($oh            !== '') $params['oh']         = $oh;
if ($material      !== '') $params['material']   = $material;
if ($q             !== '') $params['q']          = $q;
if ($hersteller_id )       $params['hersteller'] = $hersteller_id;
if ($publisher_id)         $params['publisher']  = $publisher_id;
if ($verkaeufer_id)        $params['verkaeufer'] = $verkaeufer_id;
if ($standort_id)          $params['standort']   = $standort_id;
if ($box_id !== '')        $params['box']        = $box_id;
if ($verkauf_filter)       $params['verkauf']    = '1';
if ($einkaufsdatum_filter !== '') $params['einkaufsdatum'] = $einkaufsdatum_filter;
$listUrl = 'index.php?' . http_build_query($params);
$viewUrl = 'view.php?' . http_build_query(array_merge($params, ['id'=>$id]));
// --- Navi-Variablen sicher initialisieren (auch für id==0) ---
$prev_id = $next_id = $first_id = $last_id = null;

// Basis-HREF (self) – funktioniert auch bei id=0
$selfHref  = 'edit.php?' . http_build_query(array_merge($params, ['id'=>$id]));
$prevHref  = $selfHref;
$nextHref  = $selfHref;
$firstHref = $selfHref;
$lastHref  = $selfHref;

// Spalten & Typen für das Formular (inkl. Anzahl)
// >>> WICHTIG: KEINE Zeitstempel-Felder hier! <<<
$inputs = EDIT_FIELDS;   // Reihenfolge und Art der Felder (inc_edit.php)


/* ============== Speichern (Update, Insert, Duplikat): inc_edit.php ============== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') !== 'igdb') {
    edit_handle_post($conn, $id);
}

/* ======== Datensatz laden & Prev/Next ermitteln (inc_edit.php) ======== */
$data = edit_load_record($conn, $id, $_GET);
if ($id > 0) {
    $nb = edit_neighbors($conn, $id, [
        'filter' => $filter, 'kat' => $kat, 'inkl' => $inkl, 'oh' => $oh, 'material' => $material, 'q' => $q,
        'hersteller' => $hersteller_id, 'publisher' => $publisher_id, 'verkaeufer' => $verkaeufer_id,
        'standort' => $standort_id, 'box' => $box_id, 'verkauf' => $verkauf_filter,
        'einkaufsdatum' => $einkaufsdatum_filter,
    ], $sort, $dir);
    [$prev_id, $next_id, $first_id, $last_id] = [$nb['prev'], $nb['next'], $nb['first'], $nb['last']];

    // Helper-HREFs (immer gültig, disabled zeigt dann nur Styling)
    $makeHref = function (int $targetId) use ($params): string {
        return 'edit.php?' . http_build_query(array_merge($params, ['id' => $targetId]));
    };
    $selfHref  = $makeHref((int)$id);
    $prevHref  = $prev_id ? $makeHref((int)$prev_id) : $selfHref;
    $nextHref  = $next_id ? $makeHref((int)$next_id) : $selfHref;
    $firstHref = $first_id ? $makeHref((int)$first_id) : $selfHref;
    $lastHref  = $last_id  ? $makeHref((int)$last_id)  : $selfHref;
}

/* ================ IGDB-Import-Action (robust) ================
   Trigger: POST an edit.php?id=... mit action=igdb und csrf (Button "IGDB holen")
   - läuft NACH dem Laden von $data
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'igdb') {
    igdb_import_action($conn, (int)$id, is_array($data ?? null) ? $data : null);
}

// Einmalige Meldung (z. B. vom IGDB-Import) aus der Session holen und löschen
$flashMsg  = (string)($_SESSION['flash_msg']  ?? '');
$flashDiag = (string)($_SESSION['flash_diag'] ?? '');
unset($_SESSION['flash_msg'], $_SESSION['flash_diag']);

/* ============== Formular ausgeben (reine Ausgabe, Variablen von oben) ============== */
require __DIR__ . '/inc_edit_form.php';
