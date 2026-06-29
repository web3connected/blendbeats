<?php

use App\Http\Controllers\Api\FeaturedAdController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

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

Route::get('featured-ads/preview-status', $previewStatus)
    ->name('api.featured-ads.preview-status');

Route::get('site/preview-status', $previewStatus)
    ->name('api.site.preview-status');

Route::prefix('featured-ads')
    ->middleware('public.auth')
    ->name('api.featured-ads.')
    ->group(function (): void {
        Route::get('placements', [FeaturedAdController::class, 'placements'])->name('placements');
        Route::get('analytics', [FeaturedAdController::class, 'analytics'])->name('analytics');
        Route::post('checkout', [FeaturedAdController::class, 'checkout'])->name('checkout');
        Route::post('campaigns/{campaign}/checkout', [FeaturedAdController::class, 'restartCheckout'])->name('campaigns.checkout');
        Route::post('campaigns/{campaign}/capture', [FeaturedAdController::class, 'capture'])->name('campaigns.capture');
    });
