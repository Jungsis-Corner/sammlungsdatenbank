-- Migration D9: Kategorie-Hierarchie (Phase 2)
-- ============================================================================
-- Die Kategorien bekommen eine Baumstruktur: Marke -> Plattform -> Spiele / Anwendungen / sonstiges.
-- Grundlage ist die von Jungsi freigegebene Zuordnung (kategorie_mapping_vorschlag.csv, alle 178 Zeilen "ok").
-- Kein Sammlungseintrag wird verschoben, alle bestehenden Kategorie-IDs bleiben. Namen ändern sich nur bei 3 Tippfehlern.
--
-- Was passiert:
--   1. Sicherung bak_d9_kategorie (alter Stand der Tabelle Kategorie, für Kontrolle und DOWN)
--   2. Neue Spalten in Kategorie: ParentID (Selbst-Fremdschlüssel), Ebene, Pfad (z. B. '/288/53/')
--   3. 3 Namen korrigieren (IDs bleiben): Atari 16_ 32 Bit Spiele -> Atari 16_32 Bit Spiele, Atari Portolio -> Atari Portfolio, Nintendo Game And Watch Spiele -> Nintendo Game&Watch Spiele
--   4. 5 neue Knoten ohne Einträge: Atari, Apple, Commodore, Commodore Amiga, Philips
--   5. ParentID und Ebene für alle 178 Knoten setzen, Spiele = (Ebene = 'spiele'), Pfad berechnen
--   6. Fremdschlüssel und Indizes, Eintrag in schema_migrations, Kontrolle
--
-- REIHENFOLGE: Erst diese Migration, danach den neuen Code hochladen (er braucht die neuen Spalten).
-- Der alte Code läuft nach der Migration unverändert weiter. Bis zum Upload des neuen Codes keine
-- neuen Kategorien in manage_kategorie.php anlegen (sie bekämen noch keinen Pfad).
-- Wiederholbar: Ein zweiter Lauf ändert nichts.
-- ============================================================================

-- ---------------------------------------------------------------------------
-- 1. Sicherung (bleibt beim zweiten Lauf unverändert, wird erst in D12 gelöscht)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `bak_d9_kategorie` AS SELECT * FROM `Kategorie`;

-- ---------------------------------------------------------------------------
-- 2. Neue Spalten und Indizes (übersprungen, wenn vorhanden)
-- ---------------------------------------------------------------------------
SET @sql = IF(EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Kategorie' AND COLUMN_NAME = 'Pfad'),
  'SELECT 1',
  'ALTER TABLE `Kategorie` ADD `ParentID` INT NULL DEFAULT NULL, ADD `Ebene` ENUM(''marke'',''plattform'',''spiele'',''anwendungen'',''sonstiges'') NOT NULL DEFAULT ''plattform'', ADD `Pfad` VARCHAR(190) NOT NULL DEFAULT ''/''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @sql = IF(EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Kategorie' AND INDEX_NAME = 'idx_kategorie_pfad'),
  'SELECT 1', 'ALTER TABLE `Kategorie` ADD KEY `idx_kategorie_pfad` (`Pfad`)');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @sql = IF(EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Kategorie' AND INDEX_NAME = 'idx_kategorie_parent'),
  'SELECT 1', 'ALTER TABLE `Kategorie` ADD KEY `idx_kategorie_parent` (`ParentID`)');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- ---------------------------------------------------------------------------
-- 3. Namen korrigieren (nur wenn noch der alte Name dasteht)
-- ---------------------------------------------------------------------------
UPDATE `Kategorie` SET `Kategorie` = 'Atari 16_32 Bit Spiele' WHERE `ID` = 9 AND `Kategorie` = 'Atari 16_ 32 Bit Spiele';
UPDATE `Kategorie` SET `Kategorie` = 'Atari Portfolio' WHERE `ID` = 14 AND `Kategorie` = 'Atari Portolio';
UPDATE `Kategorie` SET `Kategorie` = 'Nintendo Game&Watch Spiele' WHERE `ID` = 118 AND `Kategorie` = 'Nintendo Game And Watch Spiele';

-- ---------------------------------------------------------------------------
-- 4. Neue Knoten (nur wenn der Name noch fehlt)
-- ---------------------------------------------------------------------------
INSERT INTO `Kategorie` (`Kategorie`, `Spiele`) SELECT 'Atari', 0 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `Kategorie` WHERE `Kategorie` = 'Atari');
INSERT INTO `Kategorie` (`Kategorie`, `Spiele`) SELECT 'Apple', 0 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `Kategorie` WHERE `Kategorie` = 'Apple');
INSERT INTO `Kategorie` (`Kategorie`, `Spiele`) SELECT 'Commodore', 0 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `Kategorie` WHERE `Kategorie` = 'Commodore');
INSERT INTO `Kategorie` (`Kategorie`, `Spiele`) SELECT 'Commodore Amiga', 0 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `Kategorie` WHERE `Kategorie` = 'Commodore Amiga');
INSERT INTO `Kategorie` (`Kategorie`, `Spiele`) SELECT 'Philips', 0 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `Kategorie` WHERE `Kategorie` = 'Philips');

-- ---------------------------------------------------------------------------
-- 5. Zuordnung: Name -> Eltern -> Ebene (aus der freigegebenen CSV, Namen nach den Korrekturen)
-- ---------------------------------------------------------------------------
DROP TEMPORARY TABLE IF EXISTS `tmp_d9_map`;
CREATE TEMPORARY TABLE `tmp_d9_map` (
  `Name`   VARCHAR(190) COLLATE utf8mb4_general_ci NOT NULL,
  `Eltern` VARCHAR(190) COLLATE utf8mb4_general_ci NULL,
  `Ebene`  VARCHAR(20)  COLLATE utf8mb4_general_ci NOT NULL
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
INSERT INTO `tmp_d9_map` (`Name`, `Eltern`, `Ebene`) VALUES
  ('3do', NULL, 'plattform'),
  ('3do Spiele', '3do', 'spiele'),
  ('Acorn', NULL, 'marke'),
  ('Acorn BBC Master', 'Acorn', 'plattform'),
  ('Acorn BBC Micro', 'Acorn', 'plattform'),
  ('Acorn BBC Micro Spiele', 'Acorn BBC Micro', 'spiele'),
  ('Acorn Electron', 'Acorn', 'plattform'),
  ('Acorn Electron Spiele', 'Acorn Electron', 'spiele'),
  ('Amstrad', NULL, 'marke'),
  ('Amstrad GX4000', 'Amstrad', 'plattform'),
  ('Amstrad GX4000 Spiele', 'Amstrad GX4000', 'spiele'),
  ('Apple IIe', 'Apple', 'plattform'),
  ('Apple IIe Literatur', 'Apple IIe', 'sonstiges'),
  ('Apple IIe Spiele', 'Apple IIe', 'spiele'),
  ('Apple Macintosh', 'Apple', 'plattform'),
  ('Apple Macintosh Literatur', 'Apple Macintosh', 'sonstiges'),
  ('Atari 16_32 Bit', 'Atari', 'plattform'),
  ('Atari 16_ 32 Bit Anwendungen', 'Atari 16_32 Bit', 'anwendungen'),
  ('Atari 16_32 Bit Spiele', 'Atari 16_32 Bit', 'spiele'),
  ('Atari 7800', 'Atari', 'plattform'),
  ('Atari 7800 Spiele', 'Atari 7800', 'spiele'),
  ('Atari 8 Bit', 'Atari', 'plattform'),
  ('Atari 8 Bit Spiele', 'Atari 8 Bit', 'spiele'),
  ('Atari Jaguar', 'Atari', 'plattform'),
  ('Atari Jaguar Spiele', 'Atari Jaguar', 'spiele'),
  ('Atari Lynx', 'Atari', 'plattform'),
  ('Atari Lynx Spiele', 'Atari Lynx', 'spiele'),
  ('Atari Portfolio', 'Atari', 'plattform'),
  ('Atari VCS', 'Atari', 'plattform'),
  ('Atari VCS Spiele', 'Atari VCS', 'spiele'),
  ('Bandai', NULL, 'marke'),
  ('Bandai Wonderswan Spiele', 'Bandai', 'spiele'),
  ('Casio', NULL, 'plattform'),
  ('Colecovision', NULL, 'plattform'),
  ('Colecovision Spiele', 'Colecovision', 'spiele'),
  ('Commodore 16_116_plus4', 'Commodore', 'plattform'),
  ('Commodore 16 Spiele', 'Commodore 16_116_plus4', 'spiele'),
  ('Commodore 64_128', 'Commodore', 'plattform'),
  ('Commodore 64_128 Anwendungen', 'Commodore 64_128', 'anwendungen'),
  ('Commodore 64 Spiele', 'Commodore 64_128', 'spiele'),
  ('Commodore Amiga 1200', 'Commodore Amiga', 'plattform'),
  ('Commodore Amiga 500', 'Commodore Amiga', 'plattform'),
  ('Commodore Amiga 600', 'Commodore Amiga', 'plattform'),
  ('Commodore Amiga CD32', 'Commodore Amiga', 'plattform'),
  ('Commodore Amiga Spiele', 'Commodore Amiga', 'spiele'),
  ('Commodore CBM', 'Commodore', 'plattform'),
  ('Commodore VC20', 'Commodore', 'plattform'),
  ('Commodore VC20 Spiele', 'Commodore VC20', 'spiele'),
  ('Dragon', NULL, 'plattform'),
  ('Dragon Spiele', 'Dragon', 'spiele'),
  ('Enterprise', NULL, 'plattform'),
  ('Epson', NULL, 'plattform'),
  ('Evercade', NULL, 'plattform'),
  ('Evercade Spiele', 'Evercade', 'spiele'),
  ('Grundig', NULL, 'plattform'),
  ('Hartung', NULL, 'plattform'),
  ('Hewlett Packard', NULL, 'plattform'),
  ('IBM', NULL, 'plattform'),
  ('Intellivision', NULL, 'plattform'),
  ('Intellivision Spiele', 'Intellivision', 'spiele'),
  ('Interton', NULL, 'plattform'),
  ('Interton VC4000 Spiele', 'Interton', 'spiele'),
  ('ITT', NULL, 'marke'),
  ('ITT Telematch Processor Spiele', 'ITT', 'spiele'),
  ('Joystick', NULL, 'sonstiges'),
  ('Körting', NULL, 'plattform'),
  ('MB', NULL, 'plattform'),
  ('MBO', NULL, 'plattform'),
  ('Microsoft', NULL, 'marke'),
  ('Microsoft Xbox', 'Microsoft', 'plattform'),
  ('Microsoft XBOX 360', 'Microsoft', 'plattform'),
  ('Microsoft XBOX 360 Spiele', 'Microsoft XBOX 360', 'spiele'),
  ('Microsoft Xbox Spiele', 'Microsoft Xbox', 'spiele'),
  ('MSX', NULL, 'plattform'),
  ('MSX Spiele', 'MSX', 'spiele'),
  ('NEC', NULL, 'marke'),
  ('NEC PC Engine', 'NEC', 'plattform'),
  ('NEC PC Engine Spiele', 'NEC PC Engine', 'spiele'),
  ('NEC TurboGrafx', 'NEC', 'plattform'),
  ('Neo Geo', NULL, 'marke'),
  ('Neo Geo AES Spiele', 'Neo Geo', 'spiele'),
  ('Neo Geo Pocket', 'Neo Geo', 'plattform'),
  ('Neo Geo Pocket Spiele', 'Neo Geo Pocket', 'spiele'),
  ('Nintendo', NULL, 'marke'),
  ('Nintendo 64', 'Nintendo', 'plattform'),
  ('Nintendo 64 Spiele', 'Nintendo 64', 'spiele'),
  ('Nintendo DS Spiele', 'Nintendo', 'spiele'),
  ('Nintendo Game&Watch', 'Nintendo', 'plattform'),
  ('Nintendo Game&Watch Spiele', 'Nintendo Game&Watch', 'spiele'),
  ('Nintendo Game Boy', 'Nintendo', 'plattform'),
  ('Nintendo Game Boy Advance Spiele', 'Nintendo', 'spiele'),
  ('Nintendo Game Boy Spiele', 'Nintendo Game Boy', 'spiele'),
  ('Nintendo Gamecube', 'Nintendo', 'plattform'),
  ('Nintendo NES', 'Nintendo', 'plattform'),
  ('Nintendo NES Spiele', 'Nintendo NES', 'spiele'),
  ('Nintendo SNES', 'Nintendo', 'plattform'),
  ('Nintendo SNES Spiele', 'Nintendo SNES', 'spiele'),
  ('Nintendo Switch', 'Nintendo', 'plattform'),
  ('Nintendo Switch Spiele', 'Nintendo Switch', 'spiele'),
  ('Nintendo Wii', 'Nintendo', 'plattform'),
  ('Nintendo Wii Spiele', 'Nintendo Wii', 'spiele'),
  ('Nordmende', NULL, 'marke'),
  ('Nordmende TelePlay Spiele', 'Nordmende', 'spiele'),
  ('PC', NULL, 'plattform'),
  ('PC Spiele', 'PC', 'spiele'),
  ('Philips Cdi', 'Philips', 'plattform'),
  ('Philips Cdi Spiele', 'Philips Cdi', 'spiele'),
  ('Philips Videopac', 'Philips', 'plattform'),
  ('Philips Videopac G7000 Spiele', 'Philips Videopac', 'spiele'),
  ('Pong Konsolen', NULL, 'plattform'),
  ('Poppy MPT-03', NULL, 'plattform'),
  ('Poppy MPT-03 Spiele', 'Poppy MPT-03', 'spiele'),
  ('Privileg', NULL, 'plattform'),
  ('Psion', NULL, 'plattform'),
  ('Saba', NULL, 'marke'),
  ('Saba Videoplay Spiele', 'Saba', 'spiele'),
  ('Sanyo', NULL, 'plattform'),
  ('Schneider', NULL, 'marke'),
  ('Schneider CPC', 'Schneider', 'plattform'),
  ('Schneider CPC Anwendungen', 'Schneider CPC', 'anwendungen'),
  ('Schneider CPC Spiele', 'Schneider CPC', 'spiele'),
  ('Schneider Joyce', 'Schneider', 'plattform'),
  ('Schneider PC', 'Schneider', 'plattform'),
  ('Sega', NULL, 'marke'),
  ('Sega Dreamcast', 'Sega', 'plattform'),
  ('Sega Dreamcast Spiele', 'Sega Dreamcast', 'spiele'),
  ('Sega Game Gear', 'Sega', 'plattform'),
  ('Sega Game Gear Spiele', 'Sega Game Gear', 'spiele'),
  ('Sega Master System', 'Sega', 'plattform'),
  ('Sega Master System Spiele', 'Sega Master System', 'spiele'),
  ('Sega Mega Drive', 'Sega', 'plattform'),
  ('Sega Mega Drive Spiele', 'Sega Mega Drive', 'spiele'),
  ('Sega Saturn', 'Sega', 'plattform'),
  ('Sega Saturn Spiele', 'Sega Saturn', 'spiele'),
  ('Sharp', NULL, 'marke'),
  ('Sharp MZ', 'Sharp', 'plattform'),
  ('Sharp MZ Spiele', 'Sharp MZ', 'spiele'),
  ('SHG Blackpoint', NULL, 'plattform'),
  ('SHG Blackpoint Spiele', 'SHG Blackpoint', 'spiele'),
  ('Sinclair', NULL, 'marke'),
  ('Sinclair QL', 'Sinclair', 'plattform'),
  ('Sinclair QL Spiele', 'Sinclair QL', 'spiele'),
  ('Sinclair ZX Spectrum', 'Sinclair', 'plattform'),
  ('Sinclair ZX Spectrum Anwendungen', 'Sinclair ZX Spectrum', 'anwendungen'),
  ('Sinclair ZX Spectrum NEXT', 'Sinclair ZX Spectrum', 'plattform'),
  ('Sinclair ZX Spectrum NEXT Spiele', 'Sinclair ZX Spectrum NEXT', 'spiele'),
  ('Sinclair ZX Spectrum Spiele', 'Sinclair ZX Spectrum', 'spiele'),
  ('Sinclair ZX81', 'Sinclair', 'plattform'),
  ('Sinclair ZX81 Spiele', 'Sinclair ZX81', 'spiele'),
  ('Sonstiges', NULL, 'sonstiges'),
  ('Sony', NULL, 'marke'),
  ('Sony Playstation 2 Spiele', 'Sony', 'spiele'),
  ('Sony Playstation 3 Spiele', 'Sony', 'spiele'),
  ('Sony Playstation Spiele', 'Sony', 'spiele'),
  ('Sony PSP Spiele', 'Sony', 'spiele'),
  ('Supervision', NULL, 'plattform'),
  ('Supervision Spiele', 'Supervision', 'spiele'),
  ('Texas Instruments', NULL, 'marke'),
  ('Texas Instruments TI-59', 'Texas Instruments', 'plattform'),
  ('Texas Instruments TI-74', 'Texas Instruments', 'plattform'),
  ('Texas Instruments TI-99', 'Texas Instruments', 'plattform'),
  ('Texas Instruments TI-99 Anwendungen', 'Texas Instruments TI-99', 'anwendungen'),
  ('Texas Instruments TI-99 Spiele', 'Texas Instruments TI-99', 'spiele'),
  ('Thomson', NULL, 'plattform'),
  ('Thomson Anwendungen', 'Thomson', 'anwendungen'),
  ('Thomson Spiele', 'Thomson', 'spiele'),
  ('Tiger Electronics', NULL, 'plattform'),
  ('Toshiba', NULL, 'plattform'),
  ('VEB Mühlhausen', NULL, 'plattform'),
  ('Vectrex', NULL, 'plattform'),
  ('Vectrex Spiele', 'Vectrex', 'spiele'),
  ('Vobis', NULL, 'plattform'),
  ('VTech', NULL, 'plattform'),
  ('Atari', NULL, 'marke'),
  ('Apple', NULL, 'marke'),
  ('Commodore', NULL, 'marke'),
  ('Commodore Amiga', 'Commodore', 'plattform'),
  ('Philips', NULL, 'marke');

-- Prüfung: Namen der Zuordnung, die in Kategorie fehlen (erwartet: 0 Zeilen)
SELECT m.`Name`, m.`Eltern` FROM `tmp_d9_map` m
LEFT JOIN `Kategorie` k ON k.`Kategorie` = m.`Name`
LEFT JOIN `Kategorie` p ON p.`Kategorie` = m.`Eltern`
WHERE k.`ID` IS NULL OR (m.`Eltern` IS NOT NULL AND p.`ID` IS NULL);

UPDATE `Kategorie` k
JOIN `tmp_d9_map` m ON m.`Name` = k.`Kategorie`
LEFT JOIN `Kategorie` p ON p.`Kategorie` = m.`Eltern`
SET k.`ParentID` = p.`ID`, k.`Ebene` = m.`Ebene`, k.`Spiele` = IF(m.`Ebene` = 'spiele', 1, 0);

-- Pfad: Wurzeln zuerst, dann Ebene für Ebene (höchstens 4 Ebenen, 6 Durchgänge reichen sicher)
UPDATE `Kategorie` SET `Pfad` = CONCAT('/', `ID`, '/') WHERE `ParentID` IS NULL;
UPDATE `Kategorie` c JOIN `Kategorie` p ON p.`ID` = c.`ParentID` SET c.`Pfad` = CONCAT(p.`Pfad`, c.`ID`, '/') WHERE p.`Pfad` <> '/';
UPDATE `Kategorie` c JOIN `Kategorie` p ON p.`ID` = c.`ParentID` SET c.`Pfad` = CONCAT(p.`Pfad`, c.`ID`, '/') WHERE p.`Pfad` <> '/';
UPDATE `Kategorie` c JOIN `Kategorie` p ON p.`ID` = c.`ParentID` SET c.`Pfad` = CONCAT(p.`Pfad`, c.`ID`, '/') WHERE p.`Pfad` <> '/';
UPDATE `Kategorie` c JOIN `Kategorie` p ON p.`ID` = c.`ParentID` SET c.`Pfad` = CONCAT(p.`Pfad`, c.`ID`, '/') WHERE p.`Pfad` <> '/';
UPDATE `Kategorie` c JOIN `Kategorie` p ON p.`ID` = c.`ParentID` SET c.`Pfad` = CONCAT(p.`Pfad`, c.`ID`, '/') WHERE p.`Pfad` <> '/';
UPDATE `Kategorie` c JOIN `Kategorie` p ON p.`ID` = c.`ParentID` SET c.`Pfad` = CONCAT(p.`Pfad`, c.`ID`, '/') WHERE p.`Pfad` <> '/';
DROP TEMPORARY TABLE `tmp_d9_map`;

-- ---------------------------------------------------------------------------
-- 6. Fremdschlüssel (übersprungen, wenn vorhanden) und Eintrag in schema_migrations
-- ---------------------------------------------------------------------------
SET @sql = IF(EXISTS (SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'Kategorie' AND CONSTRAINT_NAME = 'fk_kategorie_parent'),
  'SELECT 1', 'ALTER TABLE `Kategorie` ADD CONSTRAINT `fk_kategorie_parent` FOREIGN KEY (`ParentID`) REFERENCES `Kategorie` (`ID`) ON DELETE RESTRICT ON UPDATE CASCADE');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

INSERT IGNORE INTO `schema_migrations` (`Dateiname`) VALUES ('2026-10-13_08_kategorie_hierarchie.sql');

-- ---------------------------------------------------------------------------
-- Kontrolle: Alle Zeilen sollten in der Spalte Status "OK" zeigen.
-- ---------------------------------------------------------------------------
SELECT c.Pruefung, c.erwartet, c.ist, IF(c.erwartet = c.ist, 'OK', 'PRÜFEN') AS Status FROM (
  SELECT 'Anzahl Kategorien (vorher + 5 neue)' AS Pruefung, (SELECT COUNT(*) FROM `bak_d9_kategorie`) + 5 AS erwartet, (SELECT COUNT(*) FROM `Kategorie`) AS ist, 0 AS o
  UNION ALL
  SELECT 'Kategorien ohne Pfad (Pfad = ''/'')', 0, (SELECT COUNT(*) FROM `Kategorie` WHERE `Pfad` = '/'), 1
  UNION ALL
  SELECT 'Pfad passt nicht zum Elternpfad', 0, (SELECT COUNT(*) FROM `Kategorie` c LEFT JOIN `Kategorie` p ON p.`ID` = c.`ParentID` WHERE c.`Pfad` <> CONCAT(IFNULL(p.`Pfad`, '/'), c.`ID`, '/')), 2
  UNION ALL
  SELECT 'Ebene marke', 20, (SELECT COUNT(*) FROM `Kategorie` WHERE `Ebene` = 'marke'), 3
  UNION ALL
  SELECT 'Ebene plattform', 85, (SELECT COUNT(*) FROM `Kategorie` WHERE `Ebene` = 'plattform'), 4
  UNION ALL
  SELECT 'Ebene spiele', 63, (SELECT COUNT(*) FROM `Kategorie` WHERE `Ebene` = 'spiele'), 5
  UNION ALL
  SELECT 'Ebene anwendungen', 6, (SELECT COUNT(*) FROM `Kategorie` WHERE `Ebene` = 'anwendungen'), 6
  UNION ALL
  SELECT 'Ebene sonstiges', 4, (SELECT COUNT(*) FROM `Kategorie` WHERE `Ebene` = 'sonstiges'), 7
  UNION ALL
  SELECT 'Einträge in Spiele-Kategorien = alter Stand (LIKE Spiel)', (SELECT COUNT(*) FROM `Sammlung` s JOIN `bak_d9_kategorie` b ON b.`ID` = s.`Kategorie` WHERE b.`Kategorie` LIKE '%Spiel%'), (SELECT COUNT(*) FROM `Sammlung` s JOIN `Kategorie` k ON k.`ID` = s.`Kategorie` WHERE k.`Ebene` = 'spiele'), 8
  UNION ALL
  SELECT 'Spalte Spiele passt zur Ebene', 0, (SELECT COUNT(*) FROM `Kategorie` WHERE `Spiele` <> (`Ebene` = 'spiele')), 9
  UNION ALL
  SELECT 'Neue Knoten ohne Einträge', 0, (SELECT COUNT(*) FROM `Sammlung` s JOIN `Kategorie` k ON k.`ID` = s.`Kategorie` WHERE k.`Kategorie` IN ('Atari', 'Apple', 'Commodore', 'Commodore Amiga', 'Philips')), 10
  UNION ALL
  SELECT 'Fremdschlüssel fk_kategorie_parent', 1, (SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'fk_kategorie_parent' AND DELETE_RULE = 'RESTRICT'), 11
) c ORDER BY c.o;

-- ---------------------------------------------------------------------------
-- DOWN (Rückbau): Zeilen ohne "-- " ausführen. Stellt Kategorie wie vor der Migration her
-- (Namen und Spiele-Flag aus bak_d9_kategorie, neue Knoten werden gelöscht, solange sie ohne Einträge sind).
-- ---------------------------------------------------------------------------
-- ALTER TABLE `Kategorie` DROP FOREIGN KEY `fk_kategorie_parent`;
-- ALTER TABLE `Kategorie` DROP INDEX `idx_kategorie_parent`, DROP INDEX `idx_kategorie_pfad`, DROP COLUMN `ParentID`, DROP COLUMN `Ebene`, DROP COLUMN `Pfad`;
-- DELETE FROM `Kategorie` WHERE `ID` NOT IN (SELECT `ID` FROM `bak_d9_kategorie`);
-- UPDATE `Kategorie` k JOIN `bak_d9_kategorie` b ON b.`ID` = k.`ID` SET k.`Kategorie` = b.`Kategorie`, k.`Spiele` = b.`Spiele`;
-- DELETE FROM `schema_migrations` WHERE `Dateiname` = '2026-10-13_08_kategorie_hierarchie.sql';
