<?php
/**
 * Geldbeträge: Eingabe parsen und Ausgabe formatieren (Spalten DECIMAL(10,2)).
 */

/**
 * Liest einen Betrag aus Benutzereingabe. Akzeptiert Komma oder Punkt als Dezimaltrenner,
 * Punkt als Tausendertrenner ("1.234,50"), Leerzeichen und "€".
 * Liefert "1234.50" (für DECIMAL) oder null bei leerer/ungültiger Eingabe, negativen Werten
 * und Beträgen über 99.999.999,99.
 */
function parse_decimal($v): ?string {
    $s = str_replace(["\xC2\xA0", ' ', "\t", '€'], '', trim((string)$v));
    if ($s === '') return null;
    if (preg_match('/^\d{1,3}(\.\d{3})+(,\d{1,2})?$/', $s)) {
        $s = str_replace('.', '', $s);                 // 1.234,50 / 1.234
    }
    if (!preg_match('/^(\d{1,8})(?:[.,](\d{1,2}))?$/', $s, $m)) return null;
    return $m[1] . '.' . str_pad($m[2] ?? '0', 2, '0');
}

/** "12.50" aus der Datenbank -> "12,50" (Formularfelder, CSV). Leer bei NULL/leer. */
function fmt_dec($v): string {
    if ($v === null || $v === '' || !is_numeric($v)) return '';
    return number_format((float)$v, 2, ',', '.');
}

/** "12.50" aus der Datenbank -> "12,50 €" (Anzeige). Leer bei NULL/leer. */
function fmt_eur($v): string {
    $s = fmt_dec($v);
    return $s === '' ? '' : $s . ' €';
}
