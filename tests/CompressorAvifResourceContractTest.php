<?php

declare(strict_types=1);

use Componenta\Image\CompressionException;
use Componenta\Image\GdAvifCompressor;
use Componenta\Image\Compressor\Internal\AvifStructure;
use Componenta\Image\Compressor\Internal\EncodedOutput;

function compressorAvifBox(string $type, string $payload): string
{
    return pack('N', strlen($payload) + 8) . $type . $payload;
}

function compressorAvifMetadata(string $properties): string
{
    return compressorAvifBox('meta', pack('N', 0)
        . compressorAvifBox('iprp', compressorAvifBox('ipco', $properties)));
}

function compressorAvifFileType(): string
{
    return compressorAvifBox('ftyp', 'avif' . pack('N', 0) . 'avif');
}

test('AVIF rejects duplicate metadata before reading its properties', function (): void {
    $codec = new GdAvifCompressor();
    if (!$codec->supports('image/avif')) {
        $this->markTestSkipped('AVIF codec unavailable.');
    }
    $image = imagecreatetruecolor(16, 8);
    $valid = EncodedOutput::capture(static fn(): bool => imageavif($image, null, 80));
    $property = compressorAvifBox('ispe', pack('N3', 0, 16, 8));
    $source = $valid . compressorAvifMetadata(str_repeat($property, 100000));
    expect(getimagesizefromstring($source)['mime'])->toBe('image/avif');
    expect(strlen($source))->toBeLessThan(33554432);
    expect(fn() => $codec->compress($source))->toThrow(CompressionException::class, 'Duplicate AVIF metadata box');
});

test('duplicate AVIF file type and property containers are rejected', function (string $kind): void {
    $ftyp = compressorAvifFileType();
    $propertyContainer = compressorAvifBox('iprp', compressorAvifBox('ipco', ''));
    $raw = $kind === 'ftyp'
        ? $ftyp . $ftyp
        : $ftyp . compressorAvifBox('meta', pack('N', 0) . $propertyContainer . $propertyContainer);
    $expected = $kind === 'ftyp' ? 'Duplicate AVIF file type box' : 'Duplicate AVIF item properties box';
    expect(fn() => AvifStructure::assertSupported($raw))->toThrow(CompressionException::class, $expected);
})->with(['ftyp', 'iprp']);

test('AVIF metadata is bounded by the maximum addressable property index', function (): void {
    // An ipma association has at most 15 bits for its property index.
    $ftyp = compressorAvifFileType();
    $property = compressorAvifBox('ispe', pack('N3', 0, 16, 8));
    $boundary = str_repeat($property, 0x7fff);
    // The structural guard deliberately accepts a partial synthetic container;
    // this isolates the metadata budget from the native decoder.
    AvifStructure::assertSupported($ftyp . compressorAvifMetadata($boundary));
    expect(fn() => AvifStructure::assertSupported($ftyp . compressorAvifMetadata($boundary . $property)))
        ->toThrow(CompressionException::class, 'AVIF property count exceeds supported limit');
});
