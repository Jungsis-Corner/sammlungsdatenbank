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
$flash = flash_pull();
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <?php $page_title = 'Kategorien verwalten'; include __DIR__ . '/inc_head_admin.php'; ?>
</head>
<body class="admin-page">

  <a class="skip-link" href="#inhalt">Zum Inhalt springen</a>
  <main class="page" id="inhalt">

    <header class="admin-header">
      <h1>Kategorien verwalten</h1>
      <div class="admin-header-actions">
        <a href="index.php" class="btn btn-gray"><?= icon('back') ?> Zur Sammlung</a>
      </div>
    </header>

    <div class="top-bar">
      <a href="einstellungen.php" class="back-link btn btn-blue"><?= icon('back') ?> Zurück zu Einstellungen</a>
      <form method="get" class="top-bar-search">
        <input type="text" name="q" placeholder="Kategorien suchen…" value="<?= htmlspecialchars($search) ?>">
        <button type="submit" class="btn is-small" title="Suchen"><?= icon('search','Suchen') ?></button>
        <?php if ($search !== ''): ?>
          <button type="button" class="btn btn-red is-small" onclick="location.href='<?= htmlspecialchars($self, ENT_QUOTES) ?>'" title="Suche zurücksetzen"><?= icon('close','Zurücksetzen') ?></button>
        <?php endif; ?>
      </form>
    </div>

    <?php if ($flash): ?>
      <div class="msg-box <?= htmlspecialchars($flash['type'], ENT_QUOTES, 'UTF-8') ?>" role="status"><?= htmlspecialchars($flash['text'], ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <!-- Formular: Hinzufügen/Bearbeiten -->
    <div class="form-line-center">
      <form method="post" class="admin-inline-form" action="<?= htmlspecialchars($self) ?>?<?= htmlspecialchars(http_build_query(array_filter(['q' => $search, 'id' => $id]))) ?>">
        <?= csrf_field() ?>
        <input type="text" name="Kategorie" placeholder="Name der Kategorie" value="<?= htmlspecialchars($form['name']) ?>" required>
        <select name="parent" title="Übergeordnete Kategorie">
          <option value="0">— keine (oberste Ebene) —</option>
          <?php foreach ($baum as $n): if (isset($gesperrt[$n['id']])) continue; ?>
            <option value="<?= $n['id'] ?>" <?= $form['parent'] === $n['id'] ? 'selected' : '' ?>>
              <?= str_repeat('&nbsp;&nbsp;&nbsp;', $n['depth']) . ($n['depth'] ? '↳ ' : '') . htmlspecialchars($n['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <select name="ebene" title="Ebene">
          <?php foreach (KAT_EBENEN as $k => $label): ?>
            <option value="<?= $k ?>" <?= $form['ebene'] === $k ? 'selected' : '' ?>><?= $label ?></option>
          <?php endforeach; ?>
        </select>
        <input type="submit" class="btn btn-green is-small" value="<?= $id ? 'Speichern' : 'Hinzufügen' ?>">
        <?php if ($id): ?><a class="btn is-small" href="<?= htmlspecialchars($self) ?><?= $search !== '' ? '?q=' . urlencode($search) : '' ?>">Abbrechen</a><?php endif; ?>
      </form>
    </div>

    <!-- Liste (Baum) -->
    <div class="table-wrap">
      <table class="adm-table is-striped is-hover is-compact is-sticky">
        <thead>
          <tr>
            <th class="col-id">ID</th>
            <th>Kategorie</th>
            <th>Ebene</th>
            <th>Einträge</th>
            <th class="is-center">Aktionen</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $n): ?>
            <tr>
              <td class="col-id is-numeric"><?= $n['id'] ?></td>
              <td><?= str_repeat('&nbsp;&nbsp;&nbsp;', $search === '' ? $n['depth'] : 0) . ($n['depth'] && $search === '' ? '↳ ' : '') . htmlspecialchars($n['name']) ?>
                <?php if ($n['parent'] !== null && $search !== ''): ?><small>(in <?= htmlspecialchars($namen[$n['parent']] ?? '') ?>)</small><?php endif; ?></td>
              <td><?= htmlspecialchars(KAT_EBENEN[$n['ebene']] ?? $n['ebene']) ?></td>
              <td class="is-numeric"><?= (int)($eigene[$n['id']] ?? 0) ?></td>
              <td class="actions">
                <a class="btn is-small" href="<?= htmlspecialchars($self) ?>?id=<?= $n['id'] ?><?= $search !== '' ? '&q=' . urlencode($search) : '' ?>" title="Bearbeiten"><?= icon('edit','Bearbeiten') ?></a>
                <form method="post" action="<?= htmlspecialchars($self) ?><?= $search !== '' ? '?q=' . urlencode($search) : '' ?>" style="display:inline; margin:0;"
                      onsubmit="return confirm('Wirklich löschen?')">
                  <?= csrf_field() ?>
                  <input type="hidden" name="delete" value="<?= $n['id'] ?>">
                  <button type="submit" class="btn is-small btn-red" title="Löschen"><?= icon('trash','Löschen') ?></button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$rows): ?>
            <tr><td colspan="5" class="is-center">Keine Einträge gefunden.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

  </main>
</body>
</html>
