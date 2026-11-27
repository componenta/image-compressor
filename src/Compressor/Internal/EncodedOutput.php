<?php

declare(strict_types=1);

namespace Componenta\Image\Compressor\Internal;

use Componenta\Image\CompressionException;

/** @internal */
final class EncodedOutput
{
    /** @param callable(): bool $encode */
    public static function capture(callable $encode, int $maxBytes = PHP_INT_MAX): string
    {
        $content = '';
        $overflow = false;
        $level = ob_get_level();
        // GD writes to the PHP output layer. Consume chunks without forwarding any
        // bytes to the caller's buffer; retain at most maxBytes of encoded data.
        if (!ob_start(static function (string $chunk) use (&$content, &$overflow, $maxBytes): string {
            if (!$overflow) {
                if (strlen($chunk) > $maxBytes - strlen($content)) {
                    $overflow = true;
                    $content = '';
                } else {
                    $content .= $chunk;
                }
            }

            return '';
        }, 65536)) {
            throw new CompressionException('Unable to open image output buffer.');
        }

        try {
            $success = $encode();
            if (!ob_end_flush()) {
                throw new CompressionException('Unable to finalize image output buffer.');
            }
            if ($overflow) {
                throw new CompressionException('Encoded image exceeds the output byte limit.');
            }
            if (!$success || $content === '') {
                throw new CompressionException('Image encoding failed.');
            }

            return $content;
        } finally {
            if (ob_get_level() > $level) {
                ob_end_clean();
            }
        }
    }
}
