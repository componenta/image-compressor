<?php

declare(strict_types=1);

namespace Componenta\Image;

use Psr\Http\Message\StreamInterface;

final readonly class GdWebPCompressor implements ImageCompressorInterface
{
    public function __construct(
        private int $defaultQuality = 80,
    ) {
        $this->assertQuality($defaultQuality);
    }

    #[\Override]
    public function compress(
        string|StreamInterface $source,
        ?ImageCompressionOptions $options = null,
    ): CompressedImage {
        $quality = $options?->quality ?? $this->defaultQuality;
        $raw = $source instanceof StreamInterface ? (string) $source : $source;
        $gd = @imagecreatefromstring($raw);

        if ($gd === false) {
            throw new CompressionException('Failed to create image from source data');
        }

        try {
            if (!imageistruecolor($gd)) {
                imagepalettetotruecolor($gd);
            }

            imagealphablending($gd, false);
            imagesavealpha($gd, true);

            ob_start();
            $success = imagewebp($gd, null, $quality);
            $webp = ob_get_clean();

            if (!$success || $webp === false || $webp === '') {
                throw new CompressionException('WebP compression failed');
            }

            return new CompressedImage($webp, 'image/webp', 'webp');
        } finally {
            imagedestroy($gd);
        }
    }

    #[\Override]
    public function supports(string $mimeType): bool
    {
        return strtolower($mimeType) === 'image/webp';
    }

    private function assertQuality(int $quality): void
    {
        if ($quality < 0 || $quality > 100) {
            throw CompressionException::invalidQuality($quality);
        }
    }
}
