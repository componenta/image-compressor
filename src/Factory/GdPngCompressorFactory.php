<?php

declare(strict_types=1);

namespace Componenta\Image\Factory;

use Componenta\Image\GdPngCompressor;
use Componenta\Image\ImageCompressionLimits;
use Psr\Container\ContainerInterface;

final readonly class GdPngCompressorFactory
{
    public function __invoke(ContainerInterface $container): GdPngCompressor
    {
        $limits = $container->has(ImageCompressionLimits::class)
            ? $container->get(ImageCompressionLimits::class)
            : new ImageCompressionLimits();

        return new GdPngCompressor(limits: $limits);
    }
}
