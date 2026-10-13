# Migrationen

Schemaänderungen liegen hier als nummerierte SQL-Dateien. Sie werden **von Hand in phpMyAdmin** eingespielt.
Claude Code führt keine Migration gegen die Live-Datenbank aus.

## Namen und Reihenfolge

`JJJJ-MM-TT_NN_beschreibung.sql`, zum Beispiel `2026-10-14_01_kategorie_hierarchie.sql`. Die Dateien werden in der
Reihenfolge des Namens eingespielt. `0000_login_log.sql` ist Altbestand aus der Zeit vor der Nummerierung.

## Aufbau jeder Migration

1. Kopfkommentar: Zweck, Voraussetzungen, Hinweis auf Wartungsmodus, falls Code und Datenbank gleichzeitig wechseln müssen.
2. Die Änderungen, möglichst **wiederholbar** (`IF NOT EXISTS`, `INSERT IGNORE`), damit nichts doppelt läuft.
3. Am Ende der Eintrag in `schema_migrations`:
   `INSERT IGNORE INTO schema_migrations (Dateiname) VALUES ('<dieser-dateiname>.sql');`
4. Eine **auskommentierte `-- DOWN`-Sektion** mit dem Rückbau.

`schema.sql` bildet immer den aktuellen Stand ab (komplette Struktur, plus die Liste der als eingespielt
markierten Migrationen am Ende). Jede neue Migration ändert deshalb auch `schema.sql`.

## Einspielen

1. **Dump** der Datenbank in phpMyAdmin (Exportieren, Schnell, SQL).
2. Möglichst vorher auf einer **lokalen Kopie** testen.
3. Bei Umbauten, bei denen Code und Datenbank gleichzeitig wechseln müssen, den **Wartungsmodus** einschalten (siehe unten).
4. In phpMyAdmin den Inhalt der Datei im Reiter „SQL“ ausführen.
5. Kontrolle: `SELECT * FROM schema_migrations ORDER BY Dateiname;` zeigt die Datei jetzt.
6. Dateien per FileZilla hochladen (Verzeichnisvergleich), Stichprobe, Wartungsmodus beenden.

Bei Problemen: die `DOWN`-Sektion der Migration ausführen, die vorherigen Dateien wiederherstellen und
bei Bedarf den Dump einspielen.

## Frischinstallation

`schema.sql` importieren. Sie legt alle Tabellen an und markiert die vorhandenen Migrationen am Ende als eingespielt,
es ist also nichts weiter einzuspielen.

## Bestehende Installation ohne `schema_migrations`

`2026-10-13_02_schema_migrations.sql` einspielen. Sie legt die Tabelle an und trägt `0000_login_log.sql` und
`2026-10-13_01_login_versuche.sql` nach, aber nur, wenn die jeweilige Tabelle (`Login_Log`, `Login_Versuche`) bereits existiert.
Ältere Migrationen, die danach fehlen, spielst du nach.

## Wartungsmodus

Liegt die Datei `maintenance.flag` im App-Ordner (neben `index.php`), antwortet die App für Besucher und Gäste mit
**503** und einer Hinweisseite. Angemeldete **Admins** kommen weiter durch (die Prüfung läuft serverseitig über die
Admin-Session), damit nach der Migration getestet werden kann. `login.php` und `logout.php` bleiben erreichbar.

- Einschalten: leere Datei `maintenance.flag` per FileZilla im App-Ordner anlegen.
- Ausschalten: Datei löschen.
- Die Datei steht in `.gitignore` und ist per `.htaccess` gesperrt.

Pflicht ist der Wartungsmodus bei Änderungen, bei denen Code und Datenbank gleichzeitig wechseln müssen
(Geldfelder D6, Kategorie-Hierarchie D9).
