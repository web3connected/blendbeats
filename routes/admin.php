<?php

use App\Http\Controllers\Admin\AdminCenter\AdminPermissionController;
use App\Http\Controllers\Admin\AdminCenter\FeaturedSlotController;
use App\Http\Controllers\Admin\AdminCenter\AdminRoleController;
use App\Http\Controllers\Admin\AdminCenter\LoungePlaylistController;
use App\Http\Controllers\Admin\AdminCenter\PaymentProviderController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ResourcePlaceholderController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest:admin')->group(function (): void {
    Route::get('login', [AuthController::class, 'create'])->name('login');
    Route::post('login', [AuthController::class, 'store'])->name('login.store');
});

Route::middleware('admin.auth')->group(function (): void {
    Route::get('/', DashboardController::class)->name('dashboard');
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

    Route::resource('users', UserController::class);

    Route::prefix('resources')->name('resources.')->group(function (): void {
        Route::get('{resource}', ResourcePlaceholderController::class)
            ->whereIn('resource', ['users', 'admin-users', 'roles', 'permissions', 'settings', 'content', 'reports'])
            ->name('show');
    });
});
