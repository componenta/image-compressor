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
