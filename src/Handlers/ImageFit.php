<?php

declare(strict_types=1);

// src/Ops/OpResize.php
namespace DalPraS\Image\Handlers;

use DalPraS\Image\ImageHandle;
use Imagick;
use ImagickPixel;
use RuntimeException;

class ImageFit 
{
    /**
     * Resize and position the image to fit or fill the target dimensions.
     *
     * @param int    $tw         Target width in pixels.
     * @param int    $th         Target height in pixels.
     * @param string $mode       One of 'fit', 'fitWidth', 'fitHeight', or 'fill'. Determines how to scale the image:
     *                           - 'fit':        Scale to fit inside target box, preserving aspect ratio
     *                           - 'fitWidth':   Scale so width matches $tw
     *                           - 'fitHeight':  Scale so height matches $th
     *                           - 'fill':       Scale to completely fill target box, then crop excess
     * @param string $pos        Position anchor for alignment inside target canvas.
     * @param string $background Background color for padding space (default: 'white').
     */
    public static function fitToTarget(int $tw, int $th, string $mode = 'fit', string $pos = 'center', string $background = 'white'): ImageHandle
    {
        $fn = function (Imagick $i) use ($tw, $th, $mode, $pos, $background) {
            ['width' => $ow, 'height' => $oh] = $i->getImageGeometry();
            if (!$tw && !$th) {
                $tw = $ow;
                $th = $oh;
            }
            if (!$tw) {
                $tw = (int)($ow * ($th / $oh));
            }
            if (!$th) {
                $th = (int)($oh * ($tw / $ow));
            }
            if ($tw <= 0 || $th <= 0) {
                throw new RuntimeException('Target dims must be positive.');
            }
            $sw = $tw / $ow;
            $sh = $th / $oh;
            $scale = match ($mode) {
                'fit'       => min($sw, $sh),
                'fitWidth'  => $sw,
                'fitHeight' => $sh,
                'fill'      => max($sw, $sh),
                default     => throw new RuntimeException('Bad mode')
            };
            $nw = (int)($ow * $scale);
            $nh = (int)($oh * $scale);
            $i->resizeImage($nw, $nh, Imagick::FILTER_LANCZOS, 1);
            if ($mode === 'fill') {
                $i->cropImage($tw, $th, (int)(($nw - $tw) / 2), (int)(($nh - $th) / 2));
            } else {
                [$x, $y] = self::calcOffsets($tw, $th, $nw, $nh, $pos);
                $bg = new Imagick();
                $bg->newImage($tw, $th, new ImagickPixel($background));
                $bg->setImageFormat($i->getImageFormat());
                $bg->compositeImage($i, Imagick::COMPOSITE_OVER, $x, $y);

                $blob = $bg->getImageBlob();
                if (!$blob) {
                    throw new RuntimeException('Failed to generate image blob from background.');
                }
                $i->clear();
                $i->readImageBlob($blob);
                $bg->clear();
            }
        };
        return new ImageHandle("$mode:{$tw}x{$th}:{$pos}", $fn);
    }

    private static function calcOffsets(int $tw, int $th, int $nw, int $nh, string $pos): array
    {
        return match ($pos) {
            'top-left'      => [0, 0],
            'top-center'    => [(int)(($tw - $nw) / 2), 0],
            'top-right'     => [$tw - $nw, 0],
            'center-left'   => [0, (int)(($th - $nh) / 2)],
            'center'        => [(int)(($tw - $nw) / 2), (int)(($th - $nh) / 2)],
            'center-right'  => [$tw - $nw, (int)(($th - $nh) / 2)],
            'bottom-left'   => [0, $th - $nh],
            'bottom-center' => [(int)(($tw - $nw) / 2), $th - $nh],
            'bottom-right'  => [$tw - $nw, $th - $nh],
            default => throw new RuntimeException('Bad position')
        };
    }

    public static function margin(int $t, int $r, int $b, int $l, string $background = 'transparent'): ImageHandle
    {
        $fn = function (Imagick $i) use ($t, $r, $b, $l, $background) {
            // Nothing to do?
            if (!($t | $r | $b | $l)) {
                return;
            }

            // Convert colour once
            $px = new ImagickPixel($background);

            /* ------------------------------------------------------------------
             * 1.  All four borders identical →   borderImage()  (one-shot)
             * -----------------------------------------------------------------*/
            if ($t === $r && $r === $b && $b === $l) {
                // For borderImage the two numbers are *thickness* on X and Y
                $i->borderImage($px, $l, $t);            // extends equally on every side
                return;                                  // done ✔
            }

            /* ------------------------------------------------------------------
             * 2.  Any border differs →         extentImage()  (in-place)
             * -----------------------------------------------------------------*/
            $i->setImageBackgroundColor($px);            // sets colour for new pixels
            $i->extentImage(
                $i->getImageWidth()  + $l + $r,          // new canvas width
                $i->getImageHeight() + $t + $b,          // new canvas height
                -$l,                                     // shift right by left-border
                -$t                                      // shift down  by top-border
            );
            $i->setImagePage(0, 0, 0, 0);                // normalise page offsets
        };
        return new ImageHandle("margin:{$t}-{$r}-{$b}-{$l}:{$background}", $fn);
    }

    public static function padding(int $t, int $r, int $b, int $l, string $background = 'transparent'): ImageHandle
    {
        $fn = function (Imagick $i) use ($t, $r, $b, $l, $background) {
            $i->resizeImage(
                $i->getImageWidth()  - $l - $r,
                $i->getImageHeight() - $t - $b,
                Imagick::FILTER_LANCZOS,
                1
            );

            // set the background colour that will fill the new area
            $i->setImageBackgroundColor(new ImagickPixel($background));

            // extentImage makes the canvas bigger and positions the existing pixels
            // Negative offsets shift the current content down/right to form the border
            $i->extentImage(
                $i->getImageWidth()  + $l + $r,
                $i->getImageHeight() + $t + $b,
                -$l,   // x-offset: move right by left-border width
                -$t    // y-offset: move down by top-border height
            );
        };
        return new ImageHandle("padding:{$t}-{$r}-{$b}-{$l}:{$background}", $fn);
    }
}