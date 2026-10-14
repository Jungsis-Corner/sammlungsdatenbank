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
         . $dim . ' viewBox="0 0 24 24" role="img" aria-label="Kein Bild" style="color:#9aa0a6;display:block;">'
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
