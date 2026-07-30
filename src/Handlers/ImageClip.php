<?php

declare(strict_types=1);

namespace DalPraS\Image\Handlers;

use DalPraS\Image\ImageHandle;
use Imagick;
use ImagickDraw;
use ImagickException;
use ImagickPixel;
use RuntimeException;

class ImageClip 
{

    /**
     * Apply the first Photoshop/8BIM clipping path as image transparency.
     *
     * clipPathImage() only installs a clipping mask for subsequent Imagick
     * operations. Copy the rasterized mask explicitly into the alpha channel.
     */
    public static function clipPath(bool $inside = false): ImageHandle
    {
        $fn = static function (Imagick $image) use ($inside): void {
            $image->setImageAlphaChannel(Imagick::ALPHACHANNEL_SET);
            $image->setImageBackgroundColor(new ImagickPixel('transparent'));

            try {
                if (!$image->clipPathImage('#1', $inside)) {
                    throw new ImagickException('clipPathImage failed');
                }

                $mask = $image->getImageClipMask();
                if (!$mask instanceof Imagick) {
                    throw new RuntimeException('ImageMagick did not expose the clipping-path mask.');
                }

                $mask->setImageColorspace(Imagick::COLORSPACE_GRAY);
                $mask->setImageAlphaChannel(Imagick::ALPHACHANNEL_OFF);
                $mask->setImagePage(0, 0, 0, 0);

                $image->compositeImage(
                    $mask,
                    Imagick::COMPOSITE_COPYOPACITY,
                    0,
                    0,
                    Imagick::CHANNEL_ALPHA
                );

                $image->setImageAlphaChannel(Imagick::ALPHACHANNEL_ACTIVATE);
                $image->setImageBackgroundColor(new ImagickPixel('transparent'));
                $image->setImageType(Imagick::IMGTYPE_TRUECOLORMATTE);
                $image->setImagePage(0, 0, 0, 0);

                $mask->clear();
                unset($mask);
            } catch (ImagickException | RuntimeException $exception) {
                trigger_error($exception->getMessage(), E_USER_NOTICE);
            }
        };

        // Versioned key invalidates files cached without a real alpha channel.
        return new ImageHandle("clip-path-v3:{$inside}", $fn);
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