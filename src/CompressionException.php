<?php

declare(strict_types=1);

namespace Componenta\Image;

final class CompressionException extends \RuntimeException
{
    public static function undetectableSourceType(): self
    {
        return new self('Unable to detect source image MIME type.');
    }

    public static function unsupportedSourceType(string $sourceMimeType): self
    {
        return new self(sprintf('No image compressor supports source MIME type "%s".', $sourceMimeType));
    }

    public static function invalidQuality(int $quality): self
    {
        return new self(sprintf('Image quality must be between 0 and 100, %d given.', $quality));
    }
}
