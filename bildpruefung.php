<?php
/**
 * Bildprüfung (nur Admin, nur lesend): Zustand der Bildquellen und Auffälligkeiten.
 *  - Status von PHP/GD und den wirksamen IMAGE_*-Einstellungen (ersetzt ein phpinfo.php)
 *  - Einträge, deren Bilddatei fehlt oder deren Wert ungültig ist
 *  - verwendete Originale über 2 MB
 *  - Dateien im lokalen Ordner medien/ ohne Eintrag (der WordPress-Ordner wird nicht durchsucht: dort liegen auch die Blog-Bilder)
 * Es wird nichts geändert oder gelöscht.
 */
require_once __DIR__ . '/inc_errors.php';
require_once __DIR__ . '/inc_icons.php';
require __DIR__ . '/require_login.php';
require_once __DIR__ . '/inc_security.php';
require_once __DIR__ . '/inc_admin_ui.php';
if (!is_admin()) { error_page(403, 'Kein Zugriff', 'Dieser Bereich ist nur für Administratoren.'); }

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/inc_image.php';
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) { fail_db('Datenbankfehler.', 'Verbindung: ' . $conn->connect_error); }
$conn->set_charset('utf8mb4');
@set_time_limit(60);

const BILD_GROSS = 2 * 1024 * 1024;   // ab hier "groß"
const BILD_MAX_ZEILEN = 200;          // je Liste angezeigt

$e   = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$mb  = fn(int $b) => number_format($b / 1048576, 1, ',', '.') . ' MB';
$ja  = fn(bool $b) => $b ? '✔ ja' : '✘ nein';

/* ---------- Einträge prüfen ---------- */
$gesamt = 0; $vorhanden = 0; $extern = 0; $nichtPruefbar = 0;
$fehlt = []; $ungueltig = []; $gross = []; $lokaleNamen = [];
$res = $conn->query("SELECT `ID`, `Bezeichnung`, `SammlungBild1` FROM `Sammlung` WHERE `SammlungBild1` IS NOT NULL AND TRIM(`SammlungBild1`) <> '' ORDER BY `ID`");
while ($r = $res->fetch_assoc()) {
    $gesamt++;
    $p = image_parse($r['SammlungBild1']);
    if (!$p) { $ungueltig[] = $r; continue; }
    if ($p['kind'] === 'external') { $extern++; continue; }
    if ($p['kind'] === 'local') { $lokaleNamen[$p['name']] = true; }
    $root = image_root($p['kind']);
    if ($root === null) {                                   // Quelle nicht lesbar
        if ($p['kind'] === 'wp') { $nichtPruefbar++; } else { $r['groesse'] = 0; $fehlt[] = $r; }
        continue;
    }
    $file = $root . $p['name'];
    if (!is_file($file)) { $fehlt[] = $r; continue; }
    $vorhanden++;
    $size = (int)@filesize($file);
    if ($size > BILD_GROSS) { $r['groesse'] = $size; $gross[] = $r; }
}
usort($gross, fn($a, $b) => $b['groesse'] <=> $a['groesse']);

/* ---------- lokaler Ordner: Dateien ohne Eintrag ---------- */
$lokalRoot = image_local_root();
$lokalVorhanden = is_dir($lokalRoot);
$verwaist = []; $lokalDateien = 0;
if ($lokalVorhanden) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($lokalRoot, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if (!$f->isFile() || !isset(IMAGE_EXT[strtolower($f->getExtension())])) continue;
        $lokalDateien++;
        $rel = str_replace('\\', '/', substr($f->getPathname(), strlen($lokalRoot)));
        if (!isset($lokaleNamen[$rel])) { $verwaist[] = ['name' => $rel, 'groesse' => (int)$f->getSize()]; }
    }
}

/* ---------- Miniatur-Cache ---------- */
$cacheDir = __DIR__ . '/cache/thumbs/';
$cacheDa = is_dir($cacheDir); $cacheDateien = 0; $cacheBytes = 0;
if ($cacheDa) {
    foreach (new DirectoryIterator($cacheDir) as $f) {
        if ($f->isFile() && $f->getFilename()[0] !== '.') { $cacheDateien++; $cacheBytes += (int)$f->getSize(); }
    }
}

/* ---------- Status ---------- */
$gd = function_exists('gd_info') ? gd_info() : [];
$wpRoot = image_root('wp');
$wpDir  = rtrim((string)IMAGE_WP_DIR, '/') . '/';
$flash = flash_pull();

/** Tabelle mit Einträgen (ID, Bezeichnung, Wert, optional Größe) */
function bild_tabelle(array $zeilen, callable $e, bool $mitGroesse = false): void {
    if (!$zeilen) { echo '<p class="center">Keine.</p>'; return; }
    $gesamt = count($zeilen);
    echo '<div class="table-wrap"><table class="adm-table is-striped is-hover is-compact"><thead><tr><th class="col-id">ID</th><th>Bezeichnung</th><th>Wert in der Datenbank</th>'
       . ($mitGroesse ? '<th class="is-numeric">Größe</th>' : '') . '<th></th></tr></thead><tbody>';
    foreach (array_slice($zeilen, 0, BILD_MAX_ZEILEN) as $z) {
        echo '<tr><td class="col-id is-numeric">' . (int)$z['ID'] . '</td><td>' . $e($z['Bezeichnung'] ?? '') . '</td><td>' . $e($z['SammlungBild1']) . '</td>'
           . ($mitGroesse ? '<td class="is-numeric">' . number_format($z['groesse'] / 1048576, 1, ',', '.') . ' MB</td>' : '')
           . '<td><a class="btn is-small" href="edit.php?id=' . (int)$z['ID'] . '" title="Bearbeiten">' . icon('edit','Bearbeiten') . '</a></td></tr>';
    }
    echo '</tbody></table></div>';
    if ($gesamt > BILD_MAX_ZEILEN) echo '<p class="center">Angezeigt: ' . BILD_MAX_ZEILEN . ' von ' . $gesamt . '.</p>';
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <?php $page_title = 'Bildprüfung'; include __DIR__ . '/inc_head_admin.php'; ?>
</head>
<body class="admin-page">
<?= admin_header([['Bildprüfung', '']]) ?>

<main id="inhalt">
  <h1>Bildprüfung</h1>
  <?php if ($flash): ?>
    <div class="msg-box <?= $e($flash['type']) ?>" role="status"><?= $e($flash['text']) ?></div>
  <?php endif; ?>

    <p class="center">Nur zur Ansicht: Es wird nichts geändert oder gelöscht.</p>

    <h2>Status</h2>
    <div class="table-wrap"><table class="adm-table"><tbody>
      <tr><td>PHP-Version</td><td><?= $e(PHP_VERSION) ?></td></tr>
      <tr><td>GD (Miniaturen)</td><td><?= $ja(!empty($gd)) ?><?= !empty($gd) ? ' · ' . $e($gd['GD Version'] ?? '') : '' ?></td></tr>
      <tr><td>Formate: JPEG · PNG · GIF · WebP</td><td><?= $ja(!empty($gd['JPEG Support'])) ?> · <?= $ja(!empty($gd['PNG Support'])) ?> · <?= $ja(!empty($gd['GIF Read Support'])) ?> · <?= $ja(!empty($gd['WebP Support'])) ?></td></tr>
      <tr><td>EXIF (Drehung von Fotos)</td><td><?= $ja(function_exists('exif_read_data')) ?></td></tr>
      <tr><td>memory_limit</td><td><?= $e(ini_get('memory_limit')) ?></td></tr>
      <tr><td>Bildquelle (IMAGE_SOURCE)</td><td><?= $e(IMAGE_SOURCE) ?></td></tr>
      <tr><td>WordPress-Uploads, URL-Pfad (IMAGE_WP_BASE)</td><td><?= $e(IMAGE_WP_BASE) ?></td></tr>
      <tr><td>WordPress-Uploads, Ordner auf dem Server (IMAGE_WP_DIR)</td><td><?= $e($wpDir) ?> · lesbar: <?= $ja($wpRoot !== null) ?></td></tr>
      <tr><td>Lokaler Bildordner (<?= $e(IMAGE_LOCAL_DIR) ?>)</td><td>vorhanden: <?= $ja($lokalVorhanden) ?><?= $lokalVorhanden ? ' · ' . $lokalDateien . ' Bilddateien' : '' ?></td></tr>
      <tr><td>Miniaturen eingeschaltet (IMAGE_THUMBS)</td><td><?= $ja((bool)IMAGE_THUMBS) ?></td></tr>
      <tr><td>Miniatur-Cache (cache/thumbs/)</td><td>vorhanden: <?= $ja($cacheDa) ?> · beschreibbar: <?= $ja($cacheDa && is_writable($cacheDir)) ?> · <?= $cacheDateien ?> Dateien, <?= $mb($cacheBytes) ?></td></tr>
    </tbody></table></div>

    <h2>Einträge mit Bild</h2>
    <div class="table-wrap"><table class="adm-table"><tbody>
      <tr><td>Einträge mit einem Bildwert</td><td class="is-numeric"><?= $gesamt ?></td></tr>
      <tr><td>Datei vorhanden</td><td class="is-numeric"><?= $vorhanden ?></td></tr>
      <tr><td>Datei fehlt</td><td class="is-numeric"><?= count($fehlt) ?></td></tr>
      <tr><td>Ungültiger Wert (zeigt den Platzhalter)</td><td class="is-numeric"><?= count($ungueltig) ?></td></tr>
      <tr><td>Bild von einem fremden Server (nicht prüfbar)</td><td class="is-numeric"><?= $extern ?></td></tr>
      <tr><td>WordPress-Ordner nicht lesbar (nicht prüfbar)</td><td class="is-numeric"><?= $nichtPruefbar ?></td></tr>
      <tr><td>Originale über <?= $mb(BILD_GROSS) ?></td><td class="is-numeric"><?= count($gross) ?></td></tr>
    </tbody></table></div>

    <h2>Datei fehlt (<?= count($fehlt) ?>)</h2>
    <?php bild_tabelle($fehlt, $e); ?>

    <h2>Ungültiger Wert (<?= count($ungueltig) ?>)</h2>
    <?php bild_tabelle($ungueltig, $e); ?>

    <h2>Originale über <?= $mb(BILD_GROSS) ?> (<?= count($gross) ?>)</h2>
    <?php bild_tabelle($gross, $e, true); ?>

    <h2>Dateien in <?= $e(IMAGE_LOCAL_DIR) ?> ohne Eintrag (<?= count($verwaist) ?>)</h2>
    <?php if (!$lokalVorhanden): ?>
      <p class="center">Der lokale Bildordner gibt es nicht (wird nur für die Quellen „local“ und „auto“ gebraucht).</p>
    <?php elseif (!$verwaist): ?>
      <p class="center">Keine.</p>
    <?php else: ?>
      <div class="table-wrap"><table class="adm-table is-striped is-compact"><thead><tr><th>Datei</th><th class="is-numeric">Größe</th></tr></thead><tbody>
        <?php foreach (array_slice($verwaist, 0, BILD_MAX_ZEILEN) as $v): ?>
          <tr><td><?= $e($v['name']) ?></td><td class="is-numeric"><?= $mb($v['groesse']) ?></td></tr>
        <?php endforeach; ?>
      </tbody></table></div>
      <?php if (count($verwaist) > BILD_MAX_ZEILEN): ?><p class="center">Angezeigt: <?= BILD_MAX_ZEILEN ?> von <?= count($verwaist) ?>.</p><?php endif; ?>
    <?php endif; ?>

</main>
</body>
</html>
