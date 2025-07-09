# DalPraS Image Processor

A stateless image‑processing toolkit built on **Imagick**. It pairs a single, reusable
`ImageProcessor` with a collection of composable operations in `ImgOps`,
returning a rich `ImageInfo` value‑object after each run.

---

## ✨ Why use it?

* **Singleton‑friendly** – register `ImageProcessor` once in your DI container and
  reuse it everywhere.
* **Composable** – chain any number of operations in one array.
* **No /tmp clutter** – the service sets a dedicated temp folder via `MAGICK_TEMPORARY_PATH`.
* **Strict typing** – PHP 8.1+, Psalm / PHP‑Stan‑ready.

---

## 📦 Installation

```bash
# root composer.json
composer require dalpras/image-processor
```

> **Local development**  If you keep the library in `src/packages/image-processor`,
> add a *path repository*:
>
> ```jsonc
> "repositories": [{
>   "type": "path",
>   "url" : "src/packages/image-processor"
> }]
> ```
>
> Then run `composer update dalpras/image-processor`.

---

## 🚀 Quick start

```php
use DalPraS\Image\{ImageProcessor, ImgOps};

$service = new ImageProcessor();

$thumb = $service->process('/images/photo.jpg', [
    'outputDir'  => '/cache/images', // default: dirname($source)
    'suffix'     => 'thumb',         // → photo-thumb.webp
    'operations' => [
        ImgOps::resize(320, 200),
        ImgOps::filter('grayscale'),
        ImgOps::watermark('/assets/logo.png', 'bottom-right'),
    ],
]);

echo $thumb->getPathname(); // /cache/images/photo-thumb.webp
echo $thumb->getWidth();     // 320
```

### Inside a Symfony / PSR‑11 container

```yaml
# services.yaml
DalPraS\Image\ImageProcessor:
    arguments: ['%kernel.cache_dir%/imagick-tmp']
    public: true
```

---

## 🛠️ Common operations (`ImgOps`)

| Category  | Operation           | Signature                                                              |
| --------- | ------------------- | ---------------------------------------------------------------------- |
| Resize    | `resize`            | `resize(int $w, int $h, bool $keepAR=true)`                            |
|           | `resizeByRatio`     | `resizeByRatio(int $dim, bool $isWidth=true)`                          |
| Stretch   | `stretch`           | `stretch(int $w, int $h)`                                              |
|           | `stretchByPercent`  | `stretchByPercent(float $w%, float $h%)`                               |
| Crop      | `crop`              | `crop(int $w, int $h, int $x, int $y)`                                 |
| Trim      | `trim`              | `trim(string $bg='white')`                                             |
| Rotate    | `rotate`            | `rotate(float $deg)`                                                   |
| Watermark | `watermark`         | `watermark(string $path, string $pos='center')`                        |
| Text      | `overlay`           | `overlay(string $txt, int $x, int $y, …)`                          |
| Filters   | `filter`            | `filter(string $type, ...$args)` *(grayscale, sepia, blur…)*           |
| Fit/Fill  | `fitToTarget`       | `fitToTarget(int $tw,int $th,string $mode='fit',string $pos='center')` |
| Spacing   | `margin`, `padding` | …                                                                      |

Check `src/ImgOps.php` for the full list and parameters.

---

## 🧪 Running tests

```bash
composer install       # installs phpunit if not present
vendor/bin/phpunit
```

Sample output:

```
PHPUnit 10.5.x
✔  ImgOpsTest (5 tests, 5 assertions)
```

---

## 📄 License

MIT © Stefano Dal Prà / Vimar SpA

