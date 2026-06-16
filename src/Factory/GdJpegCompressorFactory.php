<?php

declare(strict_types=1);

namespace Componenta\Image\Factory;

use Componenta\Image\GdJpegCompressor;
use Psr\Container\ContainerInterface;

final readonly class GdJpegCompressorFactory
{
    public function __invoke(ContainerInterface $container): GdJpegCompressor
    {
        return new GdJpegCompressor();
    }
}
