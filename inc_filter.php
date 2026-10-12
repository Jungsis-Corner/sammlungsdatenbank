<?php
/**
 * Gemeinsamer Filter für die Sammlungsliste (index, view, edit, export, delete).
 * Baut den WHERE-Teil mit Platzhaltern (?) und liefert die Werte zum Binden,
 * damit keine Benutzereingaben im SQL-String landen.
 */

/** Maskiert LIKE-Platzhalter (% _ \) im Suchtext, damit er wörtlich gesucht wird. */
function like_escape(string $s): string {
    return addcslashes($s, "\\%_");
}

/**
 * @param array $f     Filterwerte: filter, oh, material, q, hersteller, publisher, verkaeufer,
 *                     standort, box, verkauf (bool/'1'), einkaufsdatum (YYYY-MM-DD)
 * @param array $alias Tabellen-Aliase im jeweiligen Query: s = Sammlung, k = Kategorie, m = Material
 * @return array ['sql' => 'WHERE …' oder '', 'types' => 'ssi…', 'values' => [...]]
 */
function sammlung_filter_where(array $f, array $alias = ['s' => 'Sammlung', 'k' => 'Kategorie', 'm' => 'Material']): array {
    $s = $alias['s']; $k = $alias['k']; $m = $alias['m'];
    $w = []; $types = ''; $vals = [];

    $filter = (string)($f['filter'] ?? '');
    if     ($filter === 'spiel')          $w[] = "$k.Kategorie LIKE '%Spiel%'";
    elseif ($filter === 'spiel_original') $w[] = "$k.Kategorie LIKE '%Spiel%' AND $s.`Original/Homebrew`='Original'";
    elseif ($filter === 'spiel_homebrew') $w[] = "$k.Kategorie LIKE '%Spiel%' AND $s.`Original/Homebrew`='Homebrew'";
    elseif (is_numeric($filter))          { $w[] = "$s.Kategorie = ?"; $types .= 'i'; $vals[] = intval($filter); }

    $oh = (string)($f['oh'] ?? '');
    if     ($oh === 'Original') $w[] = "$s.`Original/Homebrew`='Original'";
    elseif ($oh === 'Homebrew') $w[] = "$s.`Original/Homebrew`='Homebrew'";

    $material = (string)($f['material'] ?? '');
    if ($material !== '') { $w[] = "$m.Material LIKE ?"; $types .= 's'; $vals[] = like_escape($material) . '%'; }

    $q = trim((string)($f['q'] ?? ''));
    if ($q !== '') {
        $w[] = "($s.Bezeichnung LIKE ? OR $s.Barcode LIKE ? OR $s.Beschreibung LIKE ?)";
        $like = '%' . like_escape($q) . '%';
        $types .= 'sss'; array_push($vals, $like, $like, $like);
    }

    foreach (['hersteller' => 'Hersteller', 'publisher' => 'Publisher', 'verkaeufer' => '`Verkäufer`', 'standort' => 'Standort'] as $key => $col) {
        $id = intval($f[$key] ?? 0);
        if ($id) { $w[] = "$s.$col = ?"; $types .= 'i'; $vals[] = $id; }
    }

    $box = trim((string)($f['box'] ?? ''));
    if ($box !== '') { $w[] = "$s.Box = ?"; $types .= 's'; $vals[] = $box; }

    $verkauf = $f['verkauf'] ?? false;
    if ($verkauf === true || $verkauf === '1' || $verkauf === 1) $w[] = "$s.`Zum Verkauf` = '1'";

    $datum = trim((string)($f['einkaufsdatum'] ?? ''));
    if ($datum !== '' && preg_match('~^\d{4}-\d{2}-\d{2}$~', $datum)) {
        $w[] = "$s.Einkaufsdatum = ?"; $types .= 's'; $vals[] = $datum;
    }

    return [
        'sql'    => $w ? 'WHERE ' . implode(' AND ', $w) : '',
        'types'  => $types,
        'values' => $vals,
    ];
}

/** Führt eine Abfrage als Prepared Statement aus und liefert das Ergebnis (mysqli_result). */
function db_select(mysqli $conn, string $sql, string $types = '', array $values = []): mysqli_result {
    $stmt = $conn->prepare($sql);
    if ($types !== '') { $stmt->bind_param($types, ...$values); }
    $stmt->execute();
    return $stmt->get_result();
}
