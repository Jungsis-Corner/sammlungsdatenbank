<?php
// fix_bildpfade.php — entfernt WordPress-Größen-Suffixe (z.B. "-225x300")
// aus Sammlung.SammlungBild1, damit die Original-/Volle-Auflösung verlinkt wird.
//
// Aufruf:
//   fix_bildpfade.php            -> nur Vorschau (Alt/Neu), es wird NICHTS gespeichert
//   fix_bildpfade.php?apply=1    -> wendet die Änderungen wirklich an (nach Bestätigung)

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/require_login.php';
if (!is_admin()) { http_response_code(403); exit('Nur für Admins.'); }

require_once __DIR__ . '/config.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$conn->set_charset('utf8mb4');

if (empty($_SESSION['csrf'])) {
  $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

// Muster: "-<Zahlen>x<Zahlen>" direkt vor der Dateiendung, z.B. "-225x300.jpg"
// Nur EIN Vorkommen am Ende des Dateinamens wird entfernt (vor der Extension).
function strip_wp_size_suffix(string $url): string {
    return preg_replace('/-\d+x\d+(\.\w+)(\?.*)?$/', '$1$2', $url);
}

$apply = isset($_GET['apply']) && $_GET['apply'] === '1';
$confirmed = $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'apply_confirmed';

// ---- Betroffene Datensätze ermitteln (immer, für Vorschau UND Anwendung) ----
$res = $conn->query("SELECT ID, Bezeichnung, SammlungBild1 FROM Sammlung WHERE SammlungBild1 IS NOT NULL AND SammlungBild1 <> ''");
$changes = [];
while ($row = $res->fetch_assoc()) {
    $old = (string)$row['SammlungBild1'];
    $new = strip_wp_size_suffix($old);
    if ($new !== $old) {
        $changes[] = ['id' => (int)$row['ID'], 'bez' => $row['Bezeichnung'], 'old' => $old, 'new' => $new];
    }
}

$updated = 0;
if ($confirmed) {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(400); exit('CSRF ungültig.');
    }
    $stmt = $conn->prepare("UPDATE Sammlung SET SammlungBild1 = ? WHERE ID = ?");
    foreach ($changes as $c) {
        $stmt->bind_param('si', $c['new'], $c['id']);
        $stmt->execute();
        $updated++;
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<title>Bildpfade korrigieren</title>
<style>
  body{font-family:Arial,Helvetica,sans-serif;font-size:10pt;padding:20px;max-width:1100px;margin:0 auto}
  h1{margin-bottom:4px}
  table{border-collapse:collapse;width:100%;margin:16px 0}
  th,td{border:1px solid #ccc;padding:6px 8px;text-align:left;vertical-align:top;font-size:0.85em}
  th{background:#eee}
  .old{color:#b42318}
  .new{color:#0f6b2f}
  .box{background:#eef;border:1px solid #99f;padding:10px;margin:10px 0;border-radius:6px}
  .warn{background:#fff8e1;border:1px solid #e0c36a;padding:10px;margin:10px 0;border-radius:6px}
  .ok{background:#eaf9ea;border:1px solid #9cd49c;padding:10px;margin:10px 0;border-radius:6px}
  button{padding:8px 16px;font-size:1em;background:#c0392b;color:#fff;border:1px solid #922b21;border-radius:6px;cursor:pointer}
  button:hover{filter:brightness(0.95)}
  a.back{display:inline-block;margin-top:16px}
</style>
</head>
<body>
  <h1>🖼️ Bildpfade korrigieren</h1>
  <p>Entfernt WordPress-Größen-Suffixe wie <code>-225x300</code> aus <code>SammlungBild1</code>, damit das Original in voller Auflösung verlinkt wird.</p>

  <?php if ($confirmed): ?>
    <div class="ok"><strong>✅ Erledigt.</strong> <?= $updated ?> Datensätze wurden aktualisiert.</div>
    <a class="back" href="index.php">← Zurück zur Sammlung</a>

  <?php elseif (!$changes): ?>
    <div class="ok">Keine betroffenen Datensätze gefunden — alle Bildpfade sehen schon unauffällig aus.</div>
    <a class="back" href="index.php">← Zurück zur Sammlung</a>

  <?php else: ?>
    <div class="box"><?= count($changes) ?> Datensätze mit vermutlich falscher Auflösung gefunden. Bitte unten prüfen.</div>

    <table>
      <tr><th>ID</th><th>Bezeichnung</th><th>Alt</th><th>Neu</th></tr>
      <?php foreach ($changes as $c): ?>
        <tr>
          <td><?= $c['id'] ?></td>
          <td><?= htmlspecialchars($c['bez']) ?></td>
          <td class="old"><?= htmlspecialchars($c['old']) ?></td>
          <td class="new"><?= htmlspecialchars($c['new']) ?></td>
        </tr>
      <?php endforeach; ?>
    </table>

    <div class="warn">
      ⚠️ Bitte kontrollieren: Die neue URL wird nur berechnet, nicht geprüft ob die Datei dort auch wirklich existiert.
      Am besten stichprobenartig ein paar der „Neu"-Links oben im Browser öffnen, bevor du bestätigst.
    </div>

    <form method="post">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>">
      <input type="hidden" name="action" value="apply_confirmed">
      <button type="submit" onclick="return confirm('Wirklich <?= count($changes) ?> Datensätze aktualisieren?');">
        ✅ Jetzt <?= count($changes) ?> Datensätze aktualisieren
      </button>
    </form>
    <a class="back" href="index.php">← Abbrechen, zurück zur Sammlung</a>
  <?php endif; ?>
</body>
</html>
