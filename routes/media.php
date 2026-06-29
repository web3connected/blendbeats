<?php

use App\Http\Controllers\Api\MediaSetupController;
use App\Http\Controllers\Api\MediaManagerController;
use Illuminate\Support\Facades\Route;

Route::prefix('media')
    ->middleware('public.auth')
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
