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
  `Box` text COLLATE utf8mb4_general_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `Box`
  ADD PRIMARY KEY (`ID`);
ALTER TABLE `Box`
  MODIFY `ID` int NOT NULL AUTO_INCREMENT;

-- --------------------------------------------------------
-- Tabelle: Datentraeger
-- --------------------------------------------------------
CREATE TABLE `Datentraeger` (
  `ID` int NOT NULL,
  `Datentrager` text COLLATE utf8mb4_general_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `Datentraeger`
  ADD PRIMARY KEY (`ID`);
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

ALTER TABLE `Einkauf`
  ADD PRIMARY KEY (`ID`),
  ADD KEY `idx_einkauf_bestell` (`Bestelldatum`),
  ADD KEY `idx_einkauf_liefer` (`Lieferdatum`),
  ADD KEY `idx_einkauf_kat` (`Kategorie`),
  ADD KEY `idx_einkauf_verk` (`Verkaeufer`);
ALTER TABLE `Einkauf`
  MODIFY `ID` int NOT NULL AUTO_INCREMENT;
ALTER TABLE `Einkauf`
  ADD CONSTRAINT `fk_einkauf_kategorie` FOREIGN KEY (`Kategorie`) REFERENCES `Kategorie` (`ID`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_einkauf_verkaeufer` FOREIGN KEY (`Verkaeufer`) REFERENCES `Verkäufer` (`ID`) ON DELETE SET NULL ON UPDATE CASCADE;

-- --------------------------------------------------------
-- Tabelle: Hersteller
-- --------------------------------------------------------
CREATE TABLE `Hersteller` (
  `Hersteller` text COLLATE utf8mb4_general_ci,
  `ID` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `Hersteller`
  ADD PRIMARY KEY (`ID`);
ALTER TABLE `Hersteller`
  MODIFY `ID` int NOT NULL AUTO_INCREMENT;

-- --------------------------------------------------------
-- Tabelle: Kategorie
-- --------------------------------------------------------
CREATE TABLE `Kategorie` (
  `Kategorie` text COLLATE utf8mb4_general_ci,
  `ID` int NOT NULL,
  `Spiele` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `Kategorie`
  ADD PRIMARY KEY (`ID`);
ALTER TABLE `Kategorie`
  MODIFY `ID` int NOT NULL AUTO_INCREMENT;

-- --------------------------------------------------------
-- Tabelle: Material
-- --------------------------------------------------------
CREATE TABLE `Material` (
  `Material` text COLLATE utf8mb4_general_ci,
  `ID` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `Material`
  ADD PRIMARY KEY (`ID`);
ALTER TABLE `Material`
  MODIFY `ID` int NOT NULL AUTO_INCREMENT;

-- --------------------------------------------------------
-- Tabelle: Publisher
-- --------------------------------------------------------
CREATE TABLE `Publisher` (
  `ID` int NOT NULL,
  `Publisher` text COLLATE utf8mb4_general_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `Publisher`
  ADD PRIMARY KEY (`ID`);
ALTER TABLE `Publisher`
  MODIFY `ID` int NOT NULL AUTO_INCREMENT;

-- --------------------------------------------------------
-- Tabelle: Sammlung
-- --------------------------------------------------------
CREATE TABLE `Sammlung` (
  `Bezeichnung` text COLLATE utf8mb4_general_ci,
  `Anzahl` float DEFAULT NULL,
  `Kategorie` float DEFAULT NULL,
  `Original/Homebrew` text COLLATE utf8mb4_general_ci,
  `Jahr` float DEFAULT NULL,
  `Zustand` int DEFAULT NULL,
  `Verpackung Status` varchar(20) COLLATE utf8mb4_general_ci DEFAULT '',
  `Verpackung` float DEFAULT NULL,
  `Anleitung Status` varchar(20) COLLATE utf8mb4_general_ci DEFAULT '',
  `Datentraeger` float DEFAULT NULL,
  `Sonstiges` int DEFAULT NULL,
  `Datenträger Status` varchar(20) COLLATE utf8mb4_general_ci DEFAULT '',
  `Sonstiges Beschreibung` varchar(255) COLLATE utf8mb4_general_ci DEFAULT '',
  `Standort` float DEFAULT NULL,
  `Zum Verkauf` varchar(1) COLLATE utf8mb4_general_ci DEFAULT '',
  `ISBN` varchar(32) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `Box` float DEFAULT NULL,
  `Material` int DEFAULT NULL,
  `Hersteller` float DEFAULT NULL,
  `Publisher` float DEFAULT NULL,
  `Einkaufspreis` text COLLATE utf8mb4_general_ci,
  `Wert` text COLLATE utf8mb4_general_ci,
  `Verkäufer` float DEFAULT NULL,
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
  ADD PRIMARY KEY (`ID`);
ALTER TABLE `Sammlung`
  MODIFY `ID` int NOT NULL AUTO_INCREMENT;

-- --------------------------------------------------------
-- Tabelle: Standort
-- --------------------------------------------------------
CREATE TABLE `Standort` (
  `ID` int NOT NULL,
  `Standort` text COLLATE utf8mb4_general_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `Standort`
  ADD PRIMARY KEY (`ID`);
ALTER TABLE `Standort`
  MODIFY `ID` int NOT NULL AUTO_INCREMENT;

-- --------------------------------------------------------
-- Tabelle: Verkäufer
-- --------------------------------------------------------
CREATE TABLE `Verkäufer` (
  `Verkäufer` text COLLATE utf8mb4_general_ci,
  `ID` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `Verkäufer`
  ADD PRIMARY KEY (`ID`);
ALTER TABLE `Verkäufer`
  MODIFY `ID` int NOT NULL AUTO_INCREMENT;

-- --------------------------------------------------------
-- Tabelle: Verpackung
-- --------------------------------------------------------
CREATE TABLE `Verpackung` (
  `ID` int NOT NULL,
  `Verpackung` text COLLATE utf8mb4_general_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `Verpackung`
  ADD PRIMARY KEY (`ID`);
ALTER TABLE `Verpackung`
  MODIFY `ID` int NOT NULL AUTO_INCREMENT;

-- --------------------------------------------------------
-- Tabelle: Zustand
-- --------------------------------------------------------
CREATE TABLE `Zustand` (
  `Zustand` text COLLATE utf8mb4_general_ci,
  `ID` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `Zustand`
  ADD PRIMARY KEY (`ID`);
ALTER TABLE `Zustand`
  MODIFY `ID` int NOT NULL AUTO_INCREMENT;

SET FOREIGN_KEY_CHECKS=1;
