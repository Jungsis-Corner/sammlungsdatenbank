<?php
require_once __DIR__ . '/inc_errors.php';
require_once __DIR__ . '/inc_icons.php';
require_once __DIR__ . '/inc_nav.php';

// ---- (Optional) PHP-Zeitzone setzen ----
date_default_timezone_set('Europe/Berlin');

// ---- Login prüfen & nur Admin zulassen ----
require_once __DIR__ . '/require_login.php';
require_once __DIR__ . '/inc_security.php';
require_once __DIR__ . '/inc_format.php';
require_once __DIR__ . '/inc_image.php';
require_once __DIR__ . '/inc_filter.php';
if (!is_admin()) { error_page(403, 'Kein Zugriff', 'Dieser Bereich ist nur für Administratoren.'); }

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
    fail_db('Datenbankfehler.', 'Verbindung: ' . $conn->connect_error);
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
/**
 * Führt INSERT/UPDATE auf Sammlung aus. Fehler 1452 (Fremdschlüssel: ungültige Auswahl, z. B. gelöschter
 * Eintrag oder manipuliertes Formular) wird als Hinweis gezeigt statt als Datenbankfehler.
 */
function execute_sammlung(mysqli_stmt $stmt, int $id): void {
    try {
        $stmt->execute();
    } catch (mysqli_sql_exception $e) {
        if ((int)$e->getCode() !== 1452) { throw $e; }
        // gleiche Session-Schlüssel wie die IGDB-Meldung (wird weiter unten angezeigt und gelöscht)
        $_SESSION['flash_msg']  = 'Nicht gespeichert: Eine Auswahl (z. B. Kategorie, Box oder „Gehört zu“) ist ungültig. Bitte neu wählen.';
        $_SESSION['flash_diag'] = '';
        $keep = ['id' => $id];
        foreach (['page','sort','dir','filter','kat','inkl','oh','material','q','hersteller','publisher','verkaeufer','standort','box','verkauf','einkaufsdatum'] as $k) {
            if (isset($_POST[$k]) && $_POST[$k] !== '') $keep[$k] = $_POST[$k];
        }
        header('Location: edit.php?' . http_build_query($keep));
        exit;
    }
}

function get_options($table, $field, $selected='') {
    global $conn;
    [$t, $f] = lookup_ident($table, $field);
    $opts = "<option value=''>— unbekannt —</option>\n";
    $order = ($table === 'Box') ? box_sort_sql($t) . ", $f" : $f;   // Box: natürliche Reihenfolge
    $res  = $conn->query("SELECT ID, $f FROM $t ORDER BY $order");
    while ($r = $res->fetch_assoc()) {
        $sel = ($r['ID'] == $selected) ? ' selected' : '';
        $opts .= "<option value=\"{$r['ID']}\"{$sel}>"
               . htmlspecialchars($r[$field] ?? '') . "</option>\n";
    }
    return $opts;
}

// Suchfeld mit Vorschlagsliste (assets/typeahead.js, Vorschläge aus api_lookup.php) statt eines Feldes mit allen Werten.
// Das versteckte ID-Feld trägt die Auswahl; ohne JavaScript oder bei geändertem Text löst der Server den exakt getippten
// Namen beim Speichern selbst auf (resolve_lookup_id).
function lookup_api_key(string $table): string {
    return ['Verkäufer' => 'verkaeufer'][$table] ?? strtolower($table);
}
function render_lookup_search($htmlName, $table, $field, $selectedId, $inputId = '') {
    global $conn;
    [$t, $f] = lookup_ident($table, $field);
    $selectedId = (int)$selectedId;
    $curLabel = '';
    if ($selectedId > 0) {
        $lr = db_select($conn, "SELECT $f FROM $t WHERE ID = ?", 'i', [$selectedId])->fetch_row();
        if ($lr) $curLabel = (string)$lr[0];
    }
    echo '<input type="text"' . ($inputId !== '' ? ' id="' . htmlspecialchars($inputId, ENT_QUOTES, 'UTF-8') . '"' : '') . ' name="' . $htmlName . '_text" class="lookup-search ta-input" autocomplete="off"'
       . ' placeholder="Tippen zum Suchen…"'
       . ' value="' . htmlspecialchars($curLabel, ENT_QUOTES, 'UTF-8') . '"'
       . ' data-ta="' . lookup_api_key($table) . '" data-ta-hidden="' . $htmlName . '_hidden">';
    echo '<input type="hidden" name="' . $htmlName . '" id="' . $htmlName . '_hidden" value="' . $selectedId . '">';
}

// ID aus Such-Textfeld ermitteln, falls das versteckte ID-Feld leer ankommt
// (z.B. veraltetes JavaScript im Browser-Cache): 1) Hidden-ID, 2) "(#ID)" im Text,
// 3) exakter Name in der Lookup-Tabelle.
function resolve_lookup_id($table, $field, $idRaw, $textRaw) {
    global $conn;
    $id = (int)$idRaw;
    if ($id > 0) return $id;
    $text = trim((string)$textRaw);
    if ($text === '') return 0;
    if (preg_match('/\(#(\d+)\)\s*$/', $text, $m)) return (int)$m[1];
    [$t, $f] = lookup_ident($table, $field);
    $stmt = $conn->prepare("SELECT ID FROM $t WHERE $f = ? LIMIT 1");
    $stmt->bind_param('s', $text);
    $stmt->execute();
    $stmt->bind_result($foundId);
    $res = $stmt->fetch() ? (int)$foundId : 0;
    $stmt->close();
    return $res;
}

// GET-Parameter
$id             = intval($_GET['id']        ?? 0);
$page           = intval($_GET['page']      ?? 1);
$sort           = $_GET['sort']             ?? 'Bezeichnung';
$dir            = strtolower($_GET['dir']   ?? 'asc');          // NEU: Richtung übernehmen
if (!in_array($dir, ['asc','desc'], true)) { $dir = 'asc'; }

$filter         = $_GET['filter']           ?? '';
$kat             = intval($_GET['kat'] ?? 0);              // Kategorie-Knoten
$inkl            = ($_GET['inkl'] ?? '') === '1';          // inkl. Unterkategorien
$oh             = $_GET['oh']               ?? '';
$material       = $_GET['material']         ?? '';
$q              = $_GET['q']                ?? '';
$hersteller_id  = intval($_GET['hersteller'] ?? 0);
$publisher_id   = intval($_GET['publisher']  ?? 0);
$verkaeufer_id  = intval($_GET['verkaeufer'] ?? 0);
$standort_id    = intval($_GET['standort']   ?? 0);
$box_id         = trim((string)($_GET['box'] ?? ''));
$verkauf_filter = ($_GET['verkauf'] ?? '') === '1';
$einkaufsdatum_filter = trim((string)($_GET['einkaufsdatum'] ?? ''));
if ($einkaufsdatum_filter !== '' && !preg_match('~^\d{4}-\d{2}-\d{2}$~', $einkaufsdatum_filter)) {
    $einkaufsdatum_filter = '';
}
$backUrl        = safe_back_url($_GET['back'] ?? null, '');  // nur relative App-Ziele

// damit die Nav-Buttons nicht undefiniert sind, auch bei $id==0
$prev_id = null;
$next_id = null;

// Erlaube id=0 für „Neuer Eintrag“
if ($id < 0) die('Ungültige ID.');

// Basis-Parameter für Prev/Next & Zurück-Link
$params = ['page'=>$page,'sort'=>$sort,'dir'=>$dir]; // NEU: dir aufnehmen
if ($filter        !== '') $params['filter']     = $filter;
if ($kat           >   0) $params['kat']        = $kat;
if ($inkl                 ) $params['inkl']       = '1';
if ($oh            !== '') $params['oh']         = $oh;
if ($material      !== '') $params['material']   = $material;
if ($q             !== '') $params['q']          = $q;
if ($hersteller_id )       $params['hersteller'] = $hersteller_id;
if ($publisher_id)         $params['publisher']  = $publisher_id;
if ($verkaeufer_id)        $params['verkaeufer'] = $verkaeufer_id;
if ($standort_id)          $params['standort']   = $standort_id;
if ($box_id !== '')        $params['box']        = $box_id;
if ($verkauf_filter)       $params['verkauf']    = '1';
if ($einkaufsdatum_filter !== '') $params['einkaufsdatum'] = $einkaufsdatum_filter;
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
  'Box'               => 'select',
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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') !== 'igdb') {
    // --- CSRF-Check ---
    require_post_csrf();

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
      'Box'                => 'select',
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
      'Datentraeger','Material','Standort','Verkäufer','VerbautIn','Box'
    ];

    // Datumsfelder, die in YYYY-MM-DD gespeichert werden
    $dateCols = ['Einkaufsdatum', 'Getestet am'];

    // Formularwerte einsammeln
    $vals   = [];
    $types  = '';   // für bind_param
    foreach ($inputs as $col => $kind) {
        $name = str_replace(' ', '_', $col); // HTML-Name wie im Formular

        if ($kind === 'checkbox') {
            // Checkbox => 1 wenn gesetzt, sonst 0
            $vals[$col] = isset($_POST[$name]) ? 1 : 0;
            $types     .= 'i';
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
            } elseif (preg_match('~^\d{1,2}\.\d{1,2}\.\d{4}$~', $raw)) {
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
                if (in_array($col, ['Kategorie','Hersteller','Publisher','Verkäufer'], true)) {
                    // Such-Textfelder: ID notfalls aus dem sichtbaren Text nachschlagen
                    $vals[$col] = resolve_lookup_id($col, $col, $_POST[$name] ?? '', $_POST[$name . '_text'] ?? '');
                } else {
                    $vals[$col] = (int)($_POST[$name] ?? 0);
                }
                // Keine Auswahl = NULL (nicht 0): "keine Angabe" für spätere Fremdschlüssel
                if ((int)$vals[$col] <= 0) $vals[$col] = null;
                $types     .= 'i';
            } else {
                // übrige selects als string
                $vals[$col] = (string)($_POST[$name] ?? '');
                $types     .= 's';
            }
            continue;
        }

        // Jahr (Zahlenspalte): leer, 0 oder keine ganze Zahl = NULL (nicht 0)
        if ($col === 'Jahr') {
            $t = trim((string)($_POST[$name] ?? ''));
            $vals[$col] = (ctype_digit($t) && (int)$t > 0 && (int)$t <= 9999) ? (int)$t : null;
            $types     .= 'i';
            continue;
        }

        // Bild: eingefügte WordPress-URL als relativen Pfad speichern
        if ($col === 'SammlungBild1') {
            $vals[$col] = image_normalize_for_storage($_POST[$name] ?? '');
            $types     .= 's';
            continue;
        }

        // Geldbeträge (DECIMAL): Komma/Punkt, leer oder ungültig = NULL
        if ($col === 'Einkaufspreis' || $col === 'Wert') {
            $vals[$col] = parse_decimal($_POST[$name] ?? '');
            $types     .= 's';
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
                if (array_key_exists($c, $vals)) $vals[$c] = ($c === 'Einkaufsdatum') ? '' : null;
            }
        }
        // Testdaten werden bei JEDEM Duplikat zurückgesetzt (auch bei "dup_keepcost"),
        // da ein neues Duplikat noch nicht getestet wurde.
        foreach (['Getestet am','Getestet Status'] as $c) {
            if (array_key_exists($c, $vals)) $vals[$c] = '';
        }
        // "Zum Verkauf" ebenfalls zurücksetzen - das Duplikat ist noch nicht
        // bewusst als Verkaufsexemplar markiert worden.
        if (array_key_exists('Zum Verkauf', $vals)) $vals['Zum Verkauf'] = 0;

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
        execute_sammlung($stmt, $id);
        $newId = (int)$conn->insert_id;

        // Redirect zurück in edit (mit Kontext)
        $keep = [];
        foreach (['page','sort','dir','filter','kat','inkl','oh','material','q','hersteller','publisher','verkaeufer','standort','box','verkauf','einkaufsdatum'] as $k) {
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
        execute_sammlung($stmt, $id);
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
        execute_sammlung($stmt, $id);
    }

    // Redirect-Ziel bestimmen
    $baseParams = [];
    foreach (['page','sort','dir','filter','kat','inkl','oh','material','q','hersteller','publisher','verkaeufer','standort','box','verkauf','einkaufsdatum'] as $k) {
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
    $data = db_select($conn, "SELECT * FROM `Sammlung` WHERE ID = ?", 'i', [$id])->fetch_assoc();
} elseif ($id === 0) {
    // Vorbefüllung für einen neuen Eintrag, z.B. aus einkauf.php übernommen
    $pfMap = [
        'pf_Bezeichnung'   => 'Bezeichnung',
        'pf_Kategorie'     => 'Kategorie',
        'pf_Verkaeufer'    => 'Verkäufer',
        'pf_Einkaufspreis' => 'Einkaufspreis',
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
      'Box'                 => box_sort_sql('BX'),
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
        $order_by_core = 'S.Wert IS NULL, S.Wert ' . strtoupper($dir);
    }

    // stabile Zweitsortierung
    $order_by = $order_by_core . ', S.Bezeichnung ASC, S.ID ASC';   // ID als letzter Schlüssel: gleiche Titel bleiben in fester Reihenfolge (Liste, Detail, Bearbeiten, Export)

    // WHERE-Klausel (gemeinsam in inc_filter.php, mit Prepared Statements)
    $flt = sammlung_filter_where([
        'filter' => $filter, 'kat' => $kat, 'inkl' => $inkl, 'oh' => $oh, 'material' => $material, 'q' => $q,
        'hersteller' => $hersteller_id, 'publisher' => $publisher_id, 'verkaeufer' => $verkaeufer_id,
        'standort' => $standort_id, 'box' => $box_id, 'verkauf' => $verkauf_filter,
        'einkaufsdatum' => $einkaufsdatum_filter,
    ], ['s' => 'S', 'k' => 'K', 'm' => 'M']);
    $where_sql = $flt['sql'];

    // Prev/Next/First/Last IDs holen – gleiche JOINs wie index.php
$from_join = "
  FROM Sammlung S
  LEFT JOIN Kategorie   K  ON S.Kategorie   = K.ID
  LEFT JOIN Material    M  ON S.Material    = M.ID
  LEFT JOIN Zustand     Z  ON S.Zustand     = Z.ID
  LEFT JOIN Verpackung  VP ON S.Verpackung  = VP.ID
  LEFT JOIN Standort    ST ON S.Standort    = ST.ID
  LEFT JOIN Box         BX ON S.Box         = BX.ID
";

$idres = db_select($conn, "
  SELECT S.ID
  $from_join
  $where_sql
  ORDER BY $order_by
", $flt['types'], $flt['values']);
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
   Trigger: POST an edit.php?id=... mit action=igdb und csrf (Button "IGDB holen")
   - läuft NACH dem Laden von $data
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'igdb') {
    require_post_csrf();
    // Sicherheitsnetz: Stelle sicher, dass $data existiert
    if (!isset($data) || ($id ?? 0) <= 0) {
        if (($id ?? 0) > 0) {
            $res = db_select($conn, "SELECT * FROM `Sammlung` WHERE ID = ?", 'i', [(int)$id]);
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

    // Diagnose (kompakt) nur einmalig über die Session anzeigen und ins Log schreiben,
    // nicht in die URL legen (Browser-Verlauf, Server-Logs)
    $diag = '';
    if (!empty($IGDB_DIAG)) {
        $diag = implode(' | ', array_map(
            fn($s) => preg_replace('/\s+/', ' ', (string)$s),
            $IGDB_DIAG
        ));
        error_log('[edit.php IGDB] ' . $diag);
    }
    $_SESSION['flash_msg']  = $msg;
    $_SESSION['flash_diag'] = $diag;

    // Zurück zur Seite (PRG), ohne Meldungstext in der URL
    $params_back = $_GET;
    unset($params_back['msg']);
    if (!headers_sent()) {
        header('Location: edit.php?' . http_build_query($params_back));
        exit;
    }
    // Fallback, falls bereits Output gesendet wurde: Meldung wird unten im selben Aufruf angezeigt
}

// Einmalige Meldung (z. B. vom IGDB-Import) aus der Session holen und löschen
$flashMsg  = (string)($_SESSION['flash_msg']  ?? '');
$flashDiag = (string)($_SESSION['flash_diag'] ?? '');
unset($_SESSION['flash_msg'], $_SESSION['flash_diag']);
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="format-detection" content="telephone=no">
<title><?= $id === 0 ? 'Neuer Eintrag' : 'Bearbeiten: „' . htmlspecialchars(trim((string)($data['Bezeichnung'] ?? '')), ENT_QUOTES, 'UTF-8') . '“' ?> – Sammlung</title>
<script src="/sammlung/assets/theme-toggle.js?v=6"></script>
<script src="/sammlung/assets/typeahead.js?v=3" defer></script>
<script src="/sammlung/assets/edit.js?v=2" defer></script>
<link rel="stylesheet" href="/sammlung/assets/nav.css?v=2">
<link rel="stylesheet" href="/sammlung/assets/edit.css?v=3">
<link rel="stylesheet" href="/sammlung/assets/base.css?v=4">
</head>



<body class="edit-page">
<a class="skip-link" href="#inhalt">Zum Inhalt springen</a>

<header class="appbar">
  <p class="appbar-titel"><a href="index.php"><?= htmlspecialchars(APP_TITLE, ENT_QUOTES, 'UTF-8') ?></a></p>
  <?= nav_appnav(['is_guest' => false, 'is_admin' => true, 'museum' => false, 'export' => true, 'params' => $params]) ?>
  <button type="button" id="themeToggle" class="btn theme-toggle appbar-icon" title="Hell/Dunkel umschalten"></button>
</header>

<main id="inhalt" tabindex="-1">
  <h1><?= $id===0 ? 'Neuer Eintrag' : 'Eintrag bearbeiten' ?></h1>

  <?php if ($flashMsg !== ''): ?>
    <div class="msg-box info" role="status">
      <?= htmlspecialchars($flashMsg, ENT_QUOTES, 'UTF-8') ?>
      <?php if ($flashDiag !== ''): ?>
        <div class="msg-diag">Diagnose: <?= htmlspecialchars($flashDiag, ENT_QUOTES, 'UTF-8') ?></div>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <?php
    // Navigationslinks: ohne Ziel deaktiviert (kein onclick nötig, edit.js fängt aria-disabled ab)
    $navLink = function (bool $aktiv, string $href, string $titel, string $inhalt): string {
        return '<a class="btn nav' . ($aktiv ? '' : ' disabled') . '" href="' . htmlspecialchars($href) . '"'
             . ($aktiv ? '' : ' aria-disabled="true" tabindex="-1"') . ' title="' . htmlspecialchars($titel) . '">' . $inhalt . '</a>';
    };
  ?>

  <!-- Speichern / Abbrechen (auf dem iPhone fest unten). KEIN data-nocheck: warnt bei ungespeicherten Änderungen -->
  <div class="edit-leiste edit-speichern">
    <a class="btn back" href="<?= htmlspecialchars($backUrl ?: $listUrl) ?>" title="Zurück zur Liste"><?= icon('back') ?> <span class="nur-gross">Zurück</span><span class="nur-klein">Abbrechen</span></a>
    <button type="submit" class="btn save" form="f" name="post_action" value="stay" title="Speichern"><?= icon('save') ?> Speichern</button>
    <button type="submit" class="btn save" form="f" name="post_action" value="next" <?= $next_id ? '' : 'disabled' ?>
            title="Speichern und zum nächsten Datensatz springen"><?= icon('save') ?> <span class="nur-gross">Speichern &amp; Nächster</span><span class="nur-klein">&amp; Weiter</span></button>
  </div>

  <!-- Navigation zwischen den Datensätzen -->
  <div class="edit-leiste edit-nav">
    <?php if ($id > 0): ?>
      <a class="btn view" data-nocheck href="<?= htmlspecialchars($viewUrl) ?>" title="Eintrag ansehen"><?= icon('eye') ?> Anzeigen</a>
    <?php endif; ?>
    <?= $navLink($id !== $first_id, $firstHref, 'Erster Datensatz', icon('back') . ' Erster') ?>
    <?= $navLink((bool)$prev_id, $prevHref, 'Vorheriger Datensatz', icon('back') . ' Vorheriger') ?>
    <?= $navLink((bool)$next_id, $nextHref, 'Nächster Datensatz', 'Nächster ' . icon('forward')) ?>
    <?= $navLink($id !== $last_id, $lastHref, 'Letzter Datensatz', 'Letzter ' . icon('forward')) ?>
  </div>

  <!-- Weitere Aktionen (auf dem iPhone eingeklappt) -->
  <details class="weitere-aktionen" open data-klein-zu>
    <summary>Weitere Aktionen</summary>
    <div>
      <button type="submit" form="f" name="action" value="dup" class="btn dup"
              title="als neuen Eintrag speichern"><?= icon('copy') ?> Als NEU speichern</button>

      <button type="submit" form="f" name="action" value="dup_keepcost" class="btn dupplus"
              title="als neuen Eintrag speichern (mit Datum/Preis/Wert)"><?= icon('copy') ?> NEU (mit Datum/Preis/Wert)</button>

      <a class="btn new" data-nocheck title="Neuer Eintrag ohne Übernahme"
         href="edit.php?<?= htmlspecialchars(http_build_query(array_merge($params, ['id'=>0,'from'=>'new']))) ?>"><?= icon('plus') ?> Neuer Eintrag (leer)</a>

      <?php $igdbQuery = $_GET; unset($igdbQuery['msg']); ?>
      <?php if ($id > 0): ?>
        <form method="post" action="edit.php?<?= htmlspecialchars(http_build_query($igdbQuery)) ?>" class="inline-form">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="igdb">
          <button type="submit" class="btn igdb" data-nocheck
                  title="Beschreibung aus IGDB importieren (summary)"><?= icon('search') ?> IGDB holen</button>
        </form>

        <button type="button" class="btn delete" data-loeschen data-nocheck
                title="Diesen Datensatz löschen"><?= icon('trash') ?> Löschen</button>
      <?php endif; ?>
    </div>
  </details>

  <form id="f" method="post">
    <?php
      foreach ($params as $k => $v) {
        if ($v !== '' && $v !== null) {
          echo '<input type="hidden" name="'.htmlspecialchars($k).'" value="'.htmlspecialchars((string)$v).'">';
        }
      }
    ?>
    <?= csrf_field() ?>
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
              $fid      = ($col === 'Barcode') ? 'barcodeInput' : 'f_' . $htmlName;   // id für <label for>

              if ($group !== $currentGroup) {
                  if ($currentGroup !== null) echo '</div></details>'; // .form-grid + Gruppe schließen
                  echo '<details class="form-group" open><summary>'.htmlspecialchars($group).'</summary><div class="form-grid">';
                  $currentGroup = $group;
              }
          ?>
            <div class="field<?= $isWide ? ' wide' : '' ?><?= $type === 'checkbox' ? ' checkbox-field' : '' ?>" data-col="<?= htmlspecialchars($htmlName) ?>">
  <label class="label" for="<?= $fid ?>"><?= htmlspecialchars($col === 'VerbautIn' ? 'Gehört zu' : $col) ?></label>

  <?php if ($type === 'textarea'): ?>

    <textarea id="<?= $fid ?>" name="<?= $htmlName ?>" rows="4"><?= htmlspecialchars($value) ?></textarea>

  <?php elseif ($type === 'select'): ?>

    <?php if ($col === 'Datentraeger'): ?>
      <select id="<?= $fid ?>" name="<?= $htmlName ?>">
        <?= get_options('Datentraeger','Datentrager',$value) ?>
      </select>

    <?php elseif ($col === 'Box'): ?>
      <select id="<?= $fid ?>" name="<?= $htmlName ?>">
        <?= get_options('Box','Box',$value) ?>
      </select>

    <?php elseif (in_array($col, ['Kategorie','Hersteller','Publisher','Verkäufer'], true)): ?>
      <?php render_lookup_search($htmlName, $col, $col, $value, $fid); ?>

    <?php elseif ($col === 'Original/Homebrew'): ?>
      <?php $sel = $value ?: 'Original'; ?>
      <select id="<?= $fid ?>" name="<?= $htmlName ?>">
        <option value="Original" <?= ($sel === 'Original' ? 'selected' : '') ?>>Original</option>
        <option value="Homebrew" <?= ($sel === 'Homebrew' ? 'selected' : '') ?>>Homebrew</option>
      </select>

    <?php elseif ($col === 'Getestet Status'): ?>
      <select id="<?= $fid ?>" name="<?= $htmlName ?>">
        <option value=""       <?= ($value === ''       ? 'selected' : '') ?>>— ungetestet —</option>
        <option value="OK"     <?= ($value === 'OK'      ? 'selected' : '') ?>>OK</option>
        <option value="Defekt" <?= ($value === 'Defekt'  ? 'selected' : '') ?>>Defekt</option>
      </select>

    <?php elseif (in_array($col, ['Verpackung Status','Anleitung Status','Datenträger Status'], true)): ?>
      <select id="<?= $fid ?>" name="<?= $htmlName ?>">
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
            $vbCurRow = db_select($conn, "SELECT Bezeichnung, Seriennummer FROM Sammlung WHERE ID = ?", 'i', [$vbCurrentId])->fetch_assoc();
            if ($vbCurRow) $vbCurrentLabel = $vbLabel($vbCurRow['Bezeichnung'], (string)($vbCurRow['Seriennummer'] ?? ''), $vbCurrentId);
        }
      ?>
      <input type="text"
             id="<?= $fid ?>"
             class="ta-input"
             autocomplete="off"
             placeholder="Tippen zum Suchen…"
             value="<?= htmlspecialchars($vbCurrentLabel) ?>"
             data-ta="sammlung" data-ta-exclude="<?= (int)$id ?>" data-ta-hidden="<?= $htmlName ?>">
      <input type="hidden" name="<?= $htmlName ?>" id="<?= $htmlName ?>" value="<?= $vbCurrentId ?>">

    <?php else: ?>
      <select id="<?= $fid ?>" name="<?= $htmlName ?>">
        <?= get_options($col, $col, $value) ?>
      </select>
    <?php endif; ?>

  <?php elseif ($type === 'checkbox'): ?>

    <input type="checkbox" id="<?= $fid ?>" name="<?= $htmlName ?>" value="1" <?= $value ? 'checked' : '' ?>>

  <?php elseif ($type === 'number'): ?>

    <input type="number" id="<?= $fid ?>" name="<?= $htmlName ?>" value="<?= htmlspecialchars($value) ?>" inputmode="numeric">

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
        <button type="button" class="btn secondary" id="startScanBtn"><?= icon('camera') ?> Scannen</button>
        <button type="button" class="btn secondary" id="stopScanBtn" hidden><?= icon('close') ?> Scan beenden</button>
        <button type="button" class="btn secondary" id="torchBtn" hidden><?= icon('flash') ?><span id="torchLabel">Licht an</span></button>
      </div>

      <div id="barcodeScanWrap" class="barcode-scan-wrap" hidden>
        <video id="barcodeVideo" autoplay playsinline muted></video>
        <div class="barcode-hint">Barcode mittig vor die Kamera halten.</div>
        <button type="button" class="btn secondary" id="captureBtn"><?= icon('camera') ?> Foto aufnehmen &amp; auswerten</button>
      </div>
      <canvas id="barcodeCanvas" hidden></canvas>

      <div id="barcodeStatus" class="barcode-status" aria-live="polite"></div>
    </div>

  <?php elseif ($col === 'Einkaufsdatum'): ?>

    <?php
      $ekdRaw = trim((string)$value);
      $ekdDisplay = '';
      if ($ekdRaw !== '' && $ekdRaw !== '0000-00-00') {
          $ekdTs = strtotime($ekdRaw);
          $ekdDisplay = $ekdTs ? date('d.m.Y', $ekdTs) : $ekdRaw;
      }
    ?>
    <input type="text"
           id="<?= $fid ?>"
           name="<?= $htmlName ?>"
           value="<?= htmlspecialchars($ekdDisplay) ?>"
           inputmode="numeric"
           autocomplete="off"
           placeholder="TT.MM.JJJJ"
           pattern="\d{1,2}\.\d{1,2}\.\d{4}"
           title="Datum als TT.MM.JJJJ">

  <?php elseif ($col === 'SammlungBild1'): ?>

    <input type="text" id="<?= $fid ?>" name="<?= $htmlName ?>" value="<?= htmlspecialchars((string)$value) ?>" autocomplete="off"
           placeholder="Dateiname oder Pfad unter wp-content/uploads, z. B. 2014/11/bild.jpg (volle URL geht auch)">

  <?php elseif ($col === 'Einkaufspreis' || $col === 'Wert'): ?>

    <?php $geldVal = parse_decimal($value); $geldVal = ($geldVal !== null) ? fmt_dec($geldVal) : (string)$value; ?>
    <input type="text" id="<?= $fid ?>" name="<?= $htmlName ?>" value="<?= htmlspecialchars($geldVal) ?>" inputmode="decimal" autocomplete="off" placeholder="z. B. 12,50">

  <?php else: ?>

    <input type="<?= $type ?>" id="<?= $fid ?>" name="<?= $htmlName ?>" value="<?= htmlspecialchars($value) ?>">

  <?php endif; ?>
</div>
<?php endforeach; ?>
          </div></details><!-- .form-grid + letzte reguläre Gruppe schließen -->

          <?php
            $erstellt  = !empty($data['Erstellt_am'])  ? date('d.m.Y H:i:s', strtotime($data['Erstellt_am']))   : '';
            $geaendert = !empty($data['Geaendert_am']) ? date('d.m.Y H:i:s', strtotime($data['Geaendert_am'])) : '';
          ?>
          <details class="form-group" open>
            <summary>Verwaltung</summary>
            <div class="form-grid">
              <div class="field readonly">
                <label class="label" for="f_erstellt">Erstellt am</label>
                <input type="text" id="f_erstellt" value="<?= htmlspecialchars($erstellt) ?>" readonly tabindex="-1">
              </div>
              <div class="field readonly">
                <label class="label" for="f_geaendert">Geändert am</label>
                <input type="text" id="f_geaendert" value="<?= htmlspecialchars($geaendert) ?>" readonly tabindex="-1">
              </div>
            </div>
          </details>
        </div>
      </div>

      <?php if (image_parse($data['SammlungBild1'] ?? '')): ?>
        <div class="image"><?= image_img($data['SammlungBild1'] ?? '', (string)($data['Bezeichnung'] ?? ''), ['widths' => [320, 640], 'sizes' => '(max-width: 767px) 100vw, 480px']) ?></div>
      <?php endif; ?>
    </div><!-- .container -->
  </form>

<?php if ($id > 0): ?>
<?php
  // Filterkontext für den Rücksprung nach index.php mitgeben
  $deleteParams = $params;
  foreach (['filter'=>$filter, 'kat'=>($kat > 0 ? (string)$kat : ''), 'inkl'=>($inkl ? '1' : ''), 'oh'=>$oh, 'material'=>$material, 'q'=>$q] as $k=>$v) {
      if ($v !== '') $deleteParams[$k] = $v;
  }
  $deleteParams['id'] = $id;
?>
<!-- Löschen bestätigen (POST mit CSRF an delete.php) -->
<dialog id="loeschDialog" class="loesch-dialog" aria-labelledby="loeschTitel">
  <form method="post" action="delete.php">
    <?= csrf_field() ?>
    <?php foreach ($deleteParams as $dk => $dv): ?>
      <input type="hidden" name="<?= htmlspecialchars((string)$dk) ?>" value="<?= htmlspecialchars((string)$dv) ?>">
    <?php endforeach; ?>
    <h2 id="loeschTitel">Eintrag löschen?</h2>
    <p>„<?= htmlspecialchars((string)($data['Bezeichnung'] ?? ''), ENT_QUOTES, 'UTF-8') ?>“ wird unwiderruflich gelöscht.</p>
    <div class="loesch-knoepfe">
      <button type="button" class="btn" data-loeschen-zu>Abbrechen</button>
      <button type="submit" class="btn delete" data-nocheck><?= icon('trash') ?> Löschen</button>
    </div>
  </form>
</dialog>
<?php endif; ?>
</main>
</body>
</html>