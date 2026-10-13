-- Migration: Login-Drosselung (Phase 1, S3)
-- Vorher: Dump per phpMyAdmin. Einmal ausführen.
-- Reihenfolge: Migration zuerst, dann den Code hochladen. (Der Code läuft auch ohne
-- Tabelle, dann aber ohne Drosselung.)
-- Hinweis: Der Eintrag in `schema_migrations` folgt mit Aufgabe D1 (Tabelle existiert noch nicht).

CREATE TABLE IF NOT EXISTS `Login_Versuche` (
  `ID` int NOT NULL AUTO_INCREMENT,
  `Zeitpunkt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `IP_Hash` char(64) NOT NULL,
  `User_Hash` char(64) NOT NULL,
  `Erfolg` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`ID`),
  KEY `idx_ip_zeit` (`IP_Hash`, `Zeitpunkt`),
  KEY `idx_user_zeit` (`User_Hash`, `Zeitpunkt`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Es werden nur HMAC-Hashes gespeichert (Salt: LOGIN_HASH_SALT aus config.php), keine Klartext-IP
-- und kein Klartext-Benutzername.

-- DOWN (Rückbau):
-- DROP TABLE IF EXISTS `Login_Versuche`;
