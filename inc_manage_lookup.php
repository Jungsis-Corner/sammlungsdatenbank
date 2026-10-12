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
 *   delete_errors  true = Löschfehler (z. B. Fremdschlüssel) abfangen und als Hinweis zeigen
 *   keep_page      true = Seitennummer in Bearbeiten-/Lösch-Links und nach dem Speichern behalten
 *
 * Tabellen- und Spaltennamen stammen ausschließlich aus dem festen $cfg der
 * aufrufenden Datei, nie aus Benutzereingaben.
 */
if (!isset($cfg) || !is_array($cfg)) { http_response_code(500); exit('Konfiguration fehlt.'); }

require __DIR__ . '/require_login.php';
if (!is_admin()) { http_response_code(403); exit('Nur für Admins.'); }

require_once __DIR__ . '/config.php';
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) { die("Verbindungsfehler: " . $conn->connect_error); }
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
if (isset($_GET['delete'])) {
    $del_id = intval($_GET['delete']);
    if (!empty($cfg['delete_errors'])) {
        // Fehler abfangen (z. B. Fremdschlüssel-Sperre) und per GET melden
        try {
            $stmt = $conn->prepare("DELETE FROM $tbl WHERE `ID` = ?");
            $stmt->bind_param('i', $del_id);
            $stmt->execute();
        } catch (Throwable $e) {
            header('Location: ' . $self . '?' . http_build_query(['page' => $page, 'q' => $search, 'err' => 'delete']));
            exit;
        }
    } else {
        $stmt = $conn->prepare("DELETE FROM $tbl WHERE `ID` = ?");
        $stmt->bind_param('i', $del_id);
        $stmt->execute();
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST[$postKey] ?? '');
    if ($id) {
        $stmt = $conn->prepare("UPDATE $tbl SET $col = ? WHERE `ID` = ?");
        $stmt->bind_param('si', $name, $id);
    } else {
        $stmt = $conn->prepare("INSERT INTO $tbl ($col) VALUES (?)");
        $stmt->bind_param('s', $name);
    }
    $stmt->execute();
    header('Location: ' . $self . '?q=' . urlencode($search) . $pageParam);
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

$stmt = $conn->prepare("SELECT `ID`, $col FROM $tbl $where ORDER BY $col LIMIT ? OFFSET ?");
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

    <?php if (($_GET['err'] ?? '') === 'delete'): ?>
      <div class="msg-box error">Löschen fehlgeschlagen. Der Eintrag wird vermutlich noch verwendet.</div>
    <?php endif; ?>

    <!-- Formular: Hinzufügen/Bearbeiten -->
    <div class="form-line-center">
      <form method="post" class="admin-inline-form">
        <input type="text" name="<?= htmlspecialchars($postKey) ?>" placeholder="<?= htmlspecialchars($cfg['name_ph']) ?>" value="<?= htmlspecialchars($name) ?>">
        <input type="submit" class="btn btn-green is-small" value="<?= $id ? 'Speichern' : 'Hinzufügen' ?>">
      </form>
    </div>

    <!-- Liste -->
    <div class="table-wrap">
      <table class="adm-table is-striped is-hover is-compact is-sticky">
        <thead>
          <tr>
            <th class="col-id">ID</th>
            <th><?= htmlspecialchars($cfg['th']) ?></th>
            <th class="is-center">Aktionen</th>
          </tr>
        </thead>
        <tbody>
          <?php while ($row = $list->fetch_assoc()): ?>
            <tr>
              <td class="col-id is-numeric"><?= (int)$row['ID'] ?></td>
              <td><?= htmlspecialchars($row[$cfg['column']] ?? '') ?></td>
              <td class="actions">
                <a class="btn is-small" href="<?= htmlspecialchars($self) ?>?id=<?= (int)$row['ID'] ?>&q=<?= urlencode($search) ?><?= $pageParam ?>" title="Bearbeiten">✏️</a>
                <a class="btn is-small btn-red"
                   href="<?= htmlspecialchars($self) ?>?delete=<?= (int)$row['ID'] ?>&q=<?= urlencode($search) ?><?= $pageParam ?>"
                   onclick="return confirm('Wirklich löschen?')"
                   title="Löschen">🗑️</a>
              </td>
            </tr>
          <?php endwhile; ?>
          <?php if ($list->num_rows === 0): ?>
            <tr><td colspan="3" class="is-center">Keine Einträge gefunden.</td></tr>
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
