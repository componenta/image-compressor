<?php

declare(strict_types=1);

namespace Componenta\Image\Compressor\Internal;

use Componenta\Image\CompressionException;

/** @internal */
final class JpegStructure
{
    /**
     * Verify that the outer JPEG stream reaches EOI after a scan. Segment
     * payloads (including EXIF thumbnails) may contain arbitrary marker bytes.
     * This is a structural check, not a full entropy/metadata validator.
     */
    public static function assertComplete(string $raw): void
    {
        $size = strlen($raw);
        if (!str_starts_with($raw, "\xff\xd8")) {
            throw new CompressionException('Missing JPEG start marker.');
        }

        $offset = 2;
        $inScan = false;
        $hasScan = false;
        while ($offset < $size) {
            if ($inScan) {
                // Skip compressed bytes in native code rather than walking pixels.
                $markerOffset = strpos($raw, "\xff", $offset);
                if ($markerOffset === false) {
                    break;
                }
                $offset = $markerOffset;
            } elseif ($raw[$offset] !== "\xff") {
                throw new CompressionException('Invalid JPEG marker boundary.');
            }

            $offset += strspn($raw, "\xff", $offset);
            if ($offset >= $size) {
                break;
            }
            $marker = ord($raw[$offset++]);
            if ($inScan && ($marker === 0 || ($marker >= 0xd0 && $marker <= 0xd7))) {
                // FF00 is a stuffed data byte; RST markers continue the scan.
                continue;
            }
            if ($marker === 0xd9) {
                if ($hasScan) {
                    return;
                }
                throw new CompressionException('JPEG end marker precedes image data.');
            }
            if ($marker === 0x01) {
                // Standalone TEM marker.
                continue;
            }
            if ($marker === 0 || $marker === 0xd8 || ($marker >= 0xd0 && $marker <= 0xd7)
                || $size - $offset < 2) {
                throw new CompressionException('Invalid or incomplete JPEG marker.');
            }

            $length = ord($raw[$offset]) * 256 + ord($raw[$offset + 1]);
            if ($length < 2 || $length > $size - $offset) {
                throw new CompressionException('Invalid JPEG segment length.');
            }
            $offset += $length;

            // SOS starts a scan; DNL can occur within one without terminating it.
            $inScan = $marker === 0xda || ($inScan && $marker === 0xdc);
            if ($marker === 0xda) {
                $hasScan = true;
            }
        }

        throw new CompressionException('JPEG image is missing its end marker.');
    }
}
