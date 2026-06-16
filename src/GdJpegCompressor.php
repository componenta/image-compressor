<?php

declare(strict_types=1);

namespace Componenta\Image;

use Psr\Http\Message\StreamInterface;

final readonly class GdJpegCompressor implements ImageCompressorInterface
{
    /** @var list<string> */
    private const array SUPPORTED = [
        'image/jpeg',
        'image/pjpeg',
    ];

    public function __construct(
        private int $defaultQuality = 82,
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

        $canvas = null;

        try {
            $width = imagesx($gd);
            $height = imagesy($gd);
            $canvas = imagecreatetruecolor($width, $height);

            if ($canvas === false) {
                throw new CompressionException('Failed to create JPEG canvas');
            }

            $background = imagecolorallocate($canvas, 255, 255, 255);

            if ($background === false) {
                throw new CompressionException('Failed to allocate JPEG background color');
            }

            imagefill($canvas, 0, 0, $background);
            imagecopy($canvas, $gd, 0, 0, 0, 0, $width, $height);

            ob_start();
            $success = imagejpeg($canvas, null, $quality);
            $jpeg = ob_get_clean();

            if (!$success || $jpeg === false || $jpeg === '') {
                throw new CompressionException('JPEG compression failed');
            }

            return new CompressedImage($jpeg, 'image/jpeg', 'jpg');
        } finally {
            if ($canvas instanceof \GdImage) {
                imagedestroy($canvas);
            }

            imagedestroy($gd);
        }
    }

    #[\Override]
    public function supports(string $mimeType): bool
    {
        return in_array(strtolower($mimeType), self::SUPPORTED, true);
    }

    private function assertQuality(int $quality): void
    {
        if ($quality < 0 || $quality > 100) {
            throw CompressionException::invalidQuality($quality);
        }
    }
}
