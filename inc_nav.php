<?php
/**
 * Navigation der Anwendung (Phase 5b, Rollout 3).
 *
 *   nav_links($o)        Menüpunkte als Array [['label','href','icon'], …] (Statistik, Export, Einstellungen, Abmelden, …)
 *   nav_appnav($o)       <nav> der oberen Leiste (ab 600 px sichtbar)
 *   nav_bottom($o)       untere Leiste auf dem iPhone (unter 600 px) mit „Mehr“-Dialog
 *
 * Optionen $o: is_guest, is_admin, museum, export (bool: Export erlaubt), params (Filterparameter für Export und „Hinzufügen“),
 *              neu_href (Link „Hinzufügen“ oder '' ohne), active ('sammlung' hebt den ersten Punkt hervor)
 * Zugriffsregeln wie bisher: Einstellungen nur Admin, Export nur wenn erlaubt, Statistik nicht im Museum-Modus.
 */
require_once __DIR__ . '/inc_icons.php';

function nav_links(array $o): array {
    $items = [];
    if (!empty($o['museum'])) {
        $items[] = ['label' => 'Museum beenden', 'href' => '/sammlung/museum/exit.php', 'icon' => 'close'];
        return $items;
    }
    $items[] = ['label' => 'Statistik', 'href' => 'statistik.php', 'icon' => 'stats'];
    if (!empty($o['export'])) {
        $items[] = ['label' => 'Excel-Export', 'href' => 'export.php?' . http_build_query($o['params'] ?? []), 'icon' => 'export'];
    }
    if (!empty($o['is_admin'])) {
        $items[] = ['label' => 'Einstellungen', 'href' => 'einstellungen.php', 'icon' => 'settings'];
    }
    $items[] = ['label' => !empty($o['is_guest']) ? 'Zur Anmeldung' : 'Abmelden', 'href' => 'logout.php', 'icon' => 'logout'];
    return $items;
}

function nav_appnav(array $o): string {
    $e = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    $out = '<nav class="appnav" aria-label="Hauptnavigation">';
    foreach (nav_links($o) as $it) {
        $out .= '<a href="' . $e($it['href']) . '">' . icon($it['icon']) . ' ' . $e($it['label']) . '</a>';
    }
    return $out . '</nav>';
}

function nav_bottom(array $o): string {
    $e = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    $out  = '<nav class="bottomnav" aria-label="Hauptnavigation unten">';
    $out .= '<a href="' . $e($o['home_href'] ?? 'index.php') . '"' . (($o['active'] ?? '') === 'sammlung' ? ' aria-current="page"' : '') . '>' . icon('menu') . '<span>Sammlung</span></a>';
    $out .= '<button type="button" data-nav-suche>' . icon('search') . '<span>Suche</span></button>';
    if (!empty($o['neu_href'])) {
        $out .= '<a href="' . $e($o['neu_href']) . '">' . icon('plus') . '<span>Hinzufügen</span></a>';
    }
    $out .= '<button type="button" data-nav-mehr aria-haspopup="dialog" aria-controls="mehrDialog">' . icon('chevron-up') . '<span>Mehr</span></button>';
    $out .= '</nav>';
    $out .= '<dialog id="mehrDialog" class="mehr-dialog" aria-label="Mehr"><div class="mehr-liste">';
    foreach (nav_links($o) as $it) {
        $out .= '<a href="' . $e($it['href']) . '">' . icon($it['icon']) . ' ' . $e($it['label']) . '</a>';
    }
    $out .= '<button type="button" class="btn" data-nav-mehr-zu>Schließen</button></div></dialog>';
    return $out;
}
