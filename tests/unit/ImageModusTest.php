<?php
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

/** Bildquelle local/auto: IMAGE_SOURCE steht nach dem Laden fest, deshalb je Test ein eigener Prozess. */
final class ImageModusTest extends TestCase
{
    #[RunInSeparateProcess]
    public function testModusLocal(): void
    {
        define('IMAGE_SOURCE', 'local');
        require_once UNIT_SRC . '/inc_image.php';
        $this->assertTrue(image_local_mode());
        $this->assertSame('local', image_parse('x.jpg')['kind']);
        $this->assertSame('/sammlung/medien/x%20y.jpg', image_url('x y.jpg'));
    }

    #[RunInSeparateProcess]
    public function testModusAuto(): void
    {
        define('IMAGE_SOURCE', 'auto');
        require_once UNIT_SRC . '/inc_image.php';
        $this->assertTrue(image_local_mode());
        $this->assertSame('local', image_parse('vorhanden.jpg')['kind'], 'Datei liegt in medien/');
        $this->assertSame('wp', image_parse('fehlt.jpg')['kind'], 'sonst WordPress');
    }
}
