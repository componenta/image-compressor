<?php

declare(strict_types=1);

namespace Componenta\Image\Factory;

use Componenta\Image\GdWebPCompressor;
use Componenta\Image\ImageCompressionLimits;
use Psr\Container\ContainerInterface;

final readonly class GdWebPCompressorFactory
{
    public function __invoke(ContainerInterface $container): GdWebPCompressor
    {
        $limits = $container->has(ImageCompressionLimits::class)
            ? $container->get(ImageCompressionLimits::class)
            : new ImageCompressionLimits();

        return new GdWebPCompressor(limits: $limits);
    }
}
