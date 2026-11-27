<?php

declare(strict_types=1);

namespace Componenta\Image\Tests;

use Componenta\Detector\MimeType;
use Componenta\Detector\MimeTypeDetectorInterface;
use Componenta\Image\CompressionException;
use Componenta\Image\CompressedImage;
use Componenta\Image\ImageCompressor;
use Componenta\Image\ImageCompressorInterface;
use Componenta\Image\ImageCompressionLimits;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;

final class CompressorStreamContractTest extends TestCase
{
    public function testOneShotStreamIsReadOnlyOnceAndProbeDoesNotConsumeIt(): void
    {
        $stream = $this->stream('image-bytes', false, 0);
        $service = $this->service('image-bytes');
        self::assertFalse($service->canCompress($stream));
        self::assertSame('result', $service->compress($stream)->content);
        self::assertTrue($stream->eof());
    }

    public function testSeekableStreamIsReadFromStartAndPositionIsPreserved(): void
    {
        $stream = $this->stream('image-bytes', true, 3);
        $service = $this->service('image-bytes');
        self::assertTrue($service->canCompress($stream));
        self::assertSame(3, $stream->tell());
        self::assertSame('result', $service->compress($stream)->content);
        self::assertSame(3, $stream->tell());
    }

    public function testByteLimitIsEnforcedAndPositionRestoredOnFailure(): void
    {
        $stream = $this->stream('too-large', true, 2);
        $service = $this->service('unused', new ImageCompressionLimits(maxInputBytes: 3));
        self::assertFalse($service->canCompress($stream));
        try {
            $service->compress($stream);
            self::fail('Expected input limit failure.');
        } catch (CompressionException $error) {
            self::assertStringContainsString('byte limit', $error->getMessage());
            self::assertSame(2, $stream->tell());
        }
    }

    public function testOversizedStringIsRejectedBeforeDetection(): void
    {
        $service = $this->service('unused', new ImageCompressionLimits(maxInputBytes: 3));
        $this->expectException(CompressionException::class);
        $service->compress('too-large');
    }

    private function service(string $expected, ImageCompressionLimits $limits = new ImageCompressionLimits()): ImageCompressor
    {
        $detector = $this->createMock(MimeTypeDetectorInterface::class);
        $detector->method('detectMimeType')->willReturnCallback(
            static function (string|StreamInterface $content) use ($expected): MimeType {
                self::assertSame($expected, $content);

                return new MimeType('image/jpeg');
            },
        );
        $handler = $this->createMock(ImageCompressorInterface::class);
        $handler->method('supports')->with('image/jpeg')->willReturn(true);
        $handler->method('compress')->willReturnCallback(
            static function (string|StreamInterface $source, ?\Componenta\Image\ImageCompressionOptions $options = null) use ($expected): CompressedImage {
                self::assertSame($expected, $source);

                return new CompressedImage('result', 'image/jpeg', 'jpg');
            },
        );
        $service = new ImageCompressor($detector, limits: $limits);
        $service->addCompressor($handler);

        return $service;
    }

    private function stream(string $bytes, bool $seekable, int $position): StreamInterface
    {
        $stream = $this->createMock(StreamInterface::class);
        $stream->method('isReadable')->willReturn(true);
        $stream->method('isSeekable')->willReturn($seekable);
        $stream->method('tell')->willReturnCallback(static function () use (&$position): int {
            return $position;
        });
        $stream->method('rewind')->willReturnCallback(static function () use (&$position): void {
            $position = 0;
        });
        $stream->method('seek')->willReturnCallback(static function (int $offset) use (&$position): void {
            $position = $offset;
        });
        $stream->method('eof')->willReturnCallback(static function () use (&$position, $bytes): bool {
            return $position >= strlen($bytes);
        });
        $stream->method('read')->willReturnCallback(static function (int $length) use (&$position, $bytes): string {
            $chunk = substr($bytes, $position, $length);
            $position += strlen($chunk);

            return $chunk;
        });
        return $stream;
    }
}
