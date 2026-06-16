<?php

declare(strict_types=1);

namespace Componenta\Image;

use Psr\Http\Message\StreamInterface;

interface ImageCompressorInterface
{
    /**
     * Compress image content without changing its format.
     *
     * @throws CompressionException
     */
    public function compress(
        string|StreamInterface $source,
        ?ImageCompressionOptions $options = null,
    ): CompressedImage;

    /**
     * Whether this compressor can process the given source MIME type.
     */
    public function supports(string $mimeType): bool;
}
