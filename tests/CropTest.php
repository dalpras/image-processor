<?php

declare(strict_types=1);

namespace DalPraS\Tests\Image;

use DalPraS\Image\ImageProcessor;
use DalPraS\Image\Handlers\ImageCrop;
use Imagick;
use ImagickPixel;
use PHPUnit\Framework\TestCase;

class CropTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/imgproc-' . uniqid();
        mkdir($this->dir);
        $img = new Imagick();
        $img->newImage(10, 10, new ImagickPixel('white'));
        $img->setImageFormat('png');
        $img->writeImage($this->dir . '/src.png');
        $img->clear();
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir . '/*'));
        rmdir($this->dir);
    }

    public function testCrop(): void
    {
        $svc = new ImageProcessor();
        $result = $svc->process($this->dir . '/src.png', [
            'outputDir' => $this->dir,
            'operations' => [ImageCrop::crop(5, 5, 0, 0)],
        ]);
        $this->assertSame([5, 5], [$result->getWidth(), $result->getHeight()]);
    }
}