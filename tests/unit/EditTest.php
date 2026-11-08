<?php
use PHPUnit\Framework\TestCase;

/** Formularwerte und Duplikat aus inc_edit.php (ohne Datenbank). */
final class EditTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once UNIT_SRC . '/inc_image.php';   // image_normalize_for_storage (Standardmodus wordpress)
    }

    private function werte(array $post): array
    {
        // Suchfelder ohne ID: Stub statt Datenbank (liefert 77 für den Namen "Atari")
        $stub = fn($t, $f, $idRoh, $textRoh) => (int)$idRoh > 0 ? (int)$idRoh : (trim((string)$textRoh) === 'Atari' ? 77 : 0);
        return edit_collect_values($post, $stub);
    }

    public function testAlleFelderSindEnthalten(): void
    {
        $v = $this->werte([]);
        $this->assertSame(array_keys(EDIT_FIELDS), array_keys($v));
        $this->assertSame(strlen(edit_bind_types()), count($v));
    }

    public function testLeereEingabeErgibtNullStattNull(): void
    {
        $v = $this->werte([]);
        foreach (EDIT_FK_COLS as $c) {
            $this->assertNull($v[$c], "$c leer = NULL");
        }
        $this->assertNull($v['Jahr']);
        $this->assertNull($v['Einkaufspreis']);
        $this->assertNull($v['Wert']);
        $this->assertSame('Original', $v['Original/Homebrew']);
        $this->assertSame(0, $v['Zum Verkauf']);
        $this->assertSame(0, $v['Anzahl']);
        $this->assertSame('', $v['Einkaufsdatum']);
    }

    public function testJahr(): void
    {
        foreach (['1984' => 1984, ' 2000 ' => 2000, '0' => null, '10000' => null, 'abc' => null, '-5' => null, '19.84' => null] as $in => $soll) {
            $this->assertSame($soll, $this->werte(['Jahr' => $in])['Jahr'], "Jahr '$in'");
        }
    }

    public function testGeldDatumBild(): void
    {
        $v = $this->werte([
            'Einkaufspreis' => '1.234,5', 'Wert' => '20 €', 'Einkaufsdatum' => '17.5.2024', 'Getestet_am' => '2025-01-02',
            'SammlungBild1' => 'https://h.example/blog/wp-content/uploads/2014/a.jpg',
        ]);
        $this->assertSame('1234.50', $v['Einkaufspreis']);
        $this->assertSame('20.00', $v['Wert']);
        $this->assertSame('2024-05-17', $v['Einkaufsdatum']);
        $this->assertSame('2025-01-02', $v['Getestet am']);
        $this->assertSame('2014/a.jpg', $v['SammlungBild1']);
        $this->assertNull($this->werte(['Wert' => 'abc'])['Wert']);
        $this->assertSame('', $this->werte(['Einkaufsdatum' => 'kein Datum'])['Einkaufsdatum']);
    }

    public function testAuswahlfelder(): void
    {
        $v = $this->werte(['Kategorie' => '56', 'Box' => '3', 'Material' => 'x', 'Zustand' => '-2', 'Hersteller' => '0']);
        $this->assertSame(56, $v['Kategorie']);
        $this->assertSame(3, $v['Box']);
        $this->assertNull($v['Material'], 'keine Zahl = NULL');
        $this->assertNull($v['Zustand'], 'negativ = NULL');
        $this->assertNull($v['Hersteller']);
    }

    public function testSuchfeldLoestNamenAuf(): void
    {
        $this->assertSame(77, $this->werte(['Hersteller_text' => 'Atari'])['Hersteller']);
        $this->assertSame(5, $this->werte(['Hersteller' => '5', 'Hersteller_text' => 'Atari'])['Hersteller'], 'ID geht vor Text');
        $this->assertNull($this->werte(['Hersteller_text' => 'Unbekannt'])['Hersteller']);
        $this->assertNull($this->werte(['Box_text' => 'Atari'])['Box'], 'nur Such-Felder lösen Namen auf');
    }

    public function testOriginalHomebrew(): void
    {
        $this->assertSame('Homebrew', $this->werte(['Original/Homebrew' => 'Homebrew'])['Original/Homebrew']);
        $this->assertSame('Original', $this->werte(['Original/Homebrew' => 'Sonstiges'])['Original/Homebrew']);
    }

    public function testCheckboxenUndLeerzeichenNamen(): void
    {
        $v = $this->werte(['Zum_Verkauf' => '1', 'Sonstiges' => 'on', 'Datentraeger' => '2', 'Verpackung_Status' => 'Original', 'Sonstiges_Beschreibung' => 'Kabel']);
        $this->assertSame(1, $v['Zum Verkauf']);
        $this->assertSame(1, $v['Sonstiges']);
        $this->assertSame(2, $v['Datentraeger']);
        $this->assertSame('Original', $v['Verpackung Status']);
        $this->assertSame('Kabel', $v['Sonstiges Beschreibung']);
    }

    public function testBindTypes(): void
    {
        $t = edit_bind_types();
        $namen = array_keys(EDIT_FIELDS);
        $this->assertSame('i', $t[array_search('Anzahl', $namen)]);
        $this->assertSame('i', $t[array_search('Kategorie', $namen)]);
        $this->assertSame('i', $t[array_search('VerbautIn', $namen)]);
        $this->assertSame('s', $t[array_search('Bezeichnung', $namen)]);
        $this->assertSame('s', $t[array_search('Jahr', $namen)], 'Jahr wird als Text gebunden (NULL möglich)');
        $this->assertSame('s', $t[array_search('Einkaufspreis', $namen)]);
        $this->assertSame('s', $t[array_search('Einkaufsdatum', $namen)]);
    }

    private function basis(): array
    {
        return $this->werte([
            'Bezeichnung' => 'Pong', 'SammlungBild1' => 'a.jpg', 'Einkaufsdatum' => '2024-01-02', 'Einkaufspreis' => '5', 'Wert' => '9',
            'Getestet_am' => '2024-02-02', 'Getestet_Status' => 'ok', 'Zum_Verkauf' => '1', 'Kategorie' => '4',
        ]);
    }

    public function testDuplikatTitel(): void
    {
        $this->assertSame('Pong (Kopie)', edit_duplicate_values($this->basis(), 'dup')['Bezeichnung']);
        foreach (['Pong (Kopie)' => 'Pong (Kopie 2)', 'Pong (Kopie 2)' => 'Pong (Kopie 3)', 'Pong (Kopie 9)' => 'Pong (Kopie 10)'] as $in => $soll) {
            $v = $this->basis(); $v['Bezeichnung'] = $in;
            $this->assertSame($soll, edit_duplicate_values($v, 'dup')['Bezeichnung']);
        }
        $v = $this->basis(); $v['Bezeichnung'] = '';
        $this->assertSame('', edit_duplicate_values($v, 'dup')['Bezeichnung'], 'leerer Titel bleibt leer');
    }

    public function testDuplikatLeertFelder(): void
    {
        $d = edit_duplicate_values($this->basis(), 'dup');
        $this->assertSame('', $d['SammlungBild1']);
        $this->assertSame('', $d['Einkaufsdatum']);
        $this->assertNull($d['Einkaufspreis']);
        $this->assertNull($d['Wert']);
        $this->assertSame('', $d['Getestet am']);
        $this->assertSame('', $d['Getestet Status']);
        $this->assertSame(0, $d['Zum Verkauf']);
        $this->assertSame(4, $d['Kategorie'], 'übrige Felder bleiben');
    }

    public function testDuplikatMitKosten(): void
    {
        $d = edit_duplicate_values($this->basis(), 'dup_keepcost');
        $this->assertSame('2024-01-02', $d['Einkaufsdatum']);
        $this->assertSame('5.00', $d['Einkaufspreis']);
        $this->assertSame('9.00', $d['Wert']);
        $this->assertSame('', $d['SammlungBild1']);
        $this->assertSame('', $d['Getestet am']);
        $this->assertSame(0, $d['Zum Verkauf']);
    }
}
