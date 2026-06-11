<?php

namespace App\Models;

use App\Traits\AvatarTrait;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'is_active', 'avatar', 'use_gravatar'])]
#[Hidden(['password', 'remember_token'])]
class Admin extends Authenticatable
{
    use AvatarTrait, Notifiable;

    public function adminlte_image(): string
    {
        return $this->avatar_url;
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
}
