<?php

declare(strict_types=1);

use Componenta\Image\GdPngCompressor;

test('grayscale PNG color-key transparency is preserved at every supported input depth', function (int $depth, string $fixture): void {
    $source = base64_decode($fixture, true);
    expect($source)->toBeString();
    $info = getimagesizefromstring($source);
    expect($info['bits'])->toBe($depth);
    $codec = new GdPngCompressor();
    if (!$codec->supports('image/png')) {
        $this->markTestSkipped('Required codec is unavailable.');
    }
    $image = $codec->compress($source);
    $decoded = imagecreatefromstring($image->content);
    expect($decoded)->toBeInstanceOf(GdImage::class);
    expect(imagesx($decoded))->toBe(8);
    expect(imagesy($decoded))->toBe(2);
    $first = imagecolorat($decoded, 0, 0);
    $opaque = imagecolorat($decoded, 2, 0);
    $key = imagecolortransparent($decoded);
    $firstAlpha = $key >= 0 && $first === $key ? 127 : imagecolorsforindex($decoded, $first)['alpha'];
    $opaqueAlpha = $key >= 0 && $opaque === $key ? 127 : imagecolorsforindex($decoded, $opaque)['alpha'];
    expect($firstAlpha)->toBe(127);
    expect($opaqueAlpha)->toBe(0);
})->with([
    '1-bit grayscale' => [1, 'iVBORw0KGgoAAAANSUhEUgAAAAgAAAACAQAAAABN76BAAAAAAnRSTlMAAHaTzTgAAAAMSURBVHicYzBmMAYAANAAZ5n3I48AAAAASUVORK5CYII='],
    '2-bit grayscale' => [2, 'iVBORw0KGgoAAAANSUhEUgAAAAgAAAACAgAAAAAKT9qQAAAAAnRSTlMAAHaTzTgAAAAOSURBVHicY+DnZ+DnBwAAugA9hX/JKQAAAABJRU5ErkJggg=='],
    '4-bit grayscale' => [4, 'iVBORw0KGgoAAAANSUhEUgAAAAgAAAACBAAAAACFDy8wAAAAAnRSTlMAAHaTzTgAAAAOSURBVHicY2D4D4QgAgAR+AP95aRjvwAAAABJRU5ErkJggg=='],
    '8-bit grayscale' => [8, 'iVBORw0KGgoAAAANSUhEUgAAAAgAAAACCAAAAABA/8IxAAAAAnRSTlMAAHaTzTgAAAAQSURBVHicY2Bg+P8fgqEMAD/SB/m0Ek/NAAAAAElFTkSuQmCC'],
]);
