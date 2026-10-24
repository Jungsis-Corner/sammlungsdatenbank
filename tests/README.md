# Tests (Phase 7)

Entwicklungsmaterial: **gehört nicht auf den Server** (nicht per FileZilla hochladen). Die Skripte laufen lokal gegen eine **Kopie** der App mit Test-`config.php` und einer Wegwerf-Datenbank, nie gegen die Produktion mit Zugangsdaten.

| Datei | Zweck |
| --- | --- |
| `smoke.sh` | T2: Statuscodes, Anmeldung (Admin, Gast), Weiterleitungsziele, POST ohne CSRF-Token, Gäste ohne Adminseiten, Seitengerüst, kein Inline-CSS/-JS (CSP) |
| `smoke.sh --header <URL>` | nur Sicherheitsheader einer URL (auch live): jeder genau einmal, CSP ohne `unsafe-inline`, kein Report-Only, HSTS |
| `smoke.sh --geschuetzt <Basis-URL>` | interne Dateien (`schema.sql`, `config.php`, `migrations/` …) werden nicht ausgeliefert (auch live, braucht Apache) |
| `db_checks.sql` | T3: nur lesende Datenbankprüfungen (verwaiste Verweise, 0 statt NULL, Geld, Kategorie-Pfade, Dubletten, Bilder, Struktur); auch in phpMyAdmin ausführbar |
| `filter_check.sh` | T4: Trefferzahl in Liste, Export und unabhängiger SQL-Zählung für 23 feste Filter |
| `../docs/abnahme.md` | T5/T6: manuelle Abnahme (Gerätematrix, Tastatur/VoiceOver, Lighthouse) |

## Testumgebung aufbauen (WSL, Ubuntu)

1. **MySQL-Wegwerf-Instanz** in einem Ordner außerhalb des Repos (`$T`), Port 3399:
   `mysqld --no-defaults --initialize-insecure --datadir=$T/data`, dann
   `mysqld --no-defaults --datadir=$T/data --port=3399 --bind-address=127.0.0.1 --mysqlx=OFF --socket=$T/sock --mysqlx-socket=$T/xsock --pid-file=$T/pid --log-error=$T/err.log &`.
   Client: `mysql -uroot -h127.0.0.1 -P3399 --default-character-set=utf8mb4`.
2. `SET GLOBAL sql_mode='NO_ENGINE_SUBSTITUTION'` (Live ist nicht strikt), Datenbank `d9t` mit `utf8mb4_general_ci` anlegen, **Zeitzonentabellen laden** (`mysql_tzinfo_to_sql /usr/share/zoneinfo | mysql … mysql`), sonst scheitert `SET time_zone`.
3. Dump `docs/bestand/dump.sql` (Stand vor D3) einspielen, danach die Migrationen `2026-10-13_03` bis `2026-10-14_01` in dieser Reihenfolge (jede muss ihre Kontrollzeilen mit `OK` melden). Ein frischer Live-Dump ersetzt Dump plus Migrationen.
4. **App-Kopie** ohne echte `config.php`: `git ls-files | grep -v '^docs/' | grep -v '^config.php$' | tar -cf - -T - | tar -xf - -C $T/www/sammlung`, dann eine Test-`config.php` (Datenbank `127.0.0.1`/root ohne Passwort/`d9t`, `ADMIN_PASSWORD_HASH` per `password_hash` für „geheim“, `APP_HOST`, `SESSION_COOKIE_SECURE` false, `LOGIN_HASH_SALT`, `ERROR_LOG_PATH`, `APP_TITLE`, `APP_TITLE_MUSEUM`, `APP_PWA_NAME`, `APP_PWA_SHORT_NAME`). In der **Kopie** `'Europe/Berlin'` per `sed` durch `'+02:00'` ersetzen.
5. Server: `php -d mysqli.default_port=3399 -S 127.0.0.1:8124` im Ordner `$T/www`. Der PHP-Server ignoriert `.htaccess`, deshalb gibt es die Header- und Schutzprüfungen nur gegen Apache (live).
6. Starten: `tests/smoke.sh`, `tests/filter_check.sh`, `mysql … d9t < tests/db_checks.sql`.

## Erwartete Ergebnisse (Dump 13.10.2026 plus Migrationen)

- `smoke.sh`: 0 Fehler. `filter_check.sh`: 0 Abweichungen. `db_checks.sql`: alle Zeilen `OK` oder `Info`.
- Bekannte Altlast (nur `Info`): rund 1.000 Einträge mit Datum `0000-00-00` in `Einkaufsdatum` oder `Getestet am`. Die Seiten behandeln den Wert wie „leer“. Aufräumen in D12.
- Die Filter-IDs in `filter_check.sh` (Hersteller 280, Standort 1, Box 8/9, Marke 32/36, Kategorie 57) gelten für diesen Dump; bei einer anderen Datenbank anpassen.
- Nach jeder Migration `db_checks.sql` vorher und nachher ausführen und die `Info`-Zahlen vergleichen (Einträge, Summen).
