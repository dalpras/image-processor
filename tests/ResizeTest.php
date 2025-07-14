<?php

declare(strict_types=1);

namespace DalPraS\Tests\Image;

use DalPraS\Image\ImageProcessor;
use DalPraS\Image\Handlers\ImageResize;
use Imagick;
use ImagickPixel;
use PHPUnit\Framework\TestCase;

class ResizeTest extends TestCase
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

    public function testResize()
    {
        $svc = new ImageProcessor();
        $result = $svc->process($this->dir . '/src.png', [
            'outputDir' => $this->dir,
            'suffix' => 'small',
            'operations' => [ImageResize::resize(5, 5)],
        ]);
        $this->assertSame([5, 5], [$result->getWidth(), $result->getHeight()]);
    }

    public function testResizeByRatioOnWidth(): void
    {
        $svc = new ImageProcessor();
        $result = $svc->process($this->dir . '/src.png', [
            'outputDir' => $this->dir,
            'operations' => [ImageResize::resizeByRatio(10, true)],
        ]);
        $this->assertSame(10, $result->getWidth());
    }

    public function testStretch(): void
    {
        $svc = new ImageProcessor();
        $result = $svc->process($this->dir . '/src.png', [
            'outputDir' => $this->dir,
            'operations' => [ImageResize::stretch(30, 10)],
        ]);
        $this->assertSame([30, 10], [$result->getWidth(), $result->getHeight()]);
    }
}