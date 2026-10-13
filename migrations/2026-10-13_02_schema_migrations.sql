-- Migration: Tabelle schema_migrations (Phase 2, D1)
-- Vorher: Dump per phpMyAdmin. Wiederholbar (IF NOT EXISTS, INSERT IGNORE).
-- Sie hält fest, welche Migrationsdateien schon eingespielt sind.

CREATE TABLE IF NOT EXISTS `schema_migrations` (
  `Dateiname` varchar(190) NOT NULL,
  `Eingespielt_am` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`Dateiname`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Bereits eingespielte ältere Migrationen nachtragen, aber nur, wenn ihre Tabelle wirklich existiert.
-- (Unter MySQL laufen Tabellennamen je nach Server groß-/kleingeschrieben, daher LOWER().)
INSERT IGNORE INTO `schema_migrations` (`Dateiname`)
SELECT '0000_login_log.sql' FROM DUAL
WHERE EXISTS (SELECT 1 FROM information_schema.tables
              WHERE table_schema = DATABASE() AND LOWER(table_name) = 'login_log');

INSERT IGNORE INTO `schema_migrations` (`Dateiname`)
SELECT '2026-10-13_01_login_versuche.sql' FROM DUAL
WHERE EXISTS (SELECT 1 FROM information_schema.tables
              WHERE table_schema = DATABASE() AND LOWER(table_name) = 'login_versuche');

-- Diese Migration trägt sich selbst ein.
INSERT IGNORE INTO `schema_migrations` (`Dateiname`) VALUES ('2026-10-13_02_schema_migrations.sql');

-- Kontrolle: SELECT * FROM schema_migrations ORDER BY Dateiname;

-- DOWN (Rückbau):
-- DROP TABLE IF EXISTS `schema_migrations`;
