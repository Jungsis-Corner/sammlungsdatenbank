<?php
$cfg = [
    'table'     => 'Verkäufer',     // Tabelle und Spalte mit Umlaut
    'column'    => 'Verkäufer',
    'post'      => 'verkaeufer',    // Formularfeld ohne Umlaut
    'self'      => 'manage_verkaeufer.php',
    'title'     => 'Verkäufer verwalten',
    'th'        => 'Verkäufer',
    'search_ph' => 'Verkäufer suchen…',
    'name_ph'   => 'Name des Verkäufers',
];
require __DIR__ . '/inc_manage_lookup.php';
