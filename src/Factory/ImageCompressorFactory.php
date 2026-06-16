<?php

declare(strict_types=1);

namespace Componenta\Image\Factory;

use Componenta\Detector\FinfoDetector;
use Componenta\Detector\MimeTypeDetectorInterface;
use Componenta\Image\GdAvifCompressor;
use Componenta\Image\GdJpegCompressor;
use Componenta\Image\GdPngCompressor;
use Componenta\Image\GdWebPCompressor;
use Componenta\Image\ImageCompressor;
use Psr\Container\ContainerInterface;

final readonly class ImageCompressorFactory
{
    public function __invoke(ContainerInterface $container): ImageCompressor
    {
        $detector = $container->has(MimeTypeDetectorInterface::class)
            ? $container->get(MimeTypeDetectorInterface::class)
            : new FinfoDetector();

        return new ImageCompressor(
            detector: $detector,
            compressors: [
                $container->has(GdJpegCompressor::class) ? $container->get(GdJpegCompressor::class) : new GdJpegCompressor(),
                $container->has(GdPngCompressor::class) ? $container->get(GdPngCompressor::class) : new GdPngCompressor(),
                $container->has(GdWebPCompressor::class) ? $container->get(GdWebPCompressor::class) : new GdWebPCompressor(),
                $container->has(GdAvifCompressor::class) ? $container->get(GdAvifCompressor::class) : new GdAvifCompressor(),
            ],
        );
    }
}
