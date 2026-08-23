<?php
require __DIR__ . '/require_login.php';
if (!is_admin()) { http_response_code(403); exit('Nur für Admins.'); }

require_once __DIR__ . '/config.php';
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) { die("Verbindungsfehler: " . $conn->connect_error); }
$conn->set_charset('utf8mb4');

/* ---------- Parameter ---------- */
$limit  = 30;
$page   = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;
$search = trim($_GET['q'] ?? '');

/* ---------- Löschen ---------- */
if (isset($_GET['delete'])) {
    $del_id = intval($_GET['delete']);
    $conn->query("DELETE FROM `Verpackung` WHERE `ID` = $del_id");
    header('Location: manage_verpackung.php?page=' . $page . '&q=' . urlencode($search));
    exit;
}

/* ---------- Add/Edit ---------- */
$id   = intval($_GET['id'] ?? 0);
$name = '';
if ($id) {
    $stmt = $conn->prepare("SELECT `Verpackung` FROM `Verpackung` WHERE `ID` = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    if ($res = $stmt->get_result()) {
        if ($res->num_rows) {
            $row  = $res->fetch_assoc();
            $name = $row['Verpackung'];
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['Verpackung'] ?? '');
    if ($id) {
        $stmt = $conn->prepare("UPDATE `Verpackung` SET `Verpackung` = ? WHERE `ID` = ?");
        $stmt->bind_param('si', $name, $id);
    } else {
        $stmt = $conn->prepare("INSERT INTO `Verpackung` (`Verpackung`) VALUES (?)");
        $stmt->bind_param('s', $name);
    }
    $stmt->execute();
    header('Location: manage_verpackung.php?q=' . urlencode($search));
    exit;
}

/* ---------- Zählung & Liste ---------- */
$where = ($search !== '')
    ? "WHERE `Verpackung` LIKE '%" . $conn->real_escape_string($search) . "%'"
    : '';

$total = 0;
if ($r = $conn->query("SELECT COUNT(*) FROM `Verpackung` $where")->fetch_row()) {
    $total = (int)$r[0];
}
$pages = max(1, (int)ceil($total / $limit));

$sql = "SELECT `ID`,`Verpackung`
        FROM `Verpackung`
        $where
        ORDER BY `Verpackung`
        LIMIT $limit OFFSET $offset";
$list = $conn->query($sql);
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
  <?php $page_title = 'Verpackung verwalten'; include __DIR__ . '/inc_head_admin.php'; ?>
</head>
<body class="admin-page">

  <main class="page"><!-- zentriert & breite begrenzt -->

    <!-- Header jetzt innerhalb von .page -->
    <header class="admin-header">
      <h1>Verpackung verwalten</h1>
      <div class="admin-header-actions">
        <a href="index.php" class="btn btn-gray">← Zur Sammlung</a>
      </div>
    </header>

    <div class="top-bar">
      <a href="einstellungen.php" class="back-link btn btn-blue">← Zurück zu Einstellungen</a>
      <form method="get" class="top-bar-search">
        <input type="text" name="q" placeholder="Verpackung suchen…" value="<?= htmlspecialchars($search) ?>">
        <button type="submit" class="btn is-small">🔍</button>
        <?php if ($search !== ''): ?>
          <button type="button" class="btn btn-red is-small" onclick="location.href='manage_verpackung.php'">✖</button>
        <?php endif; ?>
      </form>
    </div>
    <!-- Formular: Hinzufügen/Bearbeiten -->
<div class="form-line-center">
  <form method="post" class="admin-inline-form">
    <input type="text" name="Verpackung" placeholder="Name des Verpackungs" value="<?= htmlspecialchars($name) ?>">
    <input type="submit" class="btn btn-green is-small" value="<?= $id ? 'Speichern' : 'Hinzufügen' ?>">
  </form>
</div>
    <!-- Liste -->
    <div class="table-wrap">
      <table class="adm-table is-striped is-hover is-compact is-sticky">
        <thead>
          <tr>
            <th class="col-id">ID</th>
            <th>Verpackung</th>
            <th class="is-center">Aktionen</th>
          </tr>
        </thead>
        <tbody>
          <?php while ($row = $list->fetch_assoc()): ?>
            <tr>
              <td class="col-id is-numeric"><?= (int)$row['ID'] ?></td>
              <td><?= htmlspecialchars($row['Verpackung']) ?></td>
              <td class="actions">
                <a class="btn is-small" href="manage_verpackung.php?id=<?= (int)$row['ID'] ?>&q=<?= urlencode($search) ?>" title="Bearbeiten">✏️</a>
                <a class="btn is-small btn-red"
                   href="manage_verpackung.php?delete=<?= (int)$row['ID'] ?>&q=<?= urlencode($search) ?>"
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
