<?php

declare(strict_types=1);

namespace Componenta\Image\Tests;

use Componenta\Image\CompressionException;
use Componenta\Image\ImageCompressionOptions;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ImageCompressionOptionsTest extends TestCase
{
    public function testAllowsNullAndBoundaryQualityValues(): void
    {
        self::assertNull(new ImageCompressionOptions()->quality);
        self::assertSame(0, new ImageCompressionOptions(quality: 0)->quality);
        self::assertSame(100, new ImageCompressionOptions(quality: 100)->quality);
    }

    #[DataProvider('invalidQualityValues')]
    public function testRejectsInvalidQualityValues(int $quality): void
    {
        $this->expectException(CompressionException::class);
        $this->expectExceptionMessage(sprintf('Image quality must be between 0 and 100, %d given.', $quality));

        new ImageCompressionOptions(quality: $quality);
    }

    public static function invalidQualityValues(): iterable
    {
        yield 'less than zero' => [-1];
        yield 'greater than hundred' => [101];
    }
}
