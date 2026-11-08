<?php
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FormatTest extends TestCase
{
    public static function eingaben(): array
    {
        return [
            'Komma'                 => ['12,5', '12.50'],
            'Punkt'                 => ['12.5', '12.50'],
            'ganze Zahl'            => ['12', '12.00'],
            'null'                  => ['0', '0.00'],
            'Tausenderpunkt+Komma'  => ['1.234,50', '1234.50'],
            'Tausenderpunkt'        => ['1.234', '1234.00'],
            'mehrere Tausender'     => ['1.234.567,89', '1234567.89'],
            'Euro und Leerzeichen'  => ['12 €', '12.00'],
            'Euro vorn'             => ['€ 7,99', '7.99'],
            'geschütztes Leerzeichen' => ["1\xC2\xA0234", '1234.00'],
            'Obergrenze'            => ['99999999,99', '99999999.99'],
            'zu groß'               => ['100000000', null],
            'leer'                  => ['', null],
            'nur Leerzeichen'       => ['   ', null],
            'Text'                  => ['abc', null],
            'negativ'               => ['-5', null],
            'drei Nachkommastellen' => ['12,345', null],
            'US-Format'             => ['1,234.50', null],
            'zwei Trenner'          => ['1,2,3', null],
        ];
    }

    #[DataProvider('eingaben')]
    public function testParseDecimal(string $eingabe, ?string $erwartet): void
    {
        $this->assertSame($erwartet, parse_decimal($eingabe));
    }

    public function testParseDecimalNull(): void
    {
        $this->assertNull(parse_decimal(null));
    }

    public function testParseDecimalZahlTyp(): void
    {
        $this->assertSame('12.50', parse_decimal(12.5));
        $this->assertSame('3.00', parse_decimal(3));
    }

    public function testFmtDec(): void
    {
        $this->assertSame('12,50', fmt_dec('12.5'));
        $this->assertSame('1.234,50', fmt_dec('1234.5'));
        $this->assertSame('0,00', fmt_dec(0));
        $this->assertSame('', fmt_dec(null));
        $this->assertSame('', fmt_dec(''));
        $this->assertSame('', fmt_dec('abc'));
    }

    public function testFmtEur(): void
    {
        $this->assertSame('1.234,50 €', fmt_eur('1234.5'));
        $this->assertSame('0,00 €', fmt_eur('0.00'));
        $this->assertSame('', fmt_eur(null));
    }

    public function testRundlauf(): void
    {
        foreach (['1.234,50', '0,99', '12', '99999999,99'] as $eingabe) {
            $db = parse_decimal($eingabe);
            $this->assertNotNull($db);
            $this->assertSame($db, parse_decimal(fmt_dec($db)), "Rundlauf für $eingabe");
        }
    }
}
