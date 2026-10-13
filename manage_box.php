<?php
$cfg = [
    'table'     => 'Box',
    'column'    => 'Box',
    'self'      => 'manage_box.php',
    'title'     => 'Boxen verwalten',
    'th'        => 'Box',
    'search_ph' => 'Boxen suchen…',
    'name_ph'   => 'Name der Box (z. B. Box 14)',
    // natürliche Reihenfolge: Box 2 vor Box 10, andere Namen (z. B. Regal) am Ende
    'order'     => "(`Box` NOT REGEXP '^Box [0-9]+\$'), CAST(SUBSTRING(`Box`, 5) AS UNSIGNED), `Box`",
];
require __DIR__ . '/inc_manage_lookup.php';
