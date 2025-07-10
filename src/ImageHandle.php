<?php

declare(strict_types=1);

namespace DalPraS\Image;

use Imagick;

final class ImageHandle
{
    public function __construct(
        private string   $key,     // e.g. 'resize:300x200'
        private \Closure $fn
    ) {}

    public function __invoke(Imagick $im): void
    {
        ($this->fn)($im);
    }
    public function key(): string
    {
        return $this->key;
    }
}
