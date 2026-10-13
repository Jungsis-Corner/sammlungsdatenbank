-- Migration: Login_Log für Gast-Login-Zähler (Museum-Zugang)
-- Einmal ausführen (z.B. über phpMyAdmin), danach kann diese Datei gelöscht werden.

CREATE TABLE IF NOT EXISTS `Login_Log` (
  `ID` int NOT NULL AUTO_INCREMENT,
  `Zeitpunkt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `Quelle` varchar(30) DEFAULT NULL,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Quelle-Werte, die verwendet werden:
--   'museum_link'      = Aufruf über /sammlung/museum/ (z.B. QR-Code, Kurzlink)
--   'login_autologin'  = "Als Gast fortfahren"-Link auf login.php
--   'login_form'       = manuelles Eintippen von gast/gast im Login-Formular
