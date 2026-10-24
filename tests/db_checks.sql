-- tests/db_checks.sql (T3): Datenbankprüfungen, NUR LESEND (nur SELECT).
-- Lokal: mysql ... d9t < tests/db_checks.sql    Live: in phpMyAdmin (SQL) einfügen und ausführen.
-- Jede Zeile: Prüfung | Ergebnis | Soll | Status. Status OK oder FEHLER; "Info" ist nur eine Zahl zum Notieren.
-- Zahlen aus der Gesamtübersicht nach jeder Migration mit dem Stand davor vergleichen.

SELECT 'Info: Einträge in Sammlung' AS Pruefung, COUNT(*) AS Ergebnis, '' AS Soll, 'Info' AS Status FROM Sammlung
UNION ALL SELECT 'Info: Summe Wert', IFNULL(SUM(`Wert`),0), '', 'Info' FROM Sammlung
UNION ALL SELECT 'Info: Summe Einkaufspreis', IFNULL(SUM(`Einkaufspreis`),0), '', 'Info' FROM Sammlung
UNION ALL SELECT 'Info: Einträge in Einkauf', COUNT(*), '', 'Info' FROM Einkauf
UNION ALL SELECT 'Info: Summe Einkauf.Preis', IFNULL(SUM(`Preis`),0), '', 'Info' FROM Einkauf
UNION ALL SELECT 'Info: Kategorien', COUNT(*), '', 'Info' FROM Kategorie

-- Verwaiste Verweise (zusätzlich zu den Fremdschlüsseln, falls einer fehlt)
UNION ALL SELECT 'Sammlung.Kategorie ohne Kategorie', COUNT(*), '0', IF(COUNT(*)=0,'OK','FEHLER') FROM Sammlung s LEFT JOIN Kategorie x ON x.ID=s.Kategorie WHERE s.Kategorie IS NOT NULL AND x.ID IS NULL
UNION ALL SELECT 'Sammlung.Hersteller verwaist', COUNT(*), '0', IF(COUNT(*)=0,'OK','FEHLER') FROM Sammlung s LEFT JOIN Hersteller x ON x.ID=s.Hersteller WHERE s.Hersteller IS NOT NULL AND x.ID IS NULL
UNION ALL SELECT 'Sammlung.Publisher verwaist', COUNT(*), '0', IF(COUNT(*)=0,'OK','FEHLER') FROM Sammlung s LEFT JOIN Publisher x ON x.ID=s.Publisher WHERE s.Publisher IS NOT NULL AND x.ID IS NULL
UNION ALL SELECT 'Sammlung.Verkäufer verwaist', COUNT(*), '0', IF(COUNT(*)=0,'OK','FEHLER') FROM Sammlung s LEFT JOIN `Verkäufer` x ON x.ID=s.`Verkäufer` WHERE s.`Verkäufer` IS NOT NULL AND x.ID IS NULL
UNION ALL SELECT 'Sammlung.Zustand verwaist', COUNT(*), '0', IF(COUNT(*)=0,'OK','FEHLER') FROM Sammlung s LEFT JOIN Zustand x ON x.ID=s.Zustand WHERE s.Zustand IS NOT NULL AND x.ID IS NULL
UNION ALL SELECT 'Sammlung.Verpackung verwaist', COUNT(*), '0', IF(COUNT(*)=0,'OK','FEHLER') FROM Sammlung s LEFT JOIN Verpackung x ON x.ID=s.Verpackung WHERE s.Verpackung IS NOT NULL AND x.ID IS NULL
UNION ALL SELECT 'Sammlung.Datentraeger verwaist', COUNT(*), '0', IF(COUNT(*)=0,'OK','FEHLER') FROM Sammlung s LEFT JOIN Datentraeger x ON x.ID=s.Datentraeger WHERE s.Datentraeger IS NOT NULL AND x.ID IS NULL
UNION ALL SELECT 'Sammlung.Material verwaist', COUNT(*), '0', IF(COUNT(*)=0,'OK','FEHLER') FROM Sammlung s LEFT JOIN Material x ON x.ID=s.Material WHERE s.Material IS NOT NULL AND x.ID IS NULL
UNION ALL SELECT 'Sammlung.Standort verwaist', COUNT(*), '0', IF(COUNT(*)=0,'OK','FEHLER') FROM Sammlung s LEFT JOIN Standort x ON x.ID=s.Standort WHERE s.Standort IS NOT NULL AND x.ID IS NULL
UNION ALL SELECT 'Sammlung.Box verwaist', COUNT(*), '0', IF(COUNT(*)=0,'OK','FEHLER') FROM Sammlung s LEFT JOIN Box x ON x.ID=s.Box WHERE s.Box IS NOT NULL AND x.ID IS NULL
UNION ALL SELECT 'Sammlung.VerbautIn verwaist', COUNT(*), '0', IF(COUNT(*)=0,'OK','FEHLER') FROM Sammlung s LEFT JOIN Sammlung x ON x.ID=s.VerbautIn WHERE s.VerbautIn IS NOT NULL AND x.ID IS NULL
UNION ALL SELECT 'Sammlung verweist auf sich selbst (VerbautIn)', COUNT(*), '0', IF(COUNT(*)=0,'OK','FEHLER') FROM Sammlung WHERE VerbautIn = ID
UNION ALL SELECT 'Einkauf.Kategorie verwaist', COUNT(*), '0', IF(COUNT(*)=0,'OK','FEHLER') FROM Einkauf e LEFT JOIN Kategorie x ON x.ID=e.Kategorie WHERE e.Kategorie IS NOT NULL AND x.ID IS NULL
UNION ALL SELECT 'Einkauf.Verkaeufer verwaist', COUNT(*), '0', IF(COUNT(*)=0,'OK','FEHLER') FROM Einkauf e LEFT JOIN `Verkäufer` x ON x.ID=e.Verkaeufer WHERE e.Verkaeufer IS NOT NULL AND x.ID IS NULL

-- Leere Auswahlfelder müssen NULL sein, nie 0
UNION ALL SELECT 'Auswahlfelder mit 0 statt NULL', COUNT(*), '0', IF(COUNT(*)=0,'OK','FEHLER') FROM Sammlung
  WHERE Kategorie=0 OR Hersteller=0 OR Publisher=0 OR `Verkäufer`=0 OR Zustand=0 OR Verpackung=0 OR Datentraeger=0 OR Material=0 OR Standort=0 OR Box=0 OR VerbautIn=0
UNION ALL SELECT 'Einkauf: Auswahlfelder mit 0', COUNT(*), '0', IF(COUNT(*)=0,'OK','FEHLER') FROM Einkauf WHERE Kategorie=0 OR Verkaeufer=0

-- Datumswerte
-- Bekannte Altlast: 0000-00-00 steht in alten Zeilen (Einkaufs-/Testdatum); die Seiten behandeln den Wert wie "leer". Nur zählen, erst in D12 aufräumen.
UNION ALL SELECT 'Info: Sammlung mit Datum 0000-00-00 (Altlast, D12)', COUNT(*), '', 'Info' FROM Sammlung WHERE CAST(`Einkaufsdatum` AS CHAR)='0000-00-00' OR CAST(`Getestet am` AS CHAR)='0000-00-00'
UNION ALL SELECT 'Info: Einkauf mit Datum 0000-00-00 (Altlast, D12)', COUNT(*), '', 'Info' FROM Einkauf WHERE CAST(`Bestelldatum` AS CHAR)='0000-00-00' OR CAST(`Lieferdatum` AS CHAR)='0000-00-00' OR CAST(`Kickstarter_Lieferdatum` AS CHAR)='0000-00-00'

-- Geld: nie negativ
UNION ALL SELECT 'Negative Geldbeträge', COUNT(*), '0', IF(COUNT(*)=0,'OK','FEHLER') FROM Sammlung WHERE `Wert`<0 OR `Einkaufspreis`<0
UNION ALL SELECT 'Einkauf: negative Preise', COUNT(*), '0', IF(COUNT(*)=0,'OK','FEHLER') FROM Einkauf WHERE `Preis`<0

-- Kategorie-Baum
UNION ALL SELECT 'Kategorie ohne Pfad', COUNT(*), '0', IF(COUNT(*)=0,'OK','FEHLER') FROM Kategorie WHERE Pfad IS NULL OR Pfad='' OR Pfad='/'
UNION ALL SELECT 'Kategorie: Pfad endet nicht auf eigener ID', COUNT(*), '0', IF(COUNT(*)=0,'OK','FEHLER') FROM Kategorie WHERE Pfad NOT LIKE CONCAT('%/', ID, '/')
UNION ALL SELECT 'Kategorie: Pfad passt nicht zum Eltern-Pfad', COUNT(*), '0', IF(COUNT(*)=0,'OK','FEHLER') FROM Kategorie k LEFT JOIN Kategorie p ON p.ID=k.ParentID WHERE k.ParentID IS NOT NULL AND k.Pfad <> CONCAT(p.Pfad, k.ID, '/')
UNION ALL SELECT 'Kategorie: Wurzel mit falschem Pfad', COUNT(*), '0', IF(COUNT(*)=0,'OK','FEHLER') FROM Kategorie WHERE ParentID IS NULL AND Pfad <> CONCAT('/', ID, '/')
UNION ALL SELECT 'Kategorie: alte Spalte Spiele passt nicht zur Ebene', COUNT(*), '0', IF(COUNT(*)=0,'OK','FEHLER') FROM Kategorie WHERE (Ebene='spiele' AND IFNULL(Spiele,0)=0) OR (Ebene<>'spiele' AND IFNULL(Spiele,0)<>0)

-- Lookup-Namen eindeutig (ohne Groß-/Kleinschreibung), leere Namen
UNION ALL SELECT 'Hersteller: doppelte Namen', COUNT(*), '0', IF(COUNT(*)=0,'OK','FEHLER') FROM (SELECT LOWER(Hersteller) n FROM Hersteller GROUP BY n HAVING COUNT(*)>1) t
UNION ALL SELECT 'Publisher: doppelte Namen', COUNT(*), '0', IF(COUNT(*)=0,'OK','FEHLER') FROM (SELECT LOWER(Publisher) n FROM Publisher GROUP BY n HAVING COUNT(*)>1) t
UNION ALL SELECT 'Verkäufer: doppelte Namen', COUNT(*), '0', IF(COUNT(*)=0,'OK','FEHLER') FROM (SELECT LOWER(`Verkäufer`) n FROM `Verkäufer` GROUP BY n HAVING COUNT(*)>1) t
UNION ALL SELECT 'Box: Namen ohne "Box N" (außer Regal)', COUNT(*), '0', IF(COUNT(*)=0,'OK','FEHLER') FROM Box WHERE Box NOT REGEXP '^Box [0-9]+$' AND Box <> 'Regal'

-- Bilder (nach Migration D8 relativ)
UNION ALL SELECT 'Bilder mit http(s)-Adresse', COUNT(*), '0', IF(COUNT(*)=0,'OK','FEHLER') FROM Sammlung WHERE SammlungBild1 LIKE 'http%' OR SammlungBild1 LIKE '//%'
UNION ALL SELECT 'Bilder mit Rückwärtsstrich oder ../ im Pfad', COUNT(*), '0', IF(COUNT(*)=0,'OK','FEHLER') FROM Sammlung WHERE SammlungBild1 LIKE '%\\\\%' OR SammlungBild1 LIKE '%../%' OR SammlungBild1 LIKE '%/..'

-- Strukturelles
UNION ALL SELECT 'Tabellen mit anderer Collation als utf8mb4_general_ci', COUNT(*), '0', IF(COUNT(*)=0,'OK','FEHLER') FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE' AND TABLE_COLLATION <> 'utf8mb4_general_ci'
UNION ALL SELECT 'Fremdschlüssel von Sammlung (erwartet 11)', COUNT(*), '11', IF(COUNT(*)=11,'OK','FEHLER') FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='Sammlung'
UNION ALL SELECT 'Eingespielte Migrationen (Info)', COUNT(*), '', 'Info' FROM schema_migrations
;
