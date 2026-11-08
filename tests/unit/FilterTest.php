<?php
use PHPUnit\Framework\TestCase;

final class FilterTest extends TestCase
{
    protected function setUp(): void
    {
        $_GET = [];
        $_COOKIE = [];
    }

    public function testOhneFilter(): void
    {
        $r = sammlung_filter_where([]);
        $this->assertSame(['sql' => '', 'types' => '', 'values' => []], $r);
    }

    public function testLeereWerteSindKeinFilter(): void
    {
        $r = sammlung_filter_where(['filter' => '', 'q' => '  ', 'box' => '', 'hersteller' => '0', 'verkauf' => '0', 'material' => '']);
        $this->assertSame('', $r['sql']);
    }

    public function testSpielFilter(): void
    {
        $r = sammlung_filter_where(['filter' => 'spiel']);
        $this->assertSame("WHERE Kategorie.Ebene = 'spiele'", $r['sql']);
        $this->assertSame('', $r['types']);
    }

    public function testSpielOriginalUndHomebrew(): void
    {
        $o = sammlung_filter_where(['filter' => 'spiel_original']);
        $h = sammlung_filter_where(['filter' => 'spiel_homebrew']);
        $this->assertStringContainsString("Ebene = 'spiele'", $o['sql']);
        $this->assertStringContainsString("='Original'", $o['sql']);
        $this->assertStringContainsString("='Homebrew'", $h['sql']);
    }

    public function testKategorieExakt(): void
    {
        $r = sammlung_filter_where(['filter' => '5']);
        $this->assertSame('WHERE Sammlung.Kategorie = ?', $r['sql']);
        $this->assertSame('i', $r['types']);
        $this->assertSame([5], $r['values']);
    }

    public function testUnbekannterFilterWirdIgnoriert(): void
    {
        $this->assertSame('', sammlung_filter_where(['filter' => 'abc; DROP TABLE'])['sql']);
    }

    public function testKategorieKnoten(): void
    {
        $exakt = sammlung_filter_where(['kat' => '7']);
        $this->assertSame('WHERE Sammlung.Kategorie = ?', $exakt['sql']);
        $this->assertSame([7], $exakt['values']);

        $inkl = sammlung_filter_where(['kat' => '7', 'inkl' => '1']);
        $this->assertStringContainsString('Kategorie.Pfad LIKE CONCAT(', $inkl['sql']);
        $this->assertSame('i', $inkl['types']);
        $this->assertSame([7], $inkl['values']);
    }

    public function testKategorieNullUndNegativ(): void
    {
        $this->assertSame('', sammlung_filter_where(['kat' => '0', 'inkl' => '1'])['sql']);
        $this->assertSame('', sammlung_filter_where(['kat' => 'abc'])['sql']);
    }

    public function testOriginalHomebrew(): void
    {
        $this->assertStringContainsString("='Original'", sammlung_filter_where(['oh' => 'Original'])['sql']);
        $this->assertStringContainsString("='Homebrew'", sammlung_filter_where(['oh' => 'Homebrew'])['sql']);
        $this->assertSame('', sammlung_filter_where(['oh' => 'Sonstiges'])['sql']);
    }

    public function testMaterialMitLikeMaskierung(): void
    {
        $r = sammlung_filter_where(['material' => 'Alu_%']);
        $this->assertSame('WHERE Material.Material LIKE ?', $r['sql']);
        $this->assertSame(['Alu\_\%%'], $r['values']);
    }

    public function testSucheDreiSpalten(): void
    {
        $r = sammlung_filter_where(['q' => '  Pong ']);
        $this->assertSame('sss', $r['types']);
        $this->assertSame(['%Pong%', '%Pong%', '%Pong%'], $r['values']);
        $this->assertSame(3, substr_count($r['sql'], '?'));
    }

    public function testSucheMaskiertJoker(): void
    {
        $r = sammlung_filter_where(['q' => '50%_off']);
        $this->assertSame('%50\%\_off%', $r['values'][0]);
    }

    public function testIdFilter(): void
    {
        $r = sammlung_filter_where(['hersteller' => '3', 'publisher' => '4', 'verkaeufer' => '5', 'standort' => '6']);
        $this->assertSame('iiii', $r['types']);
        $this->assertSame([3, 4, 5, 6], $r['values']);
        $this->assertStringContainsString('Sammlung.Hersteller = ?', $r['sql']);
        $this->assertStringContainsString('Sammlung.Publisher = ?', $r['sql']);
        $this->assertStringContainsString('Sammlung.`Verkäufer` = ?', $r['sql']);
        $this->assertStringContainsString('Sammlung.Standort = ?', $r['sql']);
    }

    public function testIdFilterUngueltig(): void
    {
        $this->assertSame('', sammlung_filter_where(['hersteller' => 'x'])['sql']);
    }

    public function testBox(): void
    {
        $r = sammlung_filter_where(['box' => '12']);
        $this->assertSame('WHERE Sammlung.Box = ?', $r['sql']);
        $this->assertSame([12], $r['values']);
        $this->assertSame('', sammlung_filter_where(['box' => '1a'])['sql']);
        $this->assertSame('', sammlung_filter_where(['box' => '-1'])['sql']);
    }

    public function testZumVerkauf(): void
    {
        foreach ([true, '1', 1] as $wert) {
            $this->assertSame('WHERE Sammlung.`Zum Verkauf` = 1', sammlung_filter_where(['verkauf' => $wert])['sql']);
        }
        $this->assertSame('', sammlung_filter_where(['verkauf' => '0'])['sql']);
        $this->assertSame('', sammlung_filter_where(['verkauf' => false])['sql']);
    }

    public function testEinkaufsdatum(): void
    {
        $r = sammlung_filter_where(['einkaufsdatum' => '2024-05-17']);
        $this->assertSame('WHERE Sammlung.Einkaufsdatum = ?', $r['sql']);
        $this->assertSame(['2024-05-17'], $r['values']);
        $this->assertSame('', sammlung_filter_where(['einkaufsdatum' => '17.05.2024'])['sql']);
        $this->assertSame('', sammlung_filter_where(['einkaufsdatum' => "2024-05-17' OR 1=1"])['sql']);
    }

    public function testKombinationReihenfolgeUndTypen(): void
    {
        $r = sammlung_filter_where(['filter' => '3', 'q' => 'x', 'standort' => '2', 'verkauf' => '1']);
        $this->assertSame('isssi', $r['types']);
        $this->assertSame([3, '%x%', '%x%', '%x%', 2], $r['values']);
        $this->assertSame(substr_count($r['sql'], '?'), strlen($r['types']));
        $this->assertStringStartsWith('WHERE ', $r['sql']);
        $this->assertSame(4, substr_count($r['sql'], ' AND ') + 1);
    }

    public function testEigeneAliase(): void
    {
        $r = sammlung_filter_where(['filter' => '5', 'material' => 'Holz'], ['s' => 's', 'k' => 'k', 'm' => 'm']);
        $this->assertSame('WHERE s.Kategorie = ? AND m.Material LIKE ?', $r['sql']);
    }

    public function testNutzereingabeLandetNieImSql(): void
    {
        $boese = "x' OR '1'='1";
        $r = sammlung_filter_where(['q' => $boese, 'material' => $boese, 'box' => $boese, 'oh' => $boese, 'filter' => $boese, 'einkaufsdatum' => $boese]);
        $this->assertStringNotContainsString("x'", $r['sql']);
        $this->assertStringNotContainsString("'1'", $r['sql']);
        $this->assertSame(substr_count($r['sql'], '?'), strlen($r['types']));
    }

    public function testFilterKeepParams(): void
    {
        $post = ['q' => 'x', 'page' => '2', 'kat' => '5', 'inkl' => '1', 'leer' => '', 'id' => '9', 'csrf' => 'T', 'filter' => '', 'sort' => 'Jahr'];
        $this->assertSame(['page' => '2', 'sort' => 'Jahr', 'kat' => '5', 'inkl' => '1', 'q' => 'x'], filter_keep_params($post));
        $this->assertSame([], filter_keep_params([]));
        $this->assertSame([], filter_keep_params(['id' => '1', 'csrf' => 'x', 'Bezeichnung' => 'y']));
    }

    public function testFilterKeepKeysEnthaeltAlleFilter(): void
    {
        foreach (['filter', 'kat', 'inkl', 'oh', 'material', 'q', 'hersteller', 'publisher', 'verkaeufer', 'standort', 'box', 'verkauf', 'einkaufsdatum', 'page', 'sort', 'dir'] as $k) {
            $this->assertContains($k, FILTER_KEEP_KEYS);
        }
    }

    public function testLikeEscape(): void
    {
        $this->assertSame('a\%b\_c\\\\d', like_escape('a%b_c\\d'));
        $this->assertSame('', like_escape(''));
    }

    public function testBoxSortSql(): void
    {
        $sql = box_sort_sql('bx');
        $this->assertStringContainsString('bx.`Box`', $sql);
        $this->assertStringContainsString("REGEXP '^Box [0-9]+$'", $sql);
    }

    public function testPageSize(): void
    {
        $this->assertSame(25, page_size());
        $_GET['pp'] = '50';
        $this->assertSame(50, page_size());
        $_GET['pp'] = '30';
        $this->assertSame(25, page_size());
        $_GET['pp'] = "100'";
        $this->assertSame(25, page_size());
        $_GET = [];
        $_COOKIE['pp'] = '100';
        $this->assertSame(100, page_size());
        $_GET['pp'] = '50';
        $this->assertSame(50, page_size(), 'GET geht vor Cookie');
        $_GET['pp'] = 'x';
        $this->assertSame(100, page_size(), 'ungültiges GET fällt auf den Cookie zurück');
    }

    private function baum(): array
    {
        $n = fn($id, $name, $parent, $ebene) => ['id' => $id, 'name' => $name, 'parent' => $parent, 'ebene' => $ebene, 'depth' => 0, 'kinder' => 0];
        return [
            $n(1, 'Atari', null, 'marke'),
            $n(2, 'Atari VCS', 1, 'plattform'),
            $n(3, 'Spiele', 2, 'spiele'),
            $n(4, 'Anwendungen', 2, 'anwendungen'),
            $n(5, 'Amiga', null, 'plattform'),
            $n(6, 'Amiga Spiele', 5, 'spiele'),
        ];
    }

    public function testKaskadeOhneAuswahl(): void
    {
        $k = kategorie_kaskade($this->baum(), 0, false);
        $this->assertSame('', $k['s1']['wahl']);
        $this->assertCount(2, $k['s1']['optionen']);
        $this->assertNull($k['s2']);
        $this->assertNull($k['s3']);
        $this->assertSame([], $k['pfad']);
    }

    public function testKaskadePlattformEinerMarke(): void
    {
        $k = kategorie_kaskade($this->baum(), 2, true);
        $this->assertSame(['Atari', 'Atari VCS'], $k['pfad']);
        $this->assertSame('1:1', $k['s1']['wahl']);
        $this->assertSame('2:1', $k['s2']['wahl']);
        $this->assertSame('1:1', $k['s2']['leer']);
        $this->assertSame('2:1', $k['s3']['wahl']);
        $this->assertSame('2:1', $k['s3']['optionen'][0][0]);
        $this->assertSame('2:0', $k['s3']['optionen'][1][0]);
        $this->assertSame('Hardware & Zubehör', $k['s3']['optionen'][1][1]);
    }

    public function testKaskadeNurHardware(): void
    {
        $k = kategorie_kaskade($this->baum(), 2, false);
        $this->assertSame('2:0', $k['s3']['wahl']);
    }

    public function testKaskadeTyp(): void
    {
        $k = kategorie_kaskade($this->baum(), 3, false);
        $this->assertSame(['Atari', 'Atari VCS', 'Spiele'], $k['pfad']);
        $this->assertSame('3:0', $k['s3']['wahl'], 'Blatt hat keine Kinder, also inkl 0');
    }

    public function testKaskadeWurzelPlattformOhneMarke(): void
    {
        $k = kategorie_kaskade($this->baum(), 5, true);
        $this->assertNull($k['s2']);
        $this->assertNotNull($k['s3']);
        $this->assertSame('5:1', $k['s1']['wahl']);
    }

    public function testKaskadeUnbekannteId(): void
    {
        $k = kategorie_kaskade($this->baum(), 999, false);
        $this->assertSame('', $k['s1']['wahl']);
        $this->assertSame([], $k['pfad']);
    }
}
