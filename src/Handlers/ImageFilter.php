<?php

declare(strict_types=1);

// src/Ops/OpResize.php
namespace DalPraS\Image\Handlers;

use DalPraS\Image\ImageHandle;
use Imagick;
use RuntimeException;

class ImageFilter 
{
    public static function filter(string $type, int|float ...$args): ImageHandle
    {
        $fn = static function (Imagick $img) use ($type, $args): void {

            /* helper: run a closure on every frame (1-liner avoids code repetition) */
            $eachFrame = static function (callable $cb) use ($img): void {
                foreach ($img as $frame) { $cb($frame); }
            };

            switch ($type) {
                /* -----------------------------------------------------------
                 * GRAYSCALE
                 * ----------------------------------------------------------*/
                case 'grayscale':
                    $eachFrame(fn (Imagick $f) =>
                        $f->setImageType(Imagick::IMGTYPE_GRAYSCALE)
                    );
                    break;

                /* -----------------------------------------------------------
                 * SEPIA  — Imagick::sepiaToneImage expects a threshold (0-100)
                 * ----------------------------------------------------------*/
                case 'sepia':
                    $threshold = (float) ($args[0] ?? 80.0);
                    $eachFrame(fn (Imagick $f) =>
                        $f->sepiaToneImage(max(0.0, min(100.0, $threshold)))
                    );
                    break;

                /* -----------------------------------------------------------
                 * BLUR   — radius & sigma are floats; sigma≈½radius looks natural
                 * ----------------------------------------------------------*/
                case 'blur':
                    $radius = max(0.0, (float) ($args[0] ?? 5.0));
                    $sigma  = max(0.0, (float) ($args[1] ?? $radius / 2));
                    $eachFrame(fn (Imagick $f) =>
                        $f->blurImage($radius, $sigma)                 // ﻿radius, sigma :contentReference[oaicite:0]{index=0}
                    );
                    break;

                /* -----------------------------------------------------------
                 * BRIGHTNESS / CONTRAST  — ImageMagick expects −100 … +100 ﻿:contentReference[oaicite:1]{index=1}
                 * ----------------------------------------------------------*/
                case 'brightness-contrast':
                    $bright = max(-100.0, min(100.0, (float) ($args[0] ?? 0.0)));
                    $contr  = max(-100.0, min(100.0, (float) ($args[1] ?? 0.0)));
                    $eachFrame(fn (Imagick $f) =>
                        $f->brightnessContrastImage($bright, $contr)
                    );
                    break;

                /* -----------------------------------------------------------
                 * SOLARIZE — take *percent* (0-100) and convert to QuantumRange
                 * ----------------------------------------------------------*/
                case 'solarize':
                    $percent   = max(0.0, min(100.0, (float) ($args[0] ?? 30.0)));
                    $threshold = (int) round((Imagick::getQuantum() * $percent) / 100);  // ﻿:contentReference[oaicite:2]{index=2}
                    $eachFrame(fn (Imagick $f) =>
                        $f->solarizeImage($threshold)
                    );
                    break;

                /* -----------------------------------------------------------
                 * INTERLACE — once per image is enough
                 * ----------------------------------------------------------*/
                case 'interlace':
                    $scheme   = $args[0] ?? Imagick::INTERLACE_PLANE;
                    $allowed  = [
                        Imagick::INTERLACE_GIF,
                        Imagick::INTERLACE_JPEG,
                        Imagick::INTERLACE_PLANE,
                        Imagick::INTERLACE_PNG,
                    ];
                    if (in_array($scheme, $allowed, true)) {
                        $img->setInterlaceScheme($scheme);
                    }
                    break;

                default:
                    throw new RuntimeException("Unsupported filter: {$type}");
            }
        };
        return new ImageHandle("filter:{$type}", $fn);
    }
}