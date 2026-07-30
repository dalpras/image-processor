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


    /**
     * Reproduce the canvas composition historically provided by Thumbnail.
     *
     * The source is resized proportionally, then composited on a canvas whose
     * dimensions are determined from the requested size and margins. Both
     * enlargement and shrinking are supported; Lanczos is used for enlargement
     * while thumbnailImage() is retained for efficient downscaling.
     *
     * @param array{width?:int,height?:int,ratio?:float} $size
     * @param array<int,int> $margin CSS-like [all] or [top,right,bottom,left]
     * @param array<int,int> $padding CSS-like [all] or [top,right,bottom,left]
     */
    public static function thumbnailCanvas(
        array $size,
        string $fit = 'inside',
        string $align = 'C',
        array $margin = [],
        array $padding = [],
        string $background = 'white'
    ): ImageHandle {
        $width = (int) ($size['width'] ?? 0);
        $height = (int) ($size['height'] ?? 0);
        $ratio = isset($size['ratio']) ? (float) $size['ratio'] : null;

        if ($width <= 0 && $height > 0 && $ratio !== null && $ratio > 0) {
            $width = (int) ($height * $ratio);
        } elseif ($height <= 0 && $width > 0 && $ratio !== null && $ratio > 0) {
            $height = (int) ($width / $ratio);
        }

        $margin = self::normaliseSpacing($margin);
        $padding = self::normaliseSpacing($padding);

        $fn = static function (Imagick $image) use ($width, $height, $fit, $align, $margin, $padding, $background): void {
            $sourceWidth = $image->getImageWidth();
            $sourceHeight = $image->getImageHeight();
            if ($sourceWidth <= 0 || $sourceHeight <= 0) {
                throw new RuntimeException('Source image has invalid dimensions.');
            }

            $sourceRatio = $sourceWidth / $sourceHeight;
            $canvasWidth = $width;
            $canvasHeight = $height;

            if ($canvasWidth <= 0 && $canvasHeight <= 0) {
                $canvasWidth = $sourceWidth;
                $canvasHeight = $sourceHeight;
            } elseif ($canvasWidth <= 0) {
                $canvasWidth = (int) ($canvasHeight * $sourceRatio);
            } elseif ($canvasHeight <= 0) {
                $canvasHeight = (int) ($canvasWidth / $sourceRatio);
            }

            $resizeWidth = $canvasWidth;
            $resizeHeight = $canvasHeight;
            $bestFit = false;

            switch ($fit) {
                case 'width':
                    $resizeHeight = (int) ($canvasWidth / $sourceRatio);
                    break;

                case 'height':
                    $resizeWidth = (int) ($canvasHeight * $sourceRatio);
                    break;

                case 'inside':
                case 'max':
                    $bestFit = true;
                    break;

                case 'crop':
                    $canvasRatio = $canvasWidth / $canvasHeight;
                    if ($canvasRatio > $sourceRatio) {
                        $resizeWidth = $canvasWidth;
                        $resizeHeight = (int) ($canvasWidth / $sourceRatio);
                    } else {
                        $resizeWidth = (int) ($canvasHeight * $sourceRatio);
                        $resizeHeight = $canvasHeight;
                    }
                    break;

                default:
                    throw new RuntimeException(sprintf('Unsupported thumbnail fit rule "%s".', $fit));
            }

            [$paddingTop, $paddingRight, $paddingBottom, $paddingLeft] = $padding;
            $resizeWidth -= $paddingLeft + $paddingRight;
            $resizeHeight -= $paddingTop + $paddingBottom;
            if ($resizeWidth <= 0 || $resizeHeight <= 0) {
                throw new RuntimeException('Padding produces invalid resize dimensions.');
            }

            if ($bestFit) {
                $scale = min($resizeWidth / $sourceWidth, $resizeHeight / $sourceHeight);
                $targetWidth = max(1, (int) round($sourceWidth * $scale));
                $targetHeight = max(1, (int) round($sourceHeight * $scale));
            } else {
                $targetWidth = max(1, $resizeWidth);
                $targetHeight = max(1, $resizeHeight);
            }

            $scale = min($targetWidth / $sourceWidth, $targetHeight / $sourceHeight);
            if ($scale < 1.0) {
                $image->thumbnailImage($targetWidth, $targetHeight, $bestFit);
            } elseif ($scale > 1.0) {
                if ($bestFit) {
                    $image->resizeImage($targetWidth, $targetHeight, Imagick::FILTER_LANCZOS, 1);
                } else {
                    // Legacy Thumbnail preserves the aspect ratio for width/height/crop.
                    $actualWidth = (int) round($sourceWidth * $scale);
                    $actualHeight = (int) round($sourceHeight * $scale);
                    $image->resizeImage($actualWidth, $actualHeight, Imagick::FILTER_LANCZOS, 1);
                }
            }

            [$marginTop, $marginRight, $marginBottom, $marginLeft] = $margin;
            $canvasWidth += $marginLeft + $marginRight;
            $canvasHeight += $marginTop + $marginBottom;
            if ($canvasWidth <= 0 || $canvasHeight <= 0) {
                throw new RuntimeException('Margins produce invalid canvas dimensions.');
            }

            [$offsetX, $offsetY] = self::legacyOffsets(
                $image->getImageWidth(),
                $image->getImageHeight(),
                $canvasWidth,
                $canvasHeight,
                $align
            );

            $marginOffsetX = (int) (($marginLeft - $marginRight) / 2);
            $marginOffsetY = (int) (($marginTop - $marginBottom) / 2);
            $paddingOffsetX = (int) (($paddingLeft - $paddingRight) / 2);
            $paddingOffsetY = (int) (($paddingTop - $paddingBottom) / 2);

            $transparentBackground = strcasecmp($background, 'transparent') === 0;
            if ($transparentBackground) {
                // Some ImageMagick builds retain RGB black below transparent pixels
                // unless alpha is explicitly activated before composition.
                $image->setImageAlphaChannel(Imagick::ALPHACHANNEL_ACTIVATE);
                $image->setImageBackgroundColor(new ImagickPixel('transparent'));
            }

            $canvas = new Imagick();
            $canvas->newImage($canvasWidth, $canvasHeight, new ImagickPixel($background));
            if ($transparentBackground) {
                $canvas->setImageAlphaChannel(Imagick::ALPHACHANNEL_ACTIVATE);
                $canvas->setImageBackgroundColor(new ImagickPixel('transparent'));
                $canvas->setImageType(Imagick::IMGTYPE_TRUECOLORMATTE);
            }
            $canvas->setImageFormat($image->getImageFormat());
            $canvas->compositeImage(
                $image,
                Imagick::COMPOSITE_OVER,
                $offsetX + $marginOffsetX + $paddingOffsetX,
                $offsetY + $marginOffsetY + $paddingOffsetY
            );

            $blob = $canvas->getImageBlob();
            if ($blob === '') {
                throw new RuntimeException('Failed to compose thumbnail canvas.');
            }
            $image->clear();
            $image->readImageBlob($blob);
            if ($transparentBackground) {
                $image->setImageAlphaChannel(Imagick::ALPHACHANNEL_ACTIVATE);
                $image->setImageBackgroundColor(new ImagickPixel('transparent'));
                $image->setImageType(Imagick::IMGTYPE_TRUECOLORMATTE);
            }
            $image->setImagePage(0, 0, 0, 0);
            $canvas->clear();
        };

        return new ImageHandle(sprintf(
            'thumbnail-canvas-v2:%dx%d:%s:%s:m%s:p%s:bg%s',
            $width,
            $height,
            $fit,
            $align,
            implode(',', $margin),
            implode(',', $padding),
            $background
        ), $fn);
    }

    /** @return array{0:int,1:int,2:int,3:int} */
    private static function normaliseSpacing(array $spacing): array
    {
        return match (count($spacing)) {
            0 => [0, 0, 0, 0],
            1 => [(int) $spacing[0], (int) $spacing[0], (int) $spacing[0], (int) $spacing[0]],
            4 => [(int) $spacing[0], (int) $spacing[1], (int) $spacing[2], (int) $spacing[3]],
            default => throw new RuntimeException('Spacing must contain one or four values.'),
        };
    }

    /** @return array{0:int,1:int} */
    private static function legacyOffsets(
        int $sourceWidth,
        int $sourceHeight,
        int $canvasWidth,
        int $canvasHeight,
        string $align
    ): array {
        $x = match ($align) {
            'NE', 'E', 'SE' => $canvasWidth - $sourceWidth,
            'NW', 'W', 'SW' => 0,
            default => (int) round(($canvasWidth - $sourceWidth) / 2),
        };
        $y = match ($align) {
            'N', 'NE', 'NW' => 0,
            'SE', 'S', 'SW' => $canvasHeight - $sourceHeight,
            default => (int) round(($canvasHeight - $sourceHeight) / 2),
        };

        return [$x, $y];
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