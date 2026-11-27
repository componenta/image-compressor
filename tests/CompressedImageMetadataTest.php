<?php

declare(strict_types=1);

use Componenta\Image\CompressedImage;
use Componenta\Image\CompressionException;

test('image results normalize valid format metadata', function (): void {
    $result = new CompressedImage('bytes', 'IMAGE/JPEG', 'JPG');
    expect($result->mimeType)->toBe('image/jpeg');
    expect($result->extension)->toBe('jpg');
    expect($result->size)->toBe(5);
});

test('image results reject empty bytes and malformed metadata', function (string $content, string $mime, string $extension): void {
    expect(fn() => new CompressedImage($content, $mime, $extension))->toThrow(CompressionException::class);
})->with([
    ['', 'image/png', 'png'],
    ['bytes', 'text/plain', 'png'],
    ['bytes', 'image/*', 'png'],
    ['bytes', 'image/png; charset=utf-8', 'png'],
    ['bytes', 'image/png', ''],
    ['bytes', 'image/png', '.png'],
    ['bytes', 'image/png', '../png'],
    ['bytes', 'image/png', 'foo/bar'],
    ['bytes', 'image/png', 'foo\\bar'],
    ['bytes', 'image/png', 'foo:bar'],
]);

test('known image types reject extensions that contradict the MIME catalog', function (string $mime, string $extension): void {
    expect(fn() => new CompressedImage('bytes', $mime, $extension))
        ->toThrow(CompressionException::class, 'extension does not match');
})->with([
    ['image/png', 'jpg'],
    ['image/png', 'png.php'],
    ['image/jpeg', 'webp'],
    ['image/pjpeg', 'png'],
    ['image/x-png', 'jpeg'],
    ['image/avif', 'heic'],
    ['image/webp', 'php'],
    ['image/tiff', 'gif'],
]);

test('known aliases and unmapped custom image types retain valid extensions', function (string $mime, string $extension, string $expected): void {
    $result = new CompressedImage('bytes', $mime, $extension);
    expect($result->extension)->toBe($expected);
})->with([
    ['image/jpeg', 'JPE', 'jpe'],
    ['image/pjpeg', 'jpeg', 'jpeg'],
    ['image/x-png', 'PNG', 'png'],
    ['image/tiff', 'tif', 'tif'],
    ['image/x-componenta-test-format', 'ctf', 'ctf'],
]);
