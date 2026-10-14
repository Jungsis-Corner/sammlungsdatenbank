<?php
/**
 * thumb.php?f=<Bildname>&w=<Breite>[&v=<Änderungszeit>]
 * Liefert eine verkleinerte Fassung eines Sammlungsbildes (nur für angemeldete Besucher, Gäste eingeschlossen).
 *
 *  - Breite nur aus IMAGE_THUMB_WIDTHS, Name nur als relativer Pfad (keine URL), nur Bilddateien unterhalb der
 *    konfigurierten Quelle (WordPress-Uploads oder medien/); der Inhalt wird mit getimagesize() geprüft.
 *  - Zuerst eine passende WordPress-Zwischengröße (z. B. name-225x300.jpg), sonst Verkleinern mit GD.
 *  - Ergebnis liegt in cache/thumbs/ (Wegwerf-Cache, darf jederzeit gelöscht werden).
 *  - Wenn etwas nicht geht (kein GD, Cache nicht beschreibbar, Bild zu groß für den Speicher, Miniaturen ausgeschaltet),
 *    kommt das Original. Nur ungültige Eingaben ergeben einen Fehlercode (400/404/415).
 */
require_once __DIR__ . '/inc_errors.php';
require __DIR__ . '/require_login.php';
require_once __DIR__ . '/inc_image.php';
if (session_status() === PHP_SESSION_ACTIVE) { session_write_close(); }   // Sitzungssperre freigeben (viele Bilder parallel)

function thumb_fail(int $code, string $text): void {
    http_response_code($code);
    header('Content-Type: text/plain; charset=UTF-8');
    header('Cache-Control: no-store');
    echo $text;
    exit;
}

/** Datei ausliefern (mit langem Cache; die Sicherheitsheader kommen aus der .htaccess). */
function thumb_send(string $file, string $mime): void {
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($file));
    header('Cache-Control: public, max-age=31536000, immutable');
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'HEAD') { readfile($file); }
    exit;
}

/** memory_limit in Byte; 0 = unbegrenzt (PHP 8.1 hat noch kein ini_parse_quantity). */
function thumb_bytes(string $v): int {
    $v = trim($v);
    if ($v === '' || $v === '-1') return 0;
    $n = (int)$v;
    return match (strtoupper(substr($v, -1))) { 'G' => $n * 1024 ** 3, 'M' => $n * 1024 ** 2, 'K' => $n * 1024, default => $n };
}

/** Passende WordPress-Zwischengröße: gleiches Seitenverhältnis (±2 %), mindestens $w breit, schmaler als das Original. */
function thumb_wp_variant(string $real, string $rootReal, int $sw, int $sh, int $w): ?array {
    $dir  = dirname($real);
    $base = pathinfo($real, PATHINFO_FILENAME);
    $ext  = pathinfo($real, PATHINFO_EXTENSION);
    $esc  = fn(string $s) => preg_replace('~([*?\[\\\\])~', '\\\\$1', $s);
    $best = null;
    foreach (glob($dir . '/' . $esc($base) . '-*x*.' . $esc($ext)) ?: [] as $f) {
        if (!preg_match('~-(\d+)x(\d+)\.[^.]+$~', $f, $m)) continue;
        $vw = (int)$m[1]; $vh = (int)$m[2];
        if (basename($f) !== $base . '-' . $vw . 'x' . $vh . '.' . $ext) continue;     // nur genau dieses Bild, nicht name-anderes-…
        if ($vw < $w || $vw >= $sw || $vh < 1) continue;
        if (abs(($vw / $vh) - ($sw / $sh)) > 0.02 * ($sw / $sh)) continue;              // beschnittene Fassungen (z. B. 150x150) überspringen
        $r = realpath($f);
        if ($r === false || strpos($r, $rootReal . '/') !== 0) continue;
        if ($best === null || $vw < $best['w']) $best = ['path' => $r, 'w' => $vw, 'h' => $vh];
    }
    return $best;
}

/** Verkleinert $src auf die angezeigte Breite $w und schreibt nach $out. EXIF-Drehung wird beachtet. */
function thumb_resize(string $src, int $type, int $sw, int $sh, int $w, string $outType, string $out, bool $exif): bool {
    $orient = 1;
    if ($exif && $type === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
        $ex = @exif_read_data($src);
        $orient = (int)($ex['Orientation'] ?? 1);
    }
    $swap  = in_array($orient, [6, 8], true);
    $scale = $w / ($swap ? $sh : $sw);
    if ($scale >= 1) return false;                                // nie vergrößern: Original ausliefern
    $tw = max(1, (int)round($sw * $scale));
    $th = max(1, (int)round($sh * $scale));

    $img = match ($type) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($src),
        IMAGETYPE_PNG  => @imagecreatefrompng($src),
        IMAGETYPE_GIF  => @imagecreatefromgif($src),
        IMAGETYPE_WEBP => @imagecreatefromwebp($src),
        default        => false,
    };
    if (!$img) return false;
    $dst = imagecreatetruecolor($tw, $th);
    if ($outType !== 'jpg') {                                   // Transparenz erhalten
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
    }
    imagecopyresampled($dst, $img, 0, 0, 0, 0, $tw, $th, $sw, $sh);
    if ($orient === 3)      { $r = imagerotate($dst, 180, 0); }
    elseif ($orient === 6)  { $r = imagerotate($dst, -90, 0); }
    elseif ($orient === 8)  { $r = imagerotate($dst, 90, 0); }
    else                    { $r = null; }
    if ($r) { $dst = $r; }

    $ok = match ($outType) {
        'jpg'  => imagejpeg($dst, $out, (int)IMAGE_THUMB_QUALITY),
        'png'  => imagepng($dst, $out, 6),
        'webp' => imagewebp($dst, $out, (int)IMAGE_THUMB_QUALITY),
    };
    return $ok;   // GD-Objekte werden von PHP freigegeben (imagedestroy ist seit 8.0 wirkungslos, seit 8.5 veraltet)
}

/* ---------- Eingaben prüfen ---------- */
if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD'], true)) { thumb_fail(405, 'Nur GET.'); }
$f = $_GET['f'] ?? '';
$wRaw = $_GET['w'] ?? '';
if (!is_string($f) || $f === '' || preg_match('~^[a-z][a-z0-9+.-]*:~i', $f)) { thumb_fail(400, 'Ungültiger Bildname.'); }
if (!is_string($wRaw) || !ctype_digit($wRaw) || !in_array((int)$wRaw, IMAGE_THUMB_WIDTHS, true)) { thumb_fail(400, 'Ungültige Breite.'); }
$w = (int)$wRaw;
$p = image_parse($f);
if (!$p || $p['kind'] === 'external') { thumb_fail(400, 'Ungültiger Bildname.'); }
if (!isset(IMAGE_EXT[strtolower(pathinfo((string)$p['name'], PATHINFO_EXTENSION))])) { thumb_fail(415, 'Dateityp nicht unterstützt.'); }

$root = image_root($p['kind']);
if ($root === null) {                                   // Quelle nicht lesbar (z. B. WordPress auf anderem Server): Original verlinken
    header('Location: ' . image_url($f));
    exit;
}
$rootReal = rtrim((string)realpath($root), '/');
$real = realpath($root . $p['name']);
if ($real === false || $rootReal === '' || strpos($real, $rootReal . '/') !== 0 || !is_file($real)) { thumb_fail(404, 'Bild nicht gefunden.'); }
$info = @getimagesize($real);
$types = [IMAGETYPE_JPEG => 'image/jpeg', IMAGETYPE_PNG => 'image/png', IMAGETYPE_GIF => 'image/gif', IMAGETYPE_WEBP => 'image/webp'];
if (!$info || !isset($types[$info[2]]) || $info[0] < 1 || $info[1] < 1) { thumb_fail(415, 'Keine gültige Bilddatei.'); }
[$sw, $sh, $type] = $info;
$mime = $types[$type];

/* ---------- Fälle, in denen das Original ausgeliefert wird ---------- */
if (!IMAGE_THUMBS || !function_exists('imagecreatetruecolor') || $sw <= $w) {
    thumb_send($real, $mime);
}

/* ---------- Cache ---------- */
$outType = IMAGE_EXT[strtolower(pathinfo($real, PATHINFO_EXTENSION))];
$outMime = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'][$outType];
$cacheDir = __DIR__ . '/cache/thumbs/';
if (!is_dir($cacheDir)) { @mkdir($cacheDir, 0755, true); }
if (!is_dir($cacheDir) || !is_writable($cacheDir)) {
    error_log('thumb.php: Cache-Ordner nicht beschreibbar, liefere Original.');
    thumb_send($real, $mime);
}
$cacheFile = $cacheDir . sha1($p['kind'] . '|' . $p['name'] . '|' . $w . '|' . (int)@filemtime($real) . '|1') . '.' . $outType;
if (is_file($cacheFile) && filesize($cacheFile) > 0) { thumb_send($cacheFile, $outMime); }

/* ---------- Erzeugen ---------- */
$tmp = $cacheFile . '.tmp' . getmypid();
$done = false;
try {
    $srcPath = $real; $srcType = $type; $srcW = $sw; $srcH = $sh; $useExif = true;
    if ($p['kind'] === 'wp') {
        $v = thumb_wp_variant($real, $rootReal, $sw, $sh, $w);
        $vi = $v ? @getimagesize($v['path']) : false;
        if ($v && $vi && $vi[2] === $type) {
            if ($v['w'] <= (int)round($w * 1.3) && $type !== IMAGETYPE_GIF) {   // passt genau genug: Zwischengröße übernehmen
                $done = @copy($v['path'], $tmp);
            } else {                                                    // etwas größer: von der kleinen Fassung verkleinern (schnell)
                $srcPath = $v['path']; $srcW = $vi[0]; $srcH = $vi[1]; $useExif = false;
            }
        }
    }
    if (!$done) {
        // Speicherbedarf grob abschätzen (Dekodieren ca. 4 Byte je Pixel, plus Reserve), sonst Original
        $limit = thumb_bytes((string)ini_get('memory_limit'));
        $need  = (int)($srcW * $srcH * 4 * 1.4) + 8 * 1024 * 1024;
        if ($srcW * $srcH > 120_000_000 || ($limit > 0 && $need > $limit - memory_get_usage())) {
            error_log('thumb.php: Bild zu groß für den Speicher (' . $srcW . 'x' . $srcH . '), liefere Original.');
            thumb_send($real, $mime);
        }
        $done = thumb_resize($srcPath, $type, $srcW, $srcH, $w, $outType, $tmp, $useExif);
    }
} catch (Throwable $e) {
    error_log('thumb.php: ' . $e->getMessage());
    $done = false;
}
if (!$done || !is_file($tmp) || filesize($tmp) === 0) {
    @unlink($tmp);
    error_log('thumb.php: Miniatur konnte nicht erzeugt werden, liefere Original.');
    thumb_send($real, $mime);
}
@rename($tmp, $cacheFile);
thumb_send(is_file($cacheFile) ? $cacheFile : $real, is_file($cacheFile) ? $outMime : $mime);
