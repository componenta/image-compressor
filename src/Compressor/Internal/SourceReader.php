<?php

declare(strict_types=1);

namespace Componenta\Image\Compressor\Internal;

use Componenta\Image\CompressionException;
use Componenta\Image\CompressionStreamPositionRestoreException;
use Psr\Http\Message\StreamInterface;

/** @internal */
final class SourceReader
{
    public static function read(string|StreamInterface $source, int $maxBytes): string
    {
        if (is_string($source)) {
            self::assertSize($source, $maxBytes);

            return $source;
        }

        $position = null;
        $failure = null;
        try {
            if (!$source->isReadable()) {
                throw new CompressionException('Image source is not readable.');
            }
            if ($source->isSeekable()) {
                $position = $source->tell();
                $source->rewind();
            }

            $bytes = '';
            $remaining = $maxBytes;
            $madeProgress = true;
            while (!$source->eof()) {
                if (!$madeProgress) {
                    throw new CompressionException('Image source made no progress before EOF.');
                }
                $length = $remaining === 0 ? 1 : min(65536, $remaining);
                $chunk = $source->read($length);
                $count = strlen($chunk);
                if ($count > $length || $count > $remaining) {
                    throw new CompressionException('Image source exceeds the input byte limit.');
                }
                $madeProgress = $count !== 0;
                $bytes .= $chunk;
                $remaining -= $count;
            }

            return $bytes;
        } catch (\Throwable $error) {
            $failure = $error instanceof \RuntimeException && !$error instanceof CompressionException
                ? new CompressionException('Unable to read image source.', previous: $error)
                : $error;
            throw $failure;
        } finally {
            if ($position !== null) {
                try {
                    $source->seek($position);
                } catch (\Throwable $error) {
                    throw new CompressionStreamPositionRestoreException($error, $failure);
                }
            }
        }
    }

    private static function assertSize(string $source, int $maxBytes): void
    {
        if (strlen($source) > $maxBytes) {
            throw new CompressionException('Image source exceeds the input byte limit.');
        }
    }
}
