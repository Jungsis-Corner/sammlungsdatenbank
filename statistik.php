<?php
require __DIR__ . '/require_login.php';
$is_guest = is_guest();

require_once __DIR__ . '/config.php';
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) die("DB-Verbindungsfehler: " . $conn->connect_error);

// 1) Gesamtanzahl aller Datensätze
$total_res = $conn->query("SELECT COUNT(*) AS cnt FROM Sammlung");
$total_cnt = $total_res->fetch_assoc()['cnt'];

// 2) Anzahl aller Datensätze mit Kategorie LIKE '%Spiel%'
$spiel_res = $conn->query("
    SELECT COUNT(*) AS cnt
      FROM Sammlung S
      LEFT JOIN Kategorie K ON S.Kategorie = K.ID
     WHERE K.Kategorie LIKE '%Spiel%'
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

// 4) Kategorie-Statistik
$kategorien = [];
$kat_res = $conn->query("
    SELECT K.Kategorie AS Name, COUNT(S.ID) AS Anzahl
      FROM Sammlung S
      LEFT JOIN Kategorie K ON S.Kategorie = K.ID
     GROUP BY S.Kategorie
     ORDER BY Name
");
while ($row = $kat_res->fetch_assoc()) {
    $kategorien[] = $row;
}

// 5) Jahres-Statistik aus Feld `Jahr`
$jahre = [];
$jahr_res = $conn->query("
    SELECT Jahr, COUNT(*) AS Anzahl
      FROM Sammlung
     WHERE Jahr IS NOT NULL AND Jahr <> ''
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
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <title>Statistik</title>
  <script src="/sammlung/assets/theme-toggle.js"></script>
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
        <td><?= htmlspecialchars($m['Name']) ?></td>
        <td><?= htmlspecialchars($m['Anzahl']) ?></td>
      </tr>
    <?php endforeach; ?>
  </table>

  <!-- Kategorien -->
  <h2>📂 Kategorien</h2>
  <table>
    <tr><th>Kategorie</th><th>Anzahl</th></tr>
    <?php foreach ($kategorien as $k): ?>
      <tr>
        <td><?= htmlspecialchars($k['Name']) ?></td>
        <td><?= htmlspecialchars($k['Anzahl']) ?></td>
      </tr>
    <?php endforeach; ?>
  </table>

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

  <!-- Gesamtsumme -->
  <?php if (!$is_guest): ?>
    <h2>💰 Gesamtsumme</h2>
    <p class="center"><strong><?= number_format($wert, 2, ',', '.') ?> €</strong></p>
  <?php endif; ?>

  <p class="center"><a href="index.php">🔙 Zurück</a></p>
</body>
</html>
