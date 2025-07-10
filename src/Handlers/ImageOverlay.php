<?php

declare(strict_types=1);

// src/Ops/OpResize.php
namespace DalPraS\Image\Handlers;

use DalPraS\Image\ImageHandle;
use Imagick;
use ImagickDraw;
use ImagickException;
use ImagickPixel;

class ImageOverlay 
{
    /**
     * Write a string onto the image at absolute pixel coordinates.
     *
     * @param string $text  The text to draw (UTF-8).
     * @param int    $x     X-offset in pixels (baseline or gravity anchor).
     * @param int    $y     Y-offset in pixels (baseline or gravity anchor).
     * @param string $font  Path to a TTF/OTF file; falls back to ImageMagick’s default if unreadable.
     * @param int    $size  Point size.
     * @param string $color CSS/Imagemagick colour (e.g. “#ffffff80” for 50 % white).
     * @param float|null $opacity 0‒1 to fade text; null = keep colour’s alpha.
     * @param int   $angle Rotation in degrees (CCW).
     * @param int   $align One of Imagick::ALIGN_LEFT | ALIGN_CENTER | ALIGN_RIGHT.
     */
    public static function overlay(
        string  $text,
        int     $x,
        int     $y,
        string  $font   = '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
        int     $size   = 20,
        string  $color  = 'black',
        ?float  $opacity = null,
        int     $angle  = 0,
        int     $align  = Imagick::ALIGN_LEFT
    ): ImageHandle {
        $fn = static function (Imagick $img) use (
            $text, $x, $y, $font, $size, $color, $opacity, $angle, $align
        ): void {
            $draw = new ImagickDraw();

            /* ------------------------------------------------------------
            * 1.  Font setup with fallback
            * -----------------------------------------------------------*/
            try {
                $draw->setFont($font);
            } catch (ImagickException $e) {
                trigger_error("overlay: cannot load font {$font} ({$e->getMessage()})", E_USER_NOTICE);
            }
            $draw->setFontSize($size);

            /* ------------------------------------------------------------
            * 2.  Colour and global opacity
            *    (opacity in the colour string still wins if both given)
            * -----------------------------------------------------------*/
            $px = new ImagickPixel($color);
            if ($opacity !== null) {
                $px->setColorValue(Imagick::COLOR_ALPHA, max(0.0, min(1.0, $opacity)));
            }
            $draw->setFillColor($px);

            /* ------------------------------------------------------------
            * 3.  Alignment + anti-alias
            * -----------------------------------------------------------*/
            $draw->setTextAlignment($align);
            $draw->setTextAntialias(true);

            /* ------------------------------------------------------------
            * 4.  Write on every frame so animations keep the overlay
            * -----------------------------------------------------------*/
            foreach ($img as $frame) {
                $frame->annotateImage($draw, $x, $y, $angle, $text);     // baseline at (x,y) :contentReference[oaicite:0]{index=0}
            }

            /* ------------------------------------------------------------
            * 5.  Clean-up –
            *     ImagickDraw has no destroy(); clear() frees the commands.
            * -----------------------------------------------------------*/
            $draw->clear();                                              // resets draw object :contentReference[oaicite:1]{index=1}
            unset($draw);
        };

        return new ImageHandle(
            sprintf(
                'overlay:%s-%dx%dx%s:%s:%s:%s:%d',
                rawurlencode($text), $x, $y, $size, $color,
                $opacity ?? 'α', $angle, $align
            ),
            $fn
        );
    }
    
}