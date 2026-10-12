<?php
$cfg = [
    'table'     => 'Datentraeger',
    'column'    => 'Datentrager',   // Spalte heißt in der DB ohne "ä"
    'self'      => 'manage_datentraeger.php',
    'title'     => 'Datenträger verwalten',
    'th'        => 'Datenträger',
    'search_ph' => 'Datenträger suchen…',
    'name_ph'   => 'Name des Datenträgers',
];
require __DIR__ . '/inc_manage_lookup.php';
