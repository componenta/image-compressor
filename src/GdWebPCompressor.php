<?php

declare(strict_types=1);

namespace Componenta\Image;

use Componenta\Image\Compressor\Internal\GdProcessor;
use Psr\Http\Message\StreamInterface;

final readonly class GdWebPCompressor implements ImageCompressorInterface
{
    /** @var list<string> */
    private const array SUPPORTED = ['image/webp'];

    public function __construct(
        private int $defaultQuality = 80,
        private ImageCompressionLimits $limits = new ImageCompressionLimits(),
    ) {
        if ($defaultQuality < 0 || $defaultQuality > 100) {
            throw CompressionException::invalidQuality($defaultQuality);
        }
    }

    #[\Override]
    public function compress(
        string|StreamInterface $source,
        ?ImageCompressionOptions $options = null,
    ): CompressedImage {
        if ($options?->compressionLevel !== null || $options?->avifSpeed !== null) {
            throw new CompressionException('Options are not supported by this image codec.');
        }
        $level = $options?->quality ?? $this->defaultQuality;
        $content = GdProcessor::process($source, self::SUPPORTED, 'image/webp', $level, $this->limits, avifSpeed: null, onlyIfSmaller: $options?->onlyIfSmaller ?? false);

        return new CompressedImage($content, 'image/webp', 'webp');
    }

    #[\Override]
    public function supports(string $mimeType): bool
    {
        return GdProcessor::supports($mimeType, self::SUPPORTED, 'image/webp');
    }
}
