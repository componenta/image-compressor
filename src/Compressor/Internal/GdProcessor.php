<?php

declare(strict_types=1);

namespace Componenta\Image\Compressor\Internal;

use Componenta\Image\CompressionException;
use Componenta\Image\ImageCompressionLimits;
use GdImage;
use Psr\Http\Message\StreamInterface;

/** @internal */
final class GdProcessor
{
    /** @param list<string> $allowed */
    public static function supports(string $source, array $allowed, string $target): bool
    {
        $source = MimeAliases::normalize($source);

        return in_array($source, $allowed, true)
            && self::available($source)
            && self::available($target)
            && function_exists(match ($target) {
                'image/jpeg' => 'imagejpeg',
                'image/png' => 'imagepng',
                'image/webp' => 'imagewebp',
                'image/avif' => 'imageavif',
                default => 'image_codec_not_supported',
            });
    }

    /** @param list<string> $allowed */
    public static function process(
        string|StreamInterface $source,
        array $allowed,
        string $target,
        int $quality,
        ImageCompressionLimits $limits,
        ?int $avifSpeed = null,
        bool $onlyIfSmaller = false,
    ): string {
        $raw = SourceReader::read($source, $limits->maxInputBytes);
        $info = self::native(static fn() => getimagesizefromstring($raw));
        if ($info === false) {
            throw new CompressionException('Unable to identify image dimensions and format.');
        }
        $mime = MimeAliases::normalize($info['mime']);
        if (!self::supports($mime, $allowed, $target)) {
            throw new CompressionException('Source or target image format is unsupported by this GD codec.');
        }
        $width = $info[0];
        $height = $info[1];
        if ($width < 1 || $height < 1 || $width > $limits->maxWidth || $height > $limits->maxHeight
            || $height > intdiv($limits->maxPixels, $width)) {
            throw new CompressionException('Image dimensions exceed configured limits.');
        }
        if ($mime === 'image/jpeg') {
            JpegStructure::assertComplete($raw);
        }
        self::assertStatic($raw, $mime);
        if ($mime === 'image/png' && $target === 'image/png' && ($info['bits'] ?? 8) > 8) {
            throw new CompressionException('GD cannot preserve the bit depth of this PNG.');
        }

        $orientation = $mime === 'image/jpeg'
            ? self::native(static fn(): int => JpegOrientation::read($raw))
            : 1;
        if ($orientation >= 5 && ($height > $limits->maxWidth || $width > $limits->maxHeight)) {
            throw new CompressionException('Oriented image dimensions exceed configured limits.');
        }

        $image = self::native(static fn() => imagecreatefromstring($raw));
        if (!$image instanceof GdImage) {
            throw new CompressionException('Failed to decode image.');
        }
        if (imagesx($image) !== $width || imagesy($image) !== $height) {
            throw new CompressionException('Decoded dimensions differ from the image header.');
        }
        if ($orientation !== 1) {
            $image = self::orientJpeg($image, $orientation);
        }
        if ($target === 'image/jpeg' && $mime !== 'image/jpeg') {
            $image = self::flatten($image);
        } elseif ($target === 'image/webp' || $target === 'image/avif') {
            if (!imageistruecolor($image)) {
                self::requireSuccess(static fn() => imagepalettetotruecolor($image), 'Failed to convert image palette.');
            } elseif (imagecolortransparent($image) >= 0) {
                // Truecolor PNG tRNS is stored as a color key by GD, not per-pixel alpha.
                // WebP and AVIF need actual alpha samples. Copy over a transparent canvas,
                // letting GD skip the keyed pixels without a PHP loop over the image.
                $image = self::expandColorKey($image);
            }
            self::preserveAlpha($image);
        } elseif ($target === 'image/png') {
            self::preparePng($image);
        }

        $encoded = EncodedOutput::capture(static fn(): bool => self::native(static fn(): bool => match ($target) {
            'image/jpeg' => imagejpeg($image, null, $quality),
            'image/png' => imagepng($image, null, $quality),
            'image/webp' => imagewebp($image, null, $quality),
            'image/avif' => imageavif($image, null, $quality, $avifSpeed ?? -1),
            default => throw new CompressionException('Unsupported encoding format.'),
        }), $limits->maxOutputBytes);
        $output = self::native(static fn() => getimagesizefromstring($encoded));
        if ($output === false || MimeAliases::normalize($output['mime']) !== $target
            || $output[0] !== imagesx($image) || $output[1] !== imagesy($image)) {
            throw new CompressionException('Encoded image header does not match the requested format and dimensions.');
        }

        return $onlyIfSmaller && strlen($encoded) >= strlen($raw) ? $raw : $encoded;
    }

    private static function available(string $mime): bool
    {
        if (!function_exists('imagetypes') || !function_exists('imagecreatefromstring')) {
            return false;
        }
        $flag = match ($mime) {
            'image/jpeg' => IMG_JPG,
            'image/png' => IMG_PNG,
            'image/webp' => IMG_WEBP,
            'image/avif' => defined('IMG_AVIF') ? IMG_AVIF : 0,
            'image/bmp' => IMG_BMP,
            default => 0,
        };

        return $flag !== 0 && (imagetypes() & $flag) !== 0;
    }

    private static function orientJpeg(GdImage $image, int $orientation): GdImage
    {
        if (in_array($orientation, [2, 5, 7], true)) {
            self::requireSuccess(static fn() => imageflip($image, IMG_FLIP_HORIZONTAL), 'Failed to mirror JPEG orientation.');
        } elseif ($orientation === 4) {
            self::requireSuccess(static fn() => imageflip($image, IMG_FLIP_VERTICAL), 'Failed to mirror JPEG orientation.');
        }
        $angle = match ($orientation) {
            3 => 180,
            5, 8 => 90,
            6, 7 => -90,
            default => 0,
        };
        if ($angle === 0) {
            return $image;
        }
        $rotated = self::native(static fn() => imagerotate($image, $angle, 0));
        if (!$rotated instanceof GdImage) {
            throw new CompressionException('Failed to rotate JPEG orientation.');
        }

        return $rotated;
    }

    private static function flatten(GdImage $source): GdImage
    {
        $width = imagesx($source);
        $height = imagesy($source);
        $canvas = self::native(static fn() => imagecreatetruecolor($width, $height));
        if (!$canvas instanceof GdImage) {
            throw new CompressionException('Failed to create JPEG canvas.');
        }
        $background = self::native(static fn() => imagecolorallocate($canvas, 255, 255, 255));
        if ($background === false) {
            throw new CompressionException('Failed to allocate JPEG background.');
        }
        self::requireSuccess(static fn() => imagefill($canvas, 0, 0, $background), 'Failed to fill JPEG canvas.');
        self::requireSuccess(static fn() => imagecopy($canvas, $source, 0, 0, 0, 0, $width, $height), 'Failed to flatten JPEG transparency.');

        return $canvas;
    }

    private static function expandColorKey(GdImage $source): GdImage
    {
        $width = imagesx($source);
        $height = imagesy($source);
        $canvas = self::native(static fn() => imagecreatetruecolor($width, $height));
        if (!$canvas instanceof GdImage) {
            throw new CompressionException('Failed to allocate transparent canvas.');
        }
        self::preserveAlpha($canvas);
        $transparent = self::native(static fn() => imagecolorallocatealpha($canvas, 0, 0, 0, 127));
        if ($transparent === false) {
            throw new CompressionException('Failed to allocate transparent color.');
        }
        self::requireSuccess(static fn() => imagefill($canvas, 0, 0, $transparent), 'Failed to fill transparent canvas.');
        self::requireSuccess(static fn() => imagecopy($canvas, $source, 0, 0, 0, 0, $width, $height), 'Failed to expand color-key transparency.');

        return $canvas;
    }

    private static function preparePng(GdImage $image): void
    {
        $key = imagecolortransparent($image);
        if (imageistruecolor($image)) {
            if ($key >= 0) {
                // RGB tRNS must remain a color key, not an empty alpha channel.
                self::requireSuccess(static fn() => imagesavealpha($image, false), 'Failed to preserve PNG color key.');
            } else {
                self::preserveAlpha($image);
            }

            return;
        }
        if ($key < 0) {
            return;
        }
        // GD's grayscale PNG decoder can store tRNS solely as a palette
        // transparent index. The palette PNG encoder uses per-entry alpha.
        $color = self::native(static fn() => imagecolorsforindex($image, $key));
        if ($color['alpha'] !== 127) {
            self::native(static function () use ($image, $key, $color): void {
                imagecolorset($image, $key, $color['red'], $color['green'], $color['blue'], 127);
            });
            if (self::native(static fn() => imagecolorsforindex($image, $key))['alpha'] !== 127) {
                throw new CompressionException('Failed to preserve PNG palette transparency.');
            }
        }
    }

    private static function preserveAlpha(GdImage $image): void
    {
        self::requireSuccess(static fn() => imagealphablending($image, false), 'Failed to configure alpha blending.');
        self::requireSuccess(static fn() => imagesavealpha($image, true), 'Failed to preserve alpha channel.');
    }

    /** @param callable(): bool $operation */
    private static function requireSuccess(callable $operation, string $message): void
    {
        // PHP 8.5 narrows several GD return types to true; PHP 8.4 also needs false handling.
        if (!self::native($operation)) {
            throw new CompressionException($message);
        }
    }

    private static function assertStatic(string $raw, string $mime): void
    {
        if ($mime === 'image/png') {
            // APNG declares animation before the first IDAT.
            $size = strlen($raw);
            for ($offset = 8; $offset <= $size - 12;) {
                $header = unpack('Nlength', substr($raw, $offset, 4));
                $length = $header['length'] ?? null;
                if (!is_int($length) || $length < 0 || $length > $size - $offset - 12) {
                    throw new CompressionException('Invalid PNG chunk length.');
                }
                $type = substr($raw, $offset + 4, 4);
                if ($type === 'acTL') {
                    throw new CompressionException('Animated PNG is not supported.');
                }
                if ($type === 'IDAT' || $type === 'IEND') {
                    break;
                }
                $offset += $length + 12;
            }
        } elseif ($mime === 'image/webp' && strlen($raw) >= 21
            && substr($raw, 12, 4) === 'VP8X' && (ord($raw[20]) & 0x02) !== 0) {
            throw new CompressionException('Animated WebP is not supported.');
        } elseif ($mime === 'image/avif') {
            AvifStructure::assertSupported($raw);
        }
    }

    /**
     * Native calls are synchronous and execute no user callbacks.
     * @template T
     * @param callable(): T $operation
     * @return T
     */
    private static function native(callable $operation): mixed
    {
        set_error_handler(static function (int $severity, string $message): never {
            throw new \ErrorException($message, 0, $severity);
        }, E_WARNING | E_NOTICE);
        try {
            return $operation();
        } catch (\ErrorException|\ValueError $error) {
            throw new CompressionException('GD image operation failed.', previous: $error);
        } finally {
            restore_error_handler();
        }
    }
}
