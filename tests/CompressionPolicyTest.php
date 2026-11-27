<?php

declare(strict_types=1);

namespace Componenta\Image\Tests;

use Componenta\Image\CompressionException;
use Componenta\Image\GdPngCompressor;
use Componenta\Image\ImageCompressionOptions;
use Componenta\Image\Compressor\Internal\EncodedOutput;
use PHPUnit\Framework\TestCase;

final class CompressionPolicyTest extends TestCase
{
    public function testOnlyIfSmallerDoesNotIncreaseByteCount(): void
    {
        $image = imagecreatetruecolor(16, 16);
        self::assertInstanceOf(\GdImage::class, $image);
        $source = EncodedOutput::capture(static fn(): bool => imagepng($image, null, 9));
        $compressed = (new GdPngCompressor())->compress(
            $source,
            new ImageCompressionOptions(compressionLevel: 0, onlyIfSmaller: true),
        );
        self::assertSame($source, $compressed->content);
    }

    public function testPngRejectsLossyQuality(): void
    {
        $this->expectException(CompressionException::class);
        (new GdPngCompressor())->compress('unused', new ImageCompressionOptions(quality: 80));
    }
}
