<?php
/**
 * Gemeinsame Oberfläche der Verwaltungsseiten (Phase 5e-2): obere Leiste, Pfad, Meldung, Seitenwechsel, Löschen-Dialog.
 * Wird von inc_manage_lookup.php, manage_kategorie.php, einstellungen.php und bildpruefung.php benutzt.
 * Setzt inc_icons.php, inc_nav.php und inc_security.php voraus (csrf_field, flash_pull).
 */
require_once __DIR__ . '/inc_icons.php';
require_once __DIR__ . '/inc_nav.php';

function admin_e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

/**
 * Obere Leiste + Pfad. $krumen: Pfad nach „Einstellungen“, z. B. [['Material', '']] (leer = aktuelle Seite).
 * $navOpt: Optionen für nav_appnav() (Standard: Admin); $unterEinstellungen=false lässt „Einstellungen“ im Pfad weg (z. B. Statistik).
 */
function admin_header(array $krumen = [], ?array $navOpt = null, bool $unterEinstellungen = true): string {
    $title = defined('APP_TITLE') ? (string)APP_TITLE : 'Sammlung';
    $out  = '<a class="skip-link" href="#inhalt">Zum Inhalt springen</a>';
    $out .= '<header class="appbar"><p class="appbar-titel"><a href="index.php">' . admin_e($title) . '</a></p>'
          . nav_appnav($navOpt ?? ['is_guest' => false, 'is_admin' => true, 'museum' => false, 'export' => true, 'params' => []])
          . '<button type="button" id="themeToggle" class="btn theme-toggle appbar-icon" title="Hell/Dunkel umschalten"></button></header>';
    $out .= '<nav class="krumen" aria-label="Pfad"><ol><li><a href="index.php">Sammlung</a></li>';
    if ($krumen) {
        if ($unterEinstellungen) $out .= '<li><a href="einstellungen.php">Einstellungen</a></li>';
        foreach ($krumen as $i => [$text, $href]) {
            $letzte = ($i === count($krumen) - 1);
            $out .= '<li>' . ($letzte || $href === '' ? '<span aria-current="page">' . admin_e($text) . '</span>' : '<a href="' . admin_e($href) . '">' . admin_e($text) . '</a>') . '</li>';
        }
    } else {
        $out .= '<li><span aria-current="page">Einstellungen</span></li>';
    }
    return $out . '</ol></nav>';
}

/** Einmalige Meldung (Session-Flash) als Hinweisleiste. */
function admin_flash(): string {
    $f = flash_pull();
    if (!$f) return '';
    return '<div class="msg-box ' . admin_e($f['type']) . '" role="' . ($f['type'] === 'error' ? 'alert' : 'status') . '">' . admin_e($f['text']) . '</div>';
}

/** Seitenwechsel: Zurück/Weiter, Seitenzahlen im Fenster, „Seite X von Y“. */
function admin_pagination(int $page, int $pages, array $params): string {
    if ($pages <= 1) return '';
    $link = fn(int $p, string $text, string $extra = '') => '<a href="?' . admin_e(http_build_query(array_merge($params, ['page' => $p]))) . '"' . $extra . '>' . $text . '</a>';
    $out = '<nav class="pagination" aria-label="Seiten"><div class="pg-zeile">';
    $out .= $page > 1 ? $link($page - 1, '‹ Zurück', ' rel="prev"') : '<span class="pg-aus">‹ Zurück</span>';
    $out .= '<span class="pg-info">Seite ' . $page . ' von ' . $pages . '</span>';
    $out .= $page < $pages ? $link($page + 1, 'Weiter ›', ' rel="next"') : '<span class="pg-aus">Weiter ›</span>';
    $out .= '</div><div class="pg-zahlen">';
    for ($i = max(1, $page - 2); $i <= min($pages, $page + 2); $i++) {
        $out .= $i === $page ? '<strong aria-current="page">' . $i . '</strong>' : $link($i, (string)$i);
    }
    return $out . '</div></nav>';
}

/**
 * Löschen-Dialog (ein Dialog je Seite). Die Knöpfe `button[data-loeschen]` tragen data-id, data-name, data-sperre (Text, wenn Löschen gesperrt ist)
 * und data-hinweis (Text, wenn gelöscht werden darf, aber Verknüpfungen entfallen). verwaltung.js füllt den Dialog.
 * $action: Ziel des POST (mit q/page), $feld: Name des Formularfelds mit der ID (immer „delete“).
 */
function admin_loesch_dialog(string $action): string {
    return '<dialog id="loeschDialog" class="v-dialog" aria-labelledby="loeschTitel">'
        . '<form method="post" action="' . admin_e($action) . '">' . csrf_field()
        . '<input type="hidden" name="delete" id="loeschId" value="">'
        . '<h2 id="loeschTitel">Eintrag löschen?</h2>'
        . '<p id="loeschText"></p>'
        . '<div class="v-dialog-knoepfe"><button type="button" class="btn" data-dialog-zu>Abbrechen</button>'
        . '<button type="submit" class="btn delete" id="loeschOk">' . icon('trash') . ' Löschen</button></div>'
        . '</form></dialog>';
}
