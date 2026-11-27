<?php

declare(strict_types=1);

namespace Componenta\Image\Factory;

use Componenta\Detector\FinfoDetector;
use Componenta\Detector\MimeTypeDetectorInterface;
use Componenta\Image\GdJpegCompressor;
use Componenta\Image\GdPngCompressor;
use Componenta\Image\GdWebPCompressor;
use Componenta\Image\GdAvifCompressor;
use Componenta\Image\ImageCompressor;
use Componenta\Image\ImageCompressionLimits;
use Psr\Container\ContainerInterface;

final readonly class ImageCompressorFactory
{
    public function __invoke(ContainerInterface $container): ImageCompressor
    {
        $detector = $container->has(MimeTypeDetectorInterface::class)
            ? $container->get(MimeTypeDetectorInterface::class)
            : new FinfoDetector();
        $limits = $container->has(ImageCompressionLimits::class)
            ? $container->get(ImageCompressionLimits::class)
            : new ImageCompressionLimits();

        return new ImageCompressor(
            detector: $detector,
            compressors: [
                $container->has(GdJpegCompressor::class) ? $container->get(GdJpegCompressor::class) : new GdJpegCompressor(limits: $limits),
                $container->has(GdPngCompressor::class) ? $container->get(GdPngCompressor::class) : new GdPngCompressor(limits: $limits),
                $container->has(GdWebPCompressor::class) ? $container->get(GdWebPCompressor::class) : new GdWebPCompressor(limits: $limits),
                $container->has(GdAvifCompressor::class) ? $container->get(GdAvifCompressor::class) : new GdAvifCompressor(limits: $limits),
            ],
            limits: $limits,
        );
    }
}
