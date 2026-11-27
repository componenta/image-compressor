<?php

declare(strict_types=1);

use Componenta\Image\GdPngCompressor;
use Componenta\Image\Compressor\Internal\EncodedOutput;

function compressorTransparencyFixture(string $kind): string
{
    $image = $kind === 'palette' ? imagecreate(24, 8) : imagecreatetruecolor(24, 8);
    if ($image === false) {
        throw new RuntimeException('Unable to allocate PNG fixture.');
    }
    imagealphablending($image, false);
    if ($kind === 'color-key') {
        $transparent = imagecolorallocate($image, 230, 20, 30);
        $middle = imagecolorallocate($image, 20, 230, 30);
        $opaque = imagecolorallocate($image, 20, 30, 230);
        imagecolortransparent($image, $transparent);
        imagesavealpha($image, false);
    } else {
        $transparent = imagecolorallocatealpha($image, 230, 20, 30, 127);
        $middle = imagecolorallocatealpha($image, 20, 230, 30, 64);
        $opaque = imagecolorallocatealpha($image, 20, 30, 230, 0);
        imagesavealpha($image, true);
    }
    imagefilledrectangle($image, 0, 0, 7, 7, $transparent);
    imagefilledrectangle($image, 8, 0, 15, 7, $middle);
    imagefilledrectangle($image, 16, 0, 23, 7, $opaque);
    return EncodedOutput::capture(static fn(): bool => imagepng($image));
}

function compressorPixelAlpha(GdImage $image, int $x): int
{
    $index = imagecolorat($image, $x, 4);
    if ($index === imagecolortransparent($image)) {
        return 127;
    }

    return imagecolorsforindex($image, $index)['alpha'];
}

test('preserves transparency and opacity for PNG color representations', function (string $kind, string $target): void {
    $source = compressorTransparencyFixture($kind);
    $original = imagecreatefromstring($source);
    expect($original)->toBeInstanceOf(GdImage::class);
    if ($kind === 'color-key') {
        expect(str_contains($source, 'tRNS'))->toBeTrue();
    }
    expect(compressorPixelAlpha($original, 4))->toBe(127);
    expect(compressorPixelAlpha($original, 12))->toBe($kind === 'color-key' ? 0 : 64);
    expect(compressorPixelAlpha($original, 20))->toBe(0);

    $codec = match ($target) {
        'png' => new GdPngCompressor(),
    };
    if (!$codec->supports('image/png')) {
        $this->markTestSkipped('Required native codec unavailable.');
    }
    $result = $codec->compress($source);
    $decoded = imagecreatefromstring($result->content);
    expect($decoded)->toBeInstanceOf(GdImage::class);
    foreach ([4, 12, 20] as $x) {
        expect(abs(compressorPixelAlpha($decoded, $x) - compressorPixelAlpha($original, $x)))->toBeLessThanOrEqual(2);
    }
    if ($kind === 'palette') {
        expect(imageistruecolor($decoded))->toBeFalse();
    }
})->with(['color-key', 'palette', 'rgba'])->with(['png']);
