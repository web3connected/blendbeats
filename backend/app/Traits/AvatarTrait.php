<?php

namespace App\Traits;

trait AvatarTrait
{
    public function getAvatarUrlAttribute(): string
    {
        return $this->getAvatarUrl();
    }

    public function getAvatarUrl(?int $size = null): string
    {
        if ($this->usesGravatar()) {
            return $this->getGravatarUrl($size);
        }

        if ($this->avatar) {
            if (filter_var($this->avatar, FILTER_VALIDATE_URL)) {
                return $this->avatar;
            }

            // Support new per-account avatar path: accounts/{account_slug}/avatar/{file}
            if (str_starts_with($this->avatar, 'accounts/')) {
                return asset('media/'.$this->avatar);
            }

            // Fallback for legacy avatars stored directly under accounts/avatars/
            return asset('media/accounts/avatars/'.ltrim($this->avatar, '/'));
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
        if ($this->usesGravatar()) {
            return 'gravatar';
        }

        if (filter_var($this->avatar, FILTER_VALIDATE_URL)) {
            return 'url';
        }

        return $this->avatar ? 'uploaded' : 'generated';
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

        if (str_starts_with($this->avatar, 'accounts/')) {
            return asset('media/'.$this->avatar);
        }

        return asset('media/accounts/avatars/'.ltrim($this->avatar, '/'));
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
        if (! str_starts_with($filePath, 'accounts/')) {
            $filePath = 'accounts/avatars/'.basename($filePath);
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
        $name = trim((string) ($this->name ?? $this->email ?? 'BlendBeats'));
        $dimension = $size ?: 128;

        return 'https://ui-avatars.com/api/?'.http_build_query([
            'name' => $name,
            'size' => $dimension,
            'background' => 'dc3545',
            'color' => 'fff',
            'bold' => 'true',
        ]);
    }
}
