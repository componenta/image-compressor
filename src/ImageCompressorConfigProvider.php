<?php

declare(strict_types=1);

namespace Componenta\Image;

use Componenta\Image\Factory\GdAvifCompressorFactory;
use Componenta\Image\Factory\GdJpegCompressorFactory;
use Componenta\Image\Factory\GdPngCompressorFactory;
use Componenta\Image\Factory\GdWebPCompressorFactory;
use Componenta\Image\Factory\ImageCompressorFactory;

final class ImageCompressorConfigProvider extends \Componenta\Config\ConfigProvider
{
    #[\Override]
    protected function getFactories(): array
    {
        return [
            ImageCompressor::class => ImageCompressorFactory::class,
            GdAvifCompressor::class => GdAvifCompressorFactory::class,
            GdJpegCompressor::class => GdJpegCompressorFactory::class,
            GdPngCompressor::class => GdPngCompressorFactory::class,
            GdWebPCompressor::class => GdWebPCompressorFactory::class,
        ];
    }
}
