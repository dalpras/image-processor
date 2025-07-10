<?php

declare(strict_types=1);

// src/Ops/OpResize.php
namespace DalPraS\Image\Handlers;

use DalPraS\Image\ImageHandle;
use Imagick;
use ImagickPixel;

class ImageTransform 
{
    public static function rotate(float $deg): ImageHandle
    {
        $fn = fn(Imagick $i) => $i->rotateImage(new ImagickPixel('none'), $deg);
        return new ImageHandle("rotate:{$deg}", $fn);
    }
}