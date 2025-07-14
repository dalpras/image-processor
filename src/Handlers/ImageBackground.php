<?php

declare(strict_types=1);

// src/Ops/OpResize.php
namespace DalPraS\Image\Handlers;

use DalPraS\Image\ImageHandle;
use Imagick;
use ImagickPixel;

class ImageBackground 
{
    public static function backgroundColor(string $color): ImageHandle
    {
        $fn = fn(Imagick $i) => $i->setImageBackgroundColor(new ImagickPixel($color));
        return new ImageHandle("background:{$color}", $fn);
    }    

}