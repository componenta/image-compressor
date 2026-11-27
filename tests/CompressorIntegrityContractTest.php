<?php

declare(strict_types=1);

use Componenta\Image\CompressionException;
use Componenta\Image\GdPngCompressor;
use Componenta\Image\GdJpegCompressor;
use Componenta\Image\GdAvifCompressor;
use Componenta\Image\ImageCompressionLimits;
use Componenta\Image\Compressor\Internal\EncodedOutput;
use GuzzleHttp\Psr7\Utils;

function compressorIntegrityFixture(string $format): string
{
    $image = imagecreatetruecolor(32, 16);
    imagefilledrectangle($image, 0, 0, 15, 15, imagecolorallocate($image, 230, 20, 10));
    imagefilledrectangle($image, 16, 0, 31, 15, imagecolorallocate($image, 10, 30, 230));

    return EncodedOutput::capture(static fn(): bool => match ($format) {
        'png' => imagepng($image),
        'jpeg' => imagejpeg($image),
    });
}

test('rejects corrupted inputs without altering the surrounding error handler or output', function (string $format): void {
    $codec = $format === 'png' ? new GdPngCompressor() : new GdJpegCompressor();
    $source = compressorIntegrityFixture($format);
    $broken = substr($source, 0, $format === 'png' ? 33 : 12);
    $handler = static fn(int $severity, string $message): bool => true;
    set_error_handler($handler);
    ob_start();
    try {
        echo 'parent';
        $level = ob_get_level();
        foreach (['', 'not an image', $broken] as $data) {
            expect(fn() => $codec->compress($data))->toThrow(CompressionException::class);
            expect(ob_get_level())->toBe($level);
            expect(ob_get_contents())->toBe('parent');
            $installed = set_error_handler(static fn(): bool => true);
            restore_error_handler();
            expect($installed)->toBe($handler);
        }
        for ($i = 0; $i < 10; ++$i) {
            $decoded = imagecreatefromstring($codec->compress($source)->content);
            expect($decoded)->toBeInstanceOf(GdImage::class);
            expect(imagesx($decoded))->toBe(32);
            expect(imagesy($decoded))->toBe(16);
            expect(ob_get_level())->toBe($level);
            expect(ob_get_contents())->toBe('parent');
        }
    } finally {
        ob_end_clean();
        restore_error_handler();
    }
})->with(['png', 'jpeg']);

test('real two-frame AVIF sequence is not silently converted into a still image', function (): void {
    $codec = new GdAvifCompressor();
    if (!$codec->supports('image/avif')) {
        $this->markTestSkipped('AVIF decoding is unavailable.');
    }
    // Generated with Pillow/libavif from red and blue 8x6 frames.
    $source = base64_decode('AAAALGZ0eXBhdmlzAAAAAGF2aWZhdmlzbXNmMWlzbzhtaWYxbWlhZk1BMUIAAADrbWV0YQAAAAAAAAAhaGRscgAAAAAAAAAAcGljdAAAAAAAAAAAAAAAAAAAAAAOcGl0bQAAAAAAAQAAAB5pbG9jAAAAAEQAAAEAAQAAAAEAAAPlAAAAJgAAAChpaW5mAAAAAAABAAAAGmluZmUCAAAAAAEAAGF2MDFDb2xvcgAAAABqaXBycAAAAEtpcGNvAAAAFGlzcGUAAAAAAAAACAAAAAYAAAAQcGl4aQAAAAADCAgIAAAADGF2MUOBAAwAAAAAE2NvbHJuY2x4AAEADQAGgAAAABdpcG1hAAAAAAAAAAEAAQQBAoMEAAACxm1vb3YAAAB4bXZoZAEAAAAAAAAA5y9EkgAAAADnL0SSAAAD6P//////////AAEAAAEAAAAAAAAAAAAAAAABAAAAAAAAAAAAAAAAAAAAAQAAAAAAAAAAAAAAAAAAQAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAEAAAJGdHJhawAAAGh0a2hkAQAAAQAAAADnL0SSAAAAAOcvRJIAAAABAAAAAP//////////AAAAAAAAAAAAAAAAAAAAAAABAAAAAAAAAAAAAAAAAAAAAQAAAAAAAAAAAAAAAAAAQAAAAAAIAAAABgAAAAAALGVkdHMAAAAkZWxzdAEAAAEAAAABAAAAAAAAAMgAAAAAAAAAAAABAAAAAAGqbWRpYQAAACxtZGhkAQAAAAAAAADnL0SSAAAAAOcvRJIAAAPoAAAAAAAAAMhVxAAAAAAAIWhkbHIAAAAAAAAAAHBpY3QAAAAAAAAAAAAAAAAAAAABVW1pbmYAAAAUdm1oZAAAAAEAAAAAAAAAAAAAACRkaW5mAAAAHGRyZWYAAAAAAAAAAQAAAAx1cmwgAAAAAQAAARVzdGJsAAAAlXN0c2QAAAAAAAAAAQAAAIVhdjAxAAAAAAAAAAEAAAAAAAAAAAAAAAAAAAAAAAgABgBIAAAASAAAAAAAAAABCkFPTSBDb2RpbmcAAAAAAAAAAAAAAAAAAAAAAAAAAAAAGP//AAAADGF2MUOBAAwAAAAAE2NvbHJuY2x4AAEADQAGgAAAABBjY3N0AAAAAHwAAAAAAAAYc3R0cwAAAAAAAAABAAAAAgAAAGQAAAAcc3RzYwAAAAAAAAABAAAAAQAAAAIAAAABAAAAHHN0c3oAAAAAAAAAAAAAAAIAAAAmAAAAIAAAABRzdGNvAAAAAAAAAAEAAAPlAAAAFHN0c3MAAAAAAAAAAQAAAAEAAABObWRhdBIACgwAAAABF6bXzICGg0IyFBAAoAAAAkAAAAB5TNXGiE1YU082EgAyHDADwIAAAEagAAACQAAAgAAe5f10Bsh9EBues/k=', true);
    expect($source)->toBeString();
    expect(substr($source, 8, 4))->toBe('avis');
    $info = getimagesizefromstring($source);
    expect($info)->toBeArray();
    expect($info[0])->toBe(8);
    expect($info[1])->toBe(6);
    expect($info['mime'])->toBe('image/avif');
    expect(fn() => $codec->compress($source))->toThrow(CompressionException::class, 'Animated AVIF');
});

test('exact byte and geometry limits work with seekable streams', function (): void {
    $source = compressorIntegrityFixture('png');
    $codec = new GdPngCompressor(limits: new ImageCompressionLimits(
        maxInputBytes: strlen($source),
        maxPixels: 512,
        maxWidth: 32,
        maxHeight: 16,
    ));
    $stream = Utils::streamFor($source);
    $stream->seek(5);
    $result = $codec->compress($stream);
    expect($stream->tell())->toBe(5);
    expect(imagecreatefromstring($result->content))->toBeInstanceOf(GdImage::class);
});

test('one pixel over the area limit is rejected before decoding', function (): void {
    $source = compressorIntegrityFixture('png');
    $codec = new GdPngCompressor(limits: new ImageCompressionLimits(maxPixels: 511));
    expect(fn() => $codec->compress($source))->toThrow(CompressionException::class, 'dimensions exceed');
});
