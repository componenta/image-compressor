<?php

declare(strict_types=1);

namespace Componenta\Image\Factory;

use Componenta\Image\GdPngCompressor;
use Psr\Container\ContainerInterface;

final readonly class GdPngCompressorFactory
{
    public function __invoke(ContainerInterface $container): GdPngCompressor
    {
        return new GdPngCompressor();
    }
}
