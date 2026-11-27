<?php

declare(strict_types=1);

namespace Componenta\Image\Factory;

use Componenta\Image\GdJpegCompressor;
use Componenta\Image\ImageCompressionLimits;
use Psr\Container\ContainerInterface;

final readonly class GdJpegCompressorFactory
{
    public function __invoke(ContainerInterface $container): GdJpegCompressor
    {
        $limits = $container->has(ImageCompressionLimits::class)
            ? $container->get(ImageCompressionLimits::class)
            : new ImageCompressionLimits();

        return new GdJpegCompressor(limits: $limits);
    }
}
