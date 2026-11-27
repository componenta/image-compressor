<?php

declare(strict_types=1);

use Componenta\Detector\FinfoDetector;
use Componenta\Image\GdJpegCompressor;
use Componenta\Image\GdPngCompressor;
use Componenta\Image\GdWebPCompressor;
use Componenta\Image\GdAvifCompressor;
use Componenta\Image\ImageCompressor;
use Componenta\Image\ImageCompressionOptions;
use Componenta\Image\ImageCompressionLimits;
use Componenta\Image\CompressionException;
use Componenta\Image\Compressor\Internal\EncodedOutput;
use GuzzleHttp\Psr7\Utils;

function compressorCodecMatrixFixture(string $format): string
{
    $image = imagecreatetruecolor(48, 24);
    if ($image === false) {
        throw new RuntimeException('Could not allocate image fixture.');
    }
    imagealphablending($image, false);
    imagesavealpha($image, true);
    $alpha = in_array($format, ['png', 'webp', 'avif'], true);
    $left = imagecolorallocatealpha($image, 0, 0, 0, $alpha ? 127 : 0);
    $middle = imagecolorallocatealpha($image, 20, 110, 210, $alpha ? 64 : 0);
    $right = imagecolorallocatealpha($image, 180, 50, 70, 0);
    imagefilledrectangle($image, 0, 0, 15, 23, $left);
    imagefilledrectangle($image, 16, 0, 31, 23, $middle);
    imagefilledrectangle($image, 32, 0, 47, 23, $right);

    return EncodedOutput::capture(static fn(): bool => match ($format) {
        'jpeg' => imagejpeg($image, null, 100),
        'png' => imagepng($image),
        'webp' => imagewebp($image, null, 100),
        'avif' => imageavif($image, null, 100, 10),
        'bmp' => imagebmp($image),
        default => throw new InvalidArgumentException('Unexpected fixture format.'),
    });
}

/** All built-in format pairs, checked through both direct codecs and the orchestrator. */
test('advertised format pairs handle both endpoints of their encoding options', function (string $sourceFormat, string $targetFormat, bool $maximum): void {
    $codec = match ($targetFormat) {
        'jpeg' => new GdJpegCompressor(),
        'png' => new GdPngCompressor(),
        'webp' => new GdWebPCompressor(),
        'avif' => new GdAvifCompressor(),
        default => throw new InvalidArgumentException('Unexpected target format.'),
    };
    if (!$codec->supports('image/' . $sourceFormat)) {
        $this->markTestSkipped('Required GD source or target format unavailable.');
    }
    $source = compressorCodecMatrixFixture($sourceFormat);
    $options = match ($targetFormat) {
        'png' => new ImageCompressionOptions(compressionLevel: $maximum ? 9 : 0),
        'avif' => new ImageCompressionOptions(quality: $maximum ? 100 : 0, avifSpeed: $maximum ? 0 : 10),
        default => new ImageCompressionOptions(quality: $maximum ? 100 : 0),
    };
    $direct = $codec->compress($source, $options);
    $service = new ImageCompressor(new FinfoDetector(), [$codec]);
    $stream = Utils::streamFor($source);
    $stream->seek(7);
    expect($service->canCompress($stream))->toBeTrue();
    expect($stream->tell())->toBe(7);
    $routed = $service->compress($stream, $options);
    expect($stream->tell())->toBe(7);
    foreach ([$direct, $routed] as $result) {
        expect($result->mimeType)->toBe('image/' . $targetFormat);
        expect($result->extension)->toBe($targetFormat === 'jpeg' ? 'jpg' : $targetFormat);
        expect($result->size)->toBeGreaterThan(0);
        $decoded = imagecreatefromstring($result->content);
        expect($decoded)->toBeInstanceOf(GdImage::class);
        expect(imagesx($decoded))->toBe(48);
        expect(imagesy($decoded))->toBe(24);
        if (in_array($sourceFormat, ['png', 'webp', 'avif'], true)
            && $targetFormat !== 'jpeg') {
            // Check real pixel alpha, not just the output MIME or presence of an alpha channel.
            $alpha = static fn(int $x): int => imagecolorsforindex($decoded, imagecolorat($decoded, $x, 12))['alpha'];
            expect($alpha(8))->toBe(127);
            expect(abs($alpha(24) - 64))->toBeLessThanOrEqual(2);
            expect($alpha(40))->toBe(0);
        }
    }
})->with([
    'jpeg to jpeg' => ['jpeg', 'jpeg'],
    'png to png' => ['png', 'png'],
    'webp to webp' => ['webp', 'webp'],
    'avif to avif' => ['avif', 'avif'],
])->with(['minimum' => false, 'maximum' => true]);

test('all built-in encoders enforce output limits at the native boundary', function (string $targetFormat): void {
    $sourceFormat = $targetFormat;
    $limits = new ImageCompressionLimits(maxOutputBytes: 1);
    $codec = match ($targetFormat) {
        'jpeg' => new GdJpegCompressor(limits: $limits),
        'png' => new GdPngCompressor(limits: $limits),
        'webp' => new GdWebPCompressor(limits: $limits),
        'avif' => new GdAvifCompressor(limits: $limits),
    };
    if (!$codec->supports('image/' . $sourceFormat)) {
        $this->markTestSkipped('Required codec unavailable.');
    }
    $source = compressorCodecMatrixFixture($sourceFormat);
    $level = ob_get_level();
    expect(fn() => $codec->compress($source))->toThrow(CompressionException::class, 'output byte limit');
    expect(ob_get_level())->toBe($level);
})->with(['jpeg', 'png', 'webp', 'avif']);
