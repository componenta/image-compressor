<?php

declare(strict_types=1);

namespace Componenta\Image\Factory;

use Componenta\Image\GdAvifCompressor;
use Componenta\Image\ImageCompressionLimits;
use Psr\Container\ContainerInterface;

final readonly class GdAvifCompressorFactory
{
    public function __invoke(ContainerInterface $container): GdAvifCompressor
    {
        $limits = $container->has(ImageCompressionLimits::class)
            ? $container->get(ImageCompressionLimits::class)
            : new ImageCompressionLimits();

        return new GdAvifCompressor(limits: $limits);
    }
}
