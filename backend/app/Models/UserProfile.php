<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserProfile extends Model
{
    protected $fillable = [
        'user_id',
        'contact_email',
        'phone',
        'city',
        'state',
        'country',
        'postal_code',
        'timezone',
        'website_url',
        'instagram_url',
        'youtube_url',
        'soundcloud_url',
        'spotify_url',
        'bio',
        'birthdate',
        'marketing_opt_in',
    ];

    protected function casts(): array
    {
        return [
            'birthdate' => 'date',
            'marketing_opt_in' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
