<?php
/**
 * Icon-Satz als Inline-SVG (Phase 5, U4). Kein Icon-Font, keine externe Bibliothek.
 *
 *   <?= icon('edit') ?> Bearbeiten        Icon mit sichtbarem Text daneben (Icon ist für Screenreader versteckt)
 *   <?= icon('trash', 'Löschen') ?>        Icon allein: der zweite Parameter wird als unsichtbarer Text ergänzt
 *
 * Das Sprite mit allen Symbolen wird beim ersten Aufruf einmal in die Seite geschrieben.
 */

const ICON_PATHS = [
    'search'   => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
    'filter'   => '<path d="M3 5h18l-7 8v6l-4-2v-4z"/>',
    'plus'     => '<path d="M12 5v14M5 12h14"/>',
    'edit'     => '<path d="M4 20h4L19 9a2.8 2.8 0 0 0-4-4L4 16z"/><path d="m13.5 6.5 4 4"/>',
    'trash'    => '<path d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3"/>',
    'back'     => '<path d="M19 12H5M11 6l-6 6 6 6"/>',
    'forward'  => '<path d="M5 12h14M13 6l6 6-6 6"/>',
    'moon'     => '<path d="M20 14.5A8 8 0 0 1 9.5 4 8 8 0 1 0 20 14.5z"/>',
    'sun'      => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M2 12h2M20 12h2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
    'export'   => '<path d="M12 3v12M7 10l5 5 5-5M5 21h14"/>',
    'stats'    => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
    'menu'     => '<path d="M4 6h16M4 12h16M4 18h16"/>',
    'eye'      => '<path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
    'copy'     => '<rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15V6a2 2 0 0 1 2-2h9"/>',
    'save'     => '<path d="M5 3h11l4 4v14H5z"/><path d="M8 3v6h8M8 21v-7h8v7"/>',
    'close'    => '<path d="M6 6l12 12M18 6 6 18"/>',
    'check'    => '<path d="m5 13 4 4L19 7"/>',
    'settings' => '<circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3M4.9 4.9l2.1 2.1M17 17l2.1 2.1M4.9 19.1 7 17M17 7l2.1-2.1"/>',
    'logout'   => '<path d="M9 21H5V3h4M16 17l5-5-5-5M21 12H9"/>',
    'camera'   => '<path d="M4 8h3l2-3h6l2 3h3v11H4z"/><circle cx="12" cy="13" r="3.5"/>',
    'flash'    => '<path d="M13 2 4 14h7l-1 8 9-12h-7z"/>',
    'up'       => '<path d="M12 19V5M6 11l6-6 6 6"/>',
    'chevron-down' => '<path d="m6 9 6 6 6-6"/>',
    'chevron-up'   => '<path d="m6 15 6-6 6 6"/>',
    'tag'      => '<path d="M3 12V3h9l9 9-9 9z"/><circle cx="7.5" cy="7.5" r="1.5"/>',
    'cart'     => '<circle cx="9" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/><path d="M2 3h3l3 12h11l2-8H6"/>',
];

/** Gibt das Sprite genau einmal aus (beim ersten icon()-Aufruf). */
function icon_sprite(): string {
    static $done = false;
    if ($done) return '';
    $done = true;
    $out = '<svg class="ico-sprite" aria-hidden="true" focusable="false" xmlns="http://www.w3.org/2000/svg">';
    foreach (ICON_PATHS as $name => $paths) {
        $out .= '<symbol id="i-' . $name . '" viewBox="0 0 24 24">' . $paths . '</symbol>';
    }
    return $out . '</svg>';
}

/** Ein Icon. $label: nur setzen, wenn neben dem Icon kein Text steht (wird für Screenreader ergänzt). */
function icon(string $name, string $label = ''): string {
    if (!isset(ICON_PATHS[$name])) return '';
    $svg = '<svg class="ico' . ($label !== '' ? ' ico-solo' : '') . '" aria-hidden="true" focusable="false"><use href="#i-' . $name . '"/></svg>';
    if ($label !== '') {
        $svg .= '<span class="sr-only">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>';
    }
    return icon_sprite() . $svg;
}
