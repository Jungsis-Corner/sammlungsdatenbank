<?php
require_once __DIR__ . '/inc_errors.php';
require __DIR__ . '/require_login.php';
require_once __DIR__ . '/inc_filter.php';   // kategorie_baum()
$is_guest = is_guest();

require_once __DIR__ . '/config.php';
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) fail_db('Datenbankfehler.', 'Verbindung: ' . $conn->connect_error);

// 1) Gesamtanzahl aller Datensätze
$total_res = $conn->query("SELECT COUNT(*) AS cnt FROM Sammlung");
$total_cnt = $total_res->fetch_assoc()['cnt'];

// 2) Anzahl aller Datensätze in Spiele-Kategorien (Ebene 'spiele')
$spiel_res = $conn->query("
    SELECT COUNT(*) AS cnt
      FROM Sammlung S
      LEFT JOIN Kategorie K ON S.Kategorie = K.ID
     WHERE K.Ebene = 'spiele'
");
$spiel_cnt = $spiel_res->fetch_assoc()['cnt'];

// 3) Aufschlüsselung nach Material
$material_stats = [];
$mat_res = $conn->query("
    SELECT M.Material AS Name, COUNT(S.ID) AS Anzahl
      FROM Sammlung S
      LEFT JOIN Material M ON S.Material = M.ID
     GROUP BY S.Material
     ORDER BY M.Material
");
while ($row = $mat_res->fetch_assoc()) {
    $material_stats[] = $row;
}

// 4) Kategorie-Statistik: Einträge je Kategorie (flach) und Gruppierung nach Marke, Plattform und Typ
$kategorien = [];
$kat_res = $conn->query("
    SELECT K.ID AS ID, K.Kategorie AS Name, COUNT(S.ID) AS Anzahl
      FROM Sammlung S
      LEFT JOIN Kategorie K ON S.Kategorie = K.ID
     GROUP BY S.Kategorie
     ORDER BY Name
");
while ($row = $kat_res->fetch_assoc()) {
    $kategorien[] = $row;
}

$katNode = [];
foreach (kategorie_baum($conn) as $n) { $katNode[$n['id']] = $n; }

/** Kette von der Kategorie nach oben bis zur Wurzel (Kategorie zuerst), mit Bremse gegen Zyklen. */
function kat_kette(array $katNode, int $id): array {
    $kette = [];
    while (isset($katNode[$id]) && count($kette) < 20) {
        $kette[] = $katNode[$id];
        $id = $katNode[$id]['parent'] ?? 0;
    }
    return $kette;
}

$nachMarke = [];       // Wurzel-ID => ['id','name','n']
$nachPlattform = [];   // oberste Plattform-ID bzw. 'o<Wurzel-ID>' (ohne Plattform) => ['id','name','n','link']
$nachTyp = ['spiele' => 0, 'anwendungen' => 0, 'sonstiges' => 0, 'hardware' => 0, 'ohne' => 0];
foreach ($kategorien as $k) {
    $n = (int)$k['Anzahl'];
    if ($k['ID'] === null || !isset($katNode[(int)$k['ID']])) {
        $nachMarke['ohne'] = ['id' => 0, 'name' => '(ohne Kategorie)', 'n' => ($nachMarke['ohne']['n'] ?? 0) + $n];
        $nachPlattform['ohne'] = ['id' => 0, 'name' => '(ohne Kategorie)', 'n' => ($nachPlattform['ohne']['n'] ?? 0) + $n, 'link' => false];
        $nachTyp['ohne'] += $n;
        continue;
    }
    $kette = kat_kette($katNode, (int)$k['ID']);
    $wurzel = end($kette);
    $nachMarke[$wurzel['id']] = ['id' => $wurzel['id'], 'name' => $wurzel['name'], 'n' => ($nachMarke[$wurzel['id']]['n'] ?? 0) + $n];
    // oberste Plattform in der Kette (so entspricht die Zahl genau "Plattform inkl. Unterkategorien")
    $plat = null;
    foreach ($kette as $kn) { if ($kn['ebene'] === 'plattform') $plat = $kn; }
    if ($plat) {
        $key = $plat['id']; $name = $plat['name']; $link = true;
    } else {
        $key = 'o' . $wurzel['id']; $name = $wurzel['name'] . ' (ohne Plattform)'; $link = false;
    }
    $nachPlattform[$key] = ['id' => $plat['id'] ?? 0, 'name' => $name, 'n' => ($nachPlattform[$key]['n'] ?? 0) + $n, 'link' => $link];
    $typ = $katNode[(int)$k['ID']]['ebene'];
    $nachTyp[in_array($typ, ['spiele', 'anwendungen', 'sonstiges'], true) ? $typ : 'hardware'] += $n;
}
$sortiere = function (array $liste): array {
    usort($liste, fn($a, $b) => ($b['n'] <=> $a['n']) ?: strcasecmp($a['name'], $b['name']));
    return $liste;
};
$nachMarke = $sortiere(array_values($nachMarke));
$nachPlattform = $sortiere(array_values($nachPlattform));
$topN = 15;

/** Tabelle mit den ersten $topN Zeilen, der Rest hinter "Alle anzeigen"; $linkAlle = Namen zur Liste verlinken */
function stat_tabelle(array $zeilen, string $kopf, int $topN, bool $links): void {
    $zeile = function (array $z) use ($links) {
        $name = htmlspecialchars($z['name']);
        if ($links && ($z['link'] ?? true) && $z['id'] > 0) {
            $name = '<a href="index.php?kat=' . (int)$z['id'] . '&amp;inkl=1">' . $name . '</a>';
        }
        echo '<tr><td>' . $name . '</td><td style="text-align:right;">' . number_format((int)$z['n'], 0, ',', '.') . '</td></tr>';
    };
    echo '<table><tr><th>' . htmlspecialchars($kopf) . '</th><th style="text-align:right;">Anzahl</th></tr>';
    foreach (array_slice($zeilen, 0, $topN) as $z) $zeile($z);
    echo '</table>';
    if (count($zeilen) > $topN) {
        echo '<details style="width:60%; margin:0 auto 20px;"><summary>Alle anzeigen (' . count($zeilen) . ')</summary><table style="width:100%;">';
        foreach (array_slice($zeilen, $topN) as $z) $zeile($z);
        echo '</table></details>';
    }
}

// 5) Jahres-Statistik aus Feld `Jahr`
$jahre = [];
$jahr_res = $conn->query("
    SELECT Jahr, COUNT(*) AS Anzahl
      FROM Sammlung
     WHERE Jahr IS NOT NULL
     GROUP BY Jahr
     ORDER BY Jahr
");
while ($row = $jahr_res->fetch_assoc()) {
    $jahre[] = $row;
}

// 6) Gesamtwert (nur für eingeloggte Nicht-Gäste)
$wert = null;
if (!$is_guest) {
    $sum_res = $conn->query("SELECT SUM(Wert) AS s FROM Sammlung");
    $wert = $sum_res->fetch_assoc()['s'];
}

// 7) Gast-Login-Statistik (Museum-Zugang) - nur für Admin sichtbar
$loginTotal   = null;
$loginByMonth = [];
if (!$is_guest) {
    $lt = $conn->query("SELECT COUNT(*) AS cnt FROM Login_Log");
    if ($lt) $loginTotal = (int)$lt->fetch_assoc()['cnt'];

    $lm = $conn->query("
        SELECT DATE_FORMAT(Zeitpunkt, '%Y-%m') AS Monat, COUNT(*) AS Anzahl
        FROM Login_Log
        GROUP BY Monat
        ORDER BY Monat DESC
    ");
    if ($lm) {
        while ($row = $lm->fetch_assoc()) $loginByMonth[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <title>Statistik</title>
  <script src="/sammlung/assets/theme-toggle.js?v=4"></script>
  <style>
    body { font-family: Arial; font-size: 10pt; padding: 20px; }
    h1 { text-align: center; margin-bottom: 30px; }
    h2 { margin-top: 30px; }
    table { border-collapse: collapse; width: 60%; margin: 0 auto 20px; }
    th, td { border: 1px solid #888; padding: 6px 10px; text-align: left; }
    th { background: #eee; }
    p.center { text-align: center; }
    a { text-decoration: none; color: #007bff; }

    /* =========================================================
       DARK MODE (echte Farben statt Invertierungs-Trick)
       ========================================================= */
    html[data-theme="dark"]{
      background: #121212 !important;
    }
    html[data-theme="dark"] body{
      background: #121212 !important;
      color: #e8e8e8 !important;
    }
    html[data-theme="dark"] table,
    html[data-theme="dark"] th,
    html[data-theme="dark"] td{
      background: #1a1a1a !important;
      color: #e8e8e8 !important;
      border-color: #444 !important;
    }
    html[data-theme="dark"] th{
      background: #2a2a2a !important;
    }
    html[data-theme="dark"] a{
      color: #8ab4ff !important;
    }
    .theme-toggle{
      background: #555 !important;
      border-color: #333 !important;
      color: #fff !important;
    }
    .theme-toggle-floating{
      position: fixed;
      bottom: 16px;
      right: 16px;
      z-index: 9999;
      border-radius: 50%;
      width: 44px;
      height: 44px;
      padding: 0;
      font-size: 20px;
      line-height: 42px;
      text-align: center;
      box-shadow: 0 2px 8px rgba(0,0,0,.25);
      border: 1px solid #333;
      cursor: pointer;
    }
  </style>
</head>
<body>
  <h1>📈 Statistik</h1>

  <!-- Übersicht -->
  <h2>🔢 Übersicht</h2>
  <table>
    <tr><th>Beschreibung</th><th>Anzahl</th></tr>
    <tr><td>Gesamtanzahl Datensätze</td><td><?= htmlspecialchars($total_cnt) ?></td></tr>
    <tr><td>Davon „Spiel“</td><td><?= htmlspecialchars($spiel_cnt) ?></td></tr>
  </table>

  <!-- Materialverteilung -->
  <h2>🧰 Materialverteilung</h2>
  <table>
    <tr><th>Material</th><th>Anzahl</th></tr>
    <?php foreach ($material_stats as $m): ?>
      <tr>
        <td><?= htmlspecialchars($m['Name'] ?? '') ?></td>
        <td><?= htmlspecialchars($m['Anzahl']) ?></td>
      </tr>
    <?php endforeach; ?>
  </table>

  <!-- Nach Marke (oberste Ebene der Kategorien, inkl. Unterkategorien) -->
  <h2>🏷️ Nach Marke</h2>
  <?php stat_tabelle($nachMarke, 'Marke', $topN, true); ?>

  <!-- Nach Plattform (oberste Plattform, inkl. Unterkategorien) -->
  <h2>🕹️ Nach Plattform</h2>
  <?php stat_tabelle($nachPlattform, 'Plattform', $topN, true); ?>

  <!-- Nach Typ -->
  <h2>🗂️ Nach Typ</h2>
  <table>
    <tr><th>Typ</th><th style="text-align:right;">Anzahl</th></tr>
    <tr><td>Spiele</td><td style="text-align:right;"><?= number_format($nachTyp['spiele'], 0, ',', '.') ?></td></tr>
    <tr><td>Anwendungen</td><td style="text-align:right;"><?= number_format($nachTyp['anwendungen'], 0, ',', '.') ?></td></tr>
    <tr><td>Plattform/Marke (Hardware, Zubehör, Literatur)</td><td style="text-align:right;"><?= number_format($nachTyp['hardware'], 0, ',', '.') ?></td></tr>
    <tr><td>Sonstiges</td><td style="text-align:right;"><?= number_format($nachTyp['sonstiges'], 0, ',', '.') ?></td></tr>
    <?php if ($nachTyp['ohne'] > 0): ?>
      <tr><td>(ohne Kategorie)</td><td style="text-align:right;"><?= number_format($nachTyp['ohne'], 0, ',', '.') ?></td></tr>
    <?php endif; ?>
  </table>

  <!-- Alle Kategorien einzeln (eingeklappt) -->
  <h2>📂 Alle Kategorien einzeln</h2>
  <details style="width:60%; margin:0 auto 20px;">
    <summary>Alle anzeigen (<?= count($kategorien) ?>)</summary>
    <table style="width:100%;">
      <tr><th>Kategorie</th><th style="text-align:right;">Anzahl</th></tr>
      <?php foreach ($kategorien as $k): ?>
        <tr>
          <td><?= htmlspecialchars($k['Name'] ?? '(ohne Kategorie)') ?></td>
          <td style="text-align:right;"><?= number_format((int)$k['Anzahl'], 0, ',', '.') ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
  </details>

  <!-- Jahres-Statistik aus Feld 'Jahr' -->
  <h2>📅 Anzahl pro Jahr</h2>
  <table>
    <tr><th>Jahr</th><th>Anzahl</th></tr>
    <?php foreach ($jahre as $j): ?>
      <tr>
        <td><?= htmlspecialchars($j['Jahr']) ?></td>
        <td><?= htmlspecialchars($j['Anzahl']) ?></td>
      </tr>
    <?php endforeach; ?>
  </table>

  <!-- Gast-Logins (Museum-Zugriffe) -->
  <?php if (!$is_guest && $loginTotal !== null): ?>
    <h2>🏛️ Museum-Zugriffe (Gast-Logins)</h2>
    <table>
      <tr><th>Beschreibung</th><th>Anzahl</th></tr>
      <tr><td>Gesamtanzahl Gast-Logins</td><td><?= htmlspecialchars((string)$loginTotal) ?></td></tr>
    </table>
    <?php if ($loginByMonth): ?>
      <table>
        <tr><th>Monat</th><th>Anzahl</th></tr>
        <?php foreach ($loginByMonth as $lm): ?>
          <tr>
            <td><?= htmlspecialchars($lm['Monat']) ?></td>
            <td><?= htmlspecialchars($lm['Anzahl']) ?></td>
          </tr>
        <?php endforeach; ?>
      </table>
    <?php endif; ?>
  <?php endif; ?>

  <!-- Gesamtsumme -->
  <?php if (!$is_guest): ?>
    <h2>💰 Gesamtsumme</h2>
    <p class="center"><strong><?= number_format((float)$wert, 2, ',', '.') ?> €</strong></p>
  <?php endif; ?>

  <p class="center"><a href="index.php">🔙 Zurück</a></p>
</body>
</html>
