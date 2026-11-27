<?php

declare(strict_types=1);

namespace Componenta\Image\Benchmarks;

use Componenta\Detector\FinfoDetector;
use Componenta\Image\GdJpegCompressor;
use Componenta\Image\GdPngCompressor;
use Componenta\Image\GdWebPCompressor;
use Componenta\Image\GdAvifCompressor;
use Componenta\Image\ImageCompressor;
use Componenta\Image\ImageCompressionOptions;
use GuzzleHttp\Psr7\Utils;
use PhpBench\Attributes as Bench;

#[Bench\BeforeMethods('setUp')]
#[Bench\ParamProviders('cases')]
#[Bench\Revs(1)]
#[Bench\Iterations(3)]
final class ImageCompressorBench
{
    private ImageCompressor $service;
    private string $source;
    private bool $baseline = false;

    /** @param array{source: string, target: string, width: int, height: int, stream: bool} $params */
    public function setUp(array $params): void
    {
        $file = (new \ReflectionClass(ImageCompressor::class))->getFileName();
        $this->baseline = is_string($file) && str_contains($file, '/.benchmark-baseline/');
        if ($this->baseline !== (getenv('IMAGE_BENCH_BASELINE') === '1')) {
            throw new \RuntimeException('Benchmark resolved the wrong library version: ' . $file);
        }
        $image = imagecreatetruecolor($params['width'], $params['height']);
        if ($image === false) {
            throw new \RuntimeException('Unable to allocate fixture.');
        }
        for ($y = 0; $y < $params['height']; $y += 32) {
            for ($x = 0; $x < $params['width']; $x += 32) {
                $color = imagecolorallocate($image, $x % 251, $y % 253, ($x + $y) % 255);
                imagefilledrectangle($image, $x, $y, $x + 31, $y + 31, $color);
            }
        }
        ob_start();
        try {
            $ok = match ($params['source']) {
                'jpeg' => imagejpeg($image, null, 90),
                'png' => imagepng($image, null, 6),
                'webp' => imagewebp($image, null, 90),
                'avif' => imageavif($image, null, 90),
                'bmp' => imagebmp($image),
            };
            $source = ob_get_contents();
            if (!$ok || !is_string($source) || $source === '') {
                throw new \RuntimeException('Unable to encode fixture.');
            }
            $this->source = $source;
        } finally {
            ob_end_clean();
        }
        $this->service = new ImageCompressor(new FinfoDetector(), [
            new GdJpegCompressor(), new GdPngCompressor(), new GdWebPCompressor(), new GdAvifCompressor(),
        ]);
    }

    /** @param array{source: string, target: string, width: int, height: int, stream: bool} $params */
    public function benchProcess(array $params): void
    {
        $source = $params['stream'] ? Utils::streamFor($this->source) : $this->source;
        if ($this->baseline) {
            // v1 maps quality=33 to PNG compression level 6.
            $quality = $params['target'] === 'png' ? 33 : 80;
            $this->service->compress($source, new ImageCompressionOptions(quality: $quality));
            return;
        }
        $options = $params['target'] === 'png'
            ? new ImageCompressionOptions(compressionLevel: 6)
            : new ImageCompressionOptions(quality: 80);
        $this->service->compress($source, $options);
    }

    /** @return iterable<string, array{source: string, target: string, width: int, height: int, stream: bool}> */
    public function cases(): iterable
    {
        $pairs = [['jpeg', 'jpeg'], ['png', 'png'], ['webp', 'webp'], ['avif', 'avif']];
        $compare = getenv('IMAGE_BENCH_COMPARE') === '1';
        foreach ($pairs as [$source, $target]) {

            foreach ([[1000, 1000], [2000, 2000], [4000, 3000]] as [$width, $height]) {
                if ($compare && $width === 4000 && !in_array($target, ['jpeg', 'png'], true)) {
                    continue;
                }
                foreach ([false, true] as $stream) {
                    if ($compare && $stream && $width !== 1000) {
                        continue;
                    }
                    yield sprintf('%s-%s-%dx%d-%s', $source, $target, $width, $height, $stream ? 'stream' : 'bytes') => [
                        'source' => $source, 'target' => $target,
                        'width' => $width, 'height' => $height, 'stream' => $stream,
                    ];
                }
            }
        }
    }
}
