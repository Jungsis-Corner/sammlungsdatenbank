<?php
// logview.php (nur für Admin)
require_once __DIR__ . '/inc_errors.php';
require_once __DIR__ . '/require_login.php';
if (!function_exists('is_admin') || !is_admin()) { http_response_code(403); exit('Nur für Admins.'); }

require_once __DIR__ . '/config.php';

// Login-Versuche (nur Hash-Kurzformen, keine Klartext-IP): logview.php?login=1
if (isset($_GET['login'])) {
  header('Content-Type: text/plain; charset=UTF-8');
  try {
    $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    $db->set_charset('utf8mb4');
    $r = $db->query('SELECT COUNT(*) FROM `Login_Versuche` WHERE `Erfolg` = 0 AND `Zeitpunkt` > (NOW() - INTERVAL 24 HOUR)');
    echo 'Fehlversuche der letzten 24 Stunden: ' . (int)$r->fetch_row()[0] . "\n";
    echo "Letzte 50 Fehlversuche (Zeit | IP-Hash | Benutzer-Hash):\n";
    $r = $db->query('SELECT `Zeitpunkt`, LEFT(`IP_Hash`, 8) AS i, LEFT(`User_Hash`, 8) AS u
                     FROM `Login_Versuche` WHERE `Erfolg` = 0 ORDER BY `ID` DESC LIMIT 50');
    while ($row = $r->fetch_assoc()) {
      echo $row['Zeitpunkt'] . ' | ' . $row['i'] . ' | ' . $row['u'] . "\n";
    }
  } catch (\Throwable $e) {
    error_log('logview login: ' . $e->getMessage());
    echo "Login-Versuche nicht verfügbar (Migration Login_Versuche eingespielt?).\n";
  }
  exit;
}

$file = (defined('ERROR_LOG_PATH') && ERROR_LOG_PATH !== '') ? ERROR_LOG_PATH : __DIR__ . '/php-error.log';
$n = isset($_GET['n']) ? max(10, min(2000, (int)$_GET['n'])) : 400; // 10..2000 Zeilen
if (!is_readable($file)) { http_response_code(404); exit('Logdatei nicht lesbar.'); }

function tail($filename, $lines = 200) {
  $f = fopen($filename, 'rb'); if (!$f) return '';
  $pos = -1; $buffer = ''; $linecnt = 0;
  fseek($f, 0, SEEK_END);
  $filesize = ftell($f);
  while ($linecnt < $lines && -$pos < $filesize) {
    fseek($f, $pos, SEEK_END);
    $char = fgetc($f);
    $buffer = $char . $buffer;
    if ($char === "\n") $linecnt++;
    $pos--;
  }
  fclose($f);
  return $buffer;
}

$out = tail($file, $n);

// einfache Geheimnis-Redaktion (Passwörter, Tokens grob maskieren)
$out = preg_replace('/(pass(word)?|token|secret|key)\s*=\s*["\'][^"\']+["\']/i', '$1="***"', $out);

header('Content-Type: text/plain; charset=UTF-8');
echo $out;
