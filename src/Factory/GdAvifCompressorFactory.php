<?php

declare(strict_types=1);

namespace Componenta\Image\Factory;

use Componenta\Image\GdAvifCompressor;
use Psr\Container\ContainerInterface;

final readonly class GdAvifCompressorFactory
{
    public function __invoke(ContainerInterface $container): GdAvifCompressor
    {
        return new GdAvifCompressor();
    }
}
