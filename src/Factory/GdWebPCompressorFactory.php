<?php

declare(strict_types=1);

namespace Componenta\Image\Factory;

use Componenta\Image\GdWebPCompressor;
use Psr\Container\ContainerInterface;

final readonly class GdWebPCompressorFactory
{
    public function __invoke(ContainerInterface $container): GdWebPCompressor
    {
        return new GdWebPCompressor();
    }
}
