<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Traits\AvatarTrait;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Cashier\Billable;

#[Fillable([
    'name',
    'first_name',
    'last_name',
    'email',
    'password',
    'avatar',
    'is_gravatar',
    'use_gravatar',
    'media_storage_tier',
    'billing_provider',
    'comped_subscription_expires_at',
    'comped_subscription_reason',
    'comped_by_user_id',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use AvatarTrait, Billable, HasFactory, Notifiable;

    public function adminlte_image(): string
    {
        return $this->getAvatarUrl(128);
    }

    public function adminlte_desc(): string
    {
        return $this->email;
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
            'password' => 'hashed',
            'is_gravatar' => 'boolean',
            'use_gravatar' => 'boolean',
            'comped_subscription_expires_at' => 'datetime',
        ];
    }

    public function mediaFiles(): HasMany
    {
        return $this->hasMany(MediaFile::class);
    }

    public function mixes(): HasMany
    {
        return $this->hasMany(Mix::class);
    }

    public function ratings(): HasMany
    {
        return $this->hasMany(Rating::class);
    }

    public function djProfile(): HasOne
    {
        return $this->hasOne(DjProfile::class);
    }

    public function mediaAccount(): HasOne
    {
        return $this->hasOne(MediaAccount::class);
    }

    public function featureActivations(): HasMany
    {
        return $this->hasMany(UserFeatureActivation::class);
    }

    public function adCredits(): HasMany
    {
        return $this->hasMany(UserAdCredit::class);
    }

    public function playlistItems(): HasMany
    {
        return $this->hasMany(UserPlaylistItem::class);
    }

    public function affiliateAccount(): HasOne
    {
        return $this->hasOne(AffiliateAccount::class);
    }

    public function affiliateReferral(): HasOne
    {
        return $this->hasOne(AffiliateReferral::class, 'referred_user_id');
    }

    public function wallet(): HasOne
    {
        return $this->hasOne(Wallet::class);
    }

    public function walletTransactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class);
    }

    public function createdDjBattles(): HasMany
    {
        return $this->hasMany(DjBattle::class, 'created_by_user_id');
    }

    public function djBookingRequests(): HasMany
    {
        return $this->hasMany(DjBookingRequest::class, 'dj_user_id');
    }

    public function requestedDjBookings(): HasMany
    {
        return $this->hasMany(DjBookingRequest::class, 'requested_by_user_id');
    }

    public function siteActivityEvents(): HasMany
    {
        return $this->hasMany(SiteActivityEvent::class);
    }
}
