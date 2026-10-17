<?php
require_once __DIR__ . '/inc_errors.php';
require_once __DIR__ . '/inc_icons.php';
require __DIR__ . '/require_login.php';
require_once __DIR__ . '/inc_filter.php';   // kategorie_baum()
require_once __DIR__ . '/inc_security.php';
require_once __DIR__ . '/inc_admin_ui.php';
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
    $material_stats[] = ['name' => (string)($row['Name'] ?? '(ohne Material)'), 'n' => (int)$row['Anzahl']];
}
usort($material_stats, fn($a, $b) => ($b['n'] <=> $a['n']) ?: strcasecmp($a['name'], $b['name']));

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

/** Balken als Inline-SVG (Breite als Attribut, kein style=): $anteil 0..100 */
function sb_balken(float $anteil): string {
    $w = max(0.0, min(100.0, $anteil));
    return '<svg class="sb-balken" viewBox="0 0 100 1" preserveAspectRatio="none" aria-hidden="true" focusable="false"><rect width="' . round($w, 2) . '" height="1"/></svg>';
}

/** Liste mit Balken: die ersten $topN Zeilen sichtbar, der Rest hinter „Alle anzeigen“; $links = Namen zur Liste verlinken */
function stat_liste(array $zeilen, int $topN, bool $links): void {
    $max = 0;
    foreach ($zeilen as $z) { $max = max($max, (int)$z['n']); }
    $zeile = function (array $z) use ($links, $max) {
        $name = htmlspecialchars((string)$z['name']);
        if ($links && ($z['link'] ?? true) && ($z['id'] ?? 0) > 0) {
            $name = '<a href="index.php?kat=' . (int)$z['id'] . '&amp;inkl=1">' . $name . '</a>';
        }
        echo '<li><span class="sb-name">' . $name . '</span><span class="sb-zahl">' . number_format((int)$z['n'], 0, ',', '.') . '</span>'
           . sb_balken($max > 0 ? 100 * (int)$z['n'] / $max : 0) . '</li>';
    };
    echo '<ol class="sb-liste">';
    foreach (array_slice($zeilen, 0, $topN) as $z) $zeile($z);
    echo '</ol>';
    if (count($zeilen) > $topN) {
        echo '<details class="sb-mehr"><summary>Alle anzeigen (' . count($zeilen) . ')</summary><ol class="sb-liste">';
        foreach (array_slice($zeilen, $topN) as $z) $zeile($z);
        echo '</ol></details>';
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

// 6b) Zuwachs aus Erstellt_am (letzte 12 Monate / 30 Tage)
$neu12 = (int)$conn->query("SELECT COUNT(*) FROM Sammlung WHERE Erstellt_am >= (NOW() - INTERVAL 12 MONTH)")->fetch_row()[0];
$neu30 = (int)$conn->query("SELECT COUNT(*) FROM Sammlung WHERE Erstellt_am >= (NOW() - INTERVAL 30 DAY)")->fetch_row()[0];

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
$isMuseum = !empty($_SESSION['museum_mode']);
$navOpt = ['is_guest' => $is_guest, 'is_admin' => is_admin(), 'museum' => $isMuseum, 'export' => (!$isMuseum && (!$is_guest || (function_exists('guest_export_allowed') && guest_export_allowed()))), 'params' => []];
$fmt = fn($n) => number_format((int)$n, 0, ',', '.');
$e = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$markenAnzahl = count(array_filter($nachMarke, fn($m) => ($m['id'] ?? 0) > 0));
$plattformAnzahl = count(array_filter($nachPlattform, fn($m) => ($m['link'] ?? false)));
$spielAnteil = $total_cnt > 0 ? round(100 * $spiel_cnt / $total_cnt) : 0;

// Typ-Zeilen
$typZeilen = [
    ['name' => 'Spiele', 'n' => $nachTyp['spiele']],
    ['name' => 'Anwendungen', 'n' => $nachTyp['anwendungen']],
    ['name' => 'Plattform/Marke (Hardware, Zubehör, Literatur)', 'n' => $nachTyp['hardware']],
    ['name' => 'Sonstiges', 'n' => $nachTyp['sonstiges']],
];
if ($nachTyp['ohne'] > 0) $typZeilen[] = ['name' => '(ohne Kategorie)', 'n' => $nachTyp['ohne']];

// Jahres-Säulen (Inline-SVG): Breite pro Jahrgang 16 Einheiten, Höhe der Säulen 110
$jahrMax = 0;
foreach ($jahre as $j) { $jahrMax = max($jahrMax, (int)$j['Anzahl']); }
$sBreite = 16; $sHoehe = 110; $svgH = 150; $svgW = max(1, count($jahre)) * $sBreite + 8;
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <?php $page_title = 'Statistik'; include __DIR__ . '/inc_head_admin.php'; ?>
  <link rel="stylesheet" href="/sammlung/assets/statistik.css?v=1">
</head>
<body class="admin-page statistik-page">
<?= admin_header([['Statistik', '']], $navOpt, false) ?>

<main id="inhalt">
  <h1>Statistik</h1>

  <!-- Kennzahlen -->
  <ul class="st-kacheln" aria-label="Kennzahlen">
    <li><span class="st-zahl"><?= $fmt($total_cnt) ?></span><span class="st-text">Einträge insgesamt</span></li>
    <li><span class="st-zahl"><?= $fmt($spiel_cnt) ?></span><span class="st-text">davon Spiele (<?= $spielAnteil ?> %)</span></li>
    <li><span class="st-zahl"><?= $fmt($markenAnzahl) ?></span><span class="st-text">Marken</span></li>
    <li><span class="st-zahl"><?= $fmt($plattformAnzahl) ?></span><span class="st-text">Plattformen</span></li>
    <li><span class="st-zahl"><?= $fmt($neu12) ?></span><span class="st-text">neu in 12 Monaten<?= $neu30 > 0 ? ' (davon ' . $fmt($neu30) . ' in 30 Tagen)' : '' ?></span></li>
    <?php if (!$is_guest): ?>
      <li><span class="st-zahl"><?= number_format((float)$wert, 0, ',', '.') ?> €</span><span class="st-text">Gesamtwert</span></li>
    <?php endif; ?>
  </ul>

  <div class="st-raster">
    <section class="v-karte" aria-labelledby="h_marke">
      <h2 id="h_marke">Nach Marke</h2>
      <?php stat_liste($nachMarke, $topN, true); ?>
    </section>

    <section class="v-karte" aria-labelledby="h_plattform">
      <h2 id="h_plattform">Nach Plattform</h2>
      <?php stat_liste($nachPlattform, $topN, true); ?>
    </section>

    <section class="v-karte" aria-labelledby="h_typ">
      <h2 id="h_typ">Nach Typ</h2>
      <?php stat_liste($typZeilen, 99, false); ?>
    </section>

    <section class="v-karte" aria-labelledby="h_material">
      <h2 id="h_material">Materialverteilung</h2>
      <?php stat_liste($material_stats, $topN, false); ?>
    </section>
  </div>

  <!-- Anzahl pro Jahr -->
  <section class="v-karte" aria-labelledby="h_jahr">
    <h2 id="h_jahr">Anzahl pro Jahr</h2>
    <?php if ($jahre): ?>
      <div class="st-saeulen" role="region" aria-label="Diagramm: Anzahl pro Jahr (seitlich scrollbar)" tabindex="0">
        <svg width="<?= $svgW ?>" height="<?= $svgH ?>" viewBox="0 0 <?= $svgW ?> <?= $svgH ?>" role="img" aria-label="Säulendiagramm: Anzahl der Einträge pro Jahr; die Zahlen stehen darunter als Tabelle.">
          <?php foreach ($jahre as $i => $j):
              $h = $jahrMax > 0 ? max(1, round($sHoehe * (int)$j['Anzahl'] / $jahrMax)) : 0;
              $x = 4 + $i * $sBreite;
          ?>
            <rect class="st-saeule" x="<?= $x ?>" y="<?= $sHoehe - $h + 4 ?>" width="<?= $sBreite - 4 ?>" height="<?= $h ?>"><title><?= $e($j['Jahr'] . ': ' . $j['Anzahl']) ?></title></rect>
            <text class="st-jahr" font-size="9" text-anchor="end" transform="rotate(-90 <?= $x + 8 ?> <?= $sHoehe + 10 ?>)" x="<?= $x + 8 ?>" y="<?= $sHoehe + 10 ?>"><?= $e($j['Jahr']) ?></text>
          <?php endforeach; ?>
        </svg>
      </div>
      <details class="sb-mehr">
        <summary>Als Tabelle anzeigen</summary>
        <table class="st-tabelle">
          <thead><tr><th>Jahr</th><th class="is-numeric">Anzahl</th></tr></thead>
          <tbody>
            <?php foreach ($jahre as $j): ?>
              <tr><td><?= $e($j['Jahr']) ?></td><td class="is-numeric"><?= $fmt($j['Anzahl']) ?></td></tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </details>
    <?php else: ?>
      <p class="v-unter">Keine Einträge mit Jahr.</p>
    <?php endif; ?>
  </section>

  <!-- Alle Kategorien einzeln (eingeklappt) -->
  <section class="v-karte" aria-labelledby="h_kat">
    <h2 id="h_kat">Alle Kategorien einzeln</h2>
    <details class="sb-mehr">
      <summary>Alle anzeigen (<?= count($kategorien) ?>)</summary>
      <table class="st-tabelle">
        <thead><tr><th>Kategorie</th><th class="is-numeric">Anzahl</th></tr></thead>
        <tbody>
          <?php foreach ($kategorien as $k): ?>
            <tr><td><?= $e($k['Name'] ?? '(ohne Kategorie)') ?></td><td class="is-numeric"><?= $fmt($k['Anzahl']) ?></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </details>
  </section>

  <?php /* Gast-Logins (Museum-Zugriffe): nur Admin */ ?>
  <?php if (!$is_guest && $loginTotal !== null): ?>
    <section class="v-karte" aria-labelledby="h_museum">
      <h2 id="h_museum">Museum-Zugriffe (Gast-Logins)</h2>
      <p><strong><?= $fmt($loginTotal) ?></strong> Gast-Logins insgesamt</p>
      <?php if ($loginByMonth): ?>
        <details class="sb-mehr">
          <summary>Nach Monat (<?= count($loginByMonth) ?>)</summary>
          <table class="st-tabelle">
            <thead><tr><th>Monat</th><th class="is-numeric">Anzahl</th></tr></thead>
            <tbody>
              <?php foreach ($loginByMonth as $lm): ?>
                <tr><td><?= $e($lm['Monat']) ?></td><td class="is-numeric"><?= $fmt($lm['Anzahl']) ?></td></tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </details>
      <?php endif; ?>
    </section>
  <?php endif; ?>
</main>
</body>
</html>
