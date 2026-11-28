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
        $signature = self::sourceSignature($raw);
        if ($signature === null || !in_array($signature, $allowed, true)) {
            throw new CompressionException('Unsupported source image signature.');
        }
        if (!self::supports($signature, $allowed, $target)) {
            throw new CompressionException('Source or target image format is unsupported by this GD codec.');
        }
        $info = self::native(static fn() => getimagesizefromstring($raw));
        if ($info === false) {
            throw new CompressionException('Unable to identify image dimensions and format.');
        }
        $mime = MimeAliases::normalize($info['mime']);
        if ($mime !== $signature) {
            throw new CompressionException('Source image format differs from its signature.');
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
        if ($target === 'image/webp' || $target === 'image/avif') {
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
            // GD has already decoded the validated PNG. IHDR color type 2
            // carries RGB samples, with an optional single transparent color.
            self::preparePng($image, $mime === 'image/png' && $raw[25] === "\x02");
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

    /**
     * A narrow preflight, not a complete image detector. PHP's broad image
     * header parser also handles formats such as compressed SWF, which can
     * allocate decompressed data before returning an unsupported MIME type.
     */
    private static function sourceSignature(string $raw): ?string
    {
        return match (true) {
            str_starts_with($raw, "\xff\xd8\xff") => 'image/jpeg',
            str_starts_with($raw, "\x89PNG\r\n\x1a\n") => 'image/png',
            str_starts_with($raw, 'RIFF') && substr($raw, 8, 4) === 'WEBP' => 'image/webp',
            str_starts_with($raw, 'BM') => 'image/bmp',
            self::hasAvifFileType($raw) => 'image/avif',
            default => null,
        };
    }

    private static function hasAvifFileType(string $raw): bool
    {
        // A malformed SWF must not become eligible merely by placing ftyp
        // at offset four. PHP recognizes the CWS signature before BMFF.
        if (strlen($raw) < 16 || substr($raw, 4, 4) !== 'ftyp' || str_starts_with($raw, 'CWS')) {
            return false;
        }

        $size = unpack('Nsize', $raw, 0)['size'] ?? null;
        $header = 8;
        if ($size === 1) {
            if (strlen($raw) < 24 || (unpack('Nhigh', $raw, 8)['high'] ?? null) !== 0) {
                return false;
            }
            $size = unpack('Nsize', $raw, 12)['size'] ?? null;
            $header = 16;
        }
        if (!is_int($size) || $size < $header + 8 || $size > strlen($raw) || ($size - $header) % 4 !== 0) {
            return false;
        }

        // Only AVIF or AVIF sequence brands are eligible. The minor version
        // field is not a brand; the sequence guard will reject avis later.
        $major = substr($raw, $header, 4);
        if ($major === 'avif' || $major === 'avis') {
            return true;
        }
        for ($offset = $header + 8; $offset < $size; $offset += 4) {
            $brand = substr($raw, $offset, 4);
            if ($brand === 'avif' || $brand === 'avis') {
                return true;
            }
        }

        return false;
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

    private static function preparePng(GdImage $image, bool $rgbSource): void
    {
        $key = imagecolortransparent($image);
        if (imageistruecolor($image)) {
            if ($rgbSource || $key >= 0) {
                // Preserve the RGB representation, including an optional tRNS
                // color key, rather than introducing a redundant alpha channel.
                self::requireSuccess(static fn() => imagesavealpha($image, false), 'Failed to preserve PNG RGB representation.');
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
