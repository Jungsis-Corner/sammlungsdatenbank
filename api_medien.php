<?php
/**
 * api_medien.php?q=<Namensteil>&page=<n>  (nur Admin, nur lesend)
 * Liste der Bilddateien in medien/ für die Auswahl im Bearbeiten-Formular, neueste zuerst, 24 je Seite:
 * {"total":57,"page":1,"pages":3,"items":[{"name":"ab12.jpg","url":"<Miniatur-URL>"}, …]}
 * Nur im lokalen Modus (IMAGE_SOURCE local oder auto); sonst 404. Es wird nichts geändert, deshalb GET ohne CSRF-Token.
 */
require_once __DIR__ . '/inc_errors.php';
require_once __DIR__ . '/boot.php';
require_once __DIR__ . '/inc_security.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/inc_image.php';

function medien_json(int $code, array $data): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') { header('Allow: GET'); medien_json(405, ['error' => 'Nur GET.']); }
$angemeldet = !empty($_SESSION['logged_in']) || !empty($_SESSION['loggedin']);
if (!$angemeldet || ($_SESSION['role'] ?? '') !== 'admin') { medien_json(403, ['error' => 'Nur für Administratoren.']); }
if (session_status() === PHP_SESSION_ACTIVE) { session_write_close(); }
if (!image_local_mode()) { medien_json(404, ['error' => 'Kein lokaler Bildordner eingestellt.']); }

$pro = 24;
$q = mb_substr(trim((string)($_GET['q'] ?? '')), 0, 80);
$page = max(1, (int)($_GET['page'] ?? 1));
$l = image_local_list($q, ($page - 1) * $pro, $pro);
$items = [];
foreach ($l['items'] as $i) { $items[] = ['name' => $i['name'], 'url' => image_url($i['name'], 160)]; }
medien_json(200, ['total' => $l['total'], 'page' => $page, 'pages' => max(1, (int)ceil($l['total'] / $pro)), 'items' => $items]);
