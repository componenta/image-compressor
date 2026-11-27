<?php

declare(strict_types=1);

namespace Componenta\Image;

use Componenta\Detector\DetectorException;
use Componenta\Detector\MimeTypeDetectorInterface;
use Componenta\Image\Compressor\Internal\SourceReader;
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
        private readonly ImageCompressionLimits $limits = new ImageCompressionLimits(),
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
        // A capability probe must not consume a one-shot stream.
        if ($source instanceof StreamInterface && !$source->isSeekable()) {
            return false;
        }
        try {
            $source = SourceReader::read($source, $this->limits->maxInputBytes);
            $sourceMimeType = $this->detector->detectMimeType($source);
        } catch (DetectorException|CompressionException) {
            return false;
        }

        if ($sourceMimeType === null) {
            return false;
        }

        return $this->findCompressor($sourceMimeType->value) instanceof ImageCompressorInterface;
    }

    public function compress(
        string|StreamInterface $source,
        ?ImageCompressionOptions $options = null,
    ): CompressedImage {
        $source = SourceReader::read($source, $this->limits->maxInputBytes);
        try {
            $sourceMimeType = $this->detector->detectMimeType($source);
        } catch (DetectorException $error) {
            throw new CompressionException('Unable to detect source image MIME type.', previous: $error);
        }

        if ($sourceMimeType === null) {
            throw CompressionException::undetectableSourceType();
        }

        $compressor = $this->findCompressor($sourceMimeType->value);

        if (!$compressor instanceof ImageCompressorInterface) {
            throw CompressionException::unsupportedSourceType($sourceMimeType->value);
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
