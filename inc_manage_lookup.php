<?php
/**
 * Gemeinsame Logik und Darstellung für die manage_*.php-Seiten
 * (einspaltige Stammdaten-Tabellen: Name verwalten, suchen, blättern).
 *
 * Wird von manage_*.php nach dem Setzen von $cfg eingebunden:
 *   table      DB-Tabelle                      (z. B. 'Material')
 *   column     DB-Spalte mit dem Namen         (z. B. 'Material')
 *   post       Name des Formularfelds          (Default: column)
 *   self       Dateiname der Seite             (z. B. 'manage_material.php')
 *   title      Seitentitel und Überschrift     (z. B. 'Material verwalten')
 *   th         Spaltenüberschrift der Tabelle  (z. B. 'Material')
 *   search_ph  Platzhalter der Suche           (z. B. 'Material suchen…')
 *   name_ph    Platzhalter des Eingabefelds    (z. B. 'Name des Materials')
 * Optional:
 *   keep_page      true = Seitennummer in Bearbeiten-/Lösch-Links und nach dem Speichern behalten
 *   merge          false = "In anderen Eintrag überführen" ausschalten (Default: an)
 *   order          feste SQL-Sortierung der Liste (Default: Namensspalte); z. B. natürliche Reihenfolge bei Box
 *
 * Tabellen- und Spaltennamen stammen ausschließlich aus dem festen $cfg der
 * aufrufenden Datei, nie aus Benutzereingaben.
 */
if (!isset($cfg) || !is_array($cfg)) { http_response_code(500); exit('Konfiguration fehlt.'); }

require_once __DIR__ . '/inc_errors.php';
require_once __DIR__ . '/inc_icons.php';
require __DIR__ . '/require_login.php';
require_once __DIR__ . '/inc_security.php';
require_once __DIR__ . '/inc_admin_ui.php';
if (!is_admin()) { error_page(403, 'Kein Zugriff', 'Dieser Bereich ist nur für Administratoren.'); }

require_once __DIR__ . '/config.php';
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) { fail_db('Datenbankfehler.', 'Verbindung: ' . $conn->connect_error); }
$conn->set_charset('utf8mb4');

$tbl      = '`' . str_replace('`', '``', $cfg['table'])  . '`';
$col      = '`' . str_replace('`', '``', $cfg['column']) . '`';
$postKey  = $cfg['post'] ?? $cfg['column'];
$self     = $cfg['self'];

/* ---------- Parameter ---------- */
$limit  = 30;
$page   = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;
$search = trim($_GET['q'] ?? '');

/* ---------- Löschen ---------- */
if (isset($_POST['delete'])) {
    require_post_csrf();
    $del_id = intval($_POST['delete']);
    // Fehler abfangen und als einmalige Session-Meldung zeigen. 1451 = Fremdschlüssel-Sperre (noch verwendet).
    try {
        $stmt = $conn->prepare("DELETE FROM $tbl WHERE `ID` = ?");
        $stmt->bind_param('i', $del_id);
        $stmt->execute();
    } catch (mysqli_sql_exception $e) {
        if ((int)$e->getCode() === 1451) {
            $n = 0;
            foreach (lookup_refs($cfg['table']) as [$rt, $rc]) {
                if ($rt !== '`Sammlung`') continue;   // nur Sammlung sperrt das Löschen (Einkauf: ON DELETE SET NULL)
                $stmt = $conn->prepare("SELECT COUNT(*) FROM $rt WHERE $rc = ?");
                $stmt->bind_param('i', $del_id);
                $stmt->execute();
                $n += (int)$stmt->get_result()->fetch_row()[0];
            }
            flash_set('Nicht gelöscht: Der Eintrag ist noch in Verwendung' . ($n > 0 ? ' (' . $n . ' ' . ($n === 1 ? 'Eintrag' : 'Einträge') . ')' : '')
                . '. Bitte erst die Einträge umstellen oder den Eintrag in einen anderen überführen.', 'error');
        } else {
            error_log('Löschen ' . $cfg['table'] . ' ' . $del_id . ': ' . $e->getMessage());
            flash_set('Löschen fehlgeschlagen.', 'error');
        }
        header('Location: ' . $self . '?' . http_build_query(['page' => $page, 'q' => $search]));
        exit;
    }
    header('Location: ' . $self . '?page=' . $page . '&q=' . urlencode($search));
    exit;
}

// Seite in Bearbeiten-/Lösch-Links und nach dem Speichern beibehalten
$keepPage  = !empty($cfg['keep_page']);
$pageParam = $keepPage ? '&page=' . $page : '';

/* ---------- Add/Edit ---------- */
$id   = intval($_GET['id'] ?? 0);
$name = '';
if ($id) {
    $stmt = $conn->prepare("SELECT $col FROM $tbl WHERE `ID` = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    if ($res = $stmt->get_result()) {
        if ($res->num_rows) {
            $row  = $res->fetch_assoc();
            $name = $row[$cfg['column']];
        }
    }
}

$refs      = lookup_refs($cfg['table']);
$canMerge  = ($cfg['merge'] ?? true) && $id && $refs;
$backQuery = '?q=' . urlencode($search) . $pageParam;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();

    /* Zusammenführen: alle Verweise von $id auf den Eintrag mit dem Zielnamen umstellen, dann $id löschen */
    if (isset($_POST['merge_into'])) {
        if (!$canMerge) { http_response_code(400); exit('Zusammenführen nicht möglich.'); }
        $target = trim($_POST['merge_into']);
        $stmt = $conn->prepare("SELECT `ID`, $col FROM $tbl WHERE $col = ?");
        $stmt->bind_param('s', $target);
        $stmt->execute();
        $t = $stmt->get_result()->fetch_assoc();
        if (!$t) {
            flash_set('Zieleintrag „' . $target . '“ nicht gefunden. Bitte einen vorhandenen Namen wählen.', 'error');
        } elseif ((int)$t['ID'] === $id) {
            flash_set('Quelle und Ziel sind derselbe Eintrag.', 'error');
        } else {
            $toId  = (int)$t['ID'];
            $moved = 0;
            try {
                $conn->begin_transaction();
                foreach ($refs as [$rt, $rc]) {
                    // Zeitstempel (Geaendert_am) bleibt unverändert, sonst gäbe es eine Fake-Änderung
                    $stmt = $conn->prepare("UPDATE $rt SET $rc = ?" . ($rt === '`Sammlung`' || $rt === '`Einkauf`' ? ", `Geaendert_am` = `Geaendert_am`" : '') . " WHERE $rc = ?");
                    $stmt->bind_param('ii', $toId, $id);
                    $stmt->execute();
                    $moved += $stmt->affected_rows;
                }
                $stmt = $conn->prepare("DELETE FROM $tbl WHERE `ID` = ?");
                $stmt->bind_param('i', $id);
                $stmt->execute();
                $conn->commit();
                flash_set('„' . $name . '“ wurde in „' . $t[$cfg['column']] . '“ überführt (' . $moved . ' Verweise umgestellt) und gelöscht.', 'success');
            } catch (Throwable $e) {
                $conn->rollback();
                error_log('Zusammenführen ' . $cfg['table'] . ' ' . $id . '->' . $toId . ': ' . $e->getMessage());
                flash_set('Zusammenführen fehlgeschlagen, es wurde nichts geändert.', 'error');
            }
        }
        header('Location: ' . $self . $backQuery);
        exit;
    }

    $name = trim($_POST[$postKey] ?? '');
    if ($name === '') {
        flash_set('Der Name darf nicht leer sein.', 'error');
        header('Location: ' . $self . $backQuery . ($id ? '&id=' . $id : ''));
        exit;
    }
    try {
        if ($id) {
            $stmt = $conn->prepare("UPDATE $tbl SET $col = ? WHERE `ID` = ?");
            $stmt->bind_param('si', $name, $id);
        } else {
            $stmt = $conn->prepare("INSERT INTO $tbl ($col) VALUES (?)");
            $stmt->bind_param('s', $name);
        }
        $stmt->execute();
    } catch (mysqli_sql_exception $e) {
        if ((int)$e->getCode() === 1062) {
            flash_set('Diesen Namen gibt es schon (Groß-/Kleinschreibung zählt nicht).', 'error');
            header('Location: ' . $self . $backQuery . ($id ? '&id=' . $id : ''));
            exit;
        }
        fail_db('Datenbankfehler.', 'Speichern ' . $cfg['table'] . ': ' . $e->getMessage());
    }
    header('Location: ' . $self . $backQuery);
    exit;
}

/* ---------- Zählung & Liste ---------- */
$like  = '%' . $search . '%';
$where = ($search !== '') ? "WHERE $col LIKE ?" : '';

$total = 0;
$stmt = $conn->prepare("SELECT COUNT(*) FROM $tbl $where");
if ($search !== '') { $stmt->bind_param('s', $like); }
$stmt->execute();
if ($r = $stmt->get_result()->fetch_row()) {
    $total = (int)$r[0];
}
$pages = max(1, (int)ceil($total / $limit));

$orderBy = $cfg['order'] ?? $col;   // feste Angabe aus $cfg, nie aus Benutzereingaben
// Verwendungsanzahl je Eintrag (Tabellen/Spalten aus der festen Verweisliste)
$useSql = '0';
foreach ($refs as [$rt, $rc]) { $useSql .= " + (SELECT COUNT(*) FROM $rt WHERE $rt.$rc = $tbl.`ID`)"; }
// Nur Verweise aus Sammlung sperren das Löschen (Einkauf: ON DELETE SET NULL)
$blockSql = '0';
foreach ($refs as [$rt, $rc]) { if ($rt === '`Sammlung`') $blockSql .= " + (SELECT COUNT(*) FROM $rt WHERE $rt.$rc = $tbl.`ID`)"; }
$stmt = $conn->prepare("SELECT `ID`, $col, ($useSql) AS `Verwendet`, ($blockSql) AS `Gesperrt` FROM $tbl $where ORDER BY $orderBy LIMIT ? OFFSET ?");
if ($search !== '') {
    $stmt->bind_param('sii', $like, $limit, $offset);
} else {
    $stmt->bind_param('ii', $limit, $offset);
}
$stmt->execute();
$list = $stmt->get_result();

$einTr = fn(int $n): string => $n . ' ' . ($n === 1 ? 'Eintrag' : 'Einträgen');   // Dativ: „in 3 Einträgen“
$flashHtml = admin_flash();
$kruemel = [[$cfg['title'], '']];
$listParams = [];
if ($search !== '') $listParams['q'] = $search;
$loeschAction = $self . '?q=' . urlencode($search) . $pageParam;
$e = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <?php $page_title = $cfg['title']; include __DIR__ . '/inc_head_admin.php'; ?>
  <script src="/sammlung/assets/typeahead.js?v=1" defer></script>
</head>
<body class="admin-page">
<?= admin_header($kruemel) ?>

<main id="inhalt" tabindex="-1">
  <h1><?= $e($cfg['title']) ?></h1>

  <?= $flashHtml ?>

  <!-- Suche -->
  <form method="get" class="v-suche" role="search" action="<?= $e($self) ?>">
    <label class="sr-only" for="f_suche">Suchen</label>
    <input type="search" id="f_suche" name="q" placeholder="<?= $e($cfg['search_ph']) ?>" value="<?= $e($search) ?>" autocomplete="off">
    <button type="submit" class="btn"><?= icon('search') ?> Suchen</button>
    <?php if ($search !== ''): ?><a class="btn" href="<?= $e($self) ?>"><?= icon('close') ?> Zurücksetzen</a><?php endif; ?>
  </form>

  <!-- Hinzufügen / Bearbeiten -->
  <section class="v-karte" aria-labelledby="formTitel">
    <h2 id="formTitel"><?= $id ? 'Eintrag bearbeiten' : 'Neuer Eintrag' ?></h2>
    <form method="post" class="v-form zeile">
      <?= csrf_field() ?>
      <div class="v-feld">
        <label for="f_name">Name</label>
        <input type="text" id="f_name" name="<?= $e($postKey) ?>" placeholder="<?= $e($cfg['name_ph']) ?>" value="<?= $e($name) ?>" required autocomplete="off">
      </div>
      <div class="v-knoepfe">
        <button type="submit" class="btn save"><?= icon('save') ?> <?= $id ? 'Speichern' : 'Hinzufügen' ?></button>
        <?php if ($id): ?><a class="btn" href="<?= $e($self . $backQuery) ?>">Abbrechen</a><?php endif; ?>
      </div>
    </form>
  </section>

  <?php if ($canMerge): ?>
    <!-- Zusammenführen: alle Verweise auf einen anderen Eintrag umstellen, diesen Eintrag löschen -->
    <section class="v-karte" aria-labelledby="mergeTitel">
      <h2 id="mergeTitel">In anderen Eintrag überführen</h2>
      <p class="v-unter">Alle Einträge, die „<?= $e($name) ?>“ verwenden, wechseln zum gewählten Eintrag. Danach wird „<?= $e($name) ?>“ gelöscht.</p>
      <form method="post" class="v-form zeile" id="mergeForm">
        <?= csrf_field() ?>
        <div class="v-feld">
          <label for="f_merge">Ziel</label>
          <input type="text" id="f_merge" name="merge_into" data-ta="<?= $e(['Verkäufer' => 'verkaeufer'][$cfg['table']] ?? strtolower($cfg['table'])) ?>" placeholder="Tippen zum Suchen…" autocomplete="off" required>
        </div>
        <div class="v-knoepfe">
          <button type="button" class="btn delete" data-merge-pruefen data-name="<?= $e($name) ?>"><?= icon('copy') ?> Überführen und löschen</button>
        </div>
      </form>
    </section>
    <dialog id="mergeDialog" class="v-dialog" aria-labelledby="mergeDlgTitel">
      <h2 id="mergeDlgTitel">Zusammenführen?</h2>
      <p id="mergeText"></p>
      <div class="v-dialog-knoepfe">
        <button type="button" class="btn" data-dialog-zu>Abbrechen</button>
        <button type="submit" class="btn delete" form="mergeForm"><?= icon('check') ?> Überführen und löschen</button>
      </div>
    </dialog>
  <?php endif; ?>

  <!-- Liste -->
  <p class="v-unter" aria-live="polite"><?= $e($total) ?> <?= $total === 1 ? 'Eintrag' : 'Einträge' ?><?= $search !== '' ? ' zu „' . $e($search) . '“' : '' ?></p>
  <div class="table-wrap">
    <table class="vtabelle">
      <thead>
        <tr>
          <th class="col-id">ID</th>
          <th><?= $e($cfg['th']) ?></th>
          <th class="is-numeric">Einträge</th>
          <th><span class="sr-only">Aktionen</span></th>
        </tr>
      </thead>
      <tbody>
        <?php while ($row = $list->fetch_assoc()):
            $rid = (int)$row['ID']; $rname = (string)($row[$cfg['column']] ?? '');
            $verw = (int)$row['Verwendet']; $sperr = (int)$row['Gesperrt'];
            $sperreText = $sperr > 0 ? 'Der Eintrag wird noch in ' . $einTr($sperr) . ' verwendet. Stelle diese Einträge erst um oder führe „' . $rname . '“ mit einem anderen Eintrag zusammen (Bearbeiten).' : '';
            $hinweisText = ($sperr === 0 && $verw > 0) ? 'Er wird noch in ' . $verw . ' ' . ($verw === 1 ? 'Einkauf' : 'Einkäufen') . ' verwendet; die Verknüpfung dort entfällt.' : '';
        ?>
          <?php $editHref = $self . '?id=' . $rid . '&q=' . urlencode($search) . $pageParam; ?>
          <tr class="zeile-klick" data-href="<?= $e($editHref) ?>">
            <td class="col-id c-id is-numeric"><?= $rid ?></td>
            <td class="c-name"><a class="zeilenlink" href="<?= $e($editHref) ?>" title="Bearbeiten"><?= $e($rname) ?></a></td>
            <td class="is-numeric c-info"><?= $verw ?><span class="sr-only"> Einträge</span><span class="nur-karte"> <?= $verw === 1 ? 'Eintrag' : 'Einträge' ?></span></td>
            <td class="c-akt">
              <button type="button" class="btn delete" data-loeschen data-id="<?= $rid ?>" data-name="<?= $e($rname) ?>"
                      data-sperre="<?= $e($sperreText) ?>" data-hinweis="<?= $e($hinweisText) ?>"><?= icon('trash','Löschen') ?></button>
            </td>
          </tr>
        <?php endwhile; ?>
        <?php if ($list->num_rows === 0): ?>
          <tr class="leer"><td colspan="4" class="c-leer v-leer">Keine Einträge gefunden.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <?= admin_pagination($page, $pages, $listParams) ?>
</main>

<?= admin_loesch_dialog($loeschAction) ?>
</body>
</html>
