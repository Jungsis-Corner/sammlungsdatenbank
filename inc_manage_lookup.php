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
require __DIR__ . '/require_login.php';
require_once __DIR__ . '/inc_security.php';
if (!is_admin()) { http_response_code(403); exit('Nur für Admins.'); }

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
$stmt = $conn->prepare("SELECT `ID`, $col, ($useSql) AS `Verwendet` FROM $tbl $where ORDER BY $orderBy LIMIT ? OFFSET ?");
if ($search !== '') {
    $stmt->bind_param('sii', $like, $limit, $offset);
} else {
    $stmt->bind_param('ii', $limit, $offset);
}
$stmt->execute();
$list = $stmt->get_result();

/** Pagination wie index.php (Fenster = 2) */
function render_admin_pagination(int $page, int $pages, array $params): void {
  $window = 2;
  echo '<div class="pagination">';
  if ($page > 1) {
      echo '<a href="?'.http_build_query(array_merge($params,['page'=>1])).'">« Erste</a> ';
      echo '<a href="?'.http_build_query(array_merge($params,['page'=>$page-1])).'">‹ Zurück</a> ';
  }
  if ($page - $window > 2) {
      echo '<a href="?'.http_build_query(array_merge($params,['page'=>1])).'">1</a> … ';
  }
  for ($i = max(1, $page-$window); $i <= min($pages, $page+$window); $i++) {
      if ($i === $page) {
          echo "<strong>$i</strong> ";
      } else {
          echo '<a href="?'.http_build_query(array_merge($params,['page'=>$i])).'">'.$i.'</a> ';
      }
  }
  if ($page + $window < $pages-1) {
      echo '… <a href="?'.http_build_query(array_merge($params,['page'=>$pages])).'">'.$pages.'</a> ';
  }
  if ($page < $pages) {
      echo '<a href="?'.http_build_query(array_merge($params,['page'=>$page+1])).'">Weiter ›</a> ';
      echo '<a href="?'.http_build_query(array_merge($params,['page'=>$pages])).'">Letzte »</a>';
  }
  echo '</div>';
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <?php $page_title = $cfg['title']; include __DIR__ . '/inc_head_admin.php'; ?>
</head>
<body class="admin-page">

  <main class="page"><!-- zentriert & breite begrenzt -->

    <header class="admin-header">
      <h1><?= htmlspecialchars($cfg['title']) ?></h1>
      <div class="admin-header-actions">
        <a href="index.php" class="btn btn-gray">← Zur Sammlung</a>
      </div>
    </header>

    <div class="top-bar">
      <a href="einstellungen.php" class="back-link btn btn-blue">← Zurück zu Einstellungen</a>
      <form method="get" class="top-bar-search">
        <input type="text" name="q" placeholder="<?= htmlspecialchars($cfg['search_ph']) ?>" value="<?= htmlspecialchars($search) ?>">
        <button type="submit" class="btn is-small">🔍</button>
        <?php if ($search !== ''): ?>
          <button type="button" class="btn btn-red is-small" onclick="location.href='<?= htmlspecialchars($self, ENT_QUOTES) ?>'">✖</button>
        <?php endif; ?>
      </form>
    </div>

    <?php $flash = flash_pull(); ?>
    <?php if ($flash): ?>
      <div class="msg-box <?= htmlspecialchars($flash['type'], ENT_QUOTES, 'UTF-8') ?>" role="status"><?= htmlspecialchars($flash['text'], ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <!-- Formular: Hinzufügen/Bearbeiten -->
    <div class="form-line-center">
      <form method="post" class="admin-inline-form">
        <?= csrf_field() ?>
        <input type="text" name="<?= htmlspecialchars($postKey) ?>" placeholder="<?= htmlspecialchars($cfg['name_ph']) ?>" value="<?= htmlspecialchars($name) ?>">
        <input type="submit" class="btn btn-green is-small" value="<?= $id ? 'Speichern' : 'Hinzufügen' ?>">
      </form>
    </div>

    <?php if ($canMerge): ?>
      <?php $dl = $conn->query("SELECT $col FROM $tbl WHERE `ID` <> " . (int)$id . " ORDER BY $col"); ?>
      <!-- Zusammenführen: alle Verweise auf einen anderen Eintrag umstellen, diesen Eintrag löschen -->
      <div class="form-line-center">
        <form method="post" class="admin-inline-form"
              onsubmit="return confirm('Alle Verweise von „' + <?= htmlspecialchars(json_encode($name, JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?> + '“ auf den gewählten Eintrag umstellen und „' + <?= htmlspecialchars(json_encode($name, JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?> + '“ löschen?')">
          <?= csrf_field() ?>
          <input type="text" name="merge_into" list="merge-targets" placeholder="In diesen Eintrag überführen…" required>
          <datalist id="merge-targets">
            <?php while ($d = $dl->fetch_row()): ?><option value="<?= htmlspecialchars($d[0]) ?>"><?php endwhile; ?>
          </datalist>
          <input type="submit" class="btn btn-red is-small" value="Überführen und löschen">
        </form>
      </div>
    <?php endif; ?>

    <!-- Liste -->
    <div class="table-wrap">
      <table class="adm-table is-striped is-hover is-compact is-sticky">
        <thead>
          <tr>
            <th class="col-id">ID</th>
            <th><?= htmlspecialchars($cfg['th']) ?></th>
            <th class="is-numeric">Einträge</th>
            <th class="is-center">Aktionen</th>
          </tr>
        </thead>
        <tbody>
          <?php while ($row = $list->fetch_assoc()): ?>
            <tr>
              <td class="col-id is-numeric"><?= (int)$row['ID'] ?></td>
              <td><?= htmlspecialchars($row[$cfg['column']] ?? '') ?></td>
              <td class="is-numeric"><?= (int)$row['Verwendet'] ?></td>
              <td class="actions">
                <a class="btn is-small" href="<?= htmlspecialchars($self) ?>?id=<?= (int)$row['ID'] ?>&q=<?= urlencode($search) ?><?= $pageParam ?>" title="Bearbeiten">✏️</a>
                <form method="post" action="<?= htmlspecialchars($self) ?>?q=<?= urlencode($search) ?><?= $pageParam ?>" style="display:inline; margin:0;"
                      onsubmit="return confirm('Wirklich löschen?')">
                  <?= csrf_field() ?>
                  <input type="hidden" name="delete" value="<?= (int)$row['ID'] ?>">
                  <button type="submit" class="btn is-small btn-red" title="Löschen">🗑️</button>
                </form>
              </td>
            </tr>
          <?php endwhile; ?>
          <?php if ($list->num_rows === 0): ?>
            <tr><td colspan="4" class="is-center">Keine Einträge gefunden.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <nav class="pagination">
      <?php
        $params = [];
        if ($search !== '') $params['q'] = $search; // aktive Suche beibehalten
        render_admin_pagination($page, $pages, $params);
      ?>
    </nav>

  </main>
</body>
</html>
