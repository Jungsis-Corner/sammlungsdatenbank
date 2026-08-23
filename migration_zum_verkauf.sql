-- Migration: Checkbox "Zum Verkauf" für Duplikate/Doppelte
-- Einmal ausführen (z.B. über phpMyAdmin), danach kann diese Datei gelöscht werden.

ALTER TABLE `Sammlung`
  ADD COLUMN `Zum Verkauf` VARCHAR(1) NULL DEFAULT '' AFTER `Standort`;
