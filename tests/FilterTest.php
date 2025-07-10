<?php

declare(strict_types=1);

namespace DalPraS\Tests\Image;

use DalPraS\Image\ImageProcessor;
use DalPraS\Image\Handlers\ImageFilter;
use Imagick;
use ImagickPixel;
use PHPUnit\Framework\TestCase;

class FilterTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/imgproc-' . uniqid();
        mkdir($this->dir);
        $img = new Imagick();
        $img->newImage(20, 20, new ImagickPixel('white'));
        $img->setImageFormat('png');
        $img->writeImage($this->dir . '/src.png');
        $img->clear();
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir . '/*'));
        rmdir($this->dir);
    }

    public function testGrayscaleFilter(): void
    {
        $svc = new ImageProcessor();
        $out = $svc->process($this->dir . '/src.png', [
            'outputDir'  => $this->dir,
            'operations' => [ImageFilter::filter('grayscale')],
        ]);
        $im = new Imagick($out->getPathname());
        $color = $im->getImagePixelColor(10, 10)->getColor();
        $im->clear();
        // after grayscale R, G and B should be identical
        $this->assertSame($color['r'], $color['g']);
        $this->assertSame($color['g'], $color['b']);
    }


}