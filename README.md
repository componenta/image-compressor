# Componenta Image Compressor

Image compression helpers for Componenta. The package compresses image content without changing its format and selects a concrete compressor by detected MIME type.

## Installation

```bash
composer require componenta/image-compressor
```

The package declares `Componenta\Image\ImageCompressorConfigProvider` in `extra.componenta.config-providers`.
When `componenta/composer-plugin` is installed, the provider is added to the generated provider list automatically.

The package requires PHP `^8.4`, `ext-gd`, `componenta/config`, `componenta/mimetype-detector`, PSR-11, and PSR-7 streams.

## Main API

```php
use Componenta\Image\ImageCompressionOptions;
use Componenta\Image\ImageCompressor;

if ($compressor->canCompress($source)) {
    $image = $compressor->compress($source, new ImageCompressionOptions(quality: 85));
}
```

`$source` may be a binary string or `StreamInterface`. `CompressedImage` contains `content`, `mimeType`, `extension`, and computed `size`.

## Supported Formats

Built-in GD compressors support JPEG, PNG, WebP, and AVIF when the local GD extension supports the target operation.

## Configuration

`ImageCompressorConfigProvider` registers:

- `ImageCompressor`
- `GdJpegCompressor`
- `GdPngCompressor`
- `GdWebPCompressor`
- `GdAvifCompressor`

`ImageCompressorFactory` uses `MimeTypeDetectorInterface` from the container when available and falls back to `FinfoDetector`.

## Options And Errors

`ImageCompressionOptions::$quality` must be `null` or an integer from `0` to `100`. Invalid quality throws `CompressionException::invalidQuality()`.

`ImageCompressor` throws `CompressionException` when the source type cannot be detected or no compressor supports it.

## Extension Points

Implement `ImageCompressorInterface` and call `ImageCompressor::addCompressor()` to add another compressor.
