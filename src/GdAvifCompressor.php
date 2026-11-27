<?php

declare(strict_types=1);

namespace Componenta\Image;

use Componenta\Image\Compressor\Internal\GdProcessor;
use Psr\Http\Message\StreamInterface;

final readonly class GdAvifCompressor implements ImageCompressorInterface
{
    /** @var list<string> */
    private const array SUPPORTED = ['image/avif'];

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
        if ($options?->compressionLevel !== null) {
            throw new CompressionException('Options are not supported by this image codec.');
        }
        $level = $options?->quality ?? $this->defaultQuality;
        $content = GdProcessor::process($source, self::SUPPORTED, 'image/avif', $level, $this->limits, avifSpeed: $options?->avifSpeed, onlyIfSmaller: $options?->onlyIfSmaller ?? false);

        return new CompressedImage($content, 'image/avif', 'avif');
    }

    #[\Override]
    public function supports(string $mimeType): bool
    {
        return GdProcessor::supports($mimeType, self::SUPPORTED, 'image/avif');
    }
}
