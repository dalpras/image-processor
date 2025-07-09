<?php

declare(strict_types=1);

// src/Ops/OpResize.php
namespace DalPraS\Image\Ops;

use DalPraS\Image\Op;
use Imagick;
use ImagickPixel;

class OpBackground {

    public static function backgroundColor(string $color): Op
    {
        $fn = fn(Imagick $i) => $i->setImageBackgroundColor(new ImagickPixel($color));
        return new Op("background:{$color}", $fn);
    }    

}