<?php

declare(strict_types=1);

// src/Ops/OpResize.php
namespace DalPraS\Image\Ops;

use DalPraS\Image\Op;
use Imagick;
use ImagickPixel;

class OpCrop {

    public static function crop(int $w, int $h, int $x, int $y): Op
    {
        $fn = fn(Imagick $i) => $i->cropImage($w, $h, $x, $y);
        return new Op("crop:{$w}x{$h}-{$x}-{$y}", $fn);
    }

    public static function trim(string $bg = 'white'): Op
    {
        $fn = fn(Imagick $i) => ($i->setImageBackgroundColor(new ImagickPixel($bg))) && $i->trimImage(0);
        return new Op("trim:{$bg}", $fn);
    }    

}
