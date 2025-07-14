# image-processor

A modular, stateless image processing toolkit for PHP built on **Imagick**.  
Compose powerful pipelines with simple, reusable operations for resizing, cropping, transforming, filtering, watermarking, and more.

---

## ✨ Features

- **Composable:** Chain any number of operations for advanced image workflows
- **Rich Operations:** Resize, crop, fit, filter, overlay, watermark, rotate, background, and more
- **Strict typing:** PHP 8.1+, PSR-4, Psalm/PHPStan ready
- **No /tmp clutter:** Uses dedicated temp folders for all operations
- **Modern API:** Pass closures or handler objects for complete flexibility
- **Robust:** Handles missing files, fallbacks, and generates placeholder images automatically

---

## 📦 Installation

```bash
composer require dalpras/image-processor
```

Or, add to your `composer.json`:

```json
{
  "require": {
    "dalpras/image-processor": "^1.0"
  }
}
```

---

## 🚀 Quick Start

```php
use DalPraS\Image\ImageProcessor;
use DalPraS\Image\Handlers\ImageResize;
use DalPraS\Image\Handlers\ImageCrop;
use DalPraS\Image\Handlers\ImageFit;
use DalPraS\Image\Handlers\ImageOverlay;
use DalPraS\Image\Handlers\ImageWatermark;
use DalPraS\Image\Handlers\ImageTransform;
use DalPraS\Image\Handlers\ImageFilter;

$processor = new ImageProcessor(__DIR__ . '/tmp');

// Compose a processing pipeline:
$operations = [
    ImageResize::resize(800, 600),
    ImageFit::fitToTarget(400, 300, 'fill'),
    ImageOverlay::overlay("Demo", 50, 50, null, 32, 'white', 0.7),
    ImageWatermark::watermark(__DIR__.'/logo.png', 'bottom-right', 0.3),
    ImageTransform::rotate(15),
    ImageFilter::filter('grayscale')
];

// Process the image:
$info = $processor->process(
    __DIR__ . '/input.jpg',
    [
        'outputDir'   => __DIR__ . '/out',
        'outputExt'   => 'webp',
        'operations'  => $operations,
        'fallback'    => __DIR__ . '/fallback.jpg',
    ]
);

echo $info->getWidth() . ' x ' . $info->getHeight();
echo $info->getInlinePng(); // Data URI for HTML <img src="">
```

---

## 🛠️ Supported Operations

All operations return an `ImageHandle` for use in the `operations` pipeline.

### **Resize**
- `resize(int $width, int $height, bool $keepAspect = true): ImageHandle`
- `resizeByRatio(int $dim, bool $isWidth = true): ImageHandle`

### **Fit/Fill**
- `fitToTarget(int $targetWidth, int $targetHeight, string $mode = 'fit', string $anchor = 'center', string $background = 'white'): ImageHandle`
  - Modes: `fit`, `fitWidth`, `fitHeight`, `fill`

### **Crop & Trim**
- `crop(int $width, int $height, int $x, int $y): ImageHandle`
- `trim(string $background = 'white'): ImageHandle`

### **Background**
- `backgroundColor(string $color): ImageHandle`
  - Sets the image background color (useful before flattening or composing).

### **Clip (Alpha Mask)**
- `clipPath(bool $inside = false): ImageHandle`
  - Uses 8BIM clipping path #1 for precise masking.

### **Overlay (Text)**
- `overlay(string $text, int $x, int $y, string $font = null, int $size = 20, string $color = 'black', ?float $opacity = null, int $angle = 0, int $align = Imagick::ALIGN_LEFT): ImageHandle`
  - Adds text overlay at specified coordinates with style options.

### **Transform**
- `rotate(float $degrees): ImageHandle`
  - Rotates image by specified degrees.

### **Watermark**
- `watermark(string $path, string $position = 'center', ?float $opacity = null): ImageHandle`
  - Overlays an image as a watermark. Supports all corners and center.

### **Filter**
- `filter(string $type, ...$args): ImageHandle`
  - Image filters supported:
    - `grayscale`
    - `sepia` (optionally with threshold)
    - `blur` (with radius & sigma)
    - Extend with more as needed!

---

## 📋 API Reference

### ImageProcessor

- `__construct(?string $tempDir = null)`
- `process(string $imagePath, array $options = []): ImageInfo`

#### Options

| Key        | Type    | Default     | Description                                           |
|------------|---------|-------------|-------------------------------------------------------|
| outputDir  | string  | image path  | Where to write the output image                       |
| outputExt  | string  | 'webp'      | Output image extension/format                         |
| force      | bool    | false       | Overwrite output if it exists                         |
| operations | array   | []          | List of `ImageHandle` operations                      |
| fallback   | string  | null        | Fallback image path if source is missing              |

---

### ImageInfo

Returned from `process()`.  
Extends `SplFileInfo`, with:

- `getWidth(): int`
- `getHeight(): int`
- `getRatio(): float`
- `getInlinePng(): string` — Data URI for HTML `<img src>`
- `getSpaceHolder(): string` — SVG placeholder (for lazy loading, etc.)

---

## 🐘 Symfony Integration Example

```yaml
# services.yaml
DalPraS\Image\ImageProcessor:
    arguments: ['%kernel.cache_dir%/imagick-tmp']
    public: true
```

---

## 🧪 Testing

```bash
composer install
vendor/bin/phpunit
```

---

## 🤝 Contributions

Contributions are welcome!  
Open an issue or submit a pull request.

---

## 📄 License

MIT © Stefano Dal Prà

---
