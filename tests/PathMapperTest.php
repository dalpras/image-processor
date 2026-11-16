<?php

declare(strict_types=1);

namespace DalPraS\Tests\Image;

use DalPraS\Image\ImageProcessor;
use Imagick;
use ImagickPixel;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class PathMapperTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/imgproc-mapper-' . bin2hex(random_bytes(5));
        mkdir($this->directory, 0770, true);
        $image = new Imagick();
        $image->newImage(5, 5, new ImagickPixel('white'));
        $image->setImageFormat('png');
        $image->writeImage($this->directory . '/image.png');
        $image->writeImage($this->directory . '/fallback.png');
        $image->clear();
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/thumbs/*') ?: [] as $file) {
            unlink($file);
        }
        if (is_dir($this->directory . '/thumbs')) {
            rmdir($this->directory . '/thumbs');
        }
        unlink($this->directory . '/image.png');
        unlink($this->directory . '/fallback.png');
        rmdir($this->directory);
    }

    public function testMapsFilesystemInputsAndGeneratedUrl(): void
    {
        $mapperCalls = [];
        $processor = new ImageProcessor($this->directory);
        $processor->setPathMapper(function (string $path, string $context) use (&$mapperCalls): string {
            $mapperCalls[] = $context;
            return match ($context) {
                ImageProcessor::PATH_SOURCE => $this->directory . '/image.png',
                ImageProcessor::PATH_OUTPUT_DIR => $this->directory . '/thumbs',
                ImageProcessor::PATH_PUBLIC_URL => '/cache/thumbs/' . basename($path),
                default => $path,
            };
        });

        $info = $processor->process('photo', [
            'outputDir' => 'thumbs',
            'outputExt' => 'png',
        ]);

        self::assertFileExists($info->getPathname());
        self::assertStringStartsWith($this->directory . '/thumbs/', $info->getPathname());
        self::assertSame('/cache/thumbs/' . basename($info->getPathname()), $info->getPublicUrl());
        self::assertSame([
            ImageProcessor::PATH_SOURCE,
            ImageProcessor::PATH_OUTPUT_DIR,
            ImageProcessor::PATH_PUBLIC_URL,
        ], $mapperCalls);
    }

    public function testFallbackIsMappedOnlyWhenNeeded(): void
    {
        $mapperCalls = [];
        $processor = new ImageProcessor($this->directory);
        $processor->setPathMapper(function (string $path, string $context) use (&$mapperCalls): string {
            $mapperCalls[] = $context;
            return match ($context) {
                ImageProcessor::PATH_SOURCE => $this->directory . '/missing.png',
                ImageProcessor::PATH_OUTPUT_DIR => $this->directory . '/thumbs',
                ImageProcessor::PATH_FALLBACK => $this->directory . '/fallback.png',
                ImageProcessor::PATH_PUBLIC_URL => '',
            };
        });

        $info = $processor->process('missing', [
            'outputDir' => 'thumbs',
            'fallback' => 'fallback',
            'outputExt' => 'png',
        ]);

        self::assertFileExists($info->getPathname());
        self::assertNull($info->getPublicUrl());
        self::assertContains(ImageProcessor::PATH_FALLBACK, $mapperCalls);
    }

    public function testMissingSourceAndFallbackCreateOutputDirectoryBeforeBlankImage(): void
    {
        $processor = new ImageProcessor($this->directory);
        $processor->setPathMapper(fn(string $path, string $context): string => match ($context) {
            ImageProcessor::PATH_SOURCE => $this->directory . '/missing-source.png',
            ImageProcessor::PATH_OUTPUT_DIR => $this->directory . '/thumbs',
            ImageProcessor::PATH_FALLBACK => $this->directory . '/missing-fallback.png',
            ImageProcessor::PATH_PUBLIC_URL => '/cache/thumbs/' . basename($path),
        });

        $info = $processor->process('missing', [
            'outputDir' => 'thumbs',
            'fallback' => 'missing-fallback',
            'outputExt' => 'png',
        ]);

        self::assertFileExists($this->directory . '/thumbs/blank.png');
        self::assertFileExists($info->getPathname());
        self::assertSame('/cache/thumbs/' . basename($info->getPathname()), $info->getPublicUrl());
    }

    public function testInvalidFilesystemMappingFailsBeforeWriting(): void
    {
        $processor = new ImageProcessor($this->directory);
        $processor->setPathMapper(static fn(string $path, string $context): string => '');

        $this->expectException(InvalidArgumentException::class);
        $processor->process($this->directory . '/image.png');
    }

    public function testNoMapperPreservesLegacyBehaviour(): void
    {
        $processor = new ImageProcessor($this->directory);
        $info = $processor->process($this->directory . '/image.png', [
            'outputDir' => $this->directory . '/thumbs',
            'outputExt' => 'png',
        ]);

        self::assertFileExists($info->getPathname());
        self::assertNull($info->getPublicUrl());
    }
}
