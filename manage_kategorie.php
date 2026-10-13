<?php
$cfg = [
    'table'     => 'Kategorie',
    'column'    => 'Kategorie',
    'self'      => 'manage_kategorie.php',
    'title'     => 'Kategorien verwalten',
    'th'        => 'Kategorie',
    'search_ph' => 'Kategorien suchen…',
    'name_ph'   => 'Name der Kategorie',
    'merge'     => false,   // IDs werden in Links benutzt (go.php, Blog); Struktur regelt D9
];
require __DIR__ . '/inc_manage_lookup.php';
