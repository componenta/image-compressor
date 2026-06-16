<?php

declare(strict_types=1);

namespace Componenta\Image\Tests;

use Componenta\Image\CompressedImage;
use PHPUnit\Framework\TestCase;

final class CompressedImageTest extends TestCase
{
    public function testExposesCompressedImageMetadata(): void
    {
        $image = new CompressedImage('bytes', 'image/jpeg', 'jpg');

        self::assertSame('bytes', $image->content);
        self::assertSame('image/jpeg', $image->mimeType);
        self::assertSame('jpg', $image->extension);
        self::assertSame(5, $image->size);
    }
}
