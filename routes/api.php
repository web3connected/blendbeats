<?php

use App\Http\Controllers\Api\MixController;
use App\Http\Controllers\Api\AdvertisementDisplayController;
use App\Http\Controllers\Api\AdvertisementEventController;
use App\Http\Controllers\Api\BillingController;
use App\Http\Controllers\Api\CounterController;
use App\Http\Controllers\Api\DjHubController;
use App\Http\Controllers\Api\DjLoungeController;
use App\Http\Controllers\Api\DjProfileController;
use App\Http\Controllers\Api\FeaturedAdController;
use App\Http\Controllers\Api\LoungeLiveStateController;
use App\Http\Controllers\Api\MediaManagerController;
use App\Http\Controllers\Api\MediaSetupController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\RatingController;
use App\Http\Controllers\Api\Auth\UserAuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Session\Middleware\StartSession;

$previewStatus = static function (Request $request) {
    $maintenanceEnabled = filter_var(
        env('FEATURED_ADS_MAINTENANCE_MODE', env('FRONTEND_MAINTENANCE_MODE', false)),
        FILTER_VALIDATE_BOOLEAN
    );
    $admin = auth('admin')->user();
    $allowedRoles = ['super-admin', 'sys-admin', 'sys_admin', 'administrator', 'admin'];
    $adminRoles = $admin
        ? collect($admin->roles?->pluck('name')->all() ?? [])
            ->push($admin->role)
            ->filter()
            ->unique()
            ->values()
            ->all()
        : [];
    $normalizedRoles = collect($adminRoles)
        ->map(fn (string $role): string => strtolower(str_replace(' ', '-', $role)))
        ->all();
    $canPreview = $admin && $admin->is_active && count(array_intersect($allowedRoles, $normalizedRoles)) > 0;

    return response()->json([
        'maintenance_enabled' => $maintenanceEnabled,
        'can_preview' => (bool) $canPreview,
        'admin' => $canPreview ? [
            'name' => $admin->name,
            'email' => $admin->email,
            'roles' => $adminRoles,
        ] : null,
    ]);
};

Route::prefix('auth')
    ->middleware([AddQueuedCookiesToResponse::class, StartSession::class])
    ->name('api.auth.')
    ->group(function (): void {
        Route::post('register', [UserAuthController::class, 'register'])->name('register');
        Route::post('login', [UserAuthController::class, 'login'])->name('login');
        Route::get('me', [UserAuthController::class, 'me'])->name('me');
        Route::patch('account', [UserAuthController::class, 'updateAccount'])->middleware('public.auth')->name('account.update');
        Route::post('logout', [UserAuthController::class, 'logout'])->name('logout');
        Route::post('forgot-password', [UserAuthController::class, 'forgotPassword'])->name('forgot-password');
        Route::post('avatar', [UserAuthController::class, 'updateAvatar'])->middleware('public.auth')->name('avatar');
    });

Route::prefix('dj')
    ->middleware([AddQueuedCookiesToResponse::class, StartSession::class, 'public.auth'])
    ->name('api.dj.')
    ->group(function (): void {
        Route::get('profile', [DjProfileController::class, 'show'])->name('profile.show');
        Route::post('profile', [DjProfileController::class, 'store'])->name('profile.store');
    });

Route::prefix('media')
    ->middleware([AddQueuedCookiesToResponse::class, StartSession::class, 'public.auth'])
    ->name('api.media.')
    ->group(function (): void {
        Route::get('setup', [MediaSetupController::class, 'show'])->name('setup.show');
        Route::post('setup', [MediaSetupController::class, 'store'])->name('setup.store');
        Route::get('files', [MediaManagerController::class, 'index'])->name('files.index');
        Route::post('files', [MediaManagerController::class, 'store'])->name('files.store');
        Route::get('tree', [MediaManagerController::class, 'tree'])->name('tree');
        Route::get('files/{file}/stream', [MediaManagerController::class, 'stream'])->name('files.stream');
        Route::post('files/{file}', [MediaManagerController::class, 'update'])->name('files.update-form');
        Route::patch('files/{file}', [MediaManagerController::class, 'update'])->name('files.update');
        Route::delete('files/{file}', [MediaManagerController::class, 'destroy'])->name('files.destroy');
    });

Route::get('dj-hub/djs', [DjHubController::class, 'index'])->name('api.dj-hub.index');
Route::get('dj-hub/djs/{handle}', [DjHubController::class, 'show'])->name('api.dj-hub.show');
Route::get('lounge/live-state', [LoungeLiveStateController::class, 'show'])->name('api.lounge.live-state');

Route::get('featured-ads/preview-status', $previewStatus)
    ->middleware([AddQueuedCookiesToResponse::class, StartSession::class])
    ->name('api.featured-ads.preview-status');

Route::get('site/preview-status', $previewStatus)
    ->middleware([AddQueuedCookiesToResponse::class, StartSession::class])
    ->name('api.site.preview-status');

Route::get('billing/plans', [BillingController::class, 'plans'])
    ->middleware([AddQueuedCookiesToResponse::class, StartSession::class])
    ->name('api.billing.plans');
Route::prefix('billing')
    ->middleware([AddQueuedCookiesToResponse::class, StartSession::class, 'public.auth'])
    ->name('api.billing.')
    ->group(function (): void {
        Route::get('subscription', [BillingController::class, 'subscription'])->name('subscription');
        Route::get('payment-methods', [BillingController::class, 'paymentMethods'])->name('payment-methods');
        Route::post('checkout', [BillingController::class, 'checkout'])->name('checkout');
        Route::post('portal', [BillingController::class, 'portal'])->name('portal');
    });

Route::prefix('featured-ads')
    ->middleware([AddQueuedCookiesToResponse::class, StartSession::class, 'public.auth'])
    ->name('api.featured-ads.')
    ->group(function (): void {
        Route::get('placements', [FeaturedAdController::class, 'placements'])->name('placements');
        Route::get('analytics', [FeaturedAdController::class, 'analytics'])->name('analytics');
        Route::post('checkout', [FeaturedAdController::class, 'checkout'])->name('checkout');
        Route::post('campaigns/{campaign}/checkout', [FeaturedAdController::class, 'restartCheckout'])->name('campaigns.checkout');
        Route::post('campaigns/{campaign}/capture', [FeaturedAdController::class, 'capture'])->name('campaigns.capture');
    });

Route::prefix('notifications')
    ->middleware([AddQueuedCookiesToResponse::class, StartSession::class, 'public.auth'])
    ->name('api.notifications.')
    ->group(function (): void {
        Route::get('/', [NotificationController::class, 'index'])->name('index');
        Route::get('unread-count', [NotificationController::class, 'unreadCount'])->name('unread-count');
        Route::patch('read-all', [NotificationController::class, 'markAllRead'])->name('read-all');
        Route::patch('{notificationId}/read', [NotificationController::class, 'markRead'])->name('read');
        Route::delete('{notificationId}', [NotificationController::class, 'destroy'])->name('destroy');
    });

Route::prefix('dj-lounge')->name('api.dj-lounge.')->group(function (): void {
    Route::get('posts', [DjLoungeController::class, 'index'])->name('posts.index');

    Route::middleware([AddQueuedCookiesToResponse::class, StartSession::class, 'public.auth'])->group(function (): void {
        Route::post('posts', [DjLoungeController::class, 'store'])->name('posts.store');
        Route::put('posts/{post}', [DjLoungeController::class, 'update'])->name('posts.update');
        Route::delete('posts/{post}', [DjLoungeController::class, 'destroy'])->name('posts.destroy');
        Route::post('posts/{post}/report', [DjLoungeController::class, 'report'])->name('posts.report');
        Route::post('posts/{post}/replies', [DjLoungeController::class, 'storeReply'])->name('posts.replies.store');
        Route::post('posts/{post}/reaction', [DjLoungeController::class, 'toggleReaction'])->name('posts.reaction');
        Route::post('posts/{post}/repost', [DjLoungeController::class, 'toggleRepost'])->name('posts.repost');
        Route::post('posts/{post}/bookmark', [DjLoungeController::class, 'toggleBookmark'])->name('posts.bookmark');
    });
});

Route::get('mixes', [MixController::class, 'index'])->name('api.mixes.index');
Route::post('mixes/{mix:slug}/play', [MixController::class, 'play'])->name('api.mixes.play');
Route::get('ads/display', [AdvertisementDisplayController::class, 'show'])->name('api.ads.display');
Route::post('ads/events', [AdvertisementEventController::class, 'store'])
    ->middleware([AddQueuedCookiesToResponse::class, StartSession::class])
    ->name('api.ads.events.store');
Route::post('counters/{type}/{id}/{action?}', [CounterController::class, 'increment'])->name('api.counters.increment');

Route::get('ratings/{type}/{id}', [RatingController::class, 'show'])
    ->middleware([AddQueuedCookiesToResponse::class, StartSession::class])
    ->name('api.ratings.show');
Route::middleware([AddQueuedCookiesToResponse::class, StartSession::class, 'public.auth'])
    ->group(function (): void {
        Route::post('ratings/{type}/{id}', [RatingController::class, 'store'])->name('api.ratings.store');
        Route::delete('ratings/{type}/{id}', [RatingController::class, 'destroy'])->name('api.ratings.destroy');
    });
