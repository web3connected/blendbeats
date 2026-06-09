<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class DjGenre extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsToMany<DjProfile, $this>
     */
    public function profiles(): BelongsToMany
    {
        return $this->belongsToMany(DjProfile::class, 'dj_profile_genres')
            ->using(DjProfileGenre::class)
            ->withPivot(['is_primary', 'sort_order'])
            ->withTimestamps();
    }
}
