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

test('the standard factory forwards the configured geometry limits to built-in codecs', function (): void {
    $limits = new ImageCompressionLimits(maxPixels: 5);
    $container = $this->createMock(ContainerInterface::class);
    $container->method('has')->willReturnCallback(
        static fn(string $id): bool => $id === ImageCompressionLimits::class,
    );
    $container->method('get')->with(ImageCompressionLimits::class)->willReturn($limits);
    $service = (new ImageCompressorFactory())($container);
    $image = imagecreatetruecolor(3, 2);
    $source = EncodedOutput::capture(static fn(): bool => imagepng($image));
    expect(fn() => $service->compress($source))->toThrow(CompressionException::class, 'dimensions exceed');
});

test('direct codec factory forwards the configured output limits', function (): void {
    $limits = new ImageCompressionLimits(maxOutputBytes: 1);
    $container = $this->createMock(ContainerInterface::class);
    $container->method('has')->with(ImageCompressionLimits::class)->willReturn(true);
    $container->method('get')->with(ImageCompressionLimits::class)->willReturn($limits);
    $codec = (new \Componenta\Image\Factory\GdPngCompressorFactory())($container);
    $image = imagecreatetruecolor(3, 2);
    $source = EncodedOutput::capture(static fn(): bool => imagepng($image));
    expect(fn() => $codec->compress($source))->toThrow(CompressionException::class, 'output byte limit');
});
