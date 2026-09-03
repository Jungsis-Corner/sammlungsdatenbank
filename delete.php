<?php
ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(E_ALL);
session_start();
if (!isset($_SESSION['loggedin'])) { header('Location: login.php'); exit; }
$is_guest = ($_SESSION['user'] ?? '') === 'gast';
if ($is_guest) { http_response_code(403); exit('Kein Zugriff'); }

require_once __DIR__ . '/config.php';
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) die("Verbindungsfehler: " . $conn->connect_error);

// 1) Eingaben einsammeln
$id   = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { http_response_code(400); exit('Ungültige ID'); }

// Diese Parameter zurück zu index.php geben (Filter/Sortierung/Seite)
$keepKeys = ['page','sort','filter','oh','material','q','hersteller','publisher','verkaeufer','standort','box'];
$return = [];
foreach ($keepKeys as $k) {
  if (isset($_GET[$k]) && $_GET[$k] !== '') $return[$k] = $_GET[$k];
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
if (!empty($_GET['filter'])) {
    $f = $_GET['filter'];
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
if (!empty($_GET['oh'])) {
    if ($_GET['oh']==='Original')   $where[] = "Sammlung.`Original/Homebrew`='Original'";
    if ($_GET['oh']==='Homebrew')   $where[] = "Sammlung.`Original/Homebrew`='Homebrew'";
}
if (!empty($_GET['material'])) {
    $where[] = "Material.Material LIKE '" . $conn->real_escape_string($_GET['material']) . "%'";
}
if (!empty($_GET['q'])) {
    $where[] = "Sammlung.Bezeichnung LIKE '%" . $conn->real_escape_string(trim($_GET['q'])) . "%'";
}
foreach (['hersteller'=>'Hersteller','publisher'=>'Publisher','verkaeufer'=>'Verkäufer','standort'=>'Standort'] as $qp=>$col) {
    if (!empty($_GET[$qp])) $where[] = "Sammlung.$col=".(int)$_GET[$qp];
}
if (!empty($_GET['box'])) {
    $where[] = "Sammlung.Box = '" . $conn->real_escape_string($_GET['box']) . "'";
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
