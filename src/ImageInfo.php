<?php

declare(strict_types=1);

namespace DalPraS\Image;

use SplFileInfo;

final class ImageInfo extends SplFileInfo
{
    public function __construct(
        string $pathname,
        private int $width,
        private int $height,
        private ?string $publicUrl = null
    ) {
        parent::__construct($pathname);
    }

    public function getWidth(): int
    {
        return $this->width;
    }

    public function getHeight(): int
    {
        return $this->height;
    }

    /**
     * Public URL (usually site-relative), when a path mapper exposes one.
     * Filesystem methods inherited from SplFileInfo remain unchanged.
     */
    public function getPublicUrl(): ?string
    {
        return $this->publicUrl;
    }

    public function getRatio(): float
    {
        return $this->height > 0 ? $this->width / $this->height : 0.0;
    }
    
    public function getInlinePng(): string
    {
        if (!$this->isFile()) {
            throw new \RuntimeException("File not found: " . $this->getPathname());
        }
        return sprintf('data:image/png;base64,%s', base64_encode(file_get_contents($this->getRealPath())));
    }
    
    public function getSpaceHolder(): string
    {
        if (!$this->isFile()) {
            throw new \RuntimeException("File not found: " . $this->getPathname());
        }
    
        $svg = sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %d %d"></svg>',
            $this->getWidth(),
            $this->getHeight()
        );
    
        return 'data:image/svg+xml,' . rawurlencode($svg);
    }
}
