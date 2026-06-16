# Componenta Image Compressor

Помощники сжатия изображений для Componenta. Пакет сжимает содержимое изображения без смены формата и выбирает конкретный компрессор по определенному MIME-типу.

## Установка

```bash
composer require componenta/image-compressor
```

Пакет объявляет `Componenta\Image\ImageCompressorConfigProvider` в `extra.componenta.config-providers`.
Если установлен `componenta/composer-plugin`, провайдер автоматически добавляется в сгенерированный список провайдеров.

Пакет требует PHP `^8.4`, `ext-gd`, `componenta/config`, `componenta/mimetype-detector`, PSR-11 и PSR-7 потоки.

## Основной API

```php
use Componenta\Image\ImageCompressionOptions;
use Componenta\Image\ImageCompressor;

if ($compressor->canCompress($source)) {
    $image = $compressor->compress($source, new ImageCompressionOptions(quality: 85));
}
```

`$source` может быть бинарной строкой или `StreamInterface`. `CompressedImage` содержит `content`, `mimeType`, `extension` и вычисляемый `size`.

## Поддерживаемые форматы

Встроенные GD-компрессоры поддерживают JPEG, PNG, WebP и AVIF, если локальное расширение GD поддерживает нужную операцию.

## Конфигурация

`ImageCompressorConfigProvider` регистрирует:

- `ImageCompressor`
- `GdJpegCompressor`
- `GdPngCompressor`
- `GdWebPCompressor`
- `GdAvifCompressor`

`ImageCompressorFactory` берет `MimeTypeDetectorInterface` из контейнера, если он есть, и иначе использует `FinfoDetector`.

## Опции и ошибки

`ImageCompressionOptions::$quality` должен быть `null` или целым числом от `0` до `100`. Некорректное качество выбрасывает `CompressionException::invalidQuality()`.

`ImageCompressor` выбрасывает `CompressionException`, если тип источника не удалось определить или ни один компрессор его не поддерживает.

## Точки расширения

Реализуйте `ImageCompressorInterface` и вызовите `ImageCompressor::addCompressor()`, чтобы добавить другой компрессор.
