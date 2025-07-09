<?php

declare(strict_types=1);

// src/Ops/OpResize.php
namespace DalPraS\Image\Ops;

use DalPraS\Image\Op;
use Imagick;
use ImagickPixel;

class OpTransform {

    public static function rotate(float $deg): Op
    {
        $fn = fn(Imagick $i) => $i->rotateImage(new ImagickPixel('none'), $deg);
        return new Op("rotate:{$deg}", $fn);
    }

}