<?php

declare(strict_types=1);

namespace Componenta\Image;

use Componenta\Detector\MimeTypeDetectorInterface;
use Psr\Http\Message\StreamInterface;

final class ImageCompressor
{
    /** @var list<ImageCompressorInterface> */
    private array $compressors = [];

    /**
     * @param iterable<ImageCompressorInterface> $compressors
     */
    public function __construct(
        private readonly MimeTypeDetectorInterface $detector,
        iterable $compressors = [],
    ) {
        foreach ($compressors as $compressor) {
            $this->addCompressor($compressor);
        }
    }

    public function addCompressor(ImageCompressorInterface $compressor): void
    {
        $this->compressors[] = $compressor;
    }

    public function canCompress(string|StreamInterface $source): bool
    {
        try {
            $sourceMimeType = $this->detector->detectMimeType($source);
        } catch (\Throwable) {
            return false;
        }

        if (!is_string($sourceMimeType) || $sourceMimeType === '') {
            return false;
        }

        return $this->findCompressor($sourceMimeType) instanceof ImageCompressorInterface;
    }

    public function compress(
        string|StreamInterface $source,
        ?ImageCompressionOptions $options = null,
    ): CompressedImage {
        $sourceMimeType = $this->detector->detectMimeType($source);

        if (!is_string($sourceMimeType) || $sourceMimeType === '') {
            throw CompressionException::undetectableSourceType();
        }

        $compressor = $this->findCompressor($sourceMimeType);

        if (!$compressor instanceof ImageCompressorInterface) {
            throw CompressionException::unsupportedSourceType($sourceMimeType);
        }

        return $compressor->compress($source, $options);
    }

    private function findCompressor(string $sourceMimeType): ?ImageCompressorInterface
    {
        foreach ($this->compressors as $compressor) {
            if ($compressor->supports($sourceMimeType)) {
                return $compressor;
            }
        }

        return null;
    }
}
