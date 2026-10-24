<?php
/**
 * bild_upload.php: Bild hochladen (nur POST mit CSRF-Token, nur Admin; Antwort als JSON).
 * Felder: csrf, bild (Datei). Der Upload muss mit IMAGE_UPLOAD_ENABLED eingeschaltet sein und die Bildquelle local oder auto sein.
 * Antwort: {"ok":true,"name":"<Dateiname>","url":"<Miniatur-URL>"} oder {"ok":false,"error":"<deutscher Text>"}.
 * Die Datei wird geprüft und mit GD neu gespeichert (siehe image_store_upload() in inc_image.php); gespeichert in der Datenbank
 * wird der Name erst, wenn das Bearbeiten-Formular gespeichert wird.
 */
require_once __DIR__ . '/inc_errors.php';
require_once __DIR__ . '/boot.php';
require_once __DIR__ . '/inc_security.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/inc_image.php';

function upload_json(int $code, array $data): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { header('Allow: POST'); upload_json(405, ['ok' => false, 'error' => 'Nur per POST erlaubt.']); }
$angemeldet = !empty($_SESSION['logged_in']) || !empty($_SESSION['loggedin']);
if (!$angemeldet || ($_SESSION['role'] ?? '') !== 'admin') { upload_json(403, ['ok' => false, 'error' => 'Nur für Administratoren.']); }

// Ist der Upload größer als post_max_size, kommen $_POST und $_FILES leer an: dann ist das die Ursache, nicht das Token.
$laenge = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
$postMax = image_ini_bytes((string)ini_get('post_max_size'));
if ($laenge > 0 && $postMax > 0 && $laenge > $postMax) {
    upload_json(413, ['ok' => false, 'error' => 'Die Datei ist größer als erlaubt (höchstens ' . number_format(image_upload_limit() / 1048576, 1, ',', '.') . ' MB).']);
}
$sess = (string)($_SESSION['csrf'] ?? '');
$post = (string)($_POST['csrf'] ?? '');
if ($sess === '' || $post === '' || !hash_equals($sess, $post)) { upload_json(400, ['ok' => false, 'error' => 'Ungültiges Formular (CSRF). Bitte die Seite neu laden.']); }
if (session_status() === PHP_SESSION_ACTIVE) { session_write_close(); }

if (!image_upload_available()) { upload_json(403, ['ok' => false, 'error' => 'Der Bild-Upload ist nicht eingeschaltet.']); }
if (!isset($_FILES['bild']) || !is_array($_FILES['bild'])) { upload_json(400, ['ok' => false, 'error' => 'Es wurde keine Datei gesendet.']); }

$r = image_store_upload($_FILES['bild']);
if (empty($r['ok'])) { upload_json(422, ['ok' => false, 'error' => (string)($r['error'] ?? 'Upload fehlgeschlagen.')]); }
upload_json(200, ['ok' => true, 'name' => $r['name'], 'url' => image_url($r['name'], 320)]);
