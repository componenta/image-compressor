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
        $level = $options?->quality ?? $this->defaultQuality;
        if ($level < 0 || $level > 100) {
            throw CompressionException::invalidQuality($level);
        }
        $content = GdProcessor::process($source, self::SUPPORTED, 'image/avif', $level, $this->limits);

        return new CompressedImage($content, 'image/avif', 'avif');
    }

    #[\Override]
    public function supports(string $mimeType): bool
    {
        return GdProcessor::supports($mimeType, self::SUPPORTED, 'image/avif');
    }
}
