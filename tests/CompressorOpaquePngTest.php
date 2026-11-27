<?php

declare(strict_types=1);

use Componenta\Image\GdPngCompressor;
use Componenta\Image\ImageCompressionOptions;
use Componenta\Image\Compressor\Internal\EncodedOutput;

test('opaque RGB PNG retains RGB representation and exact pixels on repeated compression', function (bool $interlaced, int $level): void {
    $image = imagecreatetruecolor(37, 23);
    imagesavealpha($image, false);
    imageinterlace($image, $interlaced);
    for ($y = 0; $y < 23; ++$y) {
        for ($x = 0; $x < 37; ++$x) {
            imagesetpixel($image, $x, $y, imagecolorallocate(
                $image, ($x * 37 + $y) % 256, ($y * 41 + $x) % 256, ($x * 13 + $y * 17) % 256,
            ));
        }
    }
    $source = EncodedOutput::capture(static fn(): bool => imagepng($image, null, 6));
    expect(ord($source[25]))->toBe(2);
    $codec = new GdPngCompressor();
    $options = new ImageCompressionOptions(compressionLevel: $level);
    $first = $codec->compress($source, $options);
    $second = $codec->compress($first->content, $options);
    foreach ([$first, $second] as $result) {
        expect($result->mimeType)->toBe('image/png');
        expect(ord($result->content[25]))->toBe(2);
        $decoded = imagecreatefromstring($result->content);
        expect($decoded)->toBeInstanceOf(GdImage::class);
        expect([imagesx($decoded), imagesy($decoded)])->toBe([37, 23]);
        for ($y = 0; $y < 23; ++$y) {
            for ($x = 0; $x < 37; ++$x) {
                expect(imagecolorat($decoded, $x, $y))->toBe(imagecolorat($image, $x, $y));
            }
        }
    }
})->with(['sequential' => false, 'Adam7' => true])->with([0, 6, 9]);

test('preserves RGB color keys and RGBA samples without adding or dropping alpha', function (bool $withAlpha, int $level): void {
    $image = imagecreatetruecolor(8, 4);
    imagealphablending($image, false);
    imagesavealpha($image, $withAlpha);
    $transparent = imagecolorallocatealpha($image, 200, 10, 10, $withAlpha ? 127 : 0);
    $partial = imagecolorallocatealpha($image, 10, 200, 10, $withAlpha ? 64 : 0);
    $opaque = imagecolorallocatealpha($image, 10, 10, 200, 0);
    if (!$withAlpha) {
        imagecolortransparent($image, $transparent);
    }
    imagefilledrectangle($image, 0, 0, 1, 3, $transparent);
    imagefilledrectangle($image, 2, 0, 4, 3, $partial);
    imagefilledrectangle($image, 5, 0, 7, 3, $opaque);
    $source = EncodedOutput::capture(static fn(): bool => imagepng($image, null, 6));
    $result = (new GdPngCompressor())->compress($source, new ImageCompressionOptions(compressionLevel: $level));
    expect(ord($result->content[25]))->toBe($withAlpha ? 6 : 2);
    $decoded = imagecreatefromstring($result->content);
    expect($decoded)->toBeInstanceOf(GdImage::class);
    if (!$withAlpha) {
        expect(imagecolortransparent($decoded))->toBe($transparent);
    }
    foreach ([0, 3, 6] as $x) {
        expect(imagecolorat($decoded, $x, 2))->toBe(imagecolorat($image, $x, 2));
    }
})->with(['color key' => false, 'alpha channel' => true])->with([0, 9]);
