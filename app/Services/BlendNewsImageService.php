<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class BlendNewsImageService
{
    public function store(UploadedFile $image): string
    {
        $extension = match ($image->getMimeType()) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => throw new RuntimeException('Unsupported Blend News image type.'),
        };

        $path = $this->directory().'/'.Str::uuid().'.'.$extension;
        $stored = Storage::disk($this->disk())->putFileAs(
            $this->directory(),
            $image,
            basename($path),
        );

        if ($stored === false) {
            throw new RuntimeException('The Blend News image could not be stored.');
        }

        return $path;
    }

    public function url(?string $path): ?string
    {
        if (! filled($path)) {
            return null;
        }

        if (Str::startsWith($path, ['http://', 'https://', '//'])) {
            return $path;
        }

        return Storage::disk($this->disk())->url(ltrim(str_replace('\\', '/', $path), '/'));
    }

    public function deleteManaged(?string $path): bool
    {
        if (! $this->isManaged($path)) {
            return false;
        }

        try {
            $deleted = Storage::disk($this->disk())->delete($path);

            if (! $deleted) {
                Log::warning('Blend News managed image could not be deleted.', ['path' => $path]);
            }

            return $deleted;
        } catch (Throwable $exception) {
            Log::warning('Blend News managed image deletion failed.', [
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

        return Str::startsWith($normalized, $prefix)
            && ! str_contains($normalized, '..')
            && $filename !== ''
            && ! str_contains($filename, '/');
    }

    public function disk(): string
    {
        return (string) config('blendnews.images.disk', 'public');
    }

    public function directory(): string
    {
        return trim((string) config('blendnews.images.directory', 'media/blend-news'), '/');
    }
}
