-- Migration B2 (Phase 3): Bildpfade in Sammlung.SammlungBild1 kürzen
-- ============================================================================
-- Aus  https://<Host>/blog/wp-content/uploads/2014/11/bild.jpg  (auch mit //blog)
-- wird 2014/11/bild.jpg. Der Host steht nicht mehr in der Datenbank, die App setzt die URL aus IMAGE_WP_BASE zusammen
-- (inc_image.php). Die Bildschicht liest alte und neue Werte, deshalb ändert sich auf den Seiten nichts Sichtbares.
--
-- Nur Werte mit WordPress-Uploads-Präfix werden gekürzt, und nur wenn der Rest ein sicherer Pfad ist (keine Zeichen
-- ? # % : \ , kein versteckter Ordner, kein //). Alles andere bleibt unverändert und erscheint in der Bildprüfung.
--
-- VORHER: Dump per phpMyAdmin und den Code dieser Version hochladen (inc_image.php, edit.php, bildpruefung.php,
-- einstellungen.php). Kein Wartungsmodus nötig. Wiederholbar: Ein zweiter Lauf findet nichts mehr zu kürzen.
-- Sicherung: Tabelle bak_b2_bild (ID und alter Wert der betroffenen Zeilen), wird erst in D12 gelöscht.
-- ============================================================================

-- ---------------------------------------------------------------------------
-- 1. Prüfung: WordPress-Werte, deren Rest nicht kürzbar ist (erwartet: 0 Zeilen)
-- ---------------------------------------------------------------------------
SELECT `ID`, `SammlungBild1` FROM `Sammlung`
WHERE `SammlungBild1` REGEXP '^https?://[^/]+/+blog/wp-content/uploads/+[^/]'
  AND REGEXP_REPLACE(`SammlungBild1`, '^https?://[^/]+/+blog/wp-content/uploads/+', '') REGEXP '[?#%:\\\\]|(^|/)[.]|//';

-- ---------------------------------------------------------------------------
-- 2. Sicherung der betroffenen Zeilen (bleibt beim zweiten Lauf unverändert)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `bak_b2_bild` AS
  SELECT `ID`, `SammlungBild1` FROM `Sammlung` WHERE `SammlungBild1` REGEXP '^https?://';

-- ---------------------------------------------------------------------------
-- 3. Kürzen (Geaendert_am bleibt unverändert)
-- ---------------------------------------------------------------------------
UPDATE `Sammlung`
SET `SammlungBild1` = REGEXP_REPLACE(`SammlungBild1`, '^https?://[^/]+/+blog/wp-content/uploads/+', ''),
    `Geaendert_am`  = `Geaendert_am`
WHERE `SammlungBild1` REGEXP '^https?://[^/]+/+blog/wp-content/uploads/+[^/]'
  AND NOT (REGEXP_REPLACE(`SammlungBild1`, '^https?://[^/]+/+blog/wp-content/uploads/+', '') REGEXP '[?#%:\\\\]|(^|/)[.]|//')
  AND CHAR_LENGTH(REGEXP_REPLACE(`SammlungBild1`, '^https?://[^/]+/+blog/wp-content/uploads/+', '')) BETWEEN 1 AND 300;

-- ---------------------------------------------------------------------------
-- 4. Eintrag in schema_migrations
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO `schema_migrations` (`Dateiname`) VALUES ('2026-10-14_01_bild_pfade.sql');

-- ---------------------------------------------------------------------------
-- Kontrolle: Alle Zeilen sollten in der Spalte Status "OK" zeigen.
-- ---------------------------------------------------------------------------
SELECT c.Pruefung, c.erwartet, c.ist, IF(c.erwartet = c.ist, 'OK', 'PRÜFEN') AS Status FROM (
  SELECT 'Werte mit WordPress-Präfix übrig' AS Pruefung, 0 AS erwartet,
         (SELECT COUNT(*) FROM `Sammlung` WHERE `SammlungBild1` REGEXP '^https?://[^/]+/+blog/wp-content/uploads/+[^/]') AS ist, 1 AS o
  UNION ALL
  SELECT 'Gekürzte Werte = alter Wert ohne Präfix (Abweichungen)', 0,
         (SELECT COUNT(*) FROM `bak_b2_bild` b JOIN `Sammlung` s ON s.`ID` = b.`ID`
           WHERE b.`SammlungBild1` REGEXP '^https?://[^/]+/+blog/wp-content/uploads/+[^/]'
             AND s.`SammlungBild1` <> REGEXP_REPLACE(b.`SammlungBild1`, '^https?://[^/]+/+blog/wp-content/uploads/+', '')), 2
  UNION ALL
  SELECT 'Durch das Kürzen leer geworden', 0,
         (SELECT COUNT(*) FROM `bak_b2_bild` b JOIN `Sammlung` s ON s.`ID` = b.`ID`
           WHERE s.`SammlungBild1` IS NULL OR s.`SammlungBild1` = ''), 3
  UNION ALL
  SELECT 'Sicherung deckt alle ursprünglichen http-Werte', 0,
         (SELECT COUNT(*) FROM `Sammlung` s LEFT JOIN `bak_b2_bild` b ON b.`ID` = s.`ID`
           WHERE s.`SammlungBild1` REGEXP '^https?://' AND b.`ID` IS NULL), 4
) c ORDER BY c.o;

-- ---------------------------------------------------------------------------
-- DOWN (Rückbau): Zeilen ohne "-- " ausführen. Stellt die alten Werte aus bak_b2_bild wieder her,
-- aber nur bei Zeilen, die seit der Migration nicht geändert wurden.
-- ---------------------------------------------------------------------------
-- UPDATE `Sammlung` s JOIN `bak_b2_bild` b ON b.`ID` = s.`ID`
--   SET s.`SammlungBild1` = b.`SammlungBild1`, s.`Geaendert_am` = s.`Geaendert_am`
--   WHERE s.`SammlungBild1` = REGEXP_REPLACE(b.`SammlungBild1`, '^https?://[^/]+/+blog/wp-content/uploads/+', '');
-- DELETE FROM `schema_migrations` WHERE `Dateiname` = '2026-10-14_01_bild_pfade.sql';
