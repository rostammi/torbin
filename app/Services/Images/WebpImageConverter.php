<?php

namespace App\Services\Images;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;

class WebpImageConverter
{
    public function encode(string $contents): string
    {
        $sourceSize = @getimagesizefromstring($contents);
        if (! is_array($sourceSize)) {
            throw new RuntimeException('فایل ورودی یک تصویر معتبر نیست.');
        }

        if (($sourceSize[2] ?? null) === IMAGETYPE_WEBP) {
            return $contents;
        }

        $webp = match (true) {
            function_exists('imagecreatefromstring') && function_exists('imagewebp') => $this->encodeWithGd($contents),
            class_exists(\Imagick::class) => $this->encodeWithImagick($contents),
            default => $this->encodeWithFfmpeg($contents),
        };

        $outputSize = @getimagesizefromstring($webp);
        if (! is_array($outputSize) || ($outputSize[2] ?? null) !== IMAGETYPE_WEBP) {
            throw new RuntimeException('خروجی WebP معتبر ساخته نشد.');
        }

        return $webp;
    }

    public function storeUpload(UploadedFile $upload, string $directory): string
    {
        $contents = file_get_contents($upload->getRealPath());
        if ($contents === false) {
            throw new RuntimeException('خواندن تصویر آپلودشده ناموفق بود.');
        }

        return $this->storeWebp($this->encode($contents), $directory.'/'.Str::uuid().'.webp');
    }

    public function convertStored(string $path): string
    {
        $disk = Storage::disk('public');
        if (! $disk->exists($path)) {
            throw new RuntimeException("فایل تصویر {$path} در فضای ذخیره‌سازی پیدا نشد.");
        }

        $contents = $disk->get($path);
        $size = @getimagesizefromstring($contents);
        if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'webp'
            && is_array($size)
            && ($size[2] ?? null) === IMAGETYPE_WEBP) {
            return $path;
        }

        $directory = trim(pathinfo($path, PATHINFO_DIRNAME), './');
        $filename = pathinfo($path, PATHINFO_FILENAME).'.webp';
        $target = ($directory !== '' ? $directory.'/' : '').$filename;
        if ($disk->exists($target)) {
            $target = ($directory !== '' ? $directory.'/' : '').pathinfo($path, PATHINFO_FILENAME).'-'.Str::uuid().'.webp';
        }

        return $this->storeWebp($this->encode($contents), $target);
    }

    private function storeWebp(string $contents, string $path): string
    {
        if (! Storage::disk('public')->put($path, $contents)) {
            throw new RuntimeException('ذخیره تصویر WebP در فضای عمومی ناموفق بود.');
        }

        return $path;
    }

    private function encodeWithGd(string $contents): string
    {
        $source = @imagecreatefromstring($contents);
        if ($source === false) {
            throw new RuntimeException('GD نتوانست تصویر را بخواند.');
        }

        $target = $source;
        try {
            $sourceWidth = imagesx($source);
            $sourceHeight = imagesy($source);
            $maxWidth = max(1, (int) config('crawler.images.webp_max_width', 1920));

            if ($sourceWidth > $maxWidth) {
                $targetWidth = $maxWidth;
                $targetHeight = max(1, (int) round($sourceHeight * ($targetWidth / $sourceWidth)));
                $target = imagecreatetruecolor($targetWidth, $targetHeight);
                imagealphablending($target, false);
                imagesavealpha($target, true);
                if (! imagecopyresampled($target, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $sourceWidth, $sourceHeight)) {
                    throw new RuntimeException('تغییر اندازه تصویر با GD ناموفق بود.');
                }
            } else {
                imagepalettetotruecolor($target);
                imagealphablending($target, false);
                imagesavealpha($target, true);
            }

            ob_start();
            $encoded = imagewebp($target, null, $this->quality());
            $webp = ob_get_clean();
            if (! $encoded || ! is_string($webp) || $webp === '') {
                throw new RuntimeException('تبدیل تصویر با GD به WebP ناموفق بود.');
            }

            return $webp;
        } finally {
            if ($target !== $source) {
                imagedestroy($target);
            }
            imagedestroy($source);
        }
    }

    private function encodeWithImagick(string $contents): string
    {
        $image = new \Imagick();
        try {
            $image->readImageBlob($contents);
            $image->setIteratorIndex(0);
            $image->setImageFormat('webp');
            $image->setImageCompressionQuality($this->quality());
            $maxWidth = max(1, (int) config('crawler.images.webp_max_width', 1920));
            if ($image->getImageWidth() > $maxWidth) {
                $image->thumbnailImage($maxWidth, 0);
            }
            $image->stripImage();
            $webp = $image->getImageBlob();
            if ($webp === '') {
                throw new RuntimeException('تبدیل تصویر با Imagick به WebP ناموفق بود.');
            }

            return $webp;
        } finally {
            $image->clear();
            $image->destroy();
        }
    }

    private function encodeWithFfmpeg(string $contents): string
    {
        $input = tempnam(sys_get_temp_dir(), 'geyt-webp-input-');
        if ($input === false) {
            throw new RuntimeException('ساخت فایل موقت برای تبدیل WebP ناموفق بود.');
        }
        $output = $input.'.webp';

        try {
            if (file_put_contents($input, $contents) === false) {
                throw new RuntimeException('نوشتن تصویر در فایل موقت ناموفق بود.');
            }

            $maxWidth = max(1, (int) config('crawler.images.webp_max_width', 1920));
            $process = new Process([
                (string) config('crawler.images.ffmpeg_binary', 'ffmpeg'),
                '-hide_banner', '-loglevel', 'error', '-y', '-i', $input,
                '-vf', "scale='min({$maxWidth},iw)':-1",
                '-frames:v', '1', '-c:v', 'libwebp',
                '-quality', (string) $this->quality(),
                '-compression_level', '6',
                $output,
            ]);
            $process->setTimeout(60)->run();
            if (! $process->isSuccessful() || ! is_file($output)) {
                throw new RuntimeException('تبدیل WebP با ffmpeg ناموفق بود: '.trim($process->getErrorOutput()));
            }

            $webp = file_get_contents($output);
            if ($webp === false || $webp === '') {
                throw new RuntimeException('خواندن خروجی WebP ناموفق بود.');
            }

            return $webp;
        } finally {
            @unlink($input);
            @unlink($output);
        }
    }

    private function quality(): int
    {
        return min(100, max(1, (int) config('crawler.images.webp_quality', 82)));
    }
}
