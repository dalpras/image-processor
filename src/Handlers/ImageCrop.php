<?php

declare(strict_types=1);

// src/Ops/OpResize.php
namespace DalPraS\Image\Handlers;

use DalPraS\Image\ImageHandle;
use Imagick;
use ImagickPixel;

class ImageCrop 
{
    public static function crop(int $w, int $h, int $x, int $y): ImageHandle
    {
        $fn = fn(Imagick $i) => $i->cropImage($w, $h, $x, $y);
        return new ImageHandle("crop:{$w}x{$h}-{$x}-{$y}", $fn);
    }

    public static function trim(string $bg = 'white'): ImageHandle
    {
        $fn = fn(Imagick $i) => ($i->setImageBackgroundColor(new ImagickPixel($bg))) && $i->trimImage(0);
        return new ImageHandle("trim:{$bg}", $fn);
    }    

}
