<?php
require_once __DIR__ . '/inc_errors.php';
require __DIR__ . '/require_login.php';   // Login-Prüfung + Session (boot.php)
require_once __DIR__ . '/inc_security.php';
require_once __DIR__ . '/inc_filter.php';
if (!is_admin()) { http_response_code(403); exit('Nur für Admins.'); }
require_post_csrf();                       // Löschen nur per POST mit CSRF-Token

require_once __DIR__ . '/config.php';
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) fail_db('Datenbankfehler.', 'Verbindung: ' . $conn->connect_error);

// 1) Eingaben einsammeln
$id   = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id <= 0) { http_response_code(400); exit('Ungültige ID'); }

// Diese Parameter zurück zu index.php geben (Filter/Sortierung/Seite)
$keepKeys = ['page','sort','dir','filter','kat','inkl','oh','material','q','hersteller','publisher','verkaeufer','standort','box','verkauf','einkaufsdatum'];
$return = [];
foreach ($keepKeys as $k) {
  if (isset($_POST[$k]) && $_POST[$k] !== '') $return[$k] = $_POST[$k];
}

// 2) Datensatz löschen (prepared)
$stmt = $conn->prepare("DELETE FROM Sammlung WHERE ID = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$stmt->close();

// 3) Optional: Seite anpassen, falls nach dem Löschen leer
$limit = 50; // muss mit index.php übereinstimmen

// WHERE wie in index.php (gemeinsam in inc_filter.php, mit Prepared Statements)
$flt = sammlung_filter_where([
    'filter' => $_POST['filter'] ?? '', 'kat' => $_POST['kat'] ?? 0, 'inkl' => $_POST['inkl'] ?? '', 'oh' => $_POST['oh'] ?? '', 'material' => $_POST['material'] ?? '',
    'q' => $_POST['q'] ?? '', 'hersteller' => $_POST['hersteller'] ?? 0, 'publisher' => $_POST['publisher'] ?? 0,
    'verkaeufer' => $_POST['verkaeufer'] ?? 0, 'standort' => $_POST['standort'] ?? 0,
    'box' => $_POST['box'] ?? '', 'verkauf' => $_POST['verkauf'] ?? '', 'einkaufsdatum' => $_POST['einkaufsdatum'] ?? '',
]);
$where_sql = $flt['sql'];

// Anzahl ermitteln
$count_sql = "
  SELECT COUNT(*) 
  FROM Sammlung
  LEFT JOIN Kategorie  ON Sammlung.Kategorie  = Kategorie.ID
  LEFT JOIN Material   ON Sammlung.Material   = Material.ID
  LEFT JOIN Zustand    ON Sammlung.Zustand    = Zustand.ID
  LEFT JOIN Verpackung ON Sammlung.Verpackung = Verpackung.ID
  LEFT JOIN Standort   ON Sammlung.Standort   = Standort.ID
  $where_sql
";
$res = db_select($conn, $count_sql, $flt['types'], $flt['values']);
$total = ($res && $r = $res->fetch_row()) ? (int)$r[0] : 0;
$pages = max(1, (int)ceil($total / $limit));

// Gewünschte Seite aus Parametern holen (default 1) und ggf. begrenzen
$curPage = isset($return['page']) ? max(1, (int)$return['page']) : 1;
if ($curPage > $pages) $curPage = $pages;
$return['page'] = $curPage;

// kleine Erfolgsmeldung (einmalig über die Session, nicht in der URL)
flash_set('Eintrag wurde gelöscht.', 'success');

// 4) Zurück zur Liste – mit allen Filtern/Sortierung/Seite
header('Location: index.php?' . http_build_query($return));
exit;
