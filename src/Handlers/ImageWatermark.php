<?php

declare(strict_types=1);

// src/Ops/OpResize.php
namespace DalPraS\Image\Handlers;

use DalPraS\Image\ImageHandle;
use Imagick;
use ImagickException;

class ImageWatermark
{
    /**
     * Overlay an external image on top of the current one.
     *
     * @param string      $path   Path to the watermark bitmap
     * @param string      $pos    One of:
     *                            center · top · bottom · left · right ·
     *                            top-left · top-right · bottom-left · bottom-right
     * @param float|null  $opacity 0‒1 to fade the watermark, or null = keep original
     */
    public static function watermark(string $path, string $pos = 'center', ?float $opacity = null): ImageHandle
    {
        $fn = static function (Imagick $img) use ($path, $pos, $opacity): void {
            /* ------------------------------------------------------------
            * 1.  Load the watermark once; bail early if missing
            * -----------------------------------------------------------*/
            try {
                $mark = new Imagick($path);
            } catch (ImagickException $e) {
                trigger_error("watermark: cannot read {$path} ({$e->getMessage()})", E_USER_NOTICE);
                return;
            }

            /* ------------------------------------------------------------
            * 2.  Down-scale watermark if it would overflow the base image
            *     scaleImage() is faster than resizeImage() for this job. :contentReference[oaicite:0]{index=0}
            * -----------------------------------------------------------*/
            ['width' => $iw, 'height' => $ih] = $img->getImageGeometry();
            ['width' => $mw, 'height' => $mh] = $mark->getImageGeometry();

            if ($mw > $iw || $mh > $ih) {
                $scale = min($iw / $mw, $ih / $mh);
                // bestfit=true keeps the aspect ratio
                $mark->scaleImage((int) round($mw * $scale), (int) round($mh * $scale), true);
                ['width' => $mw, 'height' => $mh] = $mark->getImageGeometry();
            }

            /* ------------------------------------------------------------
            * 3.  Optional global opacity
            * -----------------------------------------------------------*/
            if ($opacity !== null) {
                // 1.0 = fully opaque; 0.0 = invisible
                $mark->setImageAlphaChannel(Imagick::ALPHACHANNEL_SET);
                $mark->setImageOpacity(max(0.0, min(1.0, $opacity)));
            }

            /* ------------------------------------------------------------
            * 4.  Find placement coordinates
            * -----------------------------------------------------------*/
            $coords = match ($pos) {
                'top-left'      => [0, 0],
                'top-right'     => [$iw - $mw, 0],
                'bottom-left'   => [0, $ih - $mh],
                'bottom-right'  => [$iw - $mw, $ih - $mh],
                'top'           => [intdiv($iw - $mw, 2), 0],
                'bottom'        => [intdiv($iw - $mw, 2), $ih - $mh],
                'left'          => [0, intdiv($ih - $mh, 2)],
                'right'         => [$iw - $mw, intdiv($ih - $mh, 2)],
                default         => [intdiv($iw - $mw, 2), intdiv($ih - $mh, 2)], // centre
            };

            /* ------------------------------------------------------------
            * 5.  Composite over every frame (handles GIF/APNG too)
            * -----------------------------------------------------------*/
            foreach ($img as $frame) {
                $frame->compositeImage($mark, Imagick::COMPOSITE_OVER, $coords[0], $coords[1]);
            }

            /* ------------------------------------------------------------
            * 6.  Clean-up — destroy() is deprecated from imagick-3.7+. :contentReference[oaicite:3]{index=3}
            * -----------------------------------------------------------*/
            $mark->clear();
            unset($mark);
        };

        return new ImageHandle("watermark:{$path}:{$pos}", $fn);
    }
}