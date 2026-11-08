<?php
use PHPUnit\Framework\TestCase;

/** Standardmodus (IMAGE_SOURCE = wordpress). Die Konstanten stehen beim Laden fest, daher laden diese Tests inc_image.php selbst. */
final class ImageTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once UNIT_SRC . '/inc_image.php';
    }

    public function testSafeName(): void
    {
        $this->assertSame('a/b.jpg', image_safe_name('a/b.jpg'));
        $this->assertSame('x.jpg', image_safe_name('  x.jpg '));
        foreach (['', '../x.jpg', 'a/../x.jpg', '.hidden/x.jpg', 'a//b.jpg', '/x.jpg', 'a\\b.jpg', 'javascript:x', 'a?b.jpg', 'a#b.jpg', 'a%2e.jpg', "a\x01b.jpg", "a\0b.jpg", "\xFF\xFE.jpg", str_repeat('a', 301)] as $boese) {
            $this->assertNull(image_safe_name($boese), json_encode($boese));
        }
    }

    public function testParseAlteWordPressUrl(): void
    {
        $p = image_parse('https://www.example.org/blog/wp-content/uploads/2014/11/bild.jpg');
        $this->assertSame('wp', $p['kind']);
        $this->assertSame('2014/11/bild.jpg', $p['name']);
        $p = image_parse('https://www.example.org//blog/wp-content/uploads/bild.jpg');
        $this->assertSame('bild.jpg', $p['name']);
    }

    public function testParseWordPressUrlMitUnsicheremPfad(): void
    {
        $this->assertNull(image_parse('https://h.example/blog/wp-content/uploads/../../x.jpg'));
    }

    public function testParseExterneUrl(): void
    {
        $p = image_parse('https://cdn.example.com/x.jpg');
        $this->assertSame('external', $p['kind']);
        $this->assertSame('https://cdn.example.com/x.jpg', $p['url']);
    }

    public function testParseRelativ(): void
    {
        $p = image_parse('2014/11/bild.jpg');
        $this->assertSame(['kind' => 'wp', 'name' => '2014/11/bild.jpg', 'url' => null], $p);
    }

    public function testParseUngueltig(): void
    {
        foreach ([null, '', '   ', "a\x01.jpg", '../x.jpg', 'javascript:alert(1)'] as $v) {
            $this->assertNull(image_parse($v), json_encode($v));
        }
    }

    public function testNormalizeForStorage(): void
    {
        $this->assertSame('2014/11/bild.jpg', image_normalize_for_storage('https://h.example/blog/wp-content/uploads/2014/11/bild.jpg'));
        $this->assertSame('bild.jpg', image_normalize_for_storage('  bild.jpg  '));
        $this->assertSame('https://cdn.example.com/x.jpg', image_normalize_for_storage('https://cdn.example.com/x.jpg'));
        $this->assertSame('../x.jpg', image_normalize_for_storage('../x.jpg'), 'Ungültiges bleibt (getrimmt) stehen, wird aber nie ausgegeben');
        $this->assertSame('', image_normalize_for_storage(null));
    }

    public function testUrlOriginal(): void
    {
        $this->assertSame('/blog/wp-content/uploads/x%20y/%C3%A4.jpg', image_url('x y/ä.jpg'));
        $this->assertSame('https://cdn.example.com/x.jpg', image_url('https://cdn.example.com/x.jpg'));
        $this->assertSame('', image_url(''));
        $this->assertSame('', image_url('../x.jpg'));
    }

    public function testUrlMiniatur(): void
    {
        $this->assertSame('/sammlung/thumb.php?f=x%20y/%C3%A4.jpg&w=320', image_url('x y/ä.jpg', 320));
    }

    public function testUrlMiniaturNurErlaubteBreitenUndTypen(): void
    {
        $this->assertSame('/blog/wp-content/uploads/a.jpg', image_url('a.jpg', 999), 'Breite nicht in der Liste');
        $this->assertSame('/blog/wp-content/uploads/a.pdf', image_url('a.pdf', 320), 'Typ ohne Miniatur');
        $this->assertSame('https://cdn.example.com/x.jpg', image_url('https://cdn.example.com/x.jpg', 320));
    }

    public function testIniBytes(): void
    {
        $this->assertSame(8 * 1048576, image_ini_bytes('8M'));
        $this->assertSame(512 * 1024, image_ini_bytes('512k'));
        $this->assertSame(1073741824, image_ini_bytes('1G'));
        $this->assertSame(1024, image_ini_bytes('1024'));
        $this->assertSame(0, image_ini_bytes('-1'));
        $this->assertSame(0, image_ini_bytes(''));
    }

    public function testStandardmodusIstWordPress(): void
    {
        $this->assertFalse(image_local_mode());
        $this->assertFalse(image_upload_available());
    }
}
