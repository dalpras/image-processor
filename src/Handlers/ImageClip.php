<?php

declare(strict_types=1);

namespace DalPraS\Image\Handlers;

use DalPraS\Image\ImageHandle;
use Imagick;
use ImagickDraw;
use ImagickException;
use ImagickPixel;

class ImageClip 
{

    /**
     * ================================================================
     *  High-level helper — use 8BIM clipping path #1
     * ================================================================
     */
    public static function clipPath(bool $inside = false): ImageHandle
    {
        $fn = static function (Imagick $img) use ($inside): void {
            /* 1) Activate the PSD/8BIM clipping path (#1) */
            try {
                if (!$img->clipPathImage('#1', $inside)) {    // keeps later ops inside or outside the path
                    throw new ImagickException('clipPathImage failed');
                }
            } catch (ImagickException $e) {
                trigger_error($e->getMessage(), E_USER_NOTICE);
            }

            /* 2) Build a draw-object that “paints nothing” except inside the clip area
              We use PAINT_RESET so that the region reset to the *background*
              colour (white), leaving everything else black. */
            $drawFactory = static function (): ImagickDraw {
                $d = new ImagickDraw();
                $d->setFillColor(new ImagickPixel('black'));                   // irrelevant for PAINT_RESET but required
                $d->color(0, 0, Imagick::PAINT_RESET);
                return $d;
            };

            /* 3) Copy the mask’s alpha back.
              invert = true  →  transparentPaintImage() makes anything *not* white transparent,
                                which means:
                                • $inside = false  → keep INSIDE the path, drop outside
                                • $inside = true   → drop inside the path, keep outside          */
            self::paintTransparent($img, $drawFactory, true);
        };

        return new ImageHandle("clip-path:{$inside}", $fn);
    }

    /**
     * ================================================================
     * HIGH-LEVEL  — cut or keep a rectangular hole
     * ================================================================
     */
    public static function drill(
        int  $x,
        int  $y,
        int  $w,
        int  $h,
        bool $invert = false
    ): ImageHandle {
        $fn = static function (Imagick $img) use ($x, $y, $w, $h, $invert): void {
            // Factory keeps paintTransparent’s signature simple
            $drawFactory = static function (): ImagickDraw {
                $d = new ImagickDraw();
                $d->setFillColor(new ImagickPixel('white'));
                $d->setStrokeOpacity(0); // no stroke; avoids edge artefacts
                return $d;
            };

            // Create the rectangle shape ONCE, then feed it to the factory
            $draw = $drawFactory();
            $draw->rectangle($x, $y, $x + $w, $y + $h);

            // Punch-out transparency or mask-in, as requested
            self::paintTransparent($img, fn() => $draw, $invert);
        };

        return new ImageHandle("drill:{$w}x{$h}-{$x}-{$y}:{$invert}", $fn);
    }

    /**
     * ================================================================
     *  Low-level alpha-mask helper  (unchanged except for a comment)
     * ================================================================
     */
    private static function paintTransparent(
        Imagick  $img,
        callable $drawFactory,
        bool     $invert
    ): void {
        ['width' => $w, 'height' => $h] = $img->getImageGeometry();

        $mask = new Imagick();
        $mask->newPseudoImage($w, $h, 'canvas:black');       // opaque black base
        $mask->setImageBackgroundColor('white');             // used by PAINT_RESET
        $mask->setImageAlphaChannel(Imagick::ALPHACHANNEL_SET);

        /** @var ImagickDraw $draw */
        $draw = $drawFactory();                              // user-supplied drawing
        $mask->drawImage($draw);

        // Make (non-)white fully transparent, depending on $invert
        $mask->transparentPaintImage(new ImagickPixel('white'), 0.0, 0.0, $invert);

        // Copy mask’s alpha channel back
        $img->compositeImage($mask, Imagick::COMPOSITE_COPYOPACITY, 0, 0);

        $mask->clear();
        unset($mask);
    }
    
}