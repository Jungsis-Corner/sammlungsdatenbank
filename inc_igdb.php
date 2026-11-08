<?php
/**
 * IGDB (Spielbeschreibung) und Übersetzung (DeepL, LibreTranslate) für den Knopf „IGDB holen“ in edit.php.
 * Zugangsdaten kommen aus der Umgebung oder den Konstanten in config.php. Diagnose sammelt $IGDB_DIAG
 * (nur für die einmalige Session-Meldung und das Log, nie in die URL).
 * Muss auf oberster Ebene eingebunden werden, damit $IGDB_DIAG global ist.
 */

// --- IGDB Diagnose gesammelt hier rein ---
$IGDB_DIAG = [];
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

/**
 * Aktion „IGDB holen“ (POST action=igdb): holt die Beschreibung, übersetzt sie, speichert sie und leitet zurück
 * (Meldung und Diagnose einmalig über die Session). $data = geladener Datensatz oder null.
 */
function igdb_import_action(mysqli $conn, int $id, ?array $data): void {
    require_post_csrf();
    // Sicherheitsnetz: Stelle sicher, dass $data existiert
    if (!isset($data) || $id <= 0) {
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
