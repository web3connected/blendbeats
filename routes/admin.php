<?php

use App\Http\Controllers\Admin\AdminCenter\AdminPermissionController;
use App\Http\Controllers\Admin\AdminCenter\AffiliateManagementController;
use App\Http\Controllers\Admin\AdminCenter\DocumentationManagementController;
use App\Http\Controllers\Admin\AdminCenter\FeaturedSlotController;
use App\Http\Controllers\Admin\AdminCenter\AdminRoleController;
use App\Http\Controllers\Admin\AdminCenter\LoungePlaylistController;
use App\Http\Controllers\Admin\AdminCenter\PaymentProviderController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\BattleDashboardController;
use App\Http\Controllers\Admin\BetaTokenController;
use App\Http\Controllers\Admin\BlendNews\PostController as BlendNewsPostController;
use App\Http\Controllers\Admin\CommerceCartController;
use App\Http\Controllers\Admin\CommerceProductController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DjBookingAdminController;
use App\Http\Controllers\Admin\ResourcePlaceholderController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest:admin')->group(function (): void {
    Route::get('login', [AuthController::class, 'create'])->name('login');
    Route::post('login', [AuthController::class, 'store'])->name('login.store');
    Route::get('password/forgot', [AuthController::class, 'forgotPassword'])->name('password.request');
    Route::post('password/email', [AuthController::class, 'sendPasswordResetLink'])->name('password.email');
    Route::get('password/reset/{token}', [AuthController::class, 'resetPassword'])->name('password.reset');
    Route::post('password/reset', [AuthController::class, 'updatePassword'])->name('password.update');
});

Route::middleware('admin.auth')->group(function (): void {
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::get('battle-admin/dashboard', BattleDashboardController::class)->name('battle-admin.dashboard');
    Route::get('account', ResourcePlaceholderController::class)
        ->defaults('resource', 'account')
        ->name('account');
    Route::post('logout', [AuthController::class, 'destroy'])->name('logout');

    Route::prefix('admin-center')->name('admin-center.')->group(function (): void {
        Route::get('permissions', fn () => redirect()->route('admin.admincenter.adminpermissions.index'))
            ->name('permissions');

        Route::get('{resource}', ResourcePlaceholderController::class)
            ->whereIn('resource', ['admin-users', 'roles'])
            ->name('show');
    });

    Route::get('admincenter/adminusers', [AdminUserController::class, 'index'])
        ->middleware('permission:adminusers.view,admin')
        ->name('admincenter.adminusers.index');
    Route::get('admincenter/adminusers/create', [AdminUserController::class, 'create'])
        ->middleware('permission:adminusers.create,admin')
        ->name('admincenter.adminusers.create');
    Route::post('admincenter/adminusers', [AdminUserController::class, 'store'])
        ->middleware('permission:adminusers.create,admin')
        ->name('admincenter.adminusers.store');
    Route::get('admincenter/adminusers/{adminuser}', [AdminUserController::class, 'show'])
        ->middleware('permission:adminusers.view,admin')
        ->name('admincenter.adminusers.show');
    Route::get('admincenter/adminusers/{adminuser}/edit', [AdminUserController::class, 'edit'])
        ->middleware('permission:adminusers.update,admin')
        ->name('admincenter.adminusers.edit');
    Route::match(['put', 'patch'], 'admincenter/adminusers/{adminuser}', [AdminUserController::class, 'update'])
        ->middleware('permission:adminusers.update,admin')
        ->name('admincenter.adminusers.update');
    Route::delete('admincenter/adminusers/{adminuser}', [AdminUserController::class, 'destroy'])
        ->middleware('permission:adminusers.delete,admin')
        ->name('admincenter.adminusers.destroy');

    Route::resource('admincenter/adminroles', AdminRoleController::class)
        ->parameters(['adminroles' => 'adminrole'])
        ->names('admincenter.adminroles');

    Route::get('admincenter/adminpermissions', [AdminPermissionController::class, 'index'])
        ->name('admincenter.adminpermissions.index');

    Route::get('admincenter/featuredslots', [FeaturedSlotController::class, 'index'])
        ->middleware('permission:featuredslots.view,admin')
        ->name('admincenter.featuredslots.index');
    Route::put('admincenter/featuredslots/{slot}', [FeaturedSlotController::class, 'update'])
        ->middleware('permission:featuredslots.update,admin')
        ->name('admincenter.featuredslots.update');
    Route::post('admincenter/featuredslots/options', [FeaturedSlotController::class, 'storeCampaignOption'])
        ->middleware('permission:featuredslots.update,admin')
        ->name('admincenter.featuredslots.options.store');
    Route::put('admincenter/featuredslots/options/{option}', [FeaturedSlotController::class, 'updateCampaignOption'])
        ->middleware('permission:featuredslots.update,admin')
        ->name('admincenter.featuredslots.options.update');
    Route::delete('admincenter/featuredslots/options/{option}', [FeaturedSlotController::class, 'destroyCampaignOption'])
        ->middleware('permission:featuredslots.update,admin')
        ->name('admincenter.featuredslots.options.destroy');

    Route::get('admincenter/loungeplaylist', [LoungePlaylistController::class, 'index'])
        ->name('admincenter.loungeplaylist.index');
    Route::post('admincenter/loungeplaylist', [LoungePlaylistController::class, 'store'])
        ->name('admincenter.loungeplaylist.store');
    Route::put('admincenter/loungeplaylist/{track}', [LoungePlaylistController::class, 'update'])
        ->name('admincenter.loungeplaylist.update');
    Route::delete('admincenter/loungeplaylist/{track}', [LoungePlaylistController::class, 'destroy'])
        ->name('admincenter.loungeplaylist.destroy');

    Route::get('admincenter/paymentproviders', [PaymentProviderController::class, 'index'])
        ->middleware('permission:paymentproviders.view,admin')
        ->name('admincenter.paymentproviders.index');
    Route::put('admincenter/paymentproviders/status', [PaymentProviderController::class, 'updateStatus'])
        ->middleware('permission:paymentproviders.update,admin')
        ->name('admincenter.paymentproviders.status.update');
    Route::put('admincenter/paymentproviders/{provider}', [PaymentProviderController::class, 'update'])
        ->middleware('permission:paymentproviders.update,admin')
        ->name('admincenter.paymentproviders.update');
    Route::get('admin_center/paymentproviders', fn () => redirect()->route('admin.admincenter.paymentproviders.index'));

    Route::get('admincenter/documentation', DocumentationManagementController::class)
        ->middleware('permission:documentation.view,admin')
        ->name('admincenter.documentation.index');
    Route::get('admincenter/djbookings', [DjBookingAdminController::class, 'index'])
        ->name('admincenter.dj-bookings.index');

    Route::get('admincenter/affiliates', [AffiliateManagementController::class, 'affiliates'])
        ->middleware('permission:affiliates.view,admin')
        ->name('admincenter.affiliates.index');
    Route::get('admincenter/affiliatesettings', [AffiliateManagementController::class, 'settings'])
        ->middleware('permission:affiliates.view,admin')
        ->name('admincenter.affiliates.settings');
    Route::get('admincenter/affiliateanalytics', [AffiliateManagementController::class, 'analytics'])
        ->middleware('permission:affiliates.view,admin')
        ->name('admincenter.affiliates.analytics');
    Route::get('admincenter/affiliatecampaigns', [AffiliateManagementController::class, 'campaigns'])
        ->middleware('permission:affiliates.view,admin')
        ->name('admincenter.affiliatecampaigns.index');
    Route::post('admincenter/affiliatecampaigns', [AffiliateManagementController::class, 'storeCampaign'])
        ->middleware('permission:affiliates.update,admin')
        ->name('admincenter.affiliatecampaigns.store');
    Route::patch('admincenter/affiliatecampaigns/{campaign}', [AffiliateManagementController::class, 'updateCampaign'])
        ->middleware('permission:affiliates.update,admin')
        ->name('admincenter.affiliatecampaigns.update');
    Route::patch('admincenter/affiliatecodes/{code}/campaign', [AffiliateManagementController::class, 'updateReferralCodeCampaign'])
        ->middleware('permission:affiliates.update,admin')
        ->name('admincenter.affiliatecodes.campaign.update');
    Route::patch('admincenter/affiliates/{affiliate}/status', [AffiliateManagementController::class, 'updateAffiliateStatus'])
        ->middleware('permission:affiliates.update,admin')
        ->name('admincenter.affiliates.status.update');
    Route::get('admincenter/affiliatereferrals', [AffiliateManagementController::class, 'referrals'])
        ->middleware('permission:affiliatereferrals.view,admin')
        ->name('admincenter.affiliatereferrals.index');
    Route::patch('admincenter/affiliatereferrals/{referral}/status', [AffiliateManagementController::class, 'updateReferralStatus'])
        ->middleware('permission:affiliatereferrals.update,admin')
        ->name('admincenter.affiliatereferrals.status.update');
    Route::get('admincenter/affiliaterewards', [AffiliateManagementController::class, 'rewards'])
        ->middleware('permission:affiliaterewards.view,admin')
        ->name('admincenter.affiliaterewards.index');
    Route::patch('admincenter/affiliaterewards/{reward}/status', [AffiliateManagementController::class, 'updateRewardStatus'])
        ->middleware('permission:affiliaterewards.update,admin')
        ->name('admincenter.affiliaterewards.status.update');
    Route::get('admincenter/affiliatepayouts', [AffiliateManagementController::class, 'payouts'])
        ->middleware('permission:affiliatepayouts.view,admin')
        ->name('admincenter.affiliatepayouts.index');
    Route::patch('admincenter/affiliatepayouts/{payout}/status', [AffiliateManagementController::class, 'updatePayoutStatus'])
        ->middleware('permission:affiliatepayouts.update,admin')
        ->name('admincenter.affiliatepayouts.status.update');

    Route::resource('users', UserController::class);
    Route::get('admincenter/beta-tokens', [BetaTokenController::class, 'index'])
        ->name('admincenter.beta-tokens.index');
    Route::get('admincenter/beta-tokens/{user}', [BetaTokenController::class, 'show'])
        ->name('admincenter.beta-tokens.show');
    Route::post('admincenter/beta-tokens/{user}/grant', [BetaTokenController::class, 'grant'])
        ->name('admincenter.beta-tokens.grant');
    Route::post('admincenter/beta-tokens/{user}/remove', [BetaTokenController::class, 'remove'])
        ->name('admincenter.beta-tokens.remove');
    Route::post('admincenter/beta-tokens/{user}/reset', [BetaTokenController::class, 'reset'])
        ->name('admincenter.beta-tokens.reset');
    Route::post('admincenter/beta-tokens/{user}/status', [BetaTokenController::class, 'status'])
        ->name('admincenter.beta-tokens.status');
    Route::resource('blendnews', BlendNewsPostController::class)
        ->parameters(['blendnews' => 'blendnews'])
        ->except(['show']);
    Route::resource('products', CommerceProductController::class)->except(['show']);
    Route::get('carts', [CommerceCartController::class, 'index'])->name('carts.index');

    Route::prefix('resources')->name('resources.')->group(function (): void {
        Route::get('{resource}', ResourcePlaceholderController::class)
            ->whereIn('resource', ['users', 'admin-users', 'roles', 'permissions', 'settings', 'content', 'reports', 'blendnews'])
            ->name('show');
    });
});
