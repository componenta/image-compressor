<?php

declare(strict_types=1);

namespace Componenta\Image;

final readonly class ImageCompressionOptions
{
    public function __construct(
        public ?int $quality = null,
    ) {
        if ($quality !== null && ($quality < 0 || $quality > 100)) {
            throw CompressionException::invalidQuality($quality);
        }
    }
}
