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
        $quality = $options?->quality;
        if ($quality !== null && ($quality < 0 || $quality > 100)) {
            throw CompressionException::invalidQuality($quality);
        }
        $level = $quality === null ? $this->defaultCompressionLevel : 9 - (int) round($quality / 100 * 9);
        $content = GdProcessor::process($source, self::SUPPORTED, 'image/png', $level, $this->limits);

        return new CompressedImage($content, 'image/png', 'png');
    }

    #[\Override]
    public function supports(string $mimeType): bool
    {
        return GdProcessor::supports($mimeType, self::SUPPORTED, 'image/png');
    }
}
