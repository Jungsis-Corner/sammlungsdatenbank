-- Migration D4: Spaltentypen anpassen (Phase 2)
-- ============================================================================
-- Reine Typänderung, keine Datenänderung (außer 'Zum Verkauf': '' wird 0).
-- VORHER: Dump per phpMyAdmin und den Code dieser Version hochladen (index.php, view.php, edit.php,
-- duplicate.php, inc_filter.php). Er läuft mit alten und neuen Spaltentypen.
-- Wiederholbar: Ein zweiter Lauf ändert nichts mehr. Kein Wartungsmodus nötig (ALTER dauert Sekunden).
--
-- Was passiert:
--   1. 'Zum Verkauf': '' wird '0' (damit die Umstellung auf TINYINT sauber läuft)
--   2. Sammlung (ein ALTER für alle Spalten):
--        Kategorie, Verpackung, Datentraeger, Standort, Box, Hersteller, Publisher, Verkäufer: float -> INT NULL
--        Jahr:         float -> SMALLINT UNSIGNED NULL
--        Anzahl:       float -> SMALLINT UNSIGNED NOT NULL DEFAULT 1
--        Zum Verkauf:  varchar(1) -> TINYINT(1) NOT NULL DEFAULT 0
--      (Material und Zustand sind schon INT.)
--   3. Einkauf und Login_Log: Collation utf8mb4_0900_ai_ci -> utf8mb4_general_ci wie alle anderen Tabellen
--   4. Eintrag in schema_migrations, Kontrolle (letzte Abfrage)
-- Geprüft vorab (D2/D3): Alle Werte in den float-Spalten sind ganze Zahlen, Anzahl hat kein NULL.
-- ============================================================================

-- ---------------------------------------------------------------------------
-- 1. 'Zum Verkauf' vorbereiten (Geaendert_am bleibt unverändert)
--    Schlägt auf einer schon umgestellten Spalte nichts mehr an (0 wird 0).
-- ---------------------------------------------------------------------------
UPDATE `Sammlung` SET `Zum Verkauf` = '0', `Geaendert_am` = `Geaendert_am`
WHERE `Zum Verkauf` IS NULL OR `Zum Verkauf` = '';

-- ---------------------------------------------------------------------------
-- 2. Sammlung: Typen (ALTER schließt eine Transaktion ab, daher ohne START TRANSACTION)
-- ---------------------------------------------------------------------------
ALTER TABLE `Sammlung`
  MODIFY `Anzahl`       SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  MODIFY `Kategorie`    INT NULL DEFAULT NULL,
  MODIFY `Jahr`         SMALLINT UNSIGNED NULL DEFAULT NULL,
  MODIFY `Verpackung`   INT NULL DEFAULT NULL,
  MODIFY `Datentraeger` INT NULL DEFAULT NULL,
  MODIFY `Standort`     INT NULL DEFAULT NULL,
  MODIFY `Zum Verkauf`  TINYINT(1) NOT NULL DEFAULT 0,
  MODIFY `Box`          INT NULL DEFAULT NULL,
  MODIFY `Hersteller`   INT NULL DEFAULT NULL,
  MODIFY `Publisher`    INT NULL DEFAULT NULL,
  MODIFY `Verkäufer`    INT NULL DEFAULT NULL;

-- ---------------------------------------------------------------------------
-- 3. Collation vereinheitlichen
-- ---------------------------------------------------------------------------
ALTER TABLE `Einkauf`   CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE `Login_Log` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

-- ---------------------------------------------------------------------------
-- 4. Eintrag in schema_migrations
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO `schema_migrations` (`Dateiname`) VALUES ('2026-10-13_04_typen.sql');

-- ---------------------------------------------------------------------------
-- Kontrolle: Alle Zeilen sollten in der Spalte Status "OK" zeigen.
-- ---------------------------------------------------------------------------
SELECT c.Pruefung, c.erwartet, c.ist, IF(c.erwartet = c.ist, 'OK', 'PRÜFEN') AS Status FROM (
  SELECT CONCAT('Sammlung.', COLUMN_NAME) AS Pruefung,
         CASE COLUMN_NAME
           WHEN 'Anzahl'      THEN 'smallint unsigned NOT NULL'
           WHEN 'Jahr'        THEN 'smallint unsigned NULL'
           WHEN 'Zum Verkauf' THEN 'tinyint(1) NOT NULL'
           ELSE 'int NULL' END AS erwartet,
         CONCAT(COLUMN_TYPE, IF(IS_NULLABLE = 'NO', ' NOT NULL', ' NULL')) AS ist,
         ORDINAL_POSITION AS o
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Sammlung'
    AND COLUMN_NAME IN ('Anzahl','Kategorie','Jahr','Verpackung','Datentraeger','Standort','Zum Verkauf','Box','Hersteller','Publisher','Verkäufer')
  UNION ALL
  SELECT 'Zum Verkauf: nur 0 oder 1', 'ok', IF(SUM(`Zum Verkauf` NOT IN (0, 1)) = 0, 'ok', 'fehler'), 1000 FROM `Sammlung`
  UNION ALL
  SELECT 'Sammlung: Anzahl Einträge', (SELECT COUNT(*) FROM `bak_d3_sammlung`), COUNT(*), 1001 FROM `Sammlung`
  UNION ALL
  SELECT 'Tabellen mit abweichender Collation', 0, COUNT(*), 1002
  FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE' AND TABLE_NAME NOT LIKE 'bak\\_%' AND TABLE_COLLATION <> 'utf8mb4_general_ci'
) c ORDER BY c.o;

-- ---------------------------------------------------------------------------
-- DOWN (Rückbau): Zeilen ohne "-- " ausführen. Stellt die alten Typen wieder her.
-- Zum Verkauf: 0 wird wieder '' (der alte Stand), 1 bleibt '1'. Alle anderen Werte bleiben unverändert.
-- ---------------------------------------------------------------------------
-- ALTER TABLE `Sammlung`
--   MODIFY `Zum Verkauf`  VARCHAR(1) COLLATE utf8mb4_general_ci DEFAULT '';
-- UPDATE `Sammlung` SET `Zum Verkauf` = '', `Geaendert_am` = `Geaendert_am` WHERE `Zum Verkauf` = '0';
-- ALTER TABLE `Sammlung`
--   MODIFY `Anzahl`       FLOAT DEFAULT NULL,
--   MODIFY `Kategorie`    FLOAT DEFAULT NULL,
--   MODIFY `Jahr`         FLOAT DEFAULT NULL,
--   MODIFY `Verpackung`   FLOAT DEFAULT NULL,
--   MODIFY `Datentraeger` FLOAT DEFAULT NULL,
--   MODIFY `Standort`     FLOAT DEFAULT NULL,
--   MODIFY `Box`          FLOAT DEFAULT NULL,
--   MODIFY `Hersteller`   FLOAT DEFAULT NULL,
--   MODIFY `Publisher`    FLOAT DEFAULT NULL,
--   MODIFY `Verkäufer`    FLOAT DEFAULT NULL;
-- ALTER TABLE `Einkauf`   CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
-- ALTER TABLE `Login_Log` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
-- DELETE FROM `schema_migrations` WHERE `Dateiname` = '2026-10-13_04_typen.sql';
