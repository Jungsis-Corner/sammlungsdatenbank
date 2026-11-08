#!/usr/bin/env bash
# Unit-Tests (PHPUnit) für inc_format, inc_filter, inc_image und inc_security.
# Läuft gegen eine Kopie der inc_*.php in einem Temp-Ordner mit leerer Test-config.php:
# die echte config.php wird nie gelesen oder kopiert. Keine Datenbank nötig.
# Aufruf: tests/unit.sh [PHPUnit-Argumente, z. B. --filter FilterTest]
set -euo pipefail
HIER="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$(dirname "$HIER")"
PHAR="$HIER/tools/phpunit.phar"
[ -f "$PHAR" ] || { echo "tests/tools/phpunit.phar fehlt (siehe tests/README.md)"; exit 1; }
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT
for f in inc_format.php inc_filter.php inc_image.php inc_security.php inc_edit.php; do cp "$ROOT/$f" "$TMP/$f"; done
printf '<?php\n// Test-config: bewusst leer\n' > "$TMP/config.php"
mkdir "$TMP/medien" && : > "$TMP/medien/vorhanden.jpg"
export UNIT_SRC="$TMP"
php "$PHAR" --configuration "$HIER/phpunit.xml" "$@"
