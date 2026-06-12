<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DjSocialLink extends Model
{
    protected $fillable = [
        'dj_profile_id',
        'platform',
        'url',
        'sort_order',
    ];

    /**
     * @return BelongsTo<DjProfile, $this>
     */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(DjProfile::class, 'dj_profile_id');
    }
}
