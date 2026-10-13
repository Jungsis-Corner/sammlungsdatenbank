-- ============================================================
-- schema.sql -- Sammlungsdatenbank
--
-- Reine Struktur (keine Daten). Erzeugt aus einem Struktur-Export
-- der Live-Datenbank, bereinigt um ungenutzte/veraltete Spalten
-- und interne Backup-/Hilfstabellen.
--
-- Import z.B. ueber phpMyAdmin -> Importieren, oder:
--   mysql -u DEIN_USER -p DEINE_DATENBANK < schema.sql
-- ============================================================

SET FOREIGN_KEY_CHECKS=0;

-- --------------------------------------------------------
-- Tabelle: Box
-- --------------------------------------------------------
CREATE TABLE `Box` (
  `ID` int NOT NULL,
  `Box` varchar(190) COLLATE utf8mb4_general_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `Box`
  ADD PRIMARY KEY (`ID`),
  ADD UNIQUE KEY `uq_Box_name` (`Box`);
ALTER TABLE `Box`
  MODIFY `ID` int NOT NULL AUTO_INCREMENT;

-- --------------------------------------------------------
-- Tabelle: Datentraeger
-- --------------------------------------------------------
CREATE TABLE `Datentraeger` (
  `ID` int NOT NULL,
  `Datentrager` varchar(190) COLLATE utf8mb4_general_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `Datentraeger`
  ADD PRIMARY KEY (`ID`),
  ADD UNIQUE KEY `uq_Datentraeger_name` (`Datentrager`);
ALTER TABLE `Datentraeger`
  MODIFY `ID` int NOT NULL AUTO_INCREMENT;

-- --------------------------------------------------------
-- Tabelle: Einkauf
-- --------------------------------------------------------
CREATE TABLE `Einkauf` (
  `ID` int NOT NULL,
  `Bestelldatum` date DEFAULT NULL,
  `Bezeichnung` varchar(255) NOT NULL,
  `Kategorie` int DEFAULT NULL,
  `Verkaeufer` int DEFAULT NULL,
  `Preis` decimal(10,2) DEFAULT NULL,
  `Lieferdatum` date DEFAULT NULL,
  `Menge` int NOT NULL DEFAULT '1',
  `Foto_erstellt` tinyint(1) NOT NULL DEFAULT '0',
  `DB_Eintrag_erstellt` tinyint(1) NOT NULL DEFAULT '0',
  `Foto_Website` tinyint(1) NOT NULL DEFAULT '0',
  `Notizen` text,
  `Erstellt_am` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `Geaendert_am` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `SCM` tinyint(1) NOT NULL DEFAULT '0',
  `Kickstarter` tinyint(1) NOT NULL DEFAULT '0',
  `Kickstarter_Lieferdatum` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `Einkauf`
  ADD PRIMARY KEY (`ID`),
  ADD KEY `idx_einkauf_bestell` (`Bestelldatum`),
  ADD KEY `idx_einkauf_liefer` (`Lieferdatum`),
  ADD KEY `idx_einkauf_kat` (`Kategorie`),
  ADD KEY `idx_einkauf_verk` (`Verkaeufer`);
ALTER TABLE `Einkauf`
  MODIFY `ID` int NOT NULL AUTO_INCREMENT;
-- Fremdschlüssel von Einkauf: siehe Abschnitt "Fremdschlüssel" am Ende (nach allen Tabellen).

-- --------------------------------------------------------
-- Tabelle: Hersteller
-- --------------------------------------------------------
CREATE TABLE `Hersteller` (
  `Hersteller` varchar(190) COLLATE utf8mb4_general_ci NOT NULL,
  `ID` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `Hersteller`
  ADD PRIMARY KEY (`ID`),
  ADD UNIQUE KEY `uq_Hersteller_name` (`Hersteller`);
ALTER TABLE `Hersteller`
  MODIFY `ID` int NOT NULL AUTO_INCREMENT;

-- --------------------------------------------------------
-- Tabelle: Kategorie
-- --------------------------------------------------------
CREATE TABLE `Kategorie` (
  `Kategorie` varchar(190) COLLATE utf8mb4_general_ci NOT NULL,
  `ID` int NOT NULL,
  `Spiele` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `Kategorie`
  ADD PRIMARY KEY (`ID`),
  ADD UNIQUE KEY `uq_Kategorie_name` (`Kategorie`);
ALTER TABLE `Kategorie`
  MODIFY `ID` int NOT NULL AUTO_INCREMENT;

-- --------------------------------------------------------
-- Tabelle: Login_Log (Gast-Login-Zähler, Statistik-Seite)
-- --------------------------------------------------------
CREATE TABLE `Login_Log` (
  `ID` int NOT NULL,
  `Zeitpunkt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `Quelle` varchar(30) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `Login_Log`
  ADD PRIMARY KEY (`ID`);
ALTER TABLE `Login_Log`
  MODIFY `ID` int NOT NULL AUTO_INCREMENT;

-- --------------------------------------------------------
-- Tabelle: Login_Versuche (Login-Drosselung, nur Hashes)
-- --------------------------------------------------------
CREATE TABLE `Login_Versuche` (
  `ID` int NOT NULL,
  `Zeitpunkt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `IP_Hash` char(64) NOT NULL,
  `User_Hash` char(64) NOT NULL,
  `Erfolg` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `Login_Versuche`
  ADD PRIMARY KEY (`ID`),
  ADD KEY `idx_ip_zeit` (`IP_Hash`, `Zeitpunkt`),
  ADD KEY `idx_user_zeit` (`User_Hash`, `Zeitpunkt`);
ALTER TABLE `Login_Versuche`
  MODIFY `ID` int NOT NULL AUTO_INCREMENT;

-- --------------------------------------------------------
-- Tabelle: Material
-- --------------------------------------------------------
CREATE TABLE `Material` (
  `Material` varchar(190) COLLATE utf8mb4_general_ci NOT NULL,
  `ID` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `Material`
  ADD PRIMARY KEY (`ID`),
  ADD UNIQUE KEY `uq_Material_name` (`Material`);
ALTER TABLE `Material`
  MODIFY `ID` int NOT NULL AUTO_INCREMENT;

-- --------------------------------------------------------
-- Tabelle: Publisher
-- --------------------------------------------------------
CREATE TABLE `Publisher` (
  `ID` int NOT NULL,
  `Publisher` varchar(190) COLLATE utf8mb4_general_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `Publisher`
  ADD PRIMARY KEY (`ID`),
  ADD UNIQUE KEY `uq_Publisher_name` (`Publisher`);
ALTER TABLE `Publisher`
  MODIFY `ID` int NOT NULL AUTO_INCREMENT;

-- --------------------------------------------------------
-- Tabelle: Sammlung
-- Hinweis: Originalverpackung, Anleitung, `Datenträger vorhanden`, Datentraegertest, Testdatum und Testergebnis
-- sind Altspalten aus dem früheren Häkchen-Modell. Der Code liest sie nicht mehr (aktuell sind die
-- `... Status`-Spalten). Sie bleiben vorerst erhalten und werden erst nach einem Datenexport entfernt (D12).
-- --------------------------------------------------------
CREATE TABLE `Sammlung` (
  `Bezeichnung` text COLLATE utf8mb4_general_ci,
  `Anzahl` smallint unsigned NOT NULL DEFAULT '1',
  `Kategorie` int DEFAULT NULL,
  `Original/Homebrew` text COLLATE utf8mb4_general_ci,
  `Jahr` smallint unsigned DEFAULT NULL,
  `Zustand` int DEFAULT NULL,
  `Originalverpackung` int DEFAULT NULL,
  `Verpackung Status` varchar(20) COLLATE utf8mb4_general_ci DEFAULT '',
  `Verpackung` int DEFAULT NULL,
  `Anleitung` int DEFAULT NULL,
  `Anleitung Status` varchar(20) COLLATE utf8mb4_general_ci DEFAULT '',
  `Datentraeger` int DEFAULT NULL,
  `Sonstiges` int DEFAULT NULL,
  `Datenträger vorhanden` varchar(1) COLLATE utf8mb4_general_ci DEFAULT '',
  `Datenträger Status` varchar(20) COLLATE utf8mb4_general_ci DEFAULT '',
  `Sonstiges Beschreibung` varchar(255) COLLATE utf8mb4_general_ci DEFAULT '',
  `Datentraegertest` int DEFAULT NULL,
  `Testdatum` text COLLATE utf8mb4_general_ci,
  `Testergebnis` int DEFAULT NULL,
  `Standort` int DEFAULT NULL,
  `Zum Verkauf` tinyint(1) NOT NULL DEFAULT '0',
  `ISBN` varchar(32) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `Box` int DEFAULT NULL,
  `Material` int DEFAULT NULL,
  `Hersteller` int DEFAULT NULL,
  `Publisher` int DEFAULT NULL,
  `Einkaufspreis` decimal(10,2) DEFAULT NULL,
  `Wert` decimal(10,2) DEFAULT NULL,
  `Verkäufer` int DEFAULT NULL,
  `Seriennummer` text COLLATE utf8mb4_general_ci,
  `Barcode` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `Beschreibung` text COLLATE utf8mb4_general_ci,
  `SammlungBild1` text COLLATE utf8mb4_general_ci,
  `Link zum Blog` text COLLATE utf8mb4_general_ci,
  `Link zu YouTube` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `ID` int NOT NULL,
  `Einkaufsdatum` date DEFAULT NULL,
  `Getestet am` date DEFAULT NULL,
  `Getestet Status` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `Erstellt_am` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `Geaendert_am` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `VerbautIn` int DEFAULT NULL COMMENT 'Verbaut als Zubehör in einem anderen Gerät (FK auf Sammlung.ID)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `Sammlung`
  ADD PRIMARY KEY (`ID`),
  ADD KEY `idx_sammlung_kategorie` (`Kategorie`),
  ADD KEY `idx_sammlung_zustand` (`Zustand`),
  ADD KEY `idx_sammlung_verpackung` (`Verpackung`),
  ADD KEY `idx_sammlung_material` (`Material`),
  ADD KEY `idx_sammlung_standort` (`Standort`),
  ADD KEY `idx_sammlung_hersteller` (`Hersteller`),
  ADD KEY `idx_sammlung_publisher` (`Publisher`),
  ADD KEY `idx_sammlung_verkaeufer` (`Verkäufer`),
  ADD KEY `idx_sammlung_datentraeger` (`Datentraeger`),
  ADD KEY `idx_sammlung_box` (`Box`),
  ADD KEY `idx_sammlung_verbautin` (`VerbautIn`),
  ADD KEY `idx_sammlung_bezeichnung` (`Bezeichnung`(191)),
  ADD KEY `idx_sammlung_verkauf` (`Zum Verkauf`),
  ADD KEY `idx_sammlung_barcode` (`Barcode`),
  ADD KEY `idx_sammlung_einkaufsdatum` (`Einkaufsdatum`);
ALTER TABLE `Sammlung`
  MODIFY `ID` int NOT NULL AUTO_INCREMENT;

-- --------------------------------------------------------
-- Tabelle: Standort
-- --------------------------------------------------------
CREATE TABLE `Standort` (
  `ID` int NOT NULL,
  `Standort` varchar(190) COLLATE utf8mb4_general_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `Standort`
  ADD PRIMARY KEY (`ID`),
  ADD UNIQUE KEY `uq_Standort_name` (`Standort`);
ALTER TABLE `Standort`
  MODIFY `ID` int NOT NULL AUTO_INCREMENT;

-- --------------------------------------------------------
-- Tabelle: Verkäufer
-- --------------------------------------------------------
CREATE TABLE `Verkäufer` (
  `Verkäufer` varchar(190) COLLATE utf8mb4_general_ci NOT NULL,
  `ID` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `Verkäufer`
  ADD PRIMARY KEY (`ID`),
  ADD UNIQUE KEY `uq_Verkäufer_name` (`Verkäufer`);
ALTER TABLE `Verkäufer`
  MODIFY `ID` int NOT NULL AUTO_INCREMENT;

-- --------------------------------------------------------
-- Tabelle: Verpackung
-- --------------------------------------------------------
CREATE TABLE `Verpackung` (
  `ID` int NOT NULL,
  `Verpackung` varchar(190) COLLATE utf8mb4_general_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `Verpackung`
  ADD PRIMARY KEY (`ID`),
  ADD UNIQUE KEY `uq_Verpackung_name` (`Verpackung`);
ALTER TABLE `Verpackung`
  MODIFY `ID` int NOT NULL AUTO_INCREMENT;

-- --------------------------------------------------------
-- Tabelle: Zustand
-- --------------------------------------------------------
CREATE TABLE `Zustand` (
  `Zustand` varchar(190) COLLATE utf8mb4_general_ci NOT NULL,
  `ID` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `Zustand`
  ADD PRIMARY KEY (`ID`),
  ADD UNIQUE KEY `uq_Zustand_name` (`Zustand`);
ALTER TABLE `Zustand`
  MODIFY `ID` int NOT NULL AUTO_INCREMENT;

-- --------------------------------------------------------
-- Tabelle: schema_migrations (welche Migrationen sind eingespielt)
-- --------------------------------------------------------
CREATE TABLE `schema_migrations` (
  `Dateiname` varchar(190) NOT NULL,
  `Eingespielt_am` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`Dateiname`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Fremdschlüssel (nach allen Tabellen, damit die Primärschlüssel schon existieren)
-- --------------------------------------------------------
ALTER TABLE `Einkauf`
  ADD CONSTRAINT `fk_einkauf_kategorie` FOREIGN KEY (`Kategorie`) REFERENCES `Kategorie` (`ID`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_einkauf_verkaeufer` FOREIGN KEY (`Verkaeufer`) REFERENCES `Verkäufer` (`ID`) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `Sammlung`
  ADD CONSTRAINT `fk_sammlung_kategorie` FOREIGN KEY (`Kategorie`) REFERENCES `Kategorie` (`ID`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_sammlung_zustand` FOREIGN KEY (`Zustand`) REFERENCES `Zustand` (`ID`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_sammlung_verpackung` FOREIGN KEY (`Verpackung`) REFERENCES `Verpackung` (`ID`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_sammlung_material` FOREIGN KEY (`Material`) REFERENCES `Material` (`ID`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_sammlung_standort` FOREIGN KEY (`Standort`) REFERENCES `Standort` (`ID`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_sammlung_hersteller` FOREIGN KEY (`Hersteller`) REFERENCES `Hersteller` (`ID`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_sammlung_publisher` FOREIGN KEY (`Publisher`) REFERENCES `Publisher` (`ID`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_sammlung_verkaeufer` FOREIGN KEY (`Verkäufer`) REFERENCES `Verkäufer` (`ID`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_sammlung_datentraeger` FOREIGN KEY (`Datentraeger`) REFERENCES `Datentraeger` (`ID`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_sammlung_box` FOREIGN KEY (`Box`) REFERENCES `Box` (`ID`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_sammlung_verbautin` FOREIGN KEY (`VerbautIn`) REFERENCES `Sammlung` (`ID`) ON DELETE SET NULL ON UPDATE CASCADE;

-- --------------------------------------------------------
-- Eingespielte Migrationen: schema.sql bildet den aktuellen Stand ab, daher gelten diese als eingespielt.
-- Jede neue Migration trägt ihren Dateinamen hier ein (siehe migrations/README.md).
-- --------------------------------------------------------
INSERT INTO `schema_migrations` (`Dateiname`) VALUES
  ('0000_login_log.sql'),
  ('2026-10-13_01_login_versuche.sql'),
  ('2026-10-13_02_schema_migrations.sql'),
  ('2026-10-13_03_bereinigung.sql'),
  ('2026-10-13_04_typen.sql'),
  ('2026-10-13_05_lookup_unique.sql'),
  ('2026-10-13_06_geld.sql'),
  ('2026-10-13_07_fremdschluessel.sql');

SET FOREIGN_KEY_CHECKS=1;
