<?php
/**
 * Kategorien verwalten (Baum: Marke -> Plattform -> Spiele / Anwendungen / sonstiges).
 *
 * Eigene Seite statt inc_manage_lookup.php, weil zusätzlich Eltern und Ebene gepflegt werden und der Pfad
 * (z. B. '/288/53/') für den Teilbaum neu berechnet werden muss. Zusammenführen gibt es hier nicht: Kategorie-IDs
 * stehen in Links (go.php, Blog).
 */
require_once __DIR__ . '/inc_errors.php';
require_once __DIR__ . '/inc_icons.php';
require __DIR__ . '/require_login.php';
require_once __DIR__ . '/inc_security.php';
require_once __DIR__ . '/inc_filter.php';
require_once __DIR__ . '/inc_admin_ui.php';
if (!is_admin()) { error_page(403, 'Kein Zugriff', 'Dieser Bereich ist nur für Administratoren.'); }

require_once __DIR__ . '/config.php';
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) { fail_db('Datenbankfehler.', 'Verbindung: ' . $conn->connect_error); }
$conn->set_charset('utf8mb4');

const KAT_EBENEN = [
    'marke'       => 'Marke',
    'plattform'   => 'Plattform',
    'spiele'      => 'Spiele',
    'anwendungen' => 'Anwendungen',
    'sonstiges'   => 'Sonstiges',
];
$self   = 'manage_kategorie.php';
$search = trim((string)($_GET['q'] ?? ''));

/** Pfad eines Knotens und seines ganzen Teilbaums neu berechnen (rekursiv, mit Tiefenbremse). */
function kategorie_pfad_neu(mysqli $conn, int $id, int $tiefe = 0): void {
    if ($tiefe > 20) { throw new RuntimeException('Kategorie-Baum zu tief oder zyklisch.'); }
    $stmt = $conn->prepare("SELECT p.`Pfad` FROM `Kategorie` k LEFT JOIN `Kategorie` p ON p.`ID` = k.`ParentID` WHERE k.`ID` = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_row();
    $pfad = (($r[0] ?? null) ?: '/') . $id . '/';
    $stmt = $conn->prepare("UPDATE `Kategorie` SET `Pfad` = ? WHERE `ID` = ?");
    $stmt->bind_param('si', $pfad, $id);
    $stmt->execute();
    $stmt = $conn->prepare("SELECT `ID` FROM `Kategorie` WHERE `ParentID` = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $kinder = array_column($stmt->get_result()->fetch_all(MYSQLI_NUM), 0);
    foreach ($kinder as $kid) { kategorie_pfad_neu($conn, (int)$kid, $tiefe + 1); }
}

/** Anzahl Verweise auf eine Kategorie (Sammlung und Einkauf) */
function kategorie_verwendet(mysqli $conn, int $id): int {
    $n = 0;
    foreach (lookup_refs('Kategorie') as [$rt, $rc]) {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM $rt WHERE $rc = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $n += (int)$stmt->get_result()->fetch_row()[0];
    }
    return $n;
}

function kat_redirect(string $self, string $search, int $id = 0): void {
    $q = [];
    if ($search !== '') $q['q'] = $search;
    if ($id) $q['id'] = $id;
    header('Location: ' . $self . ($q ? '?' . http_build_query($q) : ''));
    exit;
}

/* ---------- Löschen ---------- */
if (isset($_POST['delete'])) {
    require_post_csrf();
    $del = intval($_POST['delete']);
    $stmt = $conn->prepare("SELECT COUNT(*) FROM `Kategorie` WHERE `ParentID` = ?");
    $stmt->bind_param('i', $del);
    $stmt->execute();
    $kinder = (int)$stmt->get_result()->fetch_row()[0];
    $n = kategorie_verwendet($conn, $del);
    if ($kinder > 0) {
        flash_set('Nicht gelöscht: Die Kategorie hat noch ' . $kinder . ' Unterkategorie' . ($kinder === 1 ? '' : 'n') . '.', 'error');
    } elseif ($n > 0) {
        flash_set('Nicht gelöscht: Die Kategorie ist noch in Verwendung (' . $n . ' ' . ($n === 1 ? 'Eintrag' : 'Einträge') . ').', 'error');
    } else {
        try {
            $stmt = $conn->prepare("DELETE FROM `Kategorie` WHERE `ID` = ?");
            $stmt->bind_param('i', $del);
            $stmt->execute();
            flash_set('Kategorie gelöscht.', 'success');
        } catch (mysqli_sql_exception $e) {
            error_log('Kategorie löschen ' . $del . ': ' . $e->getMessage());
            flash_set('Löschen fehlgeschlagen.', 'error');
        }
    }
    kat_redirect($self, $search);
}

/* ---------- Bearbeiten laden ---------- */
$id = intval($_GET['id'] ?? 0);
$form = ['name' => '', 'parent' => 0, 'ebene' => 'plattform'];
if ($id) {
    $stmt = $conn->prepare("SELECT `Kategorie`, `ParentID`, `Ebene` FROM `Kategorie` WHERE `ID` = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    if ($r = $stmt->get_result()->fetch_assoc()) {
        $form = ['name' => (string)$r['Kategorie'], 'parent' => (int)($r['ParentID'] ?? 0), 'ebene' => (string)$r['Ebene']];
    } else {
        $id = 0;
    }
}

/* ---------- Speichern ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    $name   = trim((string)($_POST['Kategorie'] ?? ''));
    $parent = intval($_POST['parent'] ?? 0);
    $ebene  = (string)($_POST['ebene'] ?? '');
    $fehler = '';

    if ($name === '') {
        $fehler = 'Der Name darf nicht leer sein.';
    } elseif (!isset(KAT_EBENEN[$ebene])) {
        $fehler = 'Ungültige Ebene.';
    } elseif ($parent > 0) {
        $stmt = $conn->prepare("SELECT `Pfad` FROM `Kategorie` WHERE `ID` = ?");
        $stmt->bind_param('i', $parent);
        $stmt->execute();
        $pp = $stmt->get_result()->fetch_row();
        if (!$pp) {
            $fehler = 'Die gewählte übergeordnete Kategorie gibt es nicht.';
        } elseif ($id) {
            // Zyklus: Eltern darf nicht die Kategorie selbst oder einer ihrer Nachfolger sein
            $stmt = $conn->prepare("SELECT `Pfad` FROM `Kategorie` WHERE `ID` = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $own = (string)($stmt->get_result()->fetch_row()[0] ?? '');
            if ($own !== '' && strncmp((string)$pp[0], $own, strlen($own)) === 0) {
                $fehler = 'Die übergeordnete Kategorie darf nicht die Kategorie selbst oder eine ihrer Unterkategorien sein.';
            }
        }
    }
    if ($fehler !== '') {
        flash_set($fehler, 'error');
        kat_redirect($self, $search, $id);
    }

    $spiele   = ($ebene === 'spiele') ? 1 : 0;   // alte Spalte Spiele synchron halten
    $parentDb = $parent > 0 ? $parent : null;
    try {
        $conn->begin_transaction();
        if ($id) {
            $stmt = $conn->prepare("UPDATE `Kategorie` SET `Kategorie` = ?, `ParentID` = ?, `Ebene` = ?, `Spiele` = ? WHERE `ID` = ?");
            $stmt->bind_param('sisii', $name, $parentDb, $ebene, $spiele, $id);
            $stmt->execute();
            $neuId = $id;
        } else {
            $stmt = $conn->prepare("INSERT INTO `Kategorie` (`Kategorie`, `ParentID`, `Ebene`, `Spiele`) VALUES (?, ?, ?, ?)");
            $stmt->bind_param('sisi', $name, $parentDb, $ebene, $spiele);
            $stmt->execute();
            $neuId = (int)$conn->insert_id;
        }
        kategorie_pfad_neu($conn, $neuId);
        $conn->commit();
        flash_set($id ? 'Gespeichert.' : 'Kategorie angelegt.', 'success');
    } catch (Throwable $e) {
        $conn->rollback();
        if ($e instanceof mysqli_sql_exception && (int)$e->getCode() === 1062) {
            flash_set('Diesen Namen gibt es schon (Groß-/Kleinschreibung zählt nicht).', 'error');
            kat_redirect($self, $search, $id);
        }
        error_log('Kategorie speichern: ' . $e->getMessage());
        flash_set('Speichern fehlgeschlagen, es wurde nichts geändert.', 'error');
        kat_redirect($self, $search, $id);
    }
    kat_redirect($self, $search);
}

/* ---------- Liste ---------- */
$baum = kategorie_baum($conn);
$eigene = [];   // Einträge je Kategorie (Sammlung + Einkauf)
foreach (lookup_refs('Kategorie') as [$rt, $rc]) {
    $res = $conn->query("SELECT $rc AS k, COUNT(*) AS n FROM $rt WHERE $rc IS NOT NULL GROUP BY $rc");
    while ($r = $res->fetch_assoc()) { $eigene[(int)$r['k']] = ($eigene[(int)$r['k']] ?? 0) + (int)$r['n']; }
}
$namen = array_column($baum, 'name', 'id');
// Kategorie und ihre Nachfolger sind als Eltern nicht wählbar
$gesperrt = [];
if ($id) {
    $stmt = $conn->prepare("SELECT `ID` FROM `Kategorie` WHERE `Pfad` LIKE CONCAT((SELECT `Pfad` FROM `Kategorie` WHERE `ID` = ?), '%')");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $gesperrt = array_flip(array_column($stmt->get_result()->fetch_all(MYSQLI_NUM), 0));
}
$rows = array_values(array_filter($baum, fn($n) => $search === '' || mb_stripos($n['name'], $search) !== false));
$flashHtml = admin_flash();
$e = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$einTr = fn(int $n): string => $n . ' ' . ($n === 1 ? 'Eintrag' : 'Einträgen');   // Dativ: „in 3 Einträgen“
$kinderZahl = [];
foreach ($baum as $n) { if ($n['parent'] !== null) $kinderZahl[$n['parent']] = ($kinderZahl[$n['parent']] ?? 0) + 1; }
$loeschAction = $self . ($search !== '' ? '?q=' . urlencode($search) : '');
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <?php $page_title = 'Kategorien verwalten'; include __DIR__ . '/inc_head_admin.php'; ?>
</head>
<body class="admin-page">
<?= admin_header([['Kategorien verwalten', '']]) ?>

<main id="inhalt">
  <h1>Kategorien verwalten</h1>

  <?= $flashHtml ?>

  <form method="get" class="v-suche" role="search" action="<?= $e($self) ?>">
    <label class="sr-only" for="f_suche">Suchen</label>
    <input type="search" id="f_suche" name="q" placeholder="Kategorien suchen…" value="<?= $e($search) ?>" autocomplete="off">
    <button type="submit" class="btn"><?= icon('search') ?> Suchen</button>
    <?php if ($search !== ''): ?><a class="btn" href="<?= $e($self) ?>"><?= icon('close') ?> Zurücksetzen</a><?php endif; ?>
  </form>

  <!-- Hinzufügen / Bearbeiten -->
  <section class="v-karte" aria-labelledby="formTitel">
    <h2 id="formTitel"><?= $id ? 'Kategorie bearbeiten' : 'Neue Kategorie' ?></h2>
    <form method="post" class="v-form zeile" action="<?= $e($self) ?>?<?= $e(http_build_query(array_filter(['q' => $search, 'id' => $id]))) ?>">
      <?= csrf_field() ?>
      <div class="v-feld">
        <label for="f_name">Name</label>
        <input type="text" id="f_name" name="Kategorie" placeholder="Name der Kategorie" value="<?= $e($form['name']) ?>" required autocomplete="off">
      </div>
      <div class="v-feld">
        <label for="f_parent">Übergeordnete Kategorie</label>
        <select id="f_parent" name="parent">
          <option value="0">— keine (oberste Ebene) —</option>
          <?php foreach ($baum as $n): if (isset($gesperrt[$n['id']])) continue; ?>
            <option value="<?= $n['id'] ?>" <?= $form['parent'] === $n['id'] ? 'selected' : '' ?>>
              <?= str_repeat('&nbsp;&nbsp;&nbsp;', $n['depth']) . ($n['depth'] ? '↳ ' : '') . $e($n['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="v-feld">
        <label for="f_ebene">Ebene</label>
        <select id="f_ebene" name="ebene">
          <?php foreach (KAT_EBENEN as $k => $label): ?>
            <option value="<?= $k ?>" <?= $form['ebene'] === $k ? 'selected' : '' ?>><?= $e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="v-knoepfe">
        <button type="submit" class="btn save"><?= icon('save') ?> <?= $id ? 'Speichern' : 'Hinzufügen' ?></button>
        <?php if ($id): ?><a class="btn" href="<?= $e($self) ?><?= $search !== '' ? '?q=' . urlencode($search) : '' ?>">Abbrechen</a><?php endif; ?>
      </div>
    </form>
  </section>

  <!-- Liste (Baum) -->
  <p class="v-unter" aria-live="polite"><?= count($rows) ?> <?= count($rows) === 1 ? 'Kategorie' : 'Kategorien' ?><?= $search !== '' ? ' zu „' . $e($search) . '“' : '' ?></p>
  <div class="table-wrap">
    <table class="vtabelle">
      <thead>
        <tr>
          <th class="col-id">ID</th>
          <th>Kategorie</th>
          <th>Ebene</th>
          <th class="is-numeric">Einträge</th>
          <th><span class="sr-only">Aktionen</span></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $n):
            $verw = (int)($eigene[$n['id']] ?? 0); $kinder = (int)($kinderZahl[$n['id']] ?? 0);
            $teile = [];
            if ($kinder > 0) $teile[] = 'hat noch ' . $kinder . ' Unterkategorie' . ($kinder === 1 ? '' : 'n');
            if ($verw > 0)   $teile[] = 'wird noch in ' . $einTr($verw) . ' verwendet';
            $sperreText = $teile ? 'Die Kategorie ' . implode(' und ', $teile) . '. Räume sie erst auf.' : '';
        ?>
          <tr>
            <td class="col-id c-id is-numeric"><?= $n['id'] ?></td>
            <td class="c-name"><span class="einzug"><?= str_repeat('&nbsp;&nbsp;&nbsp;', $search === '' ? $n['depth'] : 0) . ($n['depth'] && $search === '' ? '↳ ' : '') ?></span><?= $e($n['name']) ?>
              <?php if ($n['parent'] !== null && $search !== ''): ?><small>(in <?= $e($namen[$n['parent']] ?? '') ?>)</small><?php endif; ?></td>
            <td class="c-ebene"><?= $e(KAT_EBENEN[$n['ebene']] ?? $n['ebene']) ?></td>
            <td class="is-numeric c-info"><?= $verw ?><span class="sr-only"> Einträge</span><span class="nur-karte"> <?= $verw === 1 ? 'Eintrag' : 'Einträge' ?> · <?= $e(KAT_EBENEN[$n['ebene']] ?? $n['ebene']) ?></span></td>
            <td class="c-akt">
              <a class="btn" href="<?= $e($self) ?>?id=<?= $n['id'] ?><?= $search !== '' ? '&amp;q=' . urlencode($search) : '' ?>"><?= icon('edit','Bearbeiten') ?></a>
              <button type="button" class="btn delete" data-loeschen data-id="<?= $n['id'] ?>" data-name="<?= $e($n['name']) ?>"
                      data-sperre="<?= $e($sperreText) ?>" data-hinweis=""><?= icon('trash','Löschen') ?></button>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?>
          <tr class="leer"><td colspan="5" class="c-leer v-leer">Keine Einträge gefunden.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</main>

<?= admin_loesch_dialog($loeschAction) ?>
</body>
</html>
