-- Migration 0000: Login_Log für Gast-Login-Zähler (Museum-Zugang)
-- Altbestand aus der Zeit vor der Nummerierung der Migrationen (früher migration_login_log.sql im
-- Hauptordner). Wiederholbar: Läuft mehrfach durch, ohne etwas doppelt anzulegen (IF NOT EXISTS).
-- Der Eintrag in `schema_migrations` entsteht mit 2026-10-13_02_schema_migrations.sql, aber nur, wenn
-- die Tabelle Login_Log schon existiert.

CREATE TABLE IF NOT EXISTS `Login_Log` (
  `ID` int NOT NULL AUTO_INCREMENT,
  `Zeitpunkt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `Quelle` varchar(30) DEFAULT NULL,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Quelle-Werte, die verwendet werden:
--   'museum_link'      = früherer Aufruf über /sammlung/museum/ (nur Altdaten)
--   'museum_pwa'       = Start über museum/start.php (auch PWA vom Home-Bildschirm)
--   'guest_link'       = guest.php (Gast-Link mit Ziel)
--   'login_autologin'  = "Als Gast fortfahren"-Link auf login.php
--   'login_form'       = manuelles Eintippen von gast/gast im Login-Formular

-- DOWN (Rückbau):
-- DROP TABLE IF EXISTS `Login_Log`;
