<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class DjProfileGenre extends Pivot
{
    protected $table = 'dj_profile_genres';

    public $incrementing = true;

    protected $fillable = [
        'dj_profile_id',
        'dj_genre_id',
        'is_primary',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<DjProfile, $this>
     */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(DjProfile::class, 'dj_profile_id');
    }

    /**
     * @return BelongsTo<DjGenre, $this>
     */
    public function genre(): BelongsTo
    {
        return $this->belongsTo(DjGenre::class, 'dj_genre_id');
    }
}
