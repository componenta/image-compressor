<?php

declare(strict_types=1);

namespace Componenta\Image\Compressor\Internal;

/** @internal */
final class MimeAliases
{
    public static function normalize(string $mimeType): string
    {
        return match (strtolower(trim($mimeType))) {
            'image/pjpeg' => 'image/jpeg',
            'image/x-png' => 'image/png',
            'image/x-ms-bmp' => 'image/bmp',
            default => strtolower(trim($mimeType)),
        };
    }
}
