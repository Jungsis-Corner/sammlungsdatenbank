<?php
// logview.php (nur für Admin)
require_once __DIR__ . '/require_login.php';
if (!function_exists('is_admin') || !is_admin()) { http_response_code(403); exit('Nur für Admins.'); }

require_once __DIR__ . '/config.php';
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
