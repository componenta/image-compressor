<?php

declare(strict_types=1);

namespace Componenta\Image\Tests;

use Componenta\Detector\MimeType;
use Componenta\Detector\MimeTypeDetectorInterface;
use Componenta\Image\CompressedImage;
use Componenta\Image\CompressionException;
use Componenta\Image\ImageCompressor;
use Componenta\Image\ImageCompressorInterface;
use PHPUnit\Framework\TestCase;

final class CompressorResultContractTest extends TestCase
{
    public function testRejectsDifferentResultFormat(): void
    {
        $detector = $this->createStub(MimeTypeDetectorInterface::class);
        $detector->method('detectMimeType')->willReturn(new MimeType('image/jpeg'));
        $codec = $this->createStub(ImageCompressorInterface::class);
        $codec->method('supports')->willReturn(true);
        $codec->method('compress')->willReturn(new CompressedImage('bytes', 'image/png', 'png'));
        $this->expectException(CompressionException::class);
        (new ImageCompressor($detector, [$codec]))->compress('source');
    }
}
