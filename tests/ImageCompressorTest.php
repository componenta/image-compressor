<?php

declare(strict_types=1);

namespace Componenta\Image\Tests;

use Componenta\Detector\MimeType;
use Componenta\Detector\MimeTypeDetectorInterface;
use Componenta\Image\CompressedImage;
use Componenta\Image\CompressionException;
use Componenta\Image\ImageCompressionOptions;
use Componenta\Image\ImageCompressor;
use Componenta\Image\ImageCompressorInterface;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;

final class ImageCompressorTest extends TestCase
{
    public function testCompressesUsingFirstCompressorThatSupportsDetectedMimeType(): void
    {
        $compressor = new ImageCompressor(
            detector: new CompressionFixedMimeTypeDetector('image/jpeg'),
            compressors: [
                new UnsupportedImageCompressor(),
                $supported = new SupportedImageCompressor(),
            ],
        );

        $image = $compressor->compress('source-bytes', new ImageCompressionOptions(quality: 72));

        self::assertSame('compressed-jpeg', $image->content);
        self::assertSame('image/jpeg', $image->mimeType);
        self::assertSame('jpg', $image->extension);
        self::assertSame('source-bytes', $supported->source);
        self::assertSame(72, $supported->options?->quality);
    }

    public function testCanAddCompressorAfterConstruction(): void
    {
        $compressor = new ImageCompressor(new CompressionFixedMimeTypeDetector('image/jpeg'));
        $compressor->addCompressor(new SupportedImageCompressor());

        $image = $compressor->compress('source-bytes');

        self::assertSame('compressed-jpeg', $image->content);
    }

    public function testCanCheckWhetherSourceCanBeCompressed(): void
    {
        $compressor = new ImageCompressor(
            detector: new CompressionFixedMimeTypeDetector('image/jpeg'),
            compressors: [new SupportedImageCompressor()],
        );

        self::assertTrue($compressor->canCompress('source-bytes'));
    }

    public function testCannotCompressWhenMimeTypeCannotBeDetected(): void
    {
        $compressor = new ImageCompressor(
            detector: new CompressionFixedMimeTypeDetector(null),
            compressors: [new SupportedImageCompressor()],
        );

        self::assertFalse($compressor->canCompress('source-bytes'));
    }

    public function testThrowsWhenSourceMimeTypeCannotBeDetected(): void
    {
        $compressor = new ImageCompressor(
            detector: new CompressionFixedMimeTypeDetector(null),
            compressors: [new SupportedImageCompressor()],
        );

        $this->expectException(CompressionException::class);
        $this->expectExceptionMessage('Unable to detect source image MIME type.');

        $compressor->compress('source-bytes');
    }

    public function testThrowsWhenNoCompressorSupportsSourceMimeType(): void
    {
        $compressor = new ImageCompressor(
            detector: new CompressionFixedMimeTypeDetector('image/png'),
            compressors: [new SupportedImageCompressor()],
        );

        $this->expectException(CompressionException::class);
        $this->expectExceptionMessage('No image compressor supports source MIME type "image/png".');

        $compressor->compress('source-bytes');
    }
}

final readonly class CompressionFixedMimeTypeDetector implements MimeTypeDetectorInterface
{
    public function __construct(
        private ?string $mimeType,
    ) {}

    #[\Override]
    public function detectMimeType(string|StreamInterface $content, bool $asObject = false): string|MimeType|null
    {
        return $this->mimeType;
    }
}

final class SupportedImageCompressor implements ImageCompressorInterface
{
    public string|StreamInterface|null $source = null;
    public ?ImageCompressionOptions $options = null;

    #[\Override]
    public function compress(
        string|StreamInterface $source,
        ?ImageCompressionOptions $options = null,
    ): CompressedImage {
        $this->source = $source;
        $this->options = $options;

        return new CompressedImage('compressed-jpeg', 'image/jpeg', 'jpg');
    }

    #[\Override]
    public function supports(string $mimeType): bool
    {
        return $mimeType === 'image/jpeg';
    }
}

final readonly class UnsupportedImageCompressor implements ImageCompressorInterface
{
    #[\Override]
    public function compress(
        string|StreamInterface $source,
        ?ImageCompressionOptions $options = null,
    ): CompressedImage {
        return new CompressedImage('unreachable', 'image/png', 'png');
    }

    #[\Override]
    public function supports(string $mimeType): bool
    {
        return false;
    }
}
