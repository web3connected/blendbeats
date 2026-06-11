<?php

namespace App\Models;

use App\Traits\AvatarTrait;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'role', 'is_active', 'avatar', 'use_gravatar'])]
#[Hidden(['password', 'remember_token'])]
class Admin extends Authenticatable
{
    use AvatarTrait, HasRoles, Notifiable;

    protected string $guard_name = 'admin';

    public function adminlte_image(): string
    {
        return $this->getAvatarUrl(128);
    }

    public function adminlte_desc(): string
    {
        return str($this->role)->replace(['-', '_'], ' ')->headline()->toString();
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'use_gravatar' => 'boolean',
        ];
    }

    public function mediaFiles(): HasMany
    {
        return $this->hasMany(MediaFile::class);
    }

    public function mediaAccount(): HasOne
    {
        return $this->hasOne(MediaAccount::class);
    }

    public function featureActivations(): HasMany
    {
        return $this->hasMany(UserFeatureActivation::class);
    }
}
