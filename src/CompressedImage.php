<?php

declare(strict_types=1);

namespace Componenta\Image;

final class CompressedImage
{
    public function __construct(
        public readonly string $content,
        public readonly string $mimeType,
        public readonly string $extension,
    ) {}

    public int $size {
        get => strlen($this->content);
    }
}
