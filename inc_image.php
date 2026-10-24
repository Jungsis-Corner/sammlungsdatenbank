<?php
/**
 * Bildschicht: die einzige Stelle, die aus dem gespeicherten Wert (Sammlung.SammlungBild1) eine Bild-URL
 * und ein <img> baut. Alle Seiten (Liste, Detail, Bearbeiten) benutzen nur diese Funktionen.
 *
 * Gespeicherte Werte (beide Formen werden gelesen):
 *   - alt:  volle URL der WordPress-Uploads, z. B. https://host/blog/wp-content/uploads/2014/11/bild.jpg
 *           (auch mit doppeltem Schrägstrich vor "blog"); wird wie der relative Name behandelt
 *   - neu:  relativer Pfad ohne Host, z. B. 2014/11/bild.jpg oder bild.jpg
 *   - andere volle http(s)-URLs bleiben unverändert (Quelle "external", keine Prüfung der Datei)
 *
 * Konfiguration (alles optional, Standardwerte unten; Vorlage in config.example.php):
 *   IMAGE_SOURCE    'wordpress' | 'local' | 'auto' (auto = erst lokaler Ordner, sonst WordPress)
 *   IMAGE_WP_BASE   URL-Pfad (oder volle URL) der WordPress-Uploads
 *   IMAGE_WP_DIR    Dateisystem-Pfad dazu (nur zum Prüfen, ob die Datei da ist)
 *   IMAGE_LOCAL_DIR lokaler Bildordner relativ zur App (Standard medien/)
 *
 * Der gespeicherte Wert wird nie ungeprüft ausgegeben: Pfade mit "..", Backslash, Steuerzeichen, "?", "#", "%" oder ":"
 * werden abgelehnt (Ergebnis: kein Bild, Platzhalter), die Ausgabe läuft über htmlspecialchars.
 */
require_once __DIR__ . '/config.php';

if (!defined('IMAGE_SOURCE'))     define('IMAGE_SOURCE', 'wordpress');
if (!defined('IMAGE_WP_BASE'))    define('IMAGE_WP_BASE', '/blog/wp-content/uploads/');
if (!defined('IMAGE_WP_DIR'))     define('IMAGE_WP_DIR', rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/') . '/blog/wp-content/uploads/');
if (!defined('IMAGE_LOCAL_DIR'))  define('IMAGE_LOCAL_DIR', 'medien/');
if (!defined('IMAGE_APP_BASE'))   define('IMAGE_APP_BASE', '/sammlung/');
if (!defined('IMAGE_THUMB_WIDTHS')) define('IMAGE_THUMB_WIDTHS', [80, 160, 320, 640, 1200]);
if (!defined('IMAGE_THUMBS'))     define('IMAGE_THUMBS', true);          // false = überall die Originale (Notbremse)
if (!defined('IMAGE_THUMB_QUALITY')) define('IMAGE_THUMB_QUALITY', 82); // JPEG/WebP-Qualität der Miniaturen
if (!defined('IMAGE_UPLOAD_ENABLED')) define('IMAGE_UPLOAD_ENABLED', false); // true = Bilder im Bearbeiten-Formular hochladen (nur Modus local/auto)
if (!defined('IMAGE_UPLOAD_MAX_MB'))  define('IMAGE_UPLOAD_MAX_MB', 8);      // größte erlaubte Datei (zusätzlich gilt das PHP-Limit)
if (!defined('IMAGE_UPLOAD_MAX_EDGE')) define('IMAGE_UPLOAD_MAX_EDGE', 2400); // längste Kante nach dem Upload in Pixel

/** Endung (klein) -> Ausgabeformat der Miniaturen; nur diese Dateitypen werden verkleinert. */
const IMAGE_EXT = ['jpg' => 'jpg', 'jpeg' => 'jpg', 'png' => 'png', 'gif' => 'png', 'webp' => 'webp'];

/** Prüft einen relativen Bildpfad (Segmente mit /): nur sichere Zeichen, kein "..", kein versteckter Ordner. */
function image_safe_name(string $name): ?string {
    $name = trim($name);
    if ($name === '' || strlen($name) > 300) return null;
    if (preg_match('~[\x00-\x1F\x7F\\\\?#%:]~', $name)) return null;                // auch ":" (Schemata wie javascript:)
    if (!preg_match('~~u', $name)) return null;                      // gültiges UTF-8
    foreach (explode('/', $name) as $seg) {
        if ($seg === '' || $seg[0] === '.' || trim($seg) !== $seg) return null;
    }
    return $name;
}

/**
 * Zerlegt den gespeicherten Wert.
 * @return array{kind:string,name:?string,url:?string}|null  kind: wp | local | external; null = kein (gültiges) Bild
 */
function image_parse($stored): ?array {
    $s = trim((string)$stored);
    if ($s === '' || preg_match('~[\x00-\x1F\x7F]~', $s)) return null;

    if (preg_match('~^https?://~i', $s)) {
        // Alte Form: volle URL der WordPress-Uploads (Host egal) -> relativer Name
        $basePath = trim((string)parse_url((string)IMAGE_WP_BASE, PHP_URL_PATH), '/');
        if ($basePath !== '' && preg_match('~^https?://[^/]+/+' . preg_quote($basePath, '~') . '/+(.+)$~i', $s, $m)) {
            $name = image_safe_name($m[1]);
            return $name === null ? null : ['kind' => 'wp', 'name' => $name, 'url' => null];
        }
        return ['kind' => 'external', 'name' => null, 'url' => $s];
    }

    $name = image_safe_name($s);
    if ($name === null) return null;
    $kind = (IMAGE_SOURCE === 'local') ? 'local' : 'wp';
    if (IMAGE_SOURCE === 'auto' && is_file(image_local_root() . $name)) $kind = 'local';
    return ['kind' => $kind, 'name' => $name, 'url' => null];
}

/**
 * Wert für die Datenbank aus einer Eingabe (Formularfeld): eine volle URL der WordPress-Uploads wird zum relativen Pfad,
 * ein relativer Pfad bleibt, eine fremde http(s)-URL bleibt. Alles andere wird unverändert (getrimmt) gespeichert,
 * damit nichts stillschweigend verloren geht; ausgegeben wird es ohnehin nur über image_img() (dann als Platzhalter).
 */
function image_normalize_for_storage($input): string {
    $s = trim((string)$input);
    $p = image_parse($s);
    if (!$p) return $s;
    return $p['kind'] === 'external' ? (string)$p['url'] : (string)$p['name'];
}

/** Absoluter Pfad des lokalen Bildordners (mit Schrägstrich am Ende). */
function image_local_root(): string {
    return __DIR__ . '/' . trim((string)IMAGE_LOCAL_DIR, '/') . '/';
}

/** Dateisystem-Wurzel zur Quelle, oder null, wenn sie nicht lesbar ist (z. B. WordPress auf anderem Server). */
function image_root(string $kind): ?string {
    $dir = ($kind === 'local') ? image_local_root() : rtrim((string)IMAGE_WP_DIR, '/') . '/';
    return is_dir($dir) ? $dir : null;
}

/** URL-Pfad: jedes Segment einzeln kodieren (Leerzeichen, Umlaute), Schrägstriche bleiben. */
function image_encode_path(string $name): string {
    return implode('/', array_map('rawurlencode', explode('/', $name)));
}

/** Kann es für dieses Bild eine Miniatur geben? (eigene Datei, unterstützter Typ, Miniaturen eingeschaltet) */
function image_thumb_possible(?array $p): bool {
    if (!$p || $p['kind'] === 'external' || !IMAGE_THUMBS) return false;
    return isset(IMAGE_EXT[strtolower(pathinfo((string)$p['name'], PATHINFO_EXTENSION))]);
}

/**
 * URL für den gespeicherten Wert, oder '' wenn es kein Bild gibt.
 * Mit $width (nur Werte aus IMAGE_THUMB_WIDTHS) kommt die Miniatur über thumb.php, sonst das Original.
 * Der Parameter v (Änderungszeit der Datei) macht die URL bei einem ausgetauschten Bild neu.
 */
function image_url($stored, int $width = 0): string {
    $p = image_parse($stored);
    if (!$p) return '';
    if ($p['kind'] === 'external') return (string)$p['url'];
    if ($width > 0 && in_array($width, IMAGE_THUMB_WIDTHS, true) && image_thumb_possible($p)) {
        $url = IMAGE_APP_BASE . 'thumb.php?f=' . str_replace('%2F', '/', rawurlencode((string)$p['name'])) . '&w=' . $width;
        $root = image_root($p['kind']);
        $mt = $root !== null ? @filemtime($root . $p['name']) : false;
        return $url . ($mt ? '&v=' . $mt : '');
    }
    $enc = image_encode_path((string)$p['name']);
    if ($p['kind'] === 'local') {
        return IMAGE_APP_BASE . trim((string)IMAGE_LOCAL_DIR, '/') . '/' . $enc;
    }
    return rtrim((string)IMAGE_WP_BASE, '/') . '/' . $enc;
}

/** Gibt es das Bild? Externe Bilder und nicht prüfbare Quellen (kein Dateizugriff auf WordPress) gelten als vorhanden. */
function image_exists($stored): bool {
    $p = image_parse($stored);
    if (!$p) return false;
    if ($p['kind'] === 'external') return true;
    $root = image_root($p['kind']);
    if ($root === null) return $p['kind'] === 'wp';   // WordPress nicht prüfbar: annehmen, lokal: fehlt
    return is_file($root . $p['name']);
}

/** Pixelmaße [Breite, Höhe] des Originals, wenn die Datei lesbar ist (je Anfrage gemerkt), sonst null. */
function image_size($stored): ?array {
    static $cache = [];
    $key = (string)$stored;
    if (array_key_exists($key, $cache)) return $cache[$key];
    $size = null;
    $p = image_parse($stored);
    if ($p && $p['kind'] !== 'external' && ($root = image_root($p['kind'])) !== null) {
        $info = @getimagesize($root . $p['name']);
        if ($info && $info[0] > 0 && $info[1] > 0) $size = [(int)$info[0], (int)$info[1]];
    }
    return $cache[$key] = $size;
}

/** Neutraler Platzhalter (Inline-SVG, mittleres Grau, passt in hellem und dunklem Design). */
function image_placeholder(int $height = 0, string $class = ''): string {
    $dim = $height > 0 ? ' width="' . $height . '" height="' . $height . '"' : ' width="96" height="96"';
    return '<svg class="img-placeholder' . ($class !== '' ? ' ' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') : '') . '"'
         . $dim . ' viewBox="0 0 24 24" role="img" aria-label="Kein Bild" color="#9aa0a6" display="block">'
         . '<rect x="3" y="4" width="18" height="16" rx="2" fill="none" stroke="currentColor" stroke-width="1.5"/>'
         . '<circle cx="9" cy="10" r="1.6" fill="currentColor"/>'
         . '<path d="M4 18l5-5 3.5 3.5L16 13l4 5" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>'
         . '</svg>';
}

/**
 * Fertiges <img> (oder Platzhalter, wenn das Bild fehlt).
 * Optionen: id, class, height (feste Höhe in px, Breite aus dem Seitenverhältnis), lazy (Standard true),
 *           widths (Liste aus IMAGE_THUMB_WIDTHS: srcset mit Miniaturen), sizes (Bildbreite für den Browser, z. B. "(max-width: 767px) 100vw, 640px";
 *           bei fester Höhe und bekannten Maßen automatisch die Breite in px), src_width (Miniatur für src, Standard: mittlere Breite),
 *           priority ('high' für das Bild oberhalb der Falz: kein lazy, fetchpriority="high"),
 *           placeholder (Standard true; false = bei fehlendem Bild leerer String)
 */
function image_img($stored, string $alt, array $o = []): string {
    $p = image_parse($stored);
    $widths = array_values(array_filter((array)($o['widths'] ?? []), fn($w) => in_array($w, IMAGE_THUMB_WIDTHS, true)));
    sort($widths);
    $thumbs = $widths && image_thumb_possible($p);
    $srcW = (int)($o['src_width'] ?? ($widths ? $widths[intdiv(count($widths), 2)] : 0));
    $url = image_url($stored, $thumbs ? $srcW : 0);
    if ($url === '' || !image_exists($stored)) {
        return ($o['placeholder'] ?? true) ? image_placeholder((int)($o['height'] ?? 0), (string)($o['class'] ?? '')) : '';
    }
    $e = fn(string $v) => htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $alt = trim($alt) !== '' ? $alt : 'Bild';
    $attr = ' src="' . $e($url) . '" alt="' . $e($alt) . '"';

    $size = image_size($stored);
    $h = (int)($o['height'] ?? 0);
    $dispW = null;
    if ($h > 0) {
        $attr .= ' height="' . $h . '"';
        if ($size) { $dispW = max(1, (int)round($h * $size[0] / $size[1])); $attr .= ' width="' . $dispW . '"'; }
    } elseif ($size) {
        $attr .= ' width="' . $size[0] . '" height="' . $size[1] . '"';
    }
    if ($thumbs) {
        $set = [];
        foreach ($widths as $w) $set[] = $e(image_url($stored, $w)) . ' ' . $w . 'w';
        $sizes = (string)($o['sizes'] ?? ($dispW !== null ? $dispW . 'px' : '100vw'));
        $attr .= ' srcset="' . implode(', ', $set) . '" sizes="' . $e($sizes) . '"';
    }
    if (($o['priority'] ?? '') === 'high') {
        $attr .= ' fetchpriority="high"';
    } elseif ($o['lazy'] ?? true) {
        $attr .= ' loading="lazy"';
    }
    $attr .= ' decoding="async"';
    if (!empty($o['id']))    $attr .= ' id="' . $e((string)$o['id']) . '"';
    if (!empty($o['class'])) $attr .= ' class="' . $e((string)$o['class']) . '"';
    return '<img' . $attr . '>';
}


/* ---------------------------------------------------------------------------------------------
 * Upload (B5) und Auswahl vorhandener Bilder (B6) im lokalen Modus (medien/)
 * ------------------------------------------------------------------------------------------- */

/** Php-Größenangabe ("8M", "512K") in Byte; 0 = unbegrenzt. */
function image_ini_bytes(string $v): int {
    $v = trim($v);
    if ($v === '' || $v === '-1') return 0;
    $n = (int)$v;
    return match (strtoupper(substr($v, -1))) { 'G' => $n * 1024 ** 3, 'M' => $n * 1024 ** 2, 'K' => $n * 1024, default => $n };
}

/** Auswahl aus medien/ gibt es nur, wenn der lokale Ordner zur Bildquelle gehört. */
function image_local_mode(): bool {
    return IMAGE_SOURCE === 'local' || IMAGE_SOURCE === 'auto';
}

/** Upload ist eingeschaltet, der Modus passt, GD ist da und medien/ ist beschreibbar. */
function image_upload_available(): bool {
    return IMAGE_UPLOAD_ENABLED && image_local_mode() && function_exists('imagecreatefromstring')
        && is_dir(image_local_root()) && is_writable(image_local_root());
}

/** Größte Datei, die PHP wirklich annimmt (kleinster Wert aus Konfiguration, upload_max_filesize und post_max_size) in Byte. */
function image_upload_limit(): int {
    $l = [(int)IMAGE_UPLOAD_MAX_MB * 1048576];
    foreach (['upload_max_filesize', 'post_max_size'] as $k) { $b = image_ini_bytes((string)ini_get($k)); if ($b > 0) $l[] = $b; }
    return min($l);
}

/**
 * Nimmt eine hochgeladene Datei aus $_FILES an und legt sie als neu kodiertes Bild in medien/ ab.
 *  - echter Dateityp per finfo (JPEG, PNG, WebP, GIF), nicht die Endung; Pixelzahl begrenzt (Speicher)
 *  - mit GD neu gespeichert: EXIF-Drehung angewendet, längste Kante höchstens IMAGE_UPLOAD_MAX_EDGE, Metadaten (GPS …) und
 *    alles Eingebettete fallen weg; GIF wird zu PNG
 *  - Dateiname zufällig (16 Hex-Zeichen), nie vom Nutzer
 * @return array{ok:bool,name?:string,error?:string}
 */
function image_store_upload(array $f): array {
    $err = (int)($f['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($err !== UPLOAD_ERR_OK) {
        $msg = match ($err) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Die Datei ist größer als erlaubt (höchstens ' . number_format(image_upload_limit() / 1048576, 1, ',', '.') . ' MB).',
            UPLOAD_ERR_PARTIAL   => 'Die Datei kam nicht vollständig an. Bitte noch einmal versuchen.',
            UPLOAD_ERR_NO_FILE   => 'Es wurde keine Datei gewählt.',
            default              => 'Der Server konnte die Datei nicht annehmen.',
        };
        return ['ok' => false, 'error' => $msg];
    }
    $tmp = (string)($f['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) return ['ok' => false, 'error' => 'Ungültiger Upload.'];
    if (!image_upload_available()) return ['ok' => false, 'error' => 'Der Upload ist nicht eingeschaltet oder medien/ ist nicht beschreibbar.'];
    $size = (int)filesize($tmp);
    if ($size <= 0 || $size > image_upload_limit()) {
        return ['ok' => false, 'error' => 'Die Datei ist größer als erlaubt (höchstens ' . number_format(image_upload_limit() / 1048576, 1, ',', '.') . ' MB).'];
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
    $erlaubt = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'png'];
    if (!isset($erlaubt[$mime])) return ['ok' => false, 'error' => 'Nur JPEG-, PNG-, WebP- und GIF-Bilder sind erlaubt.'];
    $ext = $erlaubt[$mime];
    $info = @getimagesize($tmp);
    if (!$info || $info[0] < 1 || $info[1] < 1) return ['ok' => false, 'error' => 'Die Datei ist kein lesbares Bild.'];
    [$w, $h] = [(int)$info[0], (int)$info[1]];
    $limit = image_ini_bytes((string)ini_get('memory_limit'));
    if ($w * $h > 80000000 || ($limit > 0 && $w * $h * 5 > $limit * 0.8)) {
        return ['ok' => false, 'error' => 'Das Bild hat zu viele Pixel für den Server. Bitte verkleinert hochladen.'];
    }
    if ($mime === 'image/webp' && !function_exists('imagewebp')) return ['ok' => false, 'error' => 'WebP wird auf diesem Server nicht unterstützt.'];

    $img = @imagecreatefromstring((string)file_get_contents($tmp));
    if (!$img) return ['ok' => false, 'error' => 'Das Bild konnte nicht gelesen werden.'];

    // EXIF-Drehung (Handyfotos) anwenden
    if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
        $ex = @exif_read_data($tmp);
        $o = (int)($ex['Orientation'] ?? 1);
        $rot = [3 => 180, 6 => -90, 8 => 90][$o] ?? 0;
        if (in_array($o, [2, 4, 5, 7], true)) imageflip($img, $o === 4 ? IMG_FLIP_VERTICAL : IMG_FLIP_HORIZONTAL);
        if ($o === 5 || $o === 7) $rot = $o === 5 ? -90 : 90;
        if ($rot !== 0) { $r = imagerotate($img, $rot, 0); if ($r) { imagedestroy($img); $img = $r; } }
    }
    // Größe begrenzen
    $w = imagesx($img); $h = imagesy($img);
    $max = max(200, (int)IMAGE_UPLOAD_MAX_EDGE);
    if (max($w, $h) > $max) {
        $s = $max / max($w, $h);
        $nw = max(1, (int)round($w * $s)); $nh = max(1, (int)round($h * $s));
        $n = imagecreatetruecolor($nw, $nh);
        if ($ext !== 'jpg') { imagealphablending($n, false); imagesavealpha($n, true); imagefill($n, 0, 0, imagecolorallocatealpha($n, 0, 0, 0, 127)); }
        imagecopyresampled($n, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagedestroy($img); $img = $n;
    }
    if ($ext !== 'jpg') imagesavealpha($img, true);

    $dir = image_local_root();
    $name = bin2hex(random_bytes(8)) . '.' . $ext;
    $ziel = $dir . $name;
    $ok = match ($ext) {
        'jpg'  => imagejpeg($img, $ziel, 88),
        'png'  => imagepng($img, $ziel, 6),
        'webp' => imagewebp($img, $ziel, 85),
    };
    imagedestroy($img);
    if (!$ok || !is_file($ziel)) { @unlink($ziel); return ['ok' => false, 'error' => 'Das Bild konnte nicht gespeichert werden.']; }
    @chmod($ziel, 0644);
    return ['ok' => true, 'name' => $name];
}

/**
 * Bilddateien in medien/ (auch Unterordner), neueste zuerst, optional nach Namensteil gefiltert.
 * @return array{total:int,items:array<int,array{name:string,mtime:int}>}
 */
function image_local_list(string $q = '', int $offset = 0, int $limit = 24): array {
    $root = image_local_root();
    $alle = [];
    if (!is_dir($root)) return ['total' => 0, 'items' => []];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    $q = mb_strtolower(trim($q));
    foreach ($it as $fi) {
        if (!$fi->isFile() || !isset(IMAGE_EXT[strtolower($fi->getExtension())])) continue;
        $rel = ltrim(str_replace('\\', '/', substr($fi->getPathname(), strlen($root))), '/');
        if (image_safe_name($rel) === null) continue;
        if ($q !== '' && mb_strpos(mb_strtolower($rel), $q) === false) continue;
        $alle[] = ['name' => $rel, 'mtime' => (int)$fi->getMTime()];
    }
    usort($alle, fn($a, $b) => [$b['mtime'], $a['name']] <=> [$a['mtime'], $b['name']]);
    return ['total' => count($alle), 'items' => array_slice($alle, max(0, $offset), max(1, $limit))];
}
