<?php
// Bootstrap der Unit-Tests: lädt die inc_*.php aus der Kopie, die unit.sh angelegt hat (nie die echte config.php).
$src = getenv('UNIT_SRC');
if (!$src || !is_dir($src)) {
    fwrite(STDERR, "UNIT_SRC fehlt: Tests mit tests/unit.sh starten.\n");
    exit(1);
}
define('UNIT_SRC', rtrim($src, '/'));
foreach (['inc_format.php', 'inc_filter.php', 'inc_security.php', 'inc_edit.php'] as $datei) {
    require_once UNIT_SRC . '/' . $datei;
}
