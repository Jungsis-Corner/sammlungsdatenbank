-- Migration D6: Geldbeträge als DECIMAL(10,2) (Phase 2)
-- ============================================================================
-- Sammlung.Wert und Sammlung.Einkaufspreis: text ("12,50") -> DECIMAL(10,2) NULL.
-- Leere Texte werden NULL. Einkauf.Preis ist schon DECIMAL.
--
-- ACHTUNG, Wartungsmodus: Code und Datenbank müssen gleichzeitig wechseln. Reihenfolge:
--   1. Dump per phpMyAdmin ziehen
--   2. Datei maintenance.flag im App-Ordner anlegen (alle Seiten antworten mit 503)
--   3. Diese Migration einspielen und die Kontrolle prüfen (alles OK)
--   4. Neuen Code hochladen (inc_format.php, edit.php, view.php, index.php, export.php,
--      statistik.php, duplicate.php, einkauf.php, einkauf_edit.php)
--   5. maintenance.flag löschen, Stichprobe
-- Wiederholbar: Ein zweiter Lauf ändert nichts (Umwandlung nur, solange die Spalten noch Text sind).
--
-- Sicherung: Tabelle bak_d6_geld (ID, alter Text von Wert und Einkaufspreis). Sie dient der
-- Kontrolle und dem DOWN und wird erst in D12 gelöscht.
-- Vorab geprüft (13.10.2026): nur ganze Zahlen oder Zahlen mit Komma und 1-2 Nachkommastellen.
-- ============================================================================

-- ---------------------------------------------------------------------------
-- 1. Prüfung: Werte, die kein einfacher Betrag sind (erwartet: 0 Zeilen)
-- ---------------------------------------------------------------------------
SELECT ID, Wert, Einkaufspreis FROM `Sammlung`
WHERE (TRIM(Wert) <> '' AND TRIM(Wert) NOT REGEXP '^[0-9]+([.,][0-9]{1,2})?$')
   OR (TRIM(Einkaufspreis) <> '' AND TRIM(Einkaufspreis) NOT REGEXP '^[0-9]+([.,][0-9]{1,2})?$');

-- ---------------------------------------------------------------------------
-- 2. Sicherung der alten Texte (bleibt beim zweiten Lauf unverändert)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `bak_d6_geld` AS SELECT `ID`, `Wert`, `Einkaufspreis` FROM `Sammlung`;

-- ---------------------------------------------------------------------------
-- 3. Texte bereinigen und Typ umstellen (nur solange beide Spalten noch Text sind)
-- ---------------------------------------------------------------------------
SET @istext = (SELECT COUNT(*) FROM information_schema.COLUMNS
               WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Sammlung'
                 AND COLUMN_NAME IN ('Wert', 'Einkaufspreis') AND DATA_TYPE IN ('text', 'varchar'));

SET @sql = IF(@istext = 2,
  'UPDATE `Sammlung` SET `Wert` = NULLIF(REPLACE(TRIM(`Wert`), '','', ''.''), ''''), `Einkaufspreis` = NULLIF(REPLACE(TRIM(`Einkaufspreis`), '','', ''.''), ''''), `Geaendert_am` = `Geaendert_am`',
  'SELECT 1');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @sql = IF(@istext = 2,
  'ALTER TABLE `Sammlung` MODIFY `Wert` DECIMAL(10,2) NULL DEFAULT NULL, MODIFY `Einkaufspreis` DECIMAL(10,2) NULL DEFAULT NULL',
  'SELECT 1');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- ---------------------------------------------------------------------------
-- 4. Eintrag in schema_migrations
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO `schema_migrations` (`Dateiname`) VALUES ('2026-10-13_06_geld.sql');

-- ---------------------------------------------------------------------------
-- Kontrolle: Alle Zeilen sollten in der Spalte Status "OK" zeigen.
-- (Die Summen werden aus den gesicherten Texten neu berechnet; Zeilen, die nach dem Einspielen
--  schon geändert wurden, können beim Wiederholen abweichen.)
-- ---------------------------------------------------------------------------
SELECT c.Pruefung, c.erwartet, c.ist, IF(c.erwartet = c.ist, 'OK', 'PRÜFEN') AS Status FROM (
  SELECT 'Typ Wert' AS Pruefung, 'decimal(10,2)' AS erwartet, (SELECT COLUMN_TYPE FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Sammlung' AND COLUMN_NAME = 'Wert') AS ist, 1 AS o
  UNION ALL
  SELECT 'Typ Einkaufspreis', 'decimal(10,2)', (SELECT COLUMN_TYPE FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Sammlung' AND COLUMN_NAME = 'Einkaufspreis'), 2
  UNION ALL
  SELECT 'Summe Wert', (SELECT CAST(SUM(CAST(REPLACE(NULLIF(TRIM(`Wert`), ''), ',', '.') AS DECIMAL(12,2))) AS CHAR) FROM `bak_d6_geld`),
         (SELECT CAST(SUM(`Wert`) AS CHAR) FROM `Sammlung`), 3
  UNION ALL
  SELECT 'Summe Einkaufspreis', (SELECT CAST(SUM(CAST(REPLACE(NULLIF(TRIM(`Einkaufspreis`), ''), ',', '.') AS DECIMAL(12,2))) AS CHAR) FROM `bak_d6_geld`),
         (SELECT CAST(SUM(`Einkaufspreis`) AS CHAR) FROM `Sammlung`), 4
  UNION ALL
  SELECT 'Anzahl Wert gefüllt', (SELECT COUNT(*) FROM `bak_d6_geld` WHERE TRIM(`Wert`) <> ''),
         (SELECT COUNT(`Wert`) FROM `Sammlung`), 5
  UNION ALL
  SELECT 'Anzahl Einkaufspreis gefüllt', (SELECT COUNT(*) FROM `bak_d6_geld` WHERE TRIM(`Einkaufspreis`) <> ''),
         (SELECT COUNT(`Einkaufspreis`) FROM `Sammlung`), 6
  UNION ALL
  SELECT 'Sammlung: Anzahl Einträge', (SELECT COUNT(*) FROM `bak_d6_geld`), (SELECT COUNT(*) FROM `Sammlung`), 7
) c ORDER BY c.o;

-- ---------------------------------------------------------------------------
-- DOWN (Rückbau): Zeilen ohne "-- " ausführen. Stellt Text mit Komma wieder her ("12.50" -> "12,50");
-- danach wird für unveränderte Beträge der exakte alte Text aus bak_d6_geld zurückgeschrieben
-- (z. B. "10" statt "10,00"; NULL bleibt NULL, früher leere Texte bleiben NULL).
-- ---------------------------------------------------------------------------
-- ALTER TABLE `Sammlung`
--   MODIFY `Wert` VARCHAR(20) COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
--   MODIFY `Einkaufspreis` VARCHAR(20) COLLATE utf8mb4_general_ci NULL DEFAULT NULL;
-- UPDATE `Sammlung` SET `Wert` = REPLACE(`Wert`, '.', ','), `Einkaufspreis` = REPLACE(`Einkaufspreis`, '.', ','), `Geaendert_am` = `Geaendert_am`;
-- UPDATE `Sammlung` s JOIN `bak_d6_geld` b ON b.`ID` = s.`ID`
--   SET s.`Wert` = b.`Wert`, s.`Geaendert_am` = s.`Geaendert_am`
--   WHERE TRIM(b.`Wert`) <> '' AND CAST(REPLACE(b.`Wert`, ',', '.') AS DECIMAL(12,2)) = CAST(REPLACE(s.`Wert`, ',', '.') AS DECIMAL(12,2));
-- UPDATE `Sammlung` s JOIN `bak_d6_geld` b ON b.`ID` = s.`ID`
--   SET s.`Einkaufspreis` = b.`Einkaufspreis`, s.`Geaendert_am` = s.`Geaendert_am`
--   WHERE TRIM(b.`Einkaufspreis`) <> '' AND CAST(REPLACE(b.`Einkaufspreis`, ',', '.') AS DECIMAL(12,2)) = CAST(REPLACE(s.`Einkaufspreis`, ',', '.') AS DECIMAL(12,2));
-- DELETE FROM `schema_migrations` WHERE `Dateiname` = '2026-10-13_06_geld.sql';
