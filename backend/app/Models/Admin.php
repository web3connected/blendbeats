<?php

namespace App\Models;

use App\Traits\AvatarTrait;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
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
        return $this->avatar_url;
    }

    public function adminlte_desc(): string
    {
        $role = $this->roles->first()?->name ?? $this->role;

        return str($role)->replace(['-', '_'], ' ')->headline()->toString();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'is_active' => 'boolean',
            'password' => 'hashed',
            'use_gravatar' => 'boolean',
        ];
    }
}
