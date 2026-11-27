<?php

declare(strict_types=1);

namespace Componenta\Image;

use Componenta\Image\Compressor\Internal\GdProcessor;
use Psr\Http\Message\StreamInterface;

final readonly class GdPngCompressor implements ImageCompressorInterface
{
    /** @var list<string> */
    private const array SUPPORTED = ['image/png'];

    public function __construct(
        private int $defaultCompressionLevel = 6,
        private ImageCompressionLimits $limits = new ImageCompressionLimits(),
    ) {
        if ($defaultCompressionLevel < 0 || $defaultCompressionLevel > 9) {
            throw new CompressionException('PNG compression level must be between 0 and 9.');
        }
    }

    #[\Override]
    public function compress(
        string|StreamInterface $source,
        ?ImageCompressionOptions $options = null,
    ): CompressedImage {
        if ($options?->quality !== null || $options?->avifSpeed !== null) {
            throw new CompressionException('PNG supports compressionLevel, not quality or avifSpeed.');
        }
        $level = $options?->compressionLevel ?? $this->defaultCompressionLevel;
        $content = GdProcessor::process($source, self::SUPPORTED, 'image/png', $level, $this->limits, avifSpeed: null, onlyIfSmaller: $options?->onlyIfSmaller ?? false);

        return new CompressedImage($content, 'image/png', 'png');
    }

    #[\Override]
    public function supports(string $mimeType): bool
    {
        return GdProcessor::supports($mimeType, self::SUPPORTED, 'image/png');
    }
}
