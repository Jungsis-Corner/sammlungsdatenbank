<?php
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SecurityTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    public static function rueckspruenge(): array
    {
        return [
            'Seite'               => ['edit.php', 'edit.php'],
            'mit Query'           => ['index.php?filter=5&page=2', 'index.php?filter=5&page=2'],
            'mit Unterstrich'     => ['einkauf_edit.php?id=3', 'einkauf_edit.php?id=3'],
            'leer'                => ['', 'FB'],
            'volle URL'           => ['https://evil.example/a.php', 'FB'],
            'protokollrelativ'    => ['//evil.example/a.php', 'FB'],
            'javascript'          => ['javascript:alert(1)', 'FB'],
            'Pfad nach oben'      => ['../a.php', 'FB'],
            'Unterordner'         => ['x/a.php', 'FB'],
            'kein php'            => ['a.html', 'FB'],
            'Backslash in Query'  => ['a.php?x=\\evil', 'FB'],
            'Zeilenumbruch'       => ["a.php?x=1\nLocation: x", 'FB'],
            'Nullbyte'            => ["a.php\0", 'FB'],
        ];
    }

    #[DataProvider('rueckspruenge')]
    public function testSafeBackUrl(string $eingabe, string $erwartet): void
    {
        $this->assertSame($erwartet, safe_back_url($eingabe, 'FB'));
    }

    public function testSafeBackUrlNull(): void
    {
        $this->assertSame('FB', safe_back_url(null, 'FB'));
    }

    public static function lokalePfade(): array
    {
        return [
            'gültig'              => ['/sammlung/index.php?x=1', '/sammlung/index.php?x=1'],
            'fremder Ordner'      => ['/blog/', 'FB'],
            'leer'                => ['', 'FB'],
            'Doppelschrägstrich'  => ['/sammlung//evil', 'FB'],
            'Protokoll'           => ['/sammlung/a?u=http://x', 'FB'],
            'Pfad nach oben'      => ['/sammlung/../config.php', 'FB'],
            'kodiert nach oben'   => ['/sammlung/%2e%2e/config.php', 'FB'],
            'kodierter Backslash' => ['/sammlung/a%5Cb', 'FB'],
            'kodierter Umbruch'   => ['/sammlung/a%0Ab', 'FB'],
            'Backslash'           => ['/sammlung/a\\b', 'FB'],
            'volle URL'           => ['https://evil.example/sammlung/', 'FB'],
        ];
    }

    #[DataProvider('lokalePfade')]
    public function testSafeLocalPath(string $eingabe, string $erwartet): void
    {
        $this->assertSame($erwartet, safe_local_path($eingabe, 'FB'));
    }

    public function testCsvSafeCell(): void
    {
        foreach (['=1+1', '+49', '-5', '@SUM(A1)', "\tx", "\rx"] as $v) {
            $this->assertSame("'" . $v, csv_safe_cell($v), 'Formelzeichen: ' . json_encode($v));
        }
        $this->assertSame('Pong', csv_safe_cell('Pong'));
        $this->assertSame('', csv_safe_cell(''));
        $this->assertSame('a=b', csv_safe_cell('a=b'));
    }

    public function testCsrfToken(): void
    {
        $t = csrf_token();
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $t);
        $this->assertSame($t, csrf_token(), 'bleibt in der Session gleich');
        $this->assertSame($t, $_SESSION['csrf']);
        $feld = csrf_field();
        $this->assertStringContainsString('name="csrf"', $feld);
        $this->assertStringContainsString('value="' . $t . '"', $feld);
    }

    public function testFlash(): void
    {
        $this->assertNull(flash_pull());
        flash_set('Gespeichert', 'success');
        $this->assertSame(['text' => 'Gespeichert', 'type' => 'success'], flash_pull());
        $this->assertNull(flash_pull(), 'einmalig');
        flash_set('x', 'gefährlich');
        $this->assertSame('info', flash_pull()['type'], 'unbekannter Typ wird info');
    }

    public function testLookupIdent(): void
    {
        $this->assertSame(['`Material`', '`Material`'], lookup_ident('Material', 'Material'));
        $this->assertSame(['`Datentraeger`', '`Datentrager`'], lookup_ident('Datentraeger', 'Datentrager'));
        $this->assertSame(['`Verkäufer`', '`Verkäufer`'], lookup_ident('Verkäufer', 'Verkäufer'));
    }

    public function testLookupRefs(): void
    {
        $this->assertSame([['`Sammlung`', '`Material`']], lookup_refs('Material'));
        $this->assertContains(['`Einkauf`', '`Verkaeufer`'], lookup_refs('Verkäufer'));
        $this->assertCount(2, lookup_refs('Kategorie'));
        $this->assertSame([], lookup_refs('Gibtesnicht'));
    }
}
