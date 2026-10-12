<?php
ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(E_ALL);
require __DIR__ . '/require_login.php';   // Login-Prüfung + Session (boot.php)
require_once __DIR__ . '/inc_security.php';
if (!is_admin()) { http_response_code(403); exit('Nur für Admins.'); }
require_post_csrf();                       // Löschen nur per POST mit CSRF-Token

require_once __DIR__ . '/config.php';
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) die("Verbindungsfehler: " . $conn->connect_error);

// 1) Eingaben einsammeln
$id   = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id <= 0) { http_response_code(400); exit('Ungültige ID'); }

// Diese Parameter zurück zu index.php geben (Filter/Sortierung/Seite)
$keepKeys = ['page','sort','dir','filter','oh','material','q','hersteller','publisher','verkaeufer','standort','box','verkauf','einkaufsdatum'];
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

// WHERE wie in index.php nachbauen
$where = [];
// Hinweis: gleiche Logik/Spaltennamen wie in index.php
if (!empty($_POST['filter'])) {
    $f = $_POST['filter'];
    if ($f === 'spiel') {
        $where[] = "Kategorie.Kategorie LIKE '%Spiel%'";
    } elseif ($f === 'spiel_original') {
        $where[] = "Kategorie.Kategorie LIKE '%Spiel%' AND Sammlung.`Original/Homebrew`='Original'";
    } elseif ($f === 'spiel_homebrew') {
        $where[] = "Kategorie.Kategorie LIKE '%Spiel%' AND Sammlung.`Original/Homebrew`='Homebrew'";
    } elseif (is_numeric($f)) {
        $where[] = "Sammlung.Kategorie=" . (int)$f;
    }
}
if (!empty($_POST['oh'])) {
    if ($_POST['oh']==='Original')   $where[] = "Sammlung.`Original/Homebrew`='Original'";
    if ($_POST['oh']==='Homebrew')   $where[] = "Sammlung.`Original/Homebrew`='Homebrew'";
}
if (!empty($_POST['material'])) {
    $where[] = "Material.Material LIKE '" . $conn->real_escape_string($_POST['material']) . "%'";
}
if (!empty($_POST['q'])) {
    $where[] = "Sammlung.Bezeichnung LIKE '%" . $conn->real_escape_string(trim($_POST['q'])) . "%'";
}
foreach (['hersteller'=>'Hersteller','publisher'=>'Publisher','verkaeufer'=>'Verkäufer','standort'=>'Standort'] as $qp=>$col) {
    if (!empty($_POST[$qp])) $where[] = "Sammlung.$col=".(int)$_POST[$qp];
}
if (!empty($_POST['box'])) {
    $where[] = "Sammlung.Box = '" . $conn->real_escape_string($_POST['box']) . "'";
}
$where_sql = $where ? 'WHERE '.implode(' AND ',$where) : '';

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
$res = $conn->query($count_sql);
$total = ($res && $r = $res->fetch_row()) ? (int)$r[0] : 0;
$pages = max(1, (int)ceil($total / $limit));

// Gewünschte Seite aus Parametern holen (default 1) und ggf. begrenzen
$curPage = isset($return['page']) ? max(1, (int)$return['page']) : 1;
if ($curPage > $pages) $curPage = $pages;
$return['page'] = $curPage;

// kleine Erfolgsmeldung
$return['msg'] = 'deleted';

// 4) Zurück zur Liste – mit allen Filtern/Sortierung/Seite
header('Location: index.php?' . http_build_query($return));
exit;
