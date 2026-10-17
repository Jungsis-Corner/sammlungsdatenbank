<?php
require_once __DIR__ . '/inc_errors.php';
require_once __DIR__ . '/inc_icons.php';
require __DIR__ . '/require_login.php';
require_once __DIR__ . '/inc_security.php';
require_once __DIR__ . '/inc_admin_ui.php';
if (!is_admin()) { error_page(403, 'Kein Zugriff', 'Dieser Bereich ist nur für Administratoren.'); }
require_once __DIR__ . '/config.php';

// [Überschrift => [[Titel, Zeile darunter, Ziel, Icon], …]]
$gruppen = [
    'Stammdaten' => [
        ['Hersteller',  'Firmen, die Geräte und Spiele bauen', 'manage_hersteller.php', 'tag'],
        ['Publisher',   'Firmen, die Spiele veröffentlichen',  'manage_publisher.php',  'export'],
        ['Verkäufer',   'Händler und Privatverkäufer',         'manage_verkaeufer.php', 'cart'],
        ['Kategorien',  'Marke, Plattform, Spiele, Anwendungen', 'manage_kategorie.php', 'layers'],
    ],
    'Zustand und Aufbewahrung' => [
        ['Material',    'Kunststoff, Metall, Holz …',           'manage_material.php',     'box'],
        ['Standort',    'Wo ein Stück liegt',                   'manage_standort.php',     'pin'],
        ['Boxen',       'Box 1, Box 2, Regal …',                'manage_box.php',          'box'],
        ['Verpackung',  'Original, Nachdruck, nicht vorhanden', 'manage_verpackung.php',   'box'],
        ['Datenträger', 'Diskette, Kassette, Modul …',          'manage_datentraeger.php', 'save'],
        ['Zustände',    'Erhaltungszustand',                    'manage_zustand.php',      'check'],
    ],
    'Werkzeuge' => [
        ['Einkäufe',    'Bestellungen und Lieferungen',         'einkauf.php',       'cart'],
        ['Bildprüfung', 'Fehlende und zu große Bilder',         'bildpruefung.php',  'image'],
        ['Protokoll',   'Fehlerlog des Servers',                'logview.php',       'file'],
    ],
];
$e = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <?php $page_title = 'Einstellungen'; include __DIR__ . '/inc_head_admin.php'; ?>
</head>
<body class="admin-page">
<?= admin_header() ?>

<main id="inhalt">
  <h1>Einstellungen</h1>
  <?= admin_flash() ?>

  <?php foreach ($gruppen as $titel => $kacheln): ?>
    <section class="kachel-gruppe" aria-labelledby="g_<?= $e(md5($titel)) ?>">
      <h2 id="g_<?= $e(md5($titel)) ?>"><?= $e($titel) ?></h2>
      <ul class="kacheln">
        <?php foreach ($kacheln as [$t, $sub, $href, $ic]): ?>
          <li><a href="<?= $e($href) ?>"><?= icon($ic) ?><span class="k-text"><span class="k-titel"><?= $e($t) ?></span><span class="k-sub"><?= $e($sub) ?></span></span></a></li>
        <?php endforeach; ?>
      </ul>
    </section>
  <?php endforeach; ?>
</main>
</body>
</html>
