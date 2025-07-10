<?php

declare(strict_types=1);

namespace DalPraS\Tests\Image;

use DalPraS\Image\ImageProcessor;
use DalPraS\Image\Handlers\ImageFit;
use Imagick;
use ImagickPixel;
use PHPUnit\Framework\TestCase;

class FitTest extends TestCase
{
    private string $tmp;

    protected function setUp(): void
    {
        $this->tmp = sys_get_temp_dir() . '/imgproc-' . uniqid();
        mkdir($this->tmp);
        $img = new Imagick();
        $img->newImage(10, 10, new ImagickPixel('white'));
        $img->setImageFormat('png');
        $img->writeImage($this->tmp . '/src.png');
        $img->clear();
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->tmp . '/*'));
        rmdir($this->tmp);
    }

    // public function testAddMarginDoesNotThrow(): void
    // {
    //     $svc = new ImageProcessor();
    //     $result = $svc->process($this->tmp . '/src.png', [
    //         'outputDir' => $this->tmp,
    //         'operations' => [OpFit::margin(2, 2, 2, 2)],
    //     ]);
    //     $this->assertFileExists($result->getPathname());
    //     $this->assertSame(14, $result->getWidth());
    //     $this->assertSame(14, $result->getHeight());
    // }

    public function testAddMarginDoesNotThrow(): void
    {
        $svc = new ImageProcessor();
        $tmp = sys_get_temp_dir() . '/imgproc-' . uniqid();
        mkdir($tmp);

        /* build a tiny white 10×10 source PNG */
        $src = $tmp . '/src.png';
        $im  = new Imagick();
        $im->newImage(10, 10, new ImagickPixel('white'));
        $im->setImageFormat('png');
        $im->writeImage($src);
        $im->clear();

        /* --- act -------------------------------------------------------------- */
        $result = $svc->process($src, [
            'outputDir'  => $tmp,
            'operations' => [ImageFit::margin(2, 2, 2, 2)],
        ]);

        /* --- assert ----------------------------------------------------------- */
        $this->assertFileExists($result->getPathname());          // path WITH signature
        $this->assertSame(14, $result->getWidth());                // 10 + 2 + 2
        $this->assertSame(14, $result->getHeight());

        /* --- cleanup ---------------------------------------------------------- */
        array_map('unlink', glob("$tmp/*"));
        rmdir($tmp);
    }

}