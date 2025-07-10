<?php

declare(strict_types=1);

// src/Ops/OpResize.php
namespace DalPraS\Image\Handlers;

use DalPraS\Image\ImageHandle;
use Imagick;
use RuntimeException;

class ImageResize 
{
    public static function resize(int $w, int $h, bool $keep = true): ImageHandle
    {
        $fn = fn(Imagick $i) => $keep
            ? $i->thumbnailImage($w, $h, true)
            : $i->resizeImage($w, $h, \Imagick::FILTER_LANCZOS, 1);

        return new ImageHandle("resize:{$w}x{$h}:{$keep}", $fn);
    }

    /**
     * Resize proportionally so that either width **or** height equals $dim.
     *
     * @param int  $dim      Target size in pixels (must be > 0).
     * @param bool $isWidth  true ⇒ match width, false ⇒ match height.
     *
     * @return Op  Callable wrapper for ImageProcessor::process()
     */
    public static function resizeByRatio(int $dim, bool $isWidth = true): ImageHandle
    {
        $fn = static function (Imagick $img) use ($dim, $isWidth): void {
            if ($dim <= 0) {
                throw new RuntimeException('Dimension must be > 0');
            }

            /* ------------------------------------------------------------
            * 1.  Calculate scale factor from the first frame
            * -----------------------------------------------------------*/
            $ratio = $dim / (
                $isWidth ? $img->getImageWidth()
                        : $img->getImageHeight()
            );

            if ($ratio === 1.0) {
                return;                           // already the right size
            }

            /* ------------------------------------------------------------
            * 2.  Helper that rescales a single frame
            * -----------------------------------------------------------*/
            $resizeFrame = static function (Imagick $frame) use ($ratio): void {
                $newW = (int) round($frame->getImageWidth()  * $ratio);
                $newH = (int) round($frame->getImageHeight() * $ratio);

                // thumbnailImage is faster for down-scaling; Lanczos for up-scaling
                if ($ratio < 1.0) {
                    $frame->thumbnailImage($newW, $newH, true);          // best-fit shrink
                } else {
                    $frame->resizeImage($newW, $newH, Imagick::FILTER_LANCZOS, 1);
                }
            };

            /* ------------------------------------------------------------
            * 3.  Apply to every frame (animated GIF/APNG safe)
            * -----------------------------------------------------------*/
            foreach ($img as $frame) {
                $resizeFrame($frame);
            }
        };

        return new ImageHandle("resize-by-ratio:{$dim}-{$isWidth}", $fn);
    }

    public static function stretch(int $newW, int $newH): ImageHandle
    {
        $fn = fn(Imagick $i) => $i->scaleImage($newW, $newH, false);
        return new ImageHandle("stretch:{$newW}-{$newH}", $fn);
    }

    public static function stretchByPercent(float $wp, float $hp): ImageHandle
    {
        $fn = function (Imagick $i) use ($wp, $hp) {
            $i->scaleImage((int)($i->getImageWidth() * $wp / 100), (int)($i->getImageHeight() * $hp / 100), false);
        };
        return new ImageHandle("resize-by-percent:{$wp}-{$hp}", $fn);
    }
}
