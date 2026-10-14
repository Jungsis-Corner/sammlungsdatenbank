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
 * @param array $f     Filterwerte: filter ('spiel', 'spiel_original', 'spiel_homebrew' oder Kategorie-ID exakt),
 *                     kat (Kategorie-ID; mit inkl=1 samt allen Unterkategorien über den Pfad), inkl, oh, material,
 *                     q, hersteller, publisher, verkaeufer, standort, box, verkauf (bool/'1'), einkaufsdatum (YYYY-MM-DD)
 * @param array $alias Tabellen-Aliase im jeweiligen Query: s = Sammlung, k = Kategorie, m = Material
 * @return array ['sql' => 'WHERE …' oder '', 'types' => 'ssi…', 'values' => [...]]
 */
function sammlung_filter_where(array $f, array $alias = ['s' => 'Sammlung', 'k' => 'Kategorie', 'm' => 'Material']): array {
    $s = $alias['s']; $k = $alias['k']; $m = $alias['m'];
    $w = []; $types = ''; $vals = [];

    $filter = (string)($f['filter'] ?? '');
    if     ($filter === 'spiel')          $w[] = "$k.Ebene = 'spiele'";
    elseif ($filter === 'spiel_original') $w[] = "$k.Ebene = 'spiele' AND $s.`Original/Homebrew`='Original'";
    elseif ($filter === 'spiel_homebrew') $w[] = "$k.Ebene = 'spiele' AND $s.`Original/Homebrew`='Homebrew'";
    elseif (is_numeric($filter))          { $w[] = "$s.Kategorie = ?"; $types .= 'i'; $vals[] = intval($filter); }

    // Kategorie-Knoten: exakt oder (inkl=1) mit allen Unterkategorien (Pfad beginnt mit dem Pfad des Knotens)
    $kat = intval($f['kat'] ?? 0);
    if ($kat > 0) {
        if (!empty($f['inkl'])) {
            $w[] = "$k.Pfad LIKE CONCAT((SELECT kp.Pfad FROM Kategorie kp WHERE kp.ID = ?), '%')";
        } else {
            $w[] = "$s.Kategorie = ?";
        }
        $types .= 'i'; $vals[] = $kat;
    }

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
    if ($box !== '' && ctype_digit($box)) { $w[] = "$s.Box = ?"; $types .= 'i'; $vals[] = (int)$box; }

    $verkauf = $f['verkauf'] ?? false;
    if ($verkauf === true || $verkauf === '1' || $verkauf === 1) $w[] = "$s.`Zum Verkauf` = 1";

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

/**
 * Sortierausdruck für die Box in natürlicher Reihenfolge (Box 2 vor Box 10, andere Namen wie "Regal" am Ende).
 * $alias = Alias der gejointen Tabelle Box (nur feste Namen aus dem Code, nie aus Benutzereingaben).
 */
function box_sort_sql(string $alias): string {
    return "CASE WHEN $alias.`Box` IS NULL THEN NULL WHEN $alias.`Box` REGEXP '^Box [0-9]+\$' THEN CAST(SUBSTRING($alias.`Box`, 5) AS UNSIGNED) ELSE 999999999 END";
}

/**
 * Kategorie-Baum als flache Liste in Anzeigereihenfolge (Kinder je Ebene alphabetisch).
 * @return array<int,array{id:int,name:string,depth:int,parent:?int,ebene:string,kinder:int}>
 */
function kategorie_baum(mysqli $conn): array {
    $nodes = []; $kids = [];
    $res = $conn->query("SELECT `ID`, `Kategorie`, `ParentID`, `Ebene` FROM `Kategorie`");
    while ($r = $res->fetch_assoc()) {
        $id = (int)$r['ID'];
        $nodes[$id] = ['id' => $id, 'name' => (string)$r['Kategorie'], 'parent' => $r['ParentID'] === null ? null : (int)$r['ParentID'], 'ebene' => (string)$r['Ebene']];
        $kids[$r['ParentID'] === null ? 0 : (int)$r['ParentID']][] = $id;
    }
    foreach ($kids as &$list) {
        usort($list, fn($a, $b) => strcasecmp($nodes[$a]['name'], $nodes[$b]['name']));
    }
    unset($list);
    $out = [];
    $walk = function (int $parent, int $depth) use (&$walk, &$out, $nodes, $kids) {
        foreach ($kids[$parent] ?? [] as $id) {
            $n = $nodes[$id]; $n['depth'] = $depth; $n['kinder'] = count($kids[$id] ?? []);
            $out[] = $n;
            $walk($id, $depth + 1);
        }
    };
    $walk(0, 0);
    return $out;
}

/** Erlaubte Seitengrößen der Liste. */
const PAGE_SIZES = [25, 50, 100];

/**
 * Seitengröße der Liste: ?pp=… (nur 25, 50, 100), sonst der Cookie "pp", sonst 25.
 * index.php setzt den Cookie, damit Liste, Rückkehr aus Detail/Bearbeiten und delete.php dieselbe Größe benutzen.
 */
function page_size(): int {
    foreach ([$_GET['pp'] ?? null, $_COOKIE['pp'] ?? null] as $v) {
        if (is_string($v) && ctype_digit($v) && in_array((int)$v, PAGE_SIZES, true)) return (int)$v;
    }
    return 25;
}
