<?php
/**
 * Bearbeiten-Formular (edit.php): Feldliste, Formularwerte einsammeln, Duplikat, Speichern.
 * Die Seite selbst (edit.php) lädt nur noch den Datensatz und gibt das Formular aus.
 * Reine Funktionen (edit_collect_values, edit_duplicate_values, edit_bind_types) sind in tests/unit/EditTest.php getestet.
 */

/**
 * Felder des Formulars in Anzeigereihenfolge (Spalte => Art). Keine Zeitstempel-Felder hier.
 * Das Formular benutzt Spaltennamen mit Leerzeichen als Unterstrich im HTML-Namen (Zum Verkauf = Zum_Verkauf).
 */
const EDIT_FIELDS = [
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
  'SammlungBild1'     => 'text',
];

/** Selects, die numerische IDs speichern (Fremdschlüssel); leere Auswahl wird NULL. */
const EDIT_FK_COLS = [
    'Hersteller', 'Publisher', 'Kategorie', 'Zustand', 'Verpackung',
    'Datentraeger', 'Material', 'Standort', 'Verkäufer', 'VerbautIn', 'Box',
];

/** Felder mit Such-Textfeld: die ID wird notfalls aus dem sichtbaren Text nachgeschlagen. */
const EDIT_LOOKUP_SEARCH_COLS = ['Kategorie', 'Hersteller', 'Publisher', 'Verkäufer'];

/** Typen-String für bind_param in der Reihenfolge von EDIT_FIELDS (i = Ganzzahl/Fremdschlüssel, sonst s). */
function edit_bind_types(): string {
    $t = '';
    foreach (EDIT_FIELDS as $col => $kind) {
        $t .= ($kind === 'number' || in_array($col, EDIT_FK_COLS, true)) ? 'i' : 's';
    }
    return $t;
}

/**
 * Liest die Formularwerte (aus $_POST) in die Spalten der Tabelle Sammlung.
 * Leere Fremdschlüssel und Jahr ergeben NULL (nie 0), Geld per parse_decimal(), Datum als YYYY-MM-DD,
 * Bild als relativer Pfad. $lookupId($tabelle, $spalte, $idRoh, $textRoh) löst Suchfelder ohne ID auf.
 * @return array<string,mixed> Spalte => Wert, in der Reihenfolge von EDIT_FIELDS
 */
function edit_collect_values(array $post, callable $lookupId): array {
    $vals = [];
    foreach (EDIT_FIELDS as $col => $kind) {
        $name = str_replace(' ', '_', $col); // HTML-Name wie im Formular

        if ($kind === 'checkbox') {
            // Checkbox => 1 wenn gesetzt, sonst 0
            $vals[$col] = isset($post[$name]) ? 1 : 0;
            continue;
        }

        if ($kind === 'number') {
            $vals[$col] = (int)($post[$name] ?? 0);
            continue;
        }

        if ($kind === 'date') {
            $raw = trim((string)($post[$name] ?? ''));
            // Akzeptiere dd.mm.yyyy oder yyyy-mm-dd, speichere als yyyy-mm-dd
            if ($raw === '') {
                $vals[$col] = '';
            } elseif (preg_match('~^\d{1,2}\.\d{1,2}\.\d{4}$~', $raw)) {
                [$d, $m, $y] = explode('.', $raw);
                $vals[$col] = sprintf('%04d-%02d-%02d', (int)$y, (int)$m, (int)$d);
            } else {
                // versuche strtotime-Fallback
                $ts = strtotime($raw);
                $vals[$col] = $ts ? date('Y-m-d', $ts) : '';
            }
            continue;
        }

        if ($kind === 'select') {
            if ($col === 'Original/Homebrew') {
                // Dropdown mit „Original | Homebrew“, Default = Original
                $v = trim((string)($post[$name] ?? ''));
                $vals[$col] = ($v === 'Homebrew') ? 'Homebrew' : 'Original';
            } elseif (in_array($col, EDIT_FK_COLS, true)) {
                // klassische FK-Selects -> als int
                if (in_array($col, EDIT_LOOKUP_SEARCH_COLS, true)) {
                    // Such-Textfelder: ID notfalls aus dem sichtbaren Text nachschlagen
                    $vals[$col] = $lookupId($col, $col, $post[$name] ?? '', $post[$name . '_text'] ?? '');
                } else {
                    $vals[$col] = (int)($post[$name] ?? 0);
                }
                // Keine Auswahl = NULL (nicht 0): "keine Angabe" für spätere Fremdschlüssel
                if ((int)$vals[$col] <= 0) $vals[$col] = null;
            } else {
                // übrige selects als string
                $vals[$col] = (string)($post[$name] ?? '');
            }
            continue;
        }

        // Jahr (Zahlenspalte): leer, 0 oder keine ganze Zahl = NULL (nicht 0)
        if ($col === 'Jahr') {
            $t = trim((string)($post[$name] ?? ''));
            $vals[$col] = (ctype_digit($t) && (int)$t > 0 && (int)$t <= 9999) ? (int)$t : null;
            continue;
        }

        // Bild: eingefügte WordPress-URL als relativen Pfad speichern
        if ($col === 'SammlungBild1') {
            $vals[$col] = image_normalize_for_storage($post[$name] ?? '');
            continue;
        }

        // Geldbeträge (DECIMAL): Komma/Punkt, leer oder ungültig = NULL
        if ($col === 'Einkaufspreis' || $col === 'Wert') {
            $vals[$col] = parse_decimal($post[$name] ?? '');
            continue;
        }

        // text/textarea oder alles andere als String
        $vals[$col] = (string)($post[$name] ?? '');
    }
    return $vals;
}

/**
 * Werte für ein Duplikat: Titel mit „(Kopie)“ bzw. „(Kopie N)“, ohne Bild, Testdaten und „Zum Verkauf“.
 * $action 'dup' leert zusätzlich Einkaufsdatum, Einkaufspreis und Wert; 'dup_keepcost' behält sie.
 */
function edit_duplicate_values(array $vals, string $action): array {
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
    return $vals;
}

/**
 * Führt INSERT/UPDATE auf Sammlung aus. Fehler 1452 (Fremdschlüssel: ungültige Auswahl, z. B. gelöschter
 * Eintrag oder manipuliertes Formular) wird als Hinweis gezeigt statt als Datenbankfehler.
 */
function execute_sammlung(mysqli_stmt $stmt, int $id): void {
    try {
        $stmt->execute();
    } catch (mysqli_sql_exception $e) {
        if ((int)$e->getCode() !== 1452) { throw $e; }
        // gleiche Session-Schlüssel wie die IGDB-Meldung (wird in edit.php angezeigt und gelöscht)
        $_SESSION['flash_msg']  = 'Nicht gespeichert: Eine Auswahl (z. B. Kategorie, Box oder „Gehört zu“) ist ungültig. Bitte neu wählen.';
        $_SESSION['flash_diag'] = '';
        $keep = array_merge(['id' => $id], filter_keep_params($_POST));
        header('Location: edit.php?' . http_build_query($keep));
        exit;
    }
}

/** ID aus Such-Textfeld ermitteln, falls das versteckte ID-Feld leer ankommt: 1) Hidden-ID, 2) "(#ID)" im Text, 3) exakter Name. */
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

/** Legt einen Eintrag an und liefert die neue ID. $kontextId = ID für den Rücksprung bei ungültiger Auswahl. */
function edit_insert(mysqli $conn, array $vals, int $kontextId): int {
    $cols = array_map(fn($c) => "`$c`", array_keys($vals));
    $ph   = array_fill(0, count($vals), '?');
    $stmt = $conn->prepare('INSERT INTO `Sammlung` (' . implode(',', $cols) . ') VALUES (' . implode(',', $ph) . ')');
    $stmt->bind_param(edit_bind_types(), ...array_values($vals));
    execute_sammlung($stmt, $kontextId);
    return (int)$conn->insert_id;
}

/** Ändert den Eintrag $id. */
function edit_update(mysqli $conn, array $vals, int $id): void {
    $sets = array_map(fn($c) => "`$c` = ?", array_keys($vals));
    $stmt = $conn->prepare('UPDATE `Sammlung` SET ' . implode(', ', $sets) . ' WHERE `ID` = ?');
    $stmt->bind_param(edit_bind_types() . 'i', ...array_merge(array_values($vals), [$id]));
    execute_sammlung($stmt, $id);
}

/**
 * Verarbeitet das Formular (POST, außer IGDB): Speichern (neu oder ändern), „Speichern & Nächster“ und Duplizieren.
 * Prüft das CSRF-Token und leitet danach (mit Filter-Kontext) zurück zu edit.php; kehrt nie zurück.
 */
function edit_handle_post(mysqli $conn, int $id): void {
    require_post_csrf();
    $vals       = edit_collect_values($_POST, 'resolve_lookup_id');
    $postAction = $_POST['post_action'] ?? 'stay';    // 'stay' | 'next'
    $action     = $_POST['action']      ?? 'save';    // 'save' | 'dup' | 'dup_keepcost'

    if ($action === 'dup' || $action === 'dup_keepcost') {
        $newId = edit_insert($conn, edit_duplicate_values($vals, $action), $id);
        $keep = filter_keep_params($_POST);
        $keep['id']   = $newId;
        $keep['from'] = 'dup';
        header('Location: edit.php?' . http_build_query($keep));
        exit;
    }

    // Normales Speichern: INSERT (id==0) oder UPDATE (id>0)
    if ($id === 0) {
        $id = edit_insert($conn, $vals, $id);
    } else {
        edit_update($conn, $vals, $id);
    }

    $baseParams = filter_keep_params($_POST);

    // „Speichern & Nächster“
    if ($postAction === 'next') {
        $nid = (int)($_POST['next_id'] ?? 0);
        if ($nid > 0 && $nid !== $id) {
            header('Location: edit.php?' . http_build_query(array_merge($baseParams, ['id' => $nid])));
            exit;
        }
    }

    // Default: auf aktuellen Datensatz bleiben
    header('Location: edit.php?' . http_build_query(array_merge($baseParams, ['id' => $id])));
    exit;
}

/**
 * Datensatz für das Formular: bei id > 0 die Zeile aus Sammlung (oder null, wenn es sie nicht gibt), bei id = 0 ein leerer Eintrag,
 * vorbefüllt aus den GET-Parametern pf_* (z. B. aus einkauf.php übernommen).
 */
function edit_load_record(mysqli $conn, int $id, array $get): ?array {
    $data = [];
    if ($id > 0) {
        return db_select($conn, "SELECT * FROM `Sammlung` WHERE ID = ?", 'i', [$id])->fetch_assoc();
    }
    if ($id === 0) {
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
            if (isset($get[$getKey]) && $get[$getKey] !== '') {
                $data[$col] = $get[$getKey];
            }
        }
    }
    return $data;
}

/**
 * Vorheriger, nächster, erster und letzter Eintrag der gefilterten, sortierten Liste (IDs oder null).
 * $f = Filterwerte für sammlung_filter_where(); Sortierung wie in index.php (stabil: Titel, dann ID).
 * @return array{prev:?int,next:?int,first:?int,last:?int}
 */
function edit_neighbors(mysqli $conn, int $id, array $f, string $sort, string $dir): array {
    // Mapping für ORDER BY — analog index.php
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
      'Link zu YouTube'     => 'S.`Link zu YouTube`',
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

    // stabile Zweitsortierung; ID als letzter Schlüssel: gleiche Titel bleiben in fester Reihenfolge (Liste, Detail, Bearbeiten, Export)
    $order_by = $order_by_core . ', S.Bezeichnung ASC, S.ID ASC';

    // WHERE-Klausel (gemeinsam in inc_filter.php, mit Prepared Statements)
    $flt = sammlung_filter_where($f, ['s' => 'S', 'k' => 'K', 'm' => 'M']);

    // gleiche JOINs wie index.php
    $idres = db_select($conn, "
      SELECT S.ID
      FROM Sammlung S
      LEFT JOIN Kategorie   K  ON S.Kategorie   = K.ID
      LEFT JOIN Material    M  ON S.Material    = M.ID
      LEFT JOIN Zustand     Z  ON S.Zustand     = Z.ID
      LEFT JOIN Verpackung  VP ON S.Verpackung  = VP.ID
      LEFT JOIN Standort    ST ON S.Standort    = ST.ID
      LEFT JOIN Box         BX ON S.Box         = BX.ID
      {$flt['sql']}
      ORDER BY $order_by
    ", $flt['types'], $flt['values']);
    $ids = array_column($idres->fetch_all(MYSQLI_ASSOC), 'ID');

    $out = ['prev' => null, 'next' => null, 'first' => null, 'last' => null];
    if ($ids) {
        $out['first'] = $ids[0];
        $out['last']  = $ids[count($ids) - 1];
        $pos = array_search($id, $ids); // nicht strict
        if ($pos !== false) {
            $out['prev'] = $ids[$pos - 1] ?? null;
            $out['next'] = $ids[$pos + 1] ?? null;
        }
    }
    return $out;
}
