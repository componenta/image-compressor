<?php

declare(strict_types=1);

namespace Componenta\Image;

use Componenta\Image\Compressor\Internal\GdProcessor;
use Psr\Http\Message\StreamInterface;

final readonly class GdJpegCompressor implements ImageCompressorInterface
{
    /** @var list<string> */
    private const array SUPPORTED = ['image/jpeg'];

    public function __construct(
        private int $defaultQuality = 82,
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
        $content = GdProcessor::process($source, self::SUPPORTED, 'image/jpeg', $level, $this->limits, avifSpeed: null, onlyIfSmaller: $options?->onlyIfSmaller ?? false);

        return new CompressedImage($content, 'image/jpeg', 'jpg');
    }

    #[\Override]
    public function supports(string $mimeType): bool
    {
        return GdProcessor::supports($mimeType, self::SUPPORTED, 'image/jpeg');
    }
}
