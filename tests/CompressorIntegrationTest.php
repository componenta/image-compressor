<?php

declare(strict_types=1);

use Componenta\Image\Factory\ImageCompressorFactory;
use Componenta\Image\ImageCompressionLimits;
use Componenta\Image\CompressionException;
use Componenta\Image\Compressor\Internal\EncodedOutput;
use GuzzleHttp\Psr7\NoSeekStream;
use GuzzleHttp\Psr7\Utils;
use Psr\Container\ContainerInterface;

test('factory integrates the current MIME detector with one-shot PSR-7 input', function (): void {
    $container = $this->createStub(ContainerInterface::class);
    $container->method('has')->willReturn(false);
    $service = (new ImageCompressorFactory())($container);
    $image = imagecreatetruecolor(3, 2);
    $source = EncodedOutput::capture(static fn(): bool => imagepng($image));
    $stream = new NoSeekStream(Utils::streamFor($source));
    expect($service->canCompress($stream))->toBeFalse();
    $result = $service->compress($stream);
    expect($result->mimeType)->toBe('image/png');
    expect(imagecreatefromstring($result->content))->toBeInstanceOf(GdImage::class);
});
