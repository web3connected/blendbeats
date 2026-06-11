<?php

namespace App\Traits;

use Illuminate\Support\Facades\Storage;

trait AvatarTrait
{
    public function getAvatarUrlAttribute(): string
    {
        return $this->getAvatarUrl();
    }

    public function getAvatarUrl(?int $size = null): string
    {
        if ($this->avatar) {
            if (filter_var($this->avatar, FILTER_VALIDATE_URL)) {
                return $this->avatar;
            }

            // Support public disk avatar paths such as media/accounts/avatar/{file}.
            if (str_starts_with($this->avatar, 'media/')) {
                if (Storage::disk('public')->exists($this->avatar)) {
                    return $this->publicStorageUrl($this->avatar);
                }

                return asset($this->avatar);
            }

            // Support legacy per-account avatar paths: accounts/{account_slug}/avatar/{file}.
            if (str_starts_with($this->avatar, 'accounts/')) {
                if (Storage::disk('public')->exists($this->avatar)) {
                    return $this->publicStorageUrl($this->avatar);
                }

                return asset('media/'.$this->avatar);
            }

            return asset('media/accounts/avatar/'.ltrim($this->avatar, '/'));
        }

        if ($this->usesGravatar()) {
            return $this->getGravatarUrl($size);
        }

        return $this->getGeneratedAvatarUrl($size);
    }

    public function getAvatarDataUrl(?int $size = null): string
    {
        return $this->getAvatarUrl($size);
    }

    public function getGravatarUrl(?int $size = null, ?string $default = null): string
    {
        $email = strtolower(trim((string) ($this->email ?? '')));
        $dimension = $size ?: 128;
        $defaultUrl = $default ?: 'mp';

        return 'https://www.gravatar.com/avatar/'.md5($email).'?'.http_build_query([
            's' => $dimension,
            'd' => $defaultUrl,
            'r' => 'g',
        ]);
    }

    public function getAvatarSourceAttribute(): string
    {
        if (filter_var($this->avatar, FILTER_VALIDATE_URL)) {
            return 'url';
        }

        if ($this->avatar) {
            return 'uploaded';
        }

        return $this->usesGravatar() ? 'gravatar' : 'generated';
    }

    public function usesGravatar(): bool
    {
        return (bool) ($this->is_gravatar ?? $this->use_gravatar ?? false);
    }

    public function getUploadedAvatarUrl(): ?string
    {
        if (! $this->avatar) {
            return null;
        }

        if (filter_var($this->avatar, FILTER_VALIDATE_URL)) {
            return $this->avatar;
        }

        if (str_starts_with($this->avatar, 'media/')) {
            if (Storage::disk('public')->exists($this->avatar)) {
                return $this->publicStorageUrl($this->avatar);
            }

            return asset($this->avatar);
        }

        if (str_starts_with($this->avatar, 'accounts/')) {
            if (Storage::disk('public')->exists($this->avatar)) {
                return $this->publicStorageUrl($this->avatar);
            }

            return asset('media/'.$this->avatar);
        }

        return asset('media/accounts/avatar/'.ltrim($this->avatar, '/'));
    }

    public function getInitials(): string
    {
        $name = trim((string) ($this->name ?? $this->email ?? ''));

        if ($name === '') {
            return 'BB';
        }

        $words = preg_split('/\s+/', $name);
        $initials = count($words) > 1
            ? mb_substr($words[0], 0, 1).mb_substr($words[count($words) - 1], 0, 1)
            : mb_substr($name, 0, 2);

        return mb_strtoupper($initials);
    }

    public function getFullNameAttribute(): string
    {
        return trim((string) ($this->name ?? ''));
    }

    public function setAvatarFromFile(string $filePath): void
    {
        if (! str_starts_with($filePath, 'media/accounts/avatar/')) {
            $filePath = 'media/accounts/avatar/'.basename($filePath);
        }

        $this->avatar = $filePath;
        $this->save();
    }

    public function setAvatarFromUrl(string $url): void
    {
        $this->avatar = $url;
        $this->save();
    }

    public function removeAvatar(): void
    {
        $this->avatar = null;
        $this->save();
    }

    public function getGeneratedAvatarUrl(?int $size = null): string
    {
        $dimension = $size ?: 128;
        $fontSize = (int) round($dimension * 0.42);
        $initials = e($this->getInitials());

        $svg = <<<SVG
        <svg xmlns="http://www.w3.org/2000/svg" width="{$dimension}" height="{$dimension}" viewBox="0 0 {$dimension} {$dimension}">
            <rect width="{$dimension}" height="{$dimension}" rx="{$dimension}" fill="#dc3545"/>
            <text x="50%" y="54%" dominant-baseline="middle" text-anchor="middle" fill="#ffffff" font-family="Arial, Helvetica, sans-serif" font-size="{$fontSize}" font-weight="700">{$initials}</text>
        </svg>
        SVG;

        return 'data:image/svg+xml;charset=UTF-8,'.rawurlencode($svg);
    }

    private function publicStorageUrl(string $path): string
    {
        return url('/storage/'.ltrim($path, '/'));
    }
}
