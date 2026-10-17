<?php
require_once __DIR__ . '/inc_errors.php';
require_once __DIR__ . '/require_login.php';
require_once __DIR__ . '/inc_security.php';
if (!is_admin()) { error_page(403, 'Kein Zugriff', 'Dieser Bereich ist nur für Administratoren.'); }
require_post_csrf();   // Löschen nur per POST mit CSRF-Token

require_once __DIR__ . '/config.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$conn->set_charset('utf8mb4');

$id = (int)($_POST['id'] ?? 0);
$back = safe_back_url($_POST['back'] ?? null, 'einkauf.php');

if ($id > 0) {
  $stmt = $conn->prepare("DELETE FROM Einkauf WHERE ID=?");
  $stmt->bind_param('i', $id);
  $stmt->execute();
}

header('Location: '.$back);
exit;
