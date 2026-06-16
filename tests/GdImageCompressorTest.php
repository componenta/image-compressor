<?php

declare(strict_types=1);

namespace Componenta\Image\Tests;

use Componenta\Detector\FinfoDetector;
use Componenta\Image\GdAvifCompressor;
use Componenta\Image\GdJpegCompressor;
use Componenta\Image\GdPngCompressor;
use Componenta\Image\GdWebPCompressor;
use Componenta\Image\ImageCompressionOptions;
use PHPUnit\Framework\TestCase;

final class GdImageCompressorTest extends TestCase
{
    public function testCompressesJpegWithoutChangingFormat(): void
    {
        $image = (new GdJpegCompressor())->compress($this->jpegFixture(), new ImageCompressionOptions(quality: 65));

        self::assertSame('image/jpeg', $image->mimeType);
        self::assertSame('jpg', $image->extension);
        self::assertSame('image/jpeg', (new FinfoDetector())->detectMimeType($image->content));
    }

    public function testCompressesPngWithoutChangingFormat(): void
    {
        $image = (new GdPngCompressor())->compress($this->pngFixture(), new ImageCompressionOptions(quality: 65));

        self::assertSame('image/png', $image->mimeType);
        self::assertSame('png', $image->extension);
        self::assertSame('image/png', (new FinfoDetector())->detectMimeType($image->content));
    }

    public function testCompressesWebPWithoutChangingFormat(): void
    {
        $image = (new GdWebPCompressor())->compress($this->webpFixture(), new ImageCompressionOptions(quality: 65));

        self::assertSame('image/webp', $image->mimeType);
        self::assertSame('webp', $image->extension);
        self::assertSame('image/webp', (new FinfoDetector())->detectMimeType($image->content));
    }

    public function testCompressesAvifWithoutChangingFormatWhenAvailable(): void
    {
        if (!function_exists('imageavif')) {
            self::markTestSkipped('AVIF is not available in the current GD build.');
        }

        $image = (new GdAvifCompressor())->compress($this->avifFixture(), new ImageCompressionOptions(quality: 65));

        self::assertSame('image/avif', $image->mimeType);
        self::assertSame('avif', $image->extension);
        self::assertSame('image/avif', (new FinfoDetector())->detectMimeType($image->content));
    }

    private function jpegFixture(): string
    {
        return $this->encodeFixture(static fn(\GdImage $gd): bool => imagejpeg($gd));
    }

    private function pngFixture(): string
    {
        return $this->encodeFixture(static fn(\GdImage $gd): bool => imagepng($gd));
    }

    private function webpFixture(): string
    {
        return $this->encodeFixture(static fn(\GdImage $gd): bool => imagewebp($gd));
    }

    private function avifFixture(): string
    {
        return $this->encodeFixture(static fn(\GdImage $gd): bool => imageavif($gd));
    }

    /**
     * @param callable(\GdImage): bool $encoder
     */
    private function encodeFixture(callable $encoder): string
    {
        $gd = imagecreatetruecolor(4, 4);

        if ($gd === false) {
            self::fail('Unable to create fixture image.');
        }

        imagealphablending($gd, false);
        imagesavealpha($gd, true);
        $color = imagecolorallocatealpha($gd, 30, 120, 220, 20);

        if ($color === false) {
            imagedestroy($gd);
            self::fail('Unable to allocate fixture color.');
        }

        imagefill($gd, 0, 0, $color);

        ob_start();
        $success = $encoder($gd);
        $content = ob_get_clean();
        imagedestroy($gd);

        self::assertTrue($success);
        self::assertIsString($content);
        self::assertNotSame('', $content);

        return $content;
    }
}
