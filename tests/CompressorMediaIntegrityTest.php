<?php

declare(strict_types=1);

use Componenta\Image\CompressionException;
use Componenta\Image\GdPngCompressor;
use Componenta\Image\GdWebPCompressor;

use Componenta\Image\Compressor\Internal\EncodedOutput;

test('rejects animated PNG rather than losing frames', function (): void {
    $source = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAIAAAACCAYAAABytg0kAAAACGFjVEwAAAACAAAAAPONk3AAAAAaZmNUTAAAAAAAAAACAAAAAgAAAAAAAAAAAAEACgAA6FTcAAAAABRJREFUeJxj/M/A0MDAwMDAxAAFABglAYNGiJUnAAAAGmZjVEwAAAABAAAAAgAAAAIAAAAAAAAAAAABAAoAAHMnNtQAAAAYZmRBVAAAAAJ4nGNkYPj/n4GBgYGJAQoAHRkCAunm7jEAAAAASUVORK5CYII=', true);
    expect(fn() => (new GdPngCompressor())->compress($source))
        ->toThrow(CompressionException::class, 'Animated PNG');
});

test('rejects animated WebP rather than losing frames', function (): void {
    $codec = new GdWebPCompressor();
    if (!$codec->supports('image/webp')) {
        $this->markTestSkipped('WebP decoding is not available.');
    }
    $source = base64_decode('UklGRoQAAABXRUJQVlA4WAoAAAASAAAAAQAAAQAAQU5JTQYAAAAAAAAAAABBTk1GKAAAAAAAAAAAAAEAAAEAAGQAAAJWUDhMDwAAAC8BQAAQBxD9jwIGIqL/AQBBTk1GKAAAAAAAAAAAAAEAAAEAAGQAAABWUDhMDwAAAC8BQAAABxDR//4HIqL/AQA=', true);
    expect(fn() => $codec->compress($source))
        ->toThrow(CompressionException::class, 'Animated WebP');
});

test('preserves PNG alpha when compressing PNG', function (): void {
    $source = imagecreatetruecolor(3, 2);
    imagealphablending($source, false);
    imagesavealpha($source, true);
    imagefill($source, 0, 0, imagecolorallocatealpha($source, 10, 80, 120, 127));
    $bytes = EncodedOutput::capture(static fn(): bool => imagepng($source));
    $codec = new GdPngCompressor();
    if (!$codec->supports('image/png')) {
        $this->markTestSkipped('Target codec is unavailable.');
    }
    $result = $codec->compress($bytes);
    $decoded = imagecreatefromstring($result->content);
    expect($decoded)->toBeInstanceOf(GdImage::class);
    expect((imagecolorat($decoded, 0, 0) >> 24) & 0x7f)->toBe(127);
});

test('rejects 16-bit PNG when GD cannot preserve its bit depth', function (): void {
    $source = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAIAAAACEAAAAAAHTY67AAAAEklEQVR4nGNsYGBgYGJgYGAAAASeAISGqV6rAAAAAElFTkSuQmCC', true);
    expect(fn() => (new GdPngCompressor())->compress($source))
        ->toThrow(CompressionException::class, 'bit depth');
});
