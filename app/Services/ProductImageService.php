<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ProductImageService
{
    public function store(UploadedFile $image): string
    {
        $extension = match ($image->getMimeType()) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => throw new RuntimeException('Unsupported product image type.'),
        };

        $directory = $this->directory();
        $filename = Str::uuid().'.'.$extension;
        $stored = Storage::disk($this->disk())->putFileAs($directory, $image, $filename);

        if ($stored === false) {
            throw new RuntimeException('The product image could not be stored.');
        }

        return $directory.'/'.$filename;
    }

    public function url(?string $path): ?string
    {
        if (! filled($path)) {
            return null;
        }

        if (Str::startsWith($path, ['http://', 'https://', '//', '/'])) {
            return $path;
        }

        if ($this->isManaged($path)) {
            return Storage::disk($this->disk())->url($path);
        }

        return url('/'.ltrim(str_replace('\\', '/', $path), '/'));
    }

    public function deleteManaged(?string $path): bool
    {
        if (! $this->isManaged($path)) {
            return false;
        }

        try {
            $deleted = Storage::disk($this->disk())->delete($path);

            if (! $deleted) {
                Log::warning('Managed product image could not be deleted.', ['path' => $path]);
            }

            return $deleted;
        } catch (Throwable $exception) {
            Log::warning('Managed product image deletion failed.', [
                'path' => $path,
                'exception' => $exception::class,
            ]);

            return false;
        }
    }

    public function isManaged(?string $path): bool
    {
        if (! filled($path)) {
            return false;
        }

        $normalized = ltrim(str_replace('\\', '/', $path), '/');
        $prefix = $this->directory().'/';
        $filename = Str::after($normalized, $prefix);

        return $path === $normalized
            && Str::startsWith($normalized, $prefix)
            && ! str_contains($normalized, '..')
            && $filename !== ''
            && ! str_contains($filename, '/')
            && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\.(?:jpg|png|webp)$/i', $filename) === 1;
    }

    public function disk(): string
    {
        return (string) config('commerce.images.disk', 'public');
    }

    public function directory(): string
    {
        return trim((string) config('commerce.images.directory', 'media/products'), '/');
    }
}
