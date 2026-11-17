<?php

declare(strict_types=1);

namespace DalPraS\Image;

use Closure;
use Imagick;
use ImagickPixel;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class ImageProcessor
{
    /** @var array<string,ImageInfo> */
    private array $resolved = [];

    private const DEFAULT_COMPRESSION_QUALITY = 75;
    private const DEFAULT_IMAGE_FORMAT = 'webp';

    public const PATH_SOURCE = 'source';
    public const PATH_OUTPUT_DIR = 'outputDir';
    public const PATH_FALLBACK = 'fallback';
    public const PATH_PUBLIC_URL = 'publicUrl';

    /** @var (Closure(string, string): string)|null */
    private ?Closure $pathMapper = null;

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
     * Register a mapping callback without depending on any application-specific
     * path service. It receives a value and one of the PATH_* contexts above.
     *
     * The first three contexts must return a filesystem path; publicUrl must
     * return a public URL/path or an empty string if the result is not public.
     * Without a mapper all existing paths retain their original meaning.
     *
     * @param callable(string, string): string $mapper
     */
    public function setPathMapper(callable $mapper): void
    {
        $this->pathMapper = Closure::fromCallable($mapper);
        $this->resolved = [];
    }

    /**
     * @param array{force?:bool,outputDir?:string,outputExt?:string,operations?:array,fallback?:string|null} $options
     */
    public function process(string $imagePath, array $options = []): ImageInfo
    {
        $imagePath = $this->mapFilesystemPath($imagePath, self::PATH_SOURCE);
        $outputExt = strtolower($options['outputExt'] ?? self::DEFAULT_IMAGE_FORMAT);
        $outputDir = rtrim(
            $this->mapFilesystemPath($options['outputDir'] ?? dirname($imagePath), self::PATH_OUTPUT_DIR),
            DIRECTORY_SEPARATOR
        );
        $force     = (bool) ($options['force'] ?? false);
        $ops       = $options['operations'] ?? [];

        // The output directory is also needed by the generated blank fallback.
        if (!is_dir($outputDir) && !@mkdir($outputDir, 0777, true) && !is_dir($outputDir)) {
            throw new RuntimeException('Cannot create output dir: ' . $outputDir);
        }

        // -------- 0. Fallback and blank logic --------
        if (!is_file($imagePath) || !is_readable($imagePath)) {
            $fallback = $options['fallback'] ?? null;
            if ($fallback !== null && $fallback !== '') {
                $fallback = $this->mapFilesystemPath($fallback, self::PATH_FALLBACK);
            }
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
        // The Colors XML references the same drawings for many products. Keep
        // resolved cache metadata in memory so repeated calls avoid filesystem
        // stat/getimagesize work during the same request or worker lifecycle.
        if (!$force && isset($this->resolved[$cachePath])) {
            return $this->resolved[$cachePath];
        }

        if (!$force && is_file($cachePath)) {
            [$w, $h] = @getimagesize($cachePath) ?: [0, 0];
            return $this->resolved[$cachePath] = $this->imageInfo($cachePath, $w, $h);
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

        // Preserve transparency when converting/compressing formats that support
        // alpha. Without explicit activation, some ImageMagick/WebP builds flatten
        // transparent canvas pixels onto their underlying black RGB values.
        if (in_array($outputExt, ['png', 'webp', 'gif'], true) && $im->getImageAlphaChannel()) {
            $im->setImageAlphaChannel(Imagick::ALPHACHANNEL_ACTIVATE);
            $im->setImageBackgroundColor(new ImagickPixel('transparent'));
        }

        $im->stripImage();
        $im->writeImage($cachePath);
        $w = $im->getImageWidth();
        $h = $im->getImageHeight();
        $im->clear();
        unset($im);

        return $this->resolved[$cachePath] = $this->imageInfo($cachePath, $w, $h);
    }

    private function mapFilesystemPath(string $value, string $context): string
    {
        if ($this->pathMapper === null || $value === '') {
            return $value;
        }

        $mapped = ($this->pathMapper)($value, $context);
        if (!is_string($mapped) || $mapped === '') {
            throw new InvalidArgumentException(sprintf('Cannot map image %s: %s', $context, $value));
        }
        return $mapped;
    }

    private function imageInfo(string $path, int $width, int $height): ImageInfo
    {
        $url = null;
        if ($this->pathMapper !== null) {
            $mapped = ($this->pathMapper)($path, self::PATH_PUBLIC_URL);
            if (!is_string($mapped)) {
                throw new InvalidArgumentException('Mapped image public URL must be a string');
            }
            $url = $mapped !== '' ? $mapped : null;
        }
        return new ImageInfo($path, $width, $height, $url);
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
