<?php

declare(strict_types=1);

namespace Componenta\Image\Tests;

use Componenta\Image\CompressionException;
use Componenta\Image\ImageCompressionLimits;
use Componenta\Image\GdJpegCompressor;
use Componenta\Image\GdPngCompressor;
use Componenta\Image\Compressor\Internal\EncodedOutput;
use PHPUnit\Framework\TestCase;

final class CompressorGdSafetyTest extends TestCase
{
    public function testDirectCodecRejectsUnsupportedSourceFormat(): void
    {
        $codec = new GdJpegCompressor();
        self::assertFalse($codec->supports('image/png'));
        $this->expectException(CompressionException::class);
        $codec->compress($this->png());
    }

    public function testDirectCodecEnforcesDimensionLimit(): void
    {
        $codec = new GdPngCompressor(limits: new ImageCompressionLimits(maxPixels: 3));
        $this->expectException(CompressionException::class);
        $codec->compress($this->png());
    }

    public function testDirectCodecEnforcesByteLimit(): void
    {
        $codec = new GdPngCompressor(limits: new ImageCompressionLimits(maxInputBytes: 3));
        $this->expectException(CompressionException::class);
        $codec->compress($this->png());
    }

    public function testOutputIsDecodableAndDimensionsArePreserved(): void
    {
        $image = (new GdPngCompressor())->compress($this->png());
        $decoded = imagecreatefromstring($image->content);
        self::assertInstanceOf(\GdImage::class, $decoded);
        self::assertSame(2, imagesx($decoded));
        self::assertSame(2, imagesy($decoded));
        self::assertSame('image/png', $image->mimeType);
    }

    public function testCodecFailureDoesNotLeakOutputBuffer(): void
    {
        $level = ob_get_level();
        try {
            EncodedOutput::capture(static function (): bool {
                echo 'partial';
                throw new \RuntimeException('encoding failed');
            });
            self::fail('Expected encoding failure.');
        } catch (\RuntimeException $error) {
            self::assertSame('encoding failed', $error->getMessage());
            self::assertSame($level, ob_get_level());
        }
    }

    private function png(): string
    {
        $image = imagecreatetruecolor(2, 2);
        self::assertInstanceOf(\GdImage::class, $image);

        return EncodedOutput::capture(static fn(): bool => imagepng($image));
    }
}
