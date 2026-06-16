<?php

declare(strict_types=1);

namespace Componenta\Image;

use Psr\Http\Message\StreamInterface;

final readonly class GdPngCompressor implements ImageCompressorInterface
{
    public function __construct(
        private int $defaultCompressionLevel = 6,
    ) {
        if ($defaultCompressionLevel < 0 || $defaultCompressionLevel > 9) {
            throw new CompressionException(sprintf(
                'PNG compression level must be between 0 and 9, %d given.',
                $defaultCompressionLevel,
            ));
        }
    }

    #[\Override]
    public function compress(
        string|StreamInterface $source,
        ?ImageCompressionOptions $options = null,
    ): CompressedImage {
        $compressionLevel = $options?->quality === null
            ? $this->defaultCompressionLevel
            : $this->qualityToCompressionLevel($options->quality);

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
            $success = imagepng($gd, null, $compressionLevel);
            $png = ob_get_clean();

            if (!$success || $png === false || $png === '') {
                throw new CompressionException('PNG compression failed');
            }

            return new CompressedImage($png, 'image/png', 'png');
        } finally {
            imagedestroy($gd);
        }
    }

    #[\Override]
    public function supports(string $mimeType): bool
    {
        return strtolower($mimeType) === 'image/png';
    }

    private function qualityToCompressionLevel(int $quality): int
    {
        return 9 - (int) round($quality / 100 * 9);
    }
}
