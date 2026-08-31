<?php
// ==== Volle Fehlerausgabe für Debugging (bei Bedarf aktiv lassen) ====
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
register_shutdown_function(function () {
    $e = error_get_last();
    if (!$e) return;
    echo "<pre style='background:#fee;border:1px solid #f00;padding:10px;white-space:pre-wrap'>";
    echo "🚨 PHP‐Fatal in edit.php on line {$e['line']}:\n";
    echo htmlspecialchars($e['message'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    echo "</pre>";
});

// ---- (Optional) PHP-Zeitzone setzen ----
date_default_timezone_set('Europe/Berlin');

// ---- Login prüfen & nur Admin zulassen ----
require_once __DIR__ . '/require_login.php';
if (!is_admin()) { http_response_code(403); exit('Nur für Admins.'); }

// --- CSRF-Token initialisieren ---
if (empty($_SESSION['csrf'])) {
  $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
$is_guest = false;                        // rein informativ für Anzeige/Buttons

// ---- DB-Verbindung ----
require_once __DIR__ . '/config.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$conn->set_charset('utf8mb4');
if ($conn->connect_errno) {
    http_response_code(500);
    exit('DB-Verbindungsfehler: '.$conn->connect_error);
}
// (Optional) MySQL-Session-Zeitzone setzen – wenn dein Hoster es unterstützt
@$conn->query("SET time_zone = 'Europe/Berlin'");

function igdb_getenv_value(string $key): ?string {
  // 1) getenv
  $v = getenv($key);
  if ($v !== false && $v !== '') return $v;

  // 2) $_SERVER / $_ENV
  if (!empty($_SERVER[$key]))        return (string)$_SERVER[$key];
  if (!empty($_ENV[$key]))           return (string)$_ENV[$key];

  // 3) manche Hoster reichen es als REDIRECT_* durch
  $rk = 'REDIRECT_'.$key;
  if (!empty($_SERVER[$rk]))         return (string)$_SERVER[$rk];
  if (!empty($_ENV[$rk]))            return (string)$_ENV[$rk];

  // 4) Fallback: Konstante aus config.php
  $const = $key.'_CONST';
  if (defined($const))               return constant($const);

  return null;
}

// --- IGDB Diagnose gesammelt hier rein ---
$IGDB_DIAG = [];

// Nach DB-Verbindung einfügen:
function igdb_ids_by_kategorie_id(int $katId): array {
  // <- HIER deine Zuordnung pflegen:
  static $MAP = [
    13  => 61,  // Atari Lynx Spiele
    24  => 16,  // Commodore Amiga Spiele
    54  => 26,  // Sinclair Spectrum Spiele
    48  => 25,  // Amstrad/Schneider CPC Spiele
    22  => 15,  // Commodore 64 Spiele
    51  => 29,  // Sega Mega Drive Spiele
    9   => 63,  // Atari ST Spiele
    111 => 309, // Evercade
    73  => 6,   // PC (Windows)
    199 => 62,  // Atari Jaguar
  ];
  if (!isset($MAP[$katId])) return [];
  $v = $MAP[$katId];
  return is_array($v) ? $v : [$v];
}

/* ===================== IGDB-Helfer (NEU) ===================== */
function igdb_token(): string {
    global $IGDB_DIAG;
    $cache = sys_get_temp_dir().'/igdb_token.json';
    if (is_file($cache)) {
        $c = json_decode(@file_get_contents($cache), true);
        if (!empty($c['access_token']) && time() < (($c['expires_at'] ?? 0) - 300)) {
            return $c['access_token'];
        }
    }
    $clientId = igdb_getenv_value('TWITCH_CLIENT_ID');
    $secret   = igdb_getenv_value('TWITCH_CLIENT_SECRET');
    if (!$clientId || !$secret) {
        $IGDB_DIAG[] = 'TOKEN: fehlende ENV Variablen (TWITCH_CLIENT_ID / TWITCH_CLIENT_SECRET).';
        return '';
    }

    $ch = curl_init('https://id.twitch.tv/oauth2/token');
    curl_setopt_array($ch, [
        CURLOPT_POST            => true,
        CURLOPT_POSTFIELDS      => http_build_query([
            'client_id'     => $clientId,
            'client_secret' => $secret,
            'grant_type'    => 'client_credentials'
        ]),
        CURLOPT_RETURNTRANSFER  => true,
        CURLOPT_TIMEOUT         => 15
    ]);
    $res = curl_exec($ch);
    if ($res === false) {
        $IGDB_DIAG[] = 'TOKEN: cURL-Fehler: ' . curl_error($ch);
        return '';
    }
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code !== 200) {
        $IGDB_DIAG[] = 'TOKEN: HTTP ' . $code . ' Antwort: ' . substr($res, 0, 300);
        return '';
    }

    $j = json_decode($res, true);
    $tok = $j['access_token'] ?? '';
    $exp = (int)($j['expires_in'] ?? 0);
    if ($tok && $exp) {
        @file_put_contents($cache, json_encode([
            'access_token' => $tok,
            'expires_at'   => time() + $exp
        ]));
    } else {
        $IGDB_DIAG[] = 'TOKEN: Unerwartete Antwort: ' . substr($res, 0, 200);
    }
    return $tok;
}

/** IGDB-Games-Suche → erster (ggf. exakt passender) Treffer mit summary */
function igdb_summary(string $title, array $platformIds = []): ?array {
  global $IGDB_DIAG;
  $token    = igdb_token();
  $clientId = igdb_getenv_value('TWITCH_CLIENT_ID');
  if (!$clientId) { $IGDB_DIAG[] = 'SUMMARY: Client-ID leer (Env/Config nicht gefunden).'; }
  if (!$token || !$clientId) return null;

  // Klammerzusätze am Ende entfernen, z.B. "Cool Spot (Mega Drive)"
  $title = preg_replace('/\s*\([^)]*\)\s*$/u', '', $title);
  $titleEsc = addslashes($title);

  $wherePlatforms = '';
  if (!empty($platformIds)) {
    $wherePlatforms = ' & platforms = ('.implode(',', array_map('intval',$platformIds)).')';
  }

  $call = function(string $query, string $label) use ($token,$clientId,&$IGDB_DIAG) {
    $ch = curl_init('https://api.igdb.com/v4/games');
    curl_setopt_array($ch, [
      CURLOPT_HTTPHEADER     => [
        'Client-ID: '.$clientId,
        'Authorization: Bearer '.$token,
        'Accept: application/json',
        'Content-Type: text/plain',
      ],
      CURLOPT_POST           => true,
      CURLOPT_POSTFIELDS     => $query,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_TIMEOUT        => 20
    ]);
    $res  = curl_exec($ch);
    if ($res === false) {
      $IGDB_DIAG[] = $label.': cURL-Fehler: ' . curl_error($ch);
      return null;
    }
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code !== 200) {
      $IGDB_DIAG[] = $label.': HTTP '.$code.' | Antwort: '.substr($res,0,300);
      return null;
    }

    $data = json_decode($res, true);
    if (!is_array($data)) {
      $IGDB_DIAG[] = $label.': JSON-Decode fehlgeschlagen. Raw: '.substr($res,0,300);
      return null;
    }
    if (!$data) {
      $IGDB_DIAG[] = $label.': Leeres Ergebnis.';
      return null;
    }
    return $data;
  };

  // 1) Exakter Name + sinnvolle Kategorien
  $q1 = <<<Q
fields name,summary,storyline,platforms,slug,first_release_date,category;
where name = "$titleEsc" & category = (0,11,13,14){$wherePlatforms};
limit 5;
Q;
  if ($d = $call($q1, 'Q1 exact')) {
    foreach ($d as $g) {
      $desc = $g['summary'] ?? '';
      if (!$desc) $desc = $g['storyline'] ?? '';
      if ($desc) {
        return ['name'=>$g['name']??$title, 'desc'=>$desc, 'slug'=>$g['slug']??null];
      }
    }
    $g = $d[0];
    return ['name'=>$g['name']??$title, 'desc'=>null, 'slug'=>$g['slug']??null];
  }

  // 2) Fallback: Suche + Heuristik
  $q2 = <<<Q
fields name,summary,storyline,platforms,slug,first_release_date,category;
search "$titleEsc";
limit 25;
Q;
  $d = $call($q2, 'Q2 search');
  if (!$d) return null;

  $norm = function(string $s): string {
    $s = mb_strtolower($s, 'UTF-8');
    $s = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $s);
    $s = preg_replace('/\s+/u', ' ', trim($s));
    return $s;
  };
  $needle = $norm($title);

  usort($d, function($a,$b) use($title,$needle,$norm){
    $aName = $a['name'] ?? ''; $bName = $b['name'] ?? '';
    $aExact = strcasecmp($aName, $title) === 0 ? 0 : 1;
    $bExact = strcasecmp($bName, $title) === 0 ? 0 : 1;
    $aNorm  = $norm($aName) === $needle ? 0 : 1;
    $bNorm  = $norm($bName) === $needle ? 0 : 1;
    $catRank = fn($g)=> (($g['category'] ?? 999) === 0) ? 0 : 1;
    $aDate = $a['first_release_date'] ?? PHP_INT_MAX;
    $bDate = $b['first_release_date'] ?? PHP_INT_MAX;
    return [$aExact,$aNorm,$catRank($a),$aDate] <=> [$bExact,$bNorm,$catRank($b),$bDate];
  });

  // Weicher Plattformfilter
  if (!empty($platformIds)) {
    foreach ($d as $g) {
      if (array_intersect($platformIds, $g['platforms'] ?? [])) {
        $desc = $g['summary'] ?? '';
        if (!$desc) $desc = $g['storyline'] ?? '';
        return ['name'=>$g['name']??$title, 'desc'=>$desc ?: null, 'slug'=>$g['slug']??null];
      }
    }
  }

  $g = $d[0];
  $desc = $g['summary'] ?? '';
  if (!$desc) $desc = $g['storyline'] ?? '';
  return ['name'=>$g['name']??$title, 'desc'=>$desc ?: null, 'slug'=>$g['slug']??null];
}

// **Übersetzung via DeepL/LibreTranslate (mit Cache)**
function translate_to_de(string $text): string {
  global $IGDB_DIAG;
  $text = trim($text);
  if ($text === '') return $text;

  $hash  = sha1('en2de|'.$text);
  $cache = sys_get_temp_dir()."/igdb_tr_{$hash}.txt";
  if (is_file($cache)) {
    $cached = @file_get_contents($cache);
    if ($cached !== false && $cached !== '') return $cached;
  }

  $deeplKey = igdb_getenv_value('DEEPL_API_KEY');
  if ($deeplKey) {
    $deeplHost = (str_contains($deeplKey, ':fx') || str_contains($deeplKey, ':fp'))
               ? 'https://api-free.deepl.com/v2/translate'
               : 'https://api.deepl.com/v2/translate';
    $post = http_build_query([
      'text'                => $text,
      'target_lang'         => 'DE',
      'source_lang'         => 'EN',
      'preserve_formatting' => '1',
      'split_sentences'     => '1',
      'formality'           => 'prefer_more',
    ]);
    $ch = curl_init($deeplHost);
    curl_setopt_array($ch, [
      CURLOPT_POST           => true,
      CURLOPT_HTTPHEADER     => [
        'Authorization: DeepL-Auth-Key ' . $deeplKey,
      ],
      CURLOPT_POSTFIELDS     => $post,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_TIMEOUT        => 20,
    ]);
    $res  = curl_exec($ch);
    if ($res !== false && curl_getinfo($ch, CURLINFO_HTTP_CODE) === 200) {
      $j = json_decode($res, true);
      $out = $j['translations'][0]['text'] ?? '';
      if ($out !== '') { @file_put_contents($cache, $out); curl_close($ch); return $out; }
    } else {
      $IGDB_DIAG[] = 'DEEPL: HTTP '.curl_getinfo($ch, CURLINFO_HTTP_CODE).' | '.substr((string)$res,0,200);
    }
    curl_close($ch);
  } else {
    $IGDB_DIAG[] = 'DEEPL: kein DEEPL_API_KEY gesetzt.';
  }

  $ltUrl = igdb_getenv_value('LT_API_URL');
  if ($ltUrl) {
    $payload = ['q'=>$text, 'source'=>'en', 'target'=>'de', 'format'=>'text'];
    $ltKey = igdb_getenv_value('LT_API_KEY'); if ($ltKey) $payload['api_key'] = $ltKey;
    $ch = curl_init($ltUrl);
    curl_setopt_array($ch, [
      CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
      CURLOPT_POST           => true,
      CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_TIMEOUT        => 20,
    ]);
    $res = curl_exec($ch);
    if ($res !== false && curl_getinfo($ch, CURLINFO_HTTP_CODE) === 200) {
      $j = json_decode($res, true);
      $out = $j['translatedText'] ?? '';
      if ($out !== '') { @file_put_contents($cache, $out); curl_close($ch); return $out; }
    } else {
      $IGDB_DIAG[] = 'LT: HTTP '.curl_getinfo($ch, CURLINFO_HTTP_CODE).' | '.substr((string)$res,0,200);
    }
    curl_close($ch);
  } else {
    $IGDB_DIAG[] = 'LT: kein LT_API_URL gesetzt (Fallback übersprungen).';
  }
  return $text;
}

// Plattformen-Heuristik
function platform_ids_for_row(array $row): array {
  $katId = (int)($row['Kategorie'] ?? 0);
  if ($katId > 0) return igdb_ids_by_kategorie_id($katId);
  return [];
}

/* =================== /IGDB-Helfer (NEU) =================== */

// Hilfsfunktion für Dropdowns
function get_options($table, $field, $selected='') {
    global $conn;
    $opts = "<option value=''>— unbekannt —</option>\n";
    $res  = $conn->query("SELECT ID, `$field` FROM `$table` ORDER BY `$field`");
    while ($r = $res->fetch_assoc()) {
        $sel = ($r['ID'] == $selected) ? ' selected' : '';
        $opts .= "<option value=\"{$r['ID']}\"{$sel}>"
               . htmlspecialchars($r[$field] ?? '') . "</option>\n";
    }
    return $opts;
}

// GET-Parameter
$id             = intval($_GET['id']        ?? 0);
$page           = intval($_GET['page']      ?? 1);
$sort           = $_GET['sort']             ?? 'Bezeichnung';
$dir            = strtolower($_GET['dir']   ?? 'asc');          // NEU: Richtung übernehmen
if (!in_array($dir, ['asc','desc'], true)) { $dir = 'asc'; }

$filter         = $_GET['filter']           ?? '';
$oh             = $_GET['oh']               ?? '';
$material       = $_GET['material']         ?? '';
$q              = $_GET['q']                ?? '';
$hersteller_id  = intval($_GET['hersteller'] ?? 0);
$publisher_id   = intval($_GET['publisher']  ?? 0);
$verkaeufer_id  = intval($_GET['verkaeufer'] ?? 0);
$standort_id    = intval($_GET['standort']   ?? 0);
$backUrl        = $_GET['back']             ?? null;

// damit die Nav-Buttons nicht undefiniert sind, auch bei $id==0
$prev_id = null;
$next_id = null;

// Erlaube id=0 für „Neuer Eintrag“
if ($id < 0) die('Ungültige ID.');

// Basis-Parameter für Prev/Next & Zurück-Link
$params = ['page'=>$page,'sort'=>$sort,'dir'=>$dir]; // NEU: dir aufnehmen
if ($filter        !== '') $params['filter']     = $filter;
if ($oh            !== '') $params['oh']         = $oh;
if ($material      !== '') $params['material']   = $material;
if ($q             !== '') $params['q']          = $q;
if ($hersteller_id )       $params['hersteller'] = $hersteller_id;
if ($publisher_id)         $params['publisher']  = $publisher_id;
if ($verkaeufer_id)        $params['verkaeufer'] = $verkaeufer_id;
if ($standort_id)          $params['standort']   = $standort_id;
$listUrl = 'index.php?' . http_build_query($params);
$viewUrl = 'view.php?' . http_build_query(array_merge($params, ['id'=>$id]));
// --- Navi-Variablen sicher initialisieren (auch für id==0) ---
$prev_id = $next_id = $first_id = $last_id = null;

// Basis-HREF (self) – funktioniert auch bei id=0
$selfHref  = 'edit.php?' . http_build_query(array_merge($params, ['id'=>$id]));
$prevHref  = $selfHref;
$nextHref  = $selfHref;
$firstHref = $selfHref;
$lastHref  = $selfHref;

// Spalten & Typen für das Formular (inkl. Anzahl)
// >>> WICHTIG: KEINE Zeitstempel-Felder hier! <<<
$inputs = [
  'Bezeichnung'       => 'text',
  'Kategorie'         => 'select',
  'Jahr'              => 'text',
  'Hersteller'        => 'select',
  'Publisher'         => 'select',
  'Seriennummer'      => 'text',
  'Anzahl'            => 'number',
  'Original/Homebrew' => 'select',
  'Zustand'           => 'select',
  'Verpackung Status' => 'select',
  'Verpackung'        => 'select',
  'Datenträger Status' => 'select',
  'Datentraeger'      => 'select',
  'Anleitung Status'  => 'select',
  'Sonstiges'         => 'checkbox',
  'Sonstiges Beschreibung' => 'text',
  'Material'          => 'select',
  'Standort'          => 'select',
  'Zum Verkauf'       => 'checkbox',
  'VerbautIn'         => 'select',
  'Box'               => 'text',
  'ISBN'              => 'text',
  'Barcode'           => 'text',
  'Einkaufsdatum'     => 'date',
  'Verkäufer'         => 'select',
  'Einkaufspreis'     => 'text',
  'Wert'              => 'text',
  'Getestet am'       => 'date',
  'Getestet Status'   => 'select',
  'Link zum Blog'     => 'text',
  'Link zu YouTube'   => 'text',  
  'Beschreibung'      => 'textarea',
  'SammlungBild1'     => 'text'
];

/* ============== Speichern-Logik (Update, Insert, Duplikat) ============== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // --- CSRF-Check ---
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(400);
        exit('Ungültiges Formular (CSRF).');
    }

    // Welche Felder hat dein Formular? (wie oben in $inputs definiert)
    $inputs = [
      'Bezeichnung'        => 'text',
      'Jahr'               => 'text',
      'Kategorie'          => 'select',
      'Hersteller'         => 'select',
      'Publisher'          => 'select',
      'Seriennummer'       => 'text',
      'Anzahl'             => 'number',
      'Original/Homebrew'  => 'select',   // <-- wird unten auf "Original" voreingestellt
      'Zustand'            => 'select',
      'Verpackung Status'  => 'select',
      'Verpackung'         => 'select',
      'Datenträger Status' => 'select',
      'Datentraeger'       => 'select',
      'Anleitung Status'   => 'select',
      'Sonstiges'          => 'checkbox',
      'Sonstiges Beschreibung' => 'text',
      'Material'           => 'select',
      'Standort'           => 'select',
      'Zum Verkauf'        => 'checkbox',
      'VerbautIn'          => 'select',
      'Box'                => 'text',
      'ISBN'               => 'text',
      'Barcode'            => 'text',
      'Einkaufsdatum'      => 'date',
      'Verkäufer'          => 'select',
      'Einkaufspreis'      => 'text',
      'Wert'               => 'text',
      'Getestet am'        => 'date',
      'Getestet Status'    => 'select',
      'Link zum Blog'      => 'text',
	  'Link zu YouTube'   => 'text',  
      'Beschreibung'       => 'textarea',
      'SammlungBild1'      => 'text',
    ];

    // Selects, die numerische IDs speichern (FK) -> als int behandeln
    $selectIntCols = [
      'Hersteller','Publisher','Kategorie','Zustand','Verpackung',
      'Datentraeger','Material','Standort','Verkäufer','VerbautIn'
    ];

    // Datumsfelder, die in YYYY-MM-DD gespeichert werden
    $dateCols = ['Einkaufsdatum', 'Getestet am'];

    // Formularwerte einsammeln
    $vals   = [];
    $types  = '';   // für bind_param
    foreach ($inputs as $col => $kind) {
        $name = str_replace(' ', '_', $col); // HTML-Name wie im Formular

        if ($kind === 'checkbox') {
            // Checkbox => "1" wenn gesetzt, sonst leer
            $vals[$col] = isset($_POST[$name]) ? '1' : '';
            $types     .= 's';
            continue;
        }

        if ($kind === 'number') {
            $vals[$col] = (int)($_POST[$name] ?? 0);
            $types     .= 'i';
            continue;
        }

        if ($kind === 'date') {
            $raw = trim((string)($_POST[$name] ?? ''));
            // Akzeptiere dd.mm.yyyy oder yyyy-mm-dd, speichere als yyyy-mm-dd
            if ($raw === '') {
                $vals[$col] = '';
            } elseif (preg_match('~^\d{2}\.\d{2}\.\d{4}$~', $raw)) {
                [$d,$m,$y] = explode('.', $raw);
                $vals[$col] = sprintf('%04d-%02d-%02d', (int)$y, (int)$m, (int)$d);
            } else {
                // versuche strtotime-Fallback
                $ts = strtotime($raw);
                $vals[$col] = $ts ? date('Y-m-d', $ts) : '';
            }
            $types .= 's';
            continue;
        }

        if ($kind === 'select') {
            if ($col === 'Original/Homebrew') {
                // Dropdown mit „Original | Homebrew“, Default = Original
                $v = trim((string)($_POST[$name] ?? ''));
                $v = ($v === 'Homebrew') ? 'Homebrew' : 'Original';
                $vals[$col] = $v;
                $types     .= 's';
            } elseif (in_array($col, $selectIntCols, true)) {
                // klassische FK-Selects -> als int
                $vals[$col] = (int)($_POST[$name] ?? 0);
                $types     .= 'i';
            } else {
                // übrige selects als string
                $vals[$col] = (string)($_POST[$name] ?? '');
                $types     .= 's';
            }
            continue;
        }

        // text/textarea oder alles andere als String
        $vals[$col] = (string)($_POST[$name] ?? '');
        $types     .= 's';
    }

    // Aktions-Typ (optional)
    $postAction = $_POST['post_action'] ?? 'stay';    // 'stay' | 'next'
    $action     = $_POST['action']      ?? 'save';    // 'save' | 'dup' | 'dup_keepcost'

    // Duplizieren?
    if ($action === 'dup' || $action === 'dup_keepcost') {
        // Titel duplizieren und kennzeichnen
        $title = (string)($vals['Bezeichnung'] ?? '');
        if ($title !== '') {
            if (preg_match('/\s\(Kopie(?:\s(\d+))?\)$/u', $title, $m)) {
                $n = isset($m[1]) ? ((int)$m[1] + 1) : 2;
                $title = preg_replace('/\s\(Kopie(?:\s\d+)?\)$/u', ' (Kopie ' . $n . ')', $title);
            } else {
                $title .= ' (Kopie)';
            }
            $vals['Bezeichnung'] = $title;
        }
        // Bild leeren
        if (array_key_exists('SammlungBild1', $vals)) $vals['SammlungBild1'] = '';
        // Preis/Datum/Wert ggf. leeren
        if ($action === 'dup') {
            foreach (['Einkaufsdatum','Einkaufspreis','Wert'] as $c) {
                if (array_key_exists($c, $vals)) $vals[$c] = '';
            }
        }
        // Testdaten werden bei JEDEM Duplikat zurückgesetzt (auch bei "dup_keepcost"),
        // da ein neues Duplikat noch nicht getestet wurde.
        foreach (['Getestet am','Getestet Status'] as $c) {
            if (array_key_exists($c, $vals)) $vals[$c] = '';
        }
        // "Zum Verkauf" ebenfalls zurücksetzen - das Duplikat ist noch nicht
        // bewusst als Verkaufsexemplar markiert worden.
        if (array_key_exists('Zum Verkauf', $vals)) $vals['Zum Verkauf'] = '';

        // INSERT
        $cols = array_map(fn($c) => "`$c`", array_keys($vals));
        $ph   = array_fill(0, count($vals), '?');
        $sql  = 'INSERT INTO `Sammlung` ('.implode(',', $cols).') VALUES ('.implode(',', $ph).')';
        $stmt = $conn->prepare($sql);

        // Typen neu aufbauen entsprechend $inputs (gleiches Muster wie oben)
        $bindTypes = '';
        $bindVals  = [];
        foreach ($inputs as $c => $kind) {
            if ($kind === 'number' || in_array($c, $selectIntCols, true)) $bindTypes .= 'i';
            else $bindTypes .= 's';
            $bindVals[] = $vals[$c];
        }
        $stmt->bind_param($bindTypes, ...$bindVals);
        $stmt->execute();
        $newId = (int)$conn->insert_id;

        // Redirect zurück in edit (mit Kontext)
        $keep = [];
        foreach (['page','sort','dir','filter','oh','material','q','hersteller','publisher','verkaeufer','standort'] as $k) {
            if (isset($_POST[$k]) && $_POST[$k] !== '') $keep[$k] = $_POST[$k];
        }
        $keep['id']   = $newId;
        $keep['from'] = 'dup';
        header('Location: edit.php?'.http_build_query($keep));
        exit;
    }

    // Normales Speichern: INSERT (id==0) oder UPDATE (id>0)
    if ($id === 0) {
        $cols = array_map(fn($c) => "`$c`", array_keys($vals));
        $ph   = array_fill(0, count($vals), '?');
        $stmt = $conn->prepare('INSERT INTO `Sammlung` ('.implode(',', $cols).') VALUES ('.implode(',', $ph).')');

        $bindTypes = '';
        $bindVals  = [];
        foreach ($inputs as $c => $kind) {
            if ($kind === 'number' || in_array($c, $selectIntCols, true)) $bindTypes .= 'i';
            else $bindTypes .= 's';
            $bindVals[] = $vals[$c];
        }
        $stmt->bind_param($bindTypes, ...$bindVals);
        $stmt->execute();
        $id = (int)$conn->insert_id;

    } else {
        $sets = array_map(fn($c) => "`$c` = ?", array_keys($vals));
        $stmt = $conn->prepare('UPDATE `Sammlung` SET '.implode(', ', $sets).' WHERE `ID` = ?');

        $bindTypes = '';
        $bindVals  = [];
        foreach ($inputs as $c => $kind) {
            if ($kind === 'number' || in_array($c, $selectIntCols, true)) $bindTypes .= 'i';
            else $bindTypes .= 's';
            $bindVals[] = $vals[$c];
        }
        $bindTypes .= 'i';
        $bindVals[]  = $id;

        $stmt->bind_param($bindTypes, ...$bindVals);
        $stmt->execute();
    }

    // Redirect-Ziel bestimmen
    $baseParams = [];
    foreach (['page','sort','dir','filter','oh','material','q','hersteller','publisher','verkaeufer','standort'] as $k) {
        if (isset($_POST[$k]) && $_POST[$k] !== '') $baseParams[$k] = $_POST[$k];
    }

    // „Speichern & Nächster“
    if ($postAction === 'next') {
        $nid = (int)($_POST['next_id'] ?? 0);
        if ($nid > 0 && $nid !== $id) {
            header('Location: edit.php?'.http_build_query(array_merge($baseParams, ['id'=>$nid])));
            exit;
        }
    }

    // Default: auf aktuellen Datensatz bleiben
    header('Location: edit.php?'.http_build_query(array_merge($baseParams, ['id'=>$id])));
    exit;
}


/* ======== Datensatz laden & Prev/Next ermitteln ======== */
$data = [];
if ($id > 0) {
    $data = $conn->query("SELECT * FROM `Sammlung` WHERE ID=$id")->fetch_assoc();
} elseif ($id === 0) {
    // Vorbefüllung für einen neuen Eintrag, z.B. aus einkauf.php übernommen
    $pfMap = [
        'pf_Bezeichnung'   => 'Bezeichnung',
        'pf_Kategorie'     => 'Kategorie',
        'pf_Verkaeufer'    => 'Verkäufer',
        'pf_Wert'          => 'Wert',
        'pf_Einkaufsdatum' => 'Einkaufsdatum',
        'pf_Anzahl'        => 'Anzahl',
    ];
    foreach ($pfMap as $getKey => $col) {
        if (isset($_GET[$getKey]) && $_GET[$getKey] !== '') {
            $data[$col] = $_GET[$getKey];
        }
    }
}
if ($id > 0) {
    // Mapping für ORDER BY — analog index.php erweitert
    $sort_map = [
      'Bezeichnung'         => 'S.Bezeichnung',
      'Jahr'                => 'S.Jahr',
      'Kategorie'           => 'K.Kategorie',
      'Original/Homebrew'   => 'S.`Original/Homebrew`',
      'Material'            => 'M.Material',
      'Zustand'             => 'Z.Zustand',
      'Verpackung'          => 'VP.Verpackung',
      'Anzahl'              => 'S.Anzahl',
      'Standort'            => 'S.Standort',
      'Box'                 => 'S.Box',
      'Einkaufsdatum'       => 'S.Einkaufsdatum',
      'Wert'                => 'S.Wert',
	  'Link zu YouTube' => 'S.`Link zu YouTube`',
    ];

    $base = $sort_map[$sort] ?? 'S.Bezeichnung';
    $order_by_core = $base . ' ' . strtoupper($dir);

    // Sonderfälle wie in index.php:
    if ($sort === 'Einkaufsdatum') {
        // NULL ans Ende
        $order_by_core = 'S.Einkaufsdatum IS NULL, ' . $order_by_core;
    }
    if ($sort === 'Wert') {
        // numerische Sortierung + NULL ans Ende
        $order_by_core = 'S.Wert IS NULL, (S.Wert+0) ' . strtoupper($dir);
    }

    // stabile Zweitsortierung
    $order_by = $order_by_core . ', S.Bezeichnung ASC';

    // WHERE-Klausel
    $where = [];
    if ($filter==='spiel')              $where[] = "K.Kategorie LIKE '%Spiel%'";
    elseif ($filter==='spiel_original') $where[] = "K.Kategorie LIKE '%Spiel%' AND S.`Original/Homebrew`='Original'";
    elseif ($filter==='spiel_homebrew') $where[] = "K.Kategorie LIKE '%Spiel%' AND S.`Original/Homebrew`='Homebrew'";
    elseif (is_numeric($filter))        $where[] = "S.Kategorie=" . intval($filter);
    if     ($oh==='Original')           $where[] = "S.`Original/Homebrew`='Original'";
    elseif ($oh==='Homebrew')           $where[] = "S.`Original/Homebrew`='Homebrew'";
    if ($material!=='')                 $where[] = "M.Material LIKE '" . $conn->real_escape_string($material) . "%'";
    if ($q!=='')                        $where[] = "S.Bezeichnung LIKE '%" . $conn->real_escape_string($q) . "%'";
    if ($hersteller_id)                 $where[] = "S.Hersteller=$hersteller_id";
    if ($publisher_id)                  $where[] = "S.Publisher=$publisher_id";
    if ($verkaeufer_id)                 $where[] = "S.Verkäufer=$verkaeufer_id";
    if ($standort_id)                   $where[] = "S.Standort=$standort_id";
    $where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    // Prev/Next/First/Last IDs holen – gleiche JOINs wie index.php
$from_join = "
  FROM Sammlung S
  LEFT JOIN Kategorie   K  ON S.Kategorie   = K.ID
  LEFT JOIN Material    M  ON S.Material    = M.ID
  LEFT JOIN Zustand     Z  ON S.Zustand     = Z.ID
  LEFT JOIN Verpackung  VP ON S.Verpackung  = VP.ID
  LEFT JOIN Standort    ST ON S.Standort    = ST.ID
";

$idres = $conn->query("
  SELECT S.ID
  $from_join
  $where_sql
  ORDER BY $order_by
");
$ids = array_column($idres->fetch_all(MYSQLI_ASSOC), 'ID');

$prev_id = $next_id = $first_id = $last_id = null;
if ($ids) {
    $first_id = $ids[0];
    $last_id  = $ids[count($ids)-1];
    $pos = array_search($id, $ids); // nicht strict
    if ($pos !== false) {
        $prev_id = $ids[$pos - 1] ?? null;
        $next_id = $ids[$pos + 1] ?? null;
    }
}

// Helper-HREFs (immer gültig, disabled zeigt dann nur Styling)
$makeHref = function(int $targetId) use ($params): string {
  return 'edit.php?' . http_build_query(array_merge($params, ['id'=>$targetId]));
};
$selfHref  = $makeHref((int)$id);
$prevHref  = $prev_id ? $makeHref((int)$prev_id) : $selfHref;
$nextHref  = $next_id ? $makeHref((int)$next_id) : $selfHref;
$firstHref = $first_id ? $makeHref((int)$first_id) : $selfHref;
$lastHref  = $last_id  ? $makeHref((int)$last_id)  : $selfHref;
}

/* ================ IGDB-Import-Action (robust) ================
   Trigger: edit.php?...&igdb=1
   - läuft NACH dem Laden von $data
*/
if (isset($_GET['igdb']) && $_GET['igdb'] === '1') {
    // Sicherheitsnetz: Stelle sicher, dass $data existiert
    if (!isset($data) || ($id ?? 0) <= 0) {
        if (($id ?? 0) > 0) {
            $res = $conn->query("SELECT * FROM `Sammlung` WHERE ID=".(int)$id);
            $data = $res ? $res->fetch_assoc() : null;
        }
    }

    $msg = 'IGDB: Kein Datensatz / fehlende Bezeichnung.';
    $ok  = false;

    // Diagnosepuffer aus der IGDB-Helper-Welt
    global $IGDB_DIAG;
    if (!is_array($IGDB_DIAG)) $IGDB_DIAG = [];

    if (($id ?? 0) > 0 && !empty($data)) {
        $title = trim((string)($data['Bezeichnung'] ?? ''));
        if ($title !== '') {
            // Plattformen aus Kategorie ableiten
            $platforms = platform_ids_for_row($data);
            $IGDB_DIAG[] = 'Titel="'.$title.'", KatID='.(int)($data['Kategorie'] ?? 0).', Plattformen=['.implode(',',$platforms).']';

            // 1. Versuch mit Plattformfilter
            $result = igdb_summary($title, $platforms);
            // 2. Fallback ohne Plattformen
            if (!$result && !empty($platforms)) {
                $IGDB_DIAG[] = 'Fallback: Plattformfilter entfernt.';
                $result = igdb_summary($title, []);
            }

            if ($result) {
                if (!empty($result['desc'])) {
                    $summaryDe = translate_to_de(trim($result['desc']));
                    $stmt = $conn->prepare("UPDATE Sammlung SET `Beschreibung`=? WHERE ID=?");
                    $stmt->bind_param('si', $summaryDe, $id);
                    $ok = $stmt->execute();
                    $stmt->close();

                    if ($ok) {
                        $msg = 'IGDB: Beschreibung importiert';
                        if (!empty($result['slug'])) {
                            $msg .= ' (Quelle: https://www.igdb.com/games/'.$result['slug'].')';
                        }
                        $msg .= '.';
                    } else {
                        $msg = 'IGDB: Konnte Beschreibung nicht speichern (DB-Fehler).';
                    }
                } else {
                    $msg = 'IGDB: Spiel gefunden, aber ohne Beschreibungstext.';
                    if (!empty($result['slug'])) {
                        $msg .= ' (https://www.igdb.com/games/'.$result['slug'].')';
                    }
                }
            } else {
                $msg = 'IGDB: Kein Treffer gefunden.';
            }
        }
    }

    // Diagnose anhängen (kompakt)
    if (!empty($IGDB_DIAG)) {
        $diag = implode(' | ', array_map(
            fn($s) => preg_replace('/\s+/', ' ', (string)$s),
            $IGDB_DIAG
        ));
        $msg .= ' ['.$diag.']';
    }

    // Redirect mit msg – falls möglich
    $params_with_msg = $_GET;
    $params_with_msg['msg'] = $msg;
    unset($params_with_msg['igdb']);

    if (!headers_sent()) {
        header('Location: edit.php?' . http_build_query($params_with_msg));
        exit;
    } else {
        // Fallback, falls bereits Output gesendet wurde:
        $_GET['msg'] = $msg;
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="format-detection" content="telephone=no">
<script src="/sammlung/assets/theme-toggle.js"></script>
<link rel="stylesheet" href="/sammlung/assets/app.css?v=10">

<style>
.barcode-field {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.barcode-actions {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}
.barcode-scan-wrap {
  max-width: 440px;
  border: 1px solid #cfd4da;
  border-radius: 10px;
  padding: 8px;
  background: #f8f9fa;
}
#barcodeVideo {
  width: 100%;
  max-width: 420px;
  border-radius: 8px;
  background: #000;
  display: block;
}
.barcode-hint {
  margin-top: 6px;
  font-size: .92rem;
  color: #555;
}
#captureBtn {
  margin-top: 8px;
  width: 100%;
}
.barcode-status {
  font-size: .92rem;
  color: #444;
  min-height: 1.4em;
}
.barcode-status.ok {
  color: #0f6b2f;
  font-weight: 600;
}
.barcode-status.err {
  color: #b42318;
  font-weight: 600;
}
.btn.secondary {
  background: #6c757d;
  color: #fff !important;
  border: 1px solid #5c636a;
}
.btn.secondary:hover {
  filter: brightness(0.94);
}
.hint {
  display: block;
  font-size: 0.8em;
  color: #777;
  margin-top: 2px;
}
</style>

</head>



<body class="edit-page">
  <h1><?= $id===0 ? 'Neuer Eintrag' : 'Eintrag bearbeiten' ?></h1>

  <?php if (!empty($_GET['msg'])): ?>
    <div style="background:#eef;border:1px solid #99f;padding:8px;margin:10px 0;">
      <?= htmlspecialchars($_GET['msg'], ENT_QUOTES, 'UTF-8') ?>
    </div>
  <?php endif; ?>

  <div class="buttons">
  <!-- Zeile 1 -->
  <div class="row row-main">
    <!-- KEIN data-nocheck -> warnen, wenn Änderungen ungespeichert -->
    <a class="btn back"
       href="<?= htmlspecialchars($backUrl ?: $listUrl) ?>"
       title="Zurück zur Liste">← Zurück</a>

    <button type="submit" class="btn save"
            form="f" name="post_action" value="stay"
            title="Speichern">💾 Speichern</button>

    <button type="submit" class="btn save"
            form="f" name="post_action" value="next"
            <?= $next_id ? '' : 'disabled' ?>
            title="Speichern und zum nächsten Datensatz springen">💾 Speichern & Nächster ▶️</button>

    <?php if ($id > 0): ?>
      <!-- Anzeigen darf ohne Warnung sein -->
      <a class="btn view" data-nocheck
         href="<?= htmlspecialchars($viewUrl) ?>"
         title="Eintrag ansehen">👁️ Anzeigen</a>
    <?php endif; ?>

    <!-- Navigation: KEIN data-nocheck -> warnen, wenn Änderungen ungespeichert -->
    <a class="btn nav<?= ($id !== $first_id) ? '' : ' disabled' ?>"
       href="<?= htmlspecialchars($firstHref) ?>"
       <?= ($id !== $first_id) ? '' : 'aria-disabled="true" tabindex="-1" onclick="return false;"' ?>
       title="Erster Datensatz">⏮️ Erster</a>

    <a class="btn nav<?= $prev_id ? '' : ' disabled' ?>"
       href="<?= htmlspecialchars($prevHref) ?>"
       <?= $prev_id ? '' : 'aria-disabled="true" tabindex="-1" onclick="return false;"' ?>
       title="Vorheriger Datensatz">◀️ Vorheriger</a>

    <a class="btn nav<?= $next_id ? '' : ' disabled' ?>"
       href="<?= htmlspecialchars($nextHref) ?>"
       <?= $next_id ? '' : 'aria-disabled="true" tabindex="-1" onclick="return false;"' ?>
       title="Nächster Datensatz">Nächster ▶️</a>

    <a class="btn nav<?= ($id !== $last_id) ? '' : ' disabled' ?>"
       href="<?= htmlspecialchars($lastHref) ?>"
       <?= ($id !== $last_id) ? '' : 'aria-disabled="true" tabindex="-1" onclick="return false;"' ?>
       title="Letzter Datensatz">Letzter ⏭️</a>
  </div>

  <!-- Zeile 2 -->
  <div class="row row-secondary">
    <button type="submit" form="f" name="action" value="dup"
            class="btn dup"
            title="als neuen Eintrag speichern">📄 Als NEU speichern</button>

    <button type="submit" form="f" name="action" value="dup_keepcost"
            class="btn dupplus"
            title="als neuen Eintrag speichern (mit Datum/Preis/Wert)">📄+ NEU (mit Datum/Preis/Wert)</button>

    <!-- Neuer Eintrag: meist ohne Warnung ok; wenn du warnen willst -> data-nocheck entfernen -->
    <a class="btn new" data-nocheck
       title="Neuer Eintrag ohne Übernahme"
       href="edit.php?<?= htmlspecialchars(http_build_query(array_merge($params, ['id'=>0,'from'=>'new']))) ?>">
       ➕ Neuer Eintrag (leer)
    </a>

    <?php $igdbParams = $_GET; $igdbParams['igdb'] = '1'; ?>
    <?php if ($id > 0): ?>
      <a class="btn igdb" data-nocheck
         href="edit.php?<?= htmlspecialchars(http_build_query($igdbParams)) ?>"
         title="Beschreibung aus IGDB importieren (summary)">🔎 IGDB holen</a>

      <?php
        // Filterkontext für den Rücksprung nach index.php mitgeben (wie im Formular oben)
        $deleteParams = $params;
        foreach (['filter'=>$filter, 'oh'=>$oh, 'material'=>$material, 'q'=>$q] as $k=>$v) {
            if ($v !== '') $deleteParams[$k] = $v;
        }
        $deleteParams['id'] = $id;
      ?>
      <a class="btn delete" data-nocheck
         href="delete.php?<?= htmlspecialchars(http_build_query($deleteParams)) ?>"
         onclick="return confirm('„<?= htmlspecialchars(addslashes($data['Bezeichnung'] ?? ''), ENT_QUOTES) ?>“ wirklich unwiderruflich löschen?');"
         title="Diesen Datensatz löschen">🗑️ Löschen</a>
    <?php endif; ?>
  </div>
</div>


  <form id="f" method="post">
    <?php
      foreach (['page','sort','dir','filter','oh','material','q','hersteller','publisher','verkaeufer','standort'] as $k) {
        if (isset($$k) && $$k !== '') {
          echo '<input type="hidden" name="'.htmlspecialchars($k).'" value="'.htmlspecialchars($$k).'">';
        }
      }
    ?>
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf'] ?? '') ?>">
    <input type="hidden" name="next_id" value="<?= htmlspecialchars((string)($next_id ?? '')) ?>">

    <?php
      // Gruppierung der Felder für die neue Karten-Ansicht
      $fieldGroups = [
        'Bezeichnung' => 'Grunddaten', 'Jahr' => 'Grunddaten', 'Hersteller' => 'Grunddaten',
        'Publisher' => 'Grunddaten', 'Seriennummer' => 'Grunddaten', 'Anzahl' => 'Grunddaten',
        'Kategorie' => 'Grunddaten', 'Original/Homebrew' => 'Grunddaten',

        'Zustand' => 'Zustand & Vollständigkeit', 'Verpackung Status' => 'Zustand & Vollständigkeit',
        'Verpackung' => 'Zustand & Vollständigkeit', 'Datenträger Status' => 'Zustand & Vollständigkeit',
        'Datentraeger' => 'Zustand & Vollständigkeit', 'Anleitung Status' => 'Zustand & Vollständigkeit',
        'Sonstiges' => 'Zustand & Vollständigkeit', 'Sonstiges Beschreibung' => 'Zustand & Vollständigkeit',
        'Material' => 'Zustand & Vollständigkeit',

        'Standort' => 'Standort & Kennung', 'Zum Verkauf' => 'Standort & Kennung', 'VerbautIn' => 'Standort & Kennung', 'ISBN' => 'Standort & Kennung',
        'Barcode' => 'Standort & Kennung', 'Box' => 'Standort & Kennung',

        'Einkaufsdatum' => 'Kauf & Test', 'Getestet am' => 'Kauf & Test', 'Getestet Status' => 'Kauf & Test',
        'Verkäufer' => 'Kauf & Test', 'Einkaufspreis' => 'Kauf & Test', 'Wert' => 'Kauf & Test',

        'Link zum Blog' => 'Links & Notizen', 'Link zu YouTube' => 'Links & Notizen',
        'Beschreibung' => 'Links & Notizen', 'SammlungBild1' => 'Links & Notizen',
      ];
      // Felder, die die volle Kartenbreite bekommen (statt 2-Spalten-Grid)
      $wideFields = ['Bezeichnung','Zustand','Barcode','Sonstiges Beschreibung','Link zum Blog','Link zu YouTube','Beschreibung','SammlungBild1'];
      $currentGroup = null;
    ?>

    <div class="container">
      <!-- Reihenfolge behalten: Felder links, Bild rechts -->
      <div class="fields">
        <div class="form-groups">
          <?php foreach ($inputs as $col => $type):
              $htmlName = str_replace(' ', '_', $col);
              $value    = $data[$col] ?? '';
              $group    = $fieldGroups[$col] ?? 'Weitere Angaben';
              $isWide   = in_array($col, $wideFields, true);

              if ($group !== $currentGroup) {
                  if ($currentGroup !== null) echo '</div></div>'; // .form-grid + .form-group schließen
                  echo '<div class="form-group"><h3>'.htmlspecialchars($group).'</h3><div class="form-grid">';
                  $currentGroup = $group;
              }
          ?>
            <div class="field<?= $isWide ? ' wide' : '' ?><?= $type === 'checkbox' ? ' checkbox-field' : '' ?>" data-col="<?= htmlspecialchars($htmlName) ?>">
  <span class="label"><?= htmlspecialchars($col === 'VerbautIn' ? 'Gehört zu' : $col) ?></span>

  <?php if ($type === 'textarea'): ?>

    <textarea name="<?= $htmlName ?>" rows="4"><?= htmlspecialchars($value) ?></textarea>

  <?php elseif ($type === 'select'): ?>

    <?php if ($col === 'Datentraeger'): ?>
      <select name="<?= $htmlName ?>">
        <?= get_options('Datentraeger','Datentrager',$value) ?>
      </select>

    <?php elseif ($col === 'Original/Homebrew'): ?>
      <?php $sel = $value ?: 'Original'; ?>
      <select name="<?= $htmlName ?>">
        <option value="Original" <?= ($sel === 'Original' ? 'selected' : '') ?>>Original</option>
        <option value="Homebrew" <?= ($sel === 'Homebrew' ? 'selected' : '') ?>>Homebrew</option>
      </select>

    <?php elseif ($col === 'Getestet Status'): ?>
      <select name="<?= $htmlName ?>">
        <option value=""       <?= ($value === ''       ? 'selected' : '') ?>>— ungetestet —</option>
        <option value="OK"     <?= ($value === 'OK'      ? 'selected' : '') ?>>✅ OK</option>
        <option value="Defekt" <?= ($value === 'Defekt'  ? 'selected' : '') ?>>❌ Defekt</option>
      </select>

    <?php elseif (in_array($col, ['Verpackung Status','Anleitung Status','Datenträger Status'], true)): ?>
      <select name="<?= $htmlName ?>">
        <option value=""          <?= ($value === ''          ? 'selected' : '') ?>>— nicht vorhanden —</option>
        <option value="Original"  <?= ($value === 'Original'   ? 'selected' : '') ?>>Original</option>
        <option value="Nachdruck" <?= ($value === 'Nachdruck'  ? 'selected' : '') ?>>Nachdruck / Repro</option>
      </select>

    <?php elseif ($col === 'VerbautIn'): ?>
      <?php
        $vbLabel = function(string $bez, string $sn, int $vbId): string {
            $l = $bez . ' (#' . $vbId . ')';
            if (trim($sn) !== '') $l .= ' – SN: ' . trim($sn);
            return $l;
        };
        $vbCurrentId    = (int)$value;
        $vbCurrentLabel = '';
        if ($vbCurrentId > 0) {
            $vbCurRow = $conn->query("SELECT Bezeichnung, Seriennummer FROM Sammlung WHERE ID=" . $vbCurrentId)->fetch_assoc();
            if ($vbCurRow) $vbCurrentLabel = $vbLabel($vbCurRow['Bezeichnung'], (string)($vbCurRow['Seriennummer'] ?? ''), $vbCurrentId);
        }
      ?>
      <input type="text"
             class="verbautin-search"
             list="verbautInList"
             autocomplete="off"
             placeholder="Tippen zum Suchen…"
             value="<?= htmlspecialchars($vbCurrentLabel) ?>"
             data-hidden-target="<?= $htmlName ?>">
      <input type="hidden" name="<?= $htmlName ?>" id="<?= $htmlName ?>" value="<?= $vbCurrentId ?>">
      <datalist id="verbautInList">
        <?php
          $vbOptions = $conn->query("SELECT ID, Bezeichnung, Seriennummer FROM Sammlung WHERE ID != " . (int)$id . " ORDER BY Bezeichnung");
          while ($vbRow = $vbOptions->fetch_assoc()):
            $vbId = (int)$vbRow['ID'];
        ?>
          <option value="<?= htmlspecialchars($vbLabel($vbRow['Bezeichnung'], (string)($vbRow['Seriennummer'] ?? ''), $vbId)) ?>">
        <?php endwhile; ?>
      </datalist>

    <?php else: ?>
      <select name="<?= $htmlName ?>">
        <?= get_options($col, $col, $value) ?>
      </select>
    <?php endif; ?>

  <?php elseif ($type === 'checkbox'): ?>

    <input type="checkbox" name="<?= $htmlName ?>" value="1" <?= $value ? 'checked' : '' ?>>

  <?php elseif ($type === 'number'): ?>

    <input type="number" name="<?= $htmlName ?>" value="<?= htmlspecialchars($value) ?>">

  <?php elseif ($col === 'Barcode'): ?>

    <div class="barcode-field">
      <input
        type="text"
        name="<?= $htmlName ?>"
        id="barcodeInput"
        value="<?= htmlspecialchars($value) ?>"
        inputmode="numeric"
        autocomplete="off"
        placeholder="Barcode / EAN scannen oder eingeben"
      >

      <div class="barcode-actions">
        <button type="button" class="btn secondary" id="startScanBtn">📷 Scannen</button>
        <button type="button" class="btn secondary" id="stopScanBtn" hidden>✖ Scan beenden</button>
        <button type="button" class="btn secondary" id="torchBtn" hidden>🔦 Licht an</button>
      </div>

      <div id="barcodeScanWrap" class="barcode-scan-wrap" hidden>
        <video id="barcodeVideo" autoplay playsinline muted></video>
        <div class="barcode-hint">Barcode mittig vor die Kamera halten.</div>
        <button type="button" class="btn secondary" id="captureBtn">📸 Foto aufnehmen &amp; auswerten</button>
      </div>
      <canvas id="barcodeCanvas" hidden></canvas>

      <div id="barcodeStatus" class="barcode-status" aria-live="polite"></div>
    </div>

  <?php else: ?>

    <input type="<?= $type ?>" name="<?= $htmlName ?>" value="<?= htmlspecialchars($value) ?>">

  <?php endif; ?>
</div>
<?php endforeach; ?>
          </div></div><!-- .form-grid + .form-group (letzte reguläre Gruppe) schließen -->

          <?php
            $erstellt  = !empty($data['Erstellt_am'])  ? date('d.m.Y H:i:s', strtotime($data['Erstellt_am']))   : '';
            $geaendert = !empty($data['Geaendert_am']) ? date('d.m.Y H:i:s', strtotime($data['Geaendert_am'])) : '';
          ?>
          <div class="form-group">
            <h3>Verwaltung</h3>
            <div class="form-grid">
              <div class="field readonly">
                <span class="label">Erstellt am</span>
                <input type="text" value="<?= htmlspecialchars($erstellt) ?>" readonly tabindex="-1">
              </div>
              <div class="field readonly">
                <span class="label">Geändert am</span>
                <input type="text" value="<?= htmlspecialchars($geaendert) ?>" readonly tabindex="-1">
              </div>
            </div>
          </div>
        </div>
      </div>

      <?php if (!empty($data['SammlungBild1'])): ?>
        <div class="image"><img src="<?= htmlspecialchars($data['SammlungBild1']) ?>" alt="Bild"></div>
      <?php endif; ?>
     </form>


  <script>
(function(){
  const input     = document.getElementById('barcodeInput');
  const startBtn  = document.getElementById('startScanBtn');
  const stopBtn   = document.getElementById('stopScanBtn');
  const wrap      = document.getElementById('barcodeScanWrap');
  const video     = document.getElementById('barcodeVideo');
  const statusBox = document.getElementById('barcodeStatus');
  const torchBtn  = document.getElementById('torchBtn');
  const captureBtn = document.getElementById('captureBtn');
  const canvas     = document.getElementById('barcodeCanvas');

  if (!input || !startBtn || !stopBtn || !wrap || !video || !statusBox) return;

  // Kamera-Constraints: hohe Auflösung + kontinuierlicher Autofokus, wo unterstützt
  const VIDEO_CONSTRAINTS = {
    facingMode: { ideal: 'environment' },
    width:  { ideal: 1920 },
    height: { ideal: 1080 },
    advanced: [{ focusMode: 'continuous' }]
  };

  let stream     = null;
  let detector   = null;  // nativer BarcodeDetector
  let codeReader = null;  // ZXing-Fallback
  let scanTimer  = null;
  let scanning   = false;
  let lastValue  = '';
  let torchOn    = false;
  let torchTrack = null;

  function setStatus(msg, kind) {
    statusBox.textContent = msg || '';
    statusBox.classList.remove('ok', 'err');
    if (kind) statusBox.classList.add(kind);
  }

  function focusBarcode() {
    input.focus();
    try { const len = input.value.length; input.setSelectionRange(len, len); } catch (e) {}
  }

  // Torch (Blitzlicht) an/aus, falls vom Gerät unterstützt
  async function setupTorch(mediaStream) {
    torchTrack = null;
    if (!torchBtn) return;
    const track = mediaStream.getVideoTracks()[0];
    if (!track) { torchBtn.hidden = true; return; }
    const caps = track.getCapabilities ? track.getCapabilities() : {};
    if (caps && caps.torch) {
      torchTrack = track;
      torchBtn.hidden = false;
      torchOn = false;
      torchBtn.textContent = '🔦 Licht an';
    } else {
      torchBtn.hidden = true;
    }
  }

  async function toggleTorch() {
    if (!torchTrack) return;
    try {
      torchOn = !torchOn;
      await torchTrack.applyConstraints({ advanced: [{ torch: torchOn }] });
      torchBtn.textContent = torchOn ? '🔦 Licht aus' : '🔦 Licht an';
    } catch (e) {
      setStatus('Blitzlicht konnte nicht umgeschaltet werden.', 'err');
    }
  }

  // Gemeinsame Ergebnis-Verarbeitung für beide Pfade
  function onBarcodeFound(rawValue) {
    if (!rawValue || rawValue === lastValue) return;
    lastValue = rawValue;
    input.value = rawValue;
    input.dispatchEvent(new Event('input',  { bubbles: true }));
    input.dispatchEvent(new Event('change', { bubbles: true }));
    setStatus('Barcode erkannt: ' + rawValue, 'ok');
    stopScanner();
    focusBarcode();
  }

  function stopScanner() {
    scanning = false;

    if (scanTimer)   { clearInterval(scanTimer); scanTimer = null; }
    if (codeReader)  { try { codeReader.reset(); } catch (e) {} codeReader = null; }

    if (video.srcObject) {
      video.srcObject.getTracks().forEach(t => t.stop());
      video.srcObject = null;
    }
    if (stream) {
      stream.getTracks().forEach(t => t.stop());
      stream = null;
    }

    torchTrack = null;
    torchOn = false;
    if (torchBtn) { torchBtn.hidden = true; torchBtn.textContent = '🔦 Licht an'; }
    if (captureBtn) captureBtn.hidden = true;

    wrap.hidden     = true;
    stopBtn.hidden  = true;
    startBtn.hidden = false;
  }

  // ---- Pfad A: nativer BarcodeDetector (Chrome, Edge, Android Chrome) ----
  async function startScannerNative() {
    try {
      const supported = await BarcodeDetector.getSupportedFormats();
      const wanted    = ['ean_13','ean_8','upc_a','upc_e','code_128','code_39'];
      const formats   = wanted.filter(f => supported.includes(f));
      detector = new BarcodeDetector({ formats: formats.length ? formats : supported });
    } catch (e) {
      setStatus('Barcode-Scanner konnte nicht initialisiert werden.', 'err');
      focusBarcode(); return;
    }

    try {
      stream = await navigator.mediaDevices.getUserMedia({
        video: VIDEO_CONSTRAINTS, audio: false
      });
      video.srcObject = stream;
      await video.play();
      await setupTorch(stream);

      wrap.hidden = false; stopBtn.hidden = false; startBtn.hidden = true; if (captureBtn) captureBtn.hidden = false;
      scanning = true; lastValue = '';
      setStatus('Scanner läuft …', '');

      scanTimer = setInterval(async () => {
        if (!scanning || !detector) return;
        try {
          const barcodes = await detector.detect(video);
          if (barcodes && barcodes.length)
            onBarcodeFound((barcodes[0].rawValue || '').trim());
        } catch (e) {}
      }, 700);

    } catch (e) {
      setStatus('Kamera konnte nicht geöffnet werden. Bitte Berechtigung prüfen.', 'err');
      focusBarcode(); stopScanner();
    }
  }

  // ---- Pfad B: ZXing-Fallback (iOS Safari, Firefox) ----
  function loadZXing() {
    return new Promise((resolve, reject) => {
      if (window.ZXing) { resolve(); return; }
      const s = document.createElement('script');
      s.src     = 'https://unpkg.com/@zxing/library@0.19.2/umd/index.min.js';
      s.onload  = resolve;
      s.onerror = () => reject(new Error('ZXing konnte nicht geladen werden.'));
      document.head.appendChild(s);
    });
  }

  async function startScannerZXing() {
    setStatus('Scanner wird geladen …', '');
    try {
      await loadZXing();
    } catch (e) {
      setStatus('Scanner-Bibliothek konnte nicht geladen werden. Bitte Barcode manuell eingeben.', 'err');
      focusBarcode(); return;
    }

    try {
      codeReader = new ZXing.BrowserMultiFormatReader();

      // Eigenen Stream mit unseren Constraints holen, statt ZXing raten zu lassen
      stream = await navigator.mediaDevices.getUserMedia({
        video: VIDEO_CONSTRAINTS, audio: false
      });
      video.srcObject = stream;
      await video.play();
      await setupTorch(stream);

      wrap.hidden = false; stopBtn.hidden = false; startBtn.hidden = true; if (captureBtn) captureBtn.hidden = false;
      scanning = true; lastValue = '';
      setStatus('Scanner läuft …', '');

      await codeReader.decodeFromStream(stream, video, (result, err) => {
        if (result) onBarcodeFound(result.getText());
        // err ist während der Suche normal – nicht anzeigen
      });

    } catch (e) {
      setStatus('Kamera konnte nicht geöffnet werden. Bitte Berechtigung prüfen.', 'err');
      focusBarcode(); stopScanner();
    }
  }

  // ---- Einzelbild aufnehmen und mit mehreren Zoomstufen auswerten ----
  // Löst zwei typische Live-Scan-Probleme: Bewegungsunschärfe und zu kleine
  // Barcodes im Vollbild. Ein scharfes Standbild wird mehrfach ausgewertet,
  // jeweils auf die Bildmitte vergrößert (100 % / 60 % / 35 % Bildausschnitt).
  async function captureAndDecode() {
    if (!video.videoWidth || !video.videoHeight) return;
    setStatus('Foto wird ausgewertet …', '');

    const vw = video.videoWidth, vh = video.videoHeight;
    canvas.width = vw; canvas.height = vh;
    const ctx = canvas.getContext('2d');
    ctx.drawImage(video, 0, 0, vw, vh);

    const zoomLevels = [1, 0.6, 0.35]; // Anteil der Bildmitte, der genutzt wird
    let zxingReader = null;

    for (const zoom of zoomLevels) {
      const cw = Math.round(vw * zoom), ch = Math.round(vh * zoom);
      const cx = Math.round((vw - cw) / 2), cy = Math.round((vh - ch) / 2);

      const cropCanvas = document.createElement('canvas');
      // auf min. 1000px Breite hochskalieren, damit kleine Barcodes genug Pixel haben
      const scale = Math.max(1, 1000 / cw);
      cropCanvas.width  = Math.round(cw * scale);
      cropCanvas.height = Math.round(ch * scale);
      const cropCtx = cropCanvas.getContext('2d');
      cropCtx.drawImage(canvas, cx, cy, cw, ch, 0, 0, cropCanvas.width, cropCanvas.height);

      // Pfad A: nativer BarcodeDetector, falls verfügbar
      if ('BarcodeDetector' in window) {
        try {
          if (!detector) {
            const supported = await BarcodeDetector.getSupportedFormats();
            const wanted    = ['ean_13','ean_8','upc_a','upc_e','code_128','code_39'];
            const formats   = wanted.filter(f => supported.includes(f));
            detector = new BarcodeDetector({ formats: formats.length ? formats : supported });
          }
          const results = await detector.detect(cropCanvas);
          if (results && results.length) {
            onBarcodeFound((results[0].rawValue || '').trim());
            return;
          }
        } catch (e) { /* weiter mit nächster Zoomstufe */ }
      } else {
        // Pfad B: ZXing gegen das Standbild
        try {
          await loadZXing();
          if (!zxingReader) zxingReader = new ZXing.BrowserMultiFormatReader();
          const dataUrl = cropCanvas.toDataURL('image/png');
          const result = await zxingReader.decodeFromImageUrl(dataUrl);
          if (result) {
            onBarcodeFound(result.getText());
            return;
          }
        } catch (e) { /* weiter mit nächster Zoomstufe */ }
      }
    }

    if (zxingReader) { try { zxingReader.reset(); } catch (e) {} }
    setStatus('Kein Barcode im Foto erkannt. Näher heran oder anderer Ausschnitt versuchen.', 'err');
  }

  // ---- Einheitlicher Einstieg ----
  async function startScanner() {
    if (!('mediaDevices' in navigator) || !navigator.mediaDevices.getUserMedia) {
      setStatus('Kamerazugriff wird von diesem Browser nicht unterstützt.', 'err');
      focusBarcode(); return;
    }
    if ('BarcodeDetector' in window) {
      await startScannerNative();
    } else {
      await startScannerZXing();
    }
  }

  startBtn.addEventListener('click', startScanner);
  stopBtn.addEventListener('click', function(){
    stopScanner();
    setStatus('Scan beendet.', '');
    focusBarcode();
  });
  if (torchBtn) torchBtn.addEventListener('click', toggleTorch);
  if (captureBtn) captureBtn.addEventListener('click', captureAndDecode);

  window.addEventListener('beforeunload', stopScanner);
})();
</script>

  <!-- "Verbaut in": Textfeld+Datalist -> verstecktes ID-Feld synchronisieren -->
  <script>
(function(){
  document.querySelectorAll('.verbautin-search').forEach(function(searchInput){
    var hidden = document.getElementById(searchInput.getAttribute('data-hidden-target'));
    if (!hidden) return;

    function sync(){
      var m = searchInput.value.match(/\(#(\d+)\)/);
      hidden.value = m ? m[1] : '0';
    }
    searchInput.addEventListener('input', sync);
    searchInput.addEventListener('change', sync);
  });
})();
  </script>

  <!-- Sonstiges-Beschreibung nur zeigen, wenn Sonstiges angehakt ist -->
  <script>
(function(){
  var checkbox = document.querySelector('input[name="Sonstiges"]');
  var descField = document.querySelector('.field[data-col="Sonstiges_Beschreibung"]');
  if (!checkbox || !descField) return;

  function sync(){
    descField.style.display = checkbox.checked ? '' : 'none';
  }
  checkbox.addEventListener('change', sync);
  sync(); // initialer Zustand beim Laden
})();
  </script>

  <!-- Unsaved-changes-Warnung: direkt vor </body> einfügen -->
  <script>
(function(){
  var form = document.getElementById('f');
  if (!form) return;

  var isDirty = false;

  function markDirty(){ isDirty = true; }
  form.addEventListener('input',  markDirty, {capture:true});
  form.addEventListener('change', markDirty, {capture:true});

  // Beim Absenden NICHT warnen
  form.addEventListener('submit', function(){ isDirty = false; });

  // Tab schließen / Reload / andere URL
  window.addEventListener('beforeunload', function(e){
    if (!isDirty) return;
    e.preventDefault();
    e.returnValue = '';
  });

  function isSubmitRelated(el){
    if (!el) return false;

    // <button> ohne type ist standardmäßig submit
    var isButtonSubmit = (el.tagName === 'BUTTON' && (el.type === 'submit' || !el.hasAttribute('type')));
    var isInputSubmit  = (el.tagName === 'INPUT'  && el.type === 'submit');

    if (!(isButtonSubmit || isInputSubmit)) return false;

    var insideForm = !!el.closest('form');
    var targetsF   = (el.getAttribute('form') === 'f');

    if (insideForm) {
      var f = el.closest('form');
      if (f && f.id === 'f') return true;
      return targetsF;
    }
    return targetsF;
  }

  // Interne Navigation abfangen: NUR Links + Buttons
  document.addEventListener('click', function(e){
    var el = e.target.closest('a,button');   // <-- wichtig: kein input hier!
    if (!el) return;

    // Ausnahmen: data-nocheck → keine Warnung
    if (el.hasAttribute('data-nocheck')) return;

    // Submit-Aktionen dürfen immer durch
    if (isSubmitRelated(el)) return;

    if (!isDirty) return;

    var ok = confirm('Es gibt ungespeicherte Änderungen. Seite wirklich verlassen?');
    if (!ok) {
      e.preventDefault();
      e.stopPropagation();
    } else {
      isDirty = false;
    }
  }, true);
})();
</script>
</body>
</html>