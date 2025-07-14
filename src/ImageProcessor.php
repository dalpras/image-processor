<?php

declare(strict_types=1);

namespace DalPraS\Image;

use Imagick;
use ImagickPixel;
use RuntimeException;
use Throwable;

final class ImageProcessor
{
    private const DEFAULT_COMPRESSION_QUALITY = 75;
    private const DEFAULT_IMAGE_FORMAT = 'webp';

    public function __construct(private ?string $tempDir = null)
    {
        if (!class_exists('Imagick')) {
            throw new RuntimeException('Imagick not available');
        }
        $this->tempDir = rtrim($this->tempDir ?? sys_get_temp_dir() . '/imagick', DIRECTORY_SEPARATOR);
        if (!is_dir($this->tempDir) && !@mkdir($this->tempDir, 0777, true) && !is_dir($this->tempDir)) {
            throw new RuntimeException('Cannot create temp dir');
        }
        putenv('MAGICK_TEMPORARY_PATH=' . $this->tempDir);
    }

    /**
     * @param array{force?:bool,outputDir?:string,outputExt?:string,operations?:array,fallback?:string|null} $options
     */
    public function process(string $imagePath, array $options = []): ImageInfo
    {
        $outputExt = strtolower($options['outputExt'] ?? self::DEFAULT_IMAGE_FORMAT);
        $outputDir = rtrim($options['outputDir'] ?? dirname($imagePath), DIRECTORY_SEPARATOR);
        $force     = (bool) ($options['force'] ?? false);
        $ops       = $options['operations'] ?? [];

        // -------- 0. Fallback and blank logic --------
        if (!is_file($imagePath) || !is_readable($imagePath)) {
            $fallback = $options['fallback'] ?? null;
            if ($fallback && is_file($fallback) && is_readable($fallback)) {
                $imagePath = $fallback;
            } else {
                $blankPath = $outputDir . DIRECTORY_SEPARATOR . 'blank.' . $outputExt;
                if (!is_file($blankPath) || !is_readable($blankPath)) {
                    $im = new Imagick();
                    $im->newImage(100, 100, new ImagickPixel('white'));
                    $im->setImageFormat($outputExt);
                    $im->writeImage($blankPath);
                    $im->clear();
                }
                $imagePath = $blankPath;
            }
        }

        if (!is_dir($outputDir) && !@mkdir($outputDir, 0777, true) && !is_dir($outputDir)) {
            throw new RuntimeException('Cannot create output dir: ' . $outputDir);
        }

        // ---------- 1. build a signature ----------
        $srcMTime = @filemtime($imagePath) ?: 0;
        $signatureData = [
            'srcMTime' => $srcMTime,
            'ops'      => self::normaliseOpsForHash($ops),
            'format'   => $outputExt,
            'quality'  => self::DEFAULT_COMPRESSION_QUALITY,
        ];
        $sig = substr(hash('sha256', json_encode($signatureData)), 0, 12);

        $basename = pathinfo($imagePath, PATHINFO_FILENAME) . "-{$sig}.{$outputExt}";
        $cachePath = $outputDir . DIRECTORY_SEPARATOR . $basename;

        // ---------- 2. fast-exit if perfect match exists ----------
        if (!$force && is_file($cachePath)) {
            [$w, $h] = @getimagesize($cachePath) ?: [0, 0];
            return new ImageInfo($cachePath, $w, $h);
        }

        // ---------- 3. create / overwrite ----------
        $im = new Imagick($imagePath);
        $im->setImageCompressionQuality(self::DEFAULT_COMPRESSION_QUALITY);

        foreach ((array) $ops as $op) {
            if (!($op instanceof ImageHandle)) {
                throw new RuntimeException('Operation not callable');
            }
            try {
                $op($im);
            } catch (Throwable $th) {
                error_log($th->getMessage());
            }
        }

        if (strtolower($im->getImageFormat()) !== $outputExt) {
            $im->setImageFormat($outputExt);
        }

        $im->stripImage();
        $im->writeImage($cachePath);
        $w = $im->getImageWidth();
        $h = $im->getImageHeight();
        $im->clear();
        unset($im);

        return new ImageInfo($cachePath, $w, $h);
    }

    private static function normaliseOpsForHash(array $ops): string
    {
        return json_encode(array_map(
            fn($op) => $op instanceof ImageHandle ? $op->key()
                      : (is_string($op) ? $op
                      : (is_array($op)  ? implode('::', $op)
                      : 'unknown')),
            $ops
        ));
    }
}
