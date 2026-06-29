<?php

use App\Http\Controllers\Api\Automation\NewsAutomationController;
use Illuminate\Support\Facades\Route;

Route::prefix('automation/news')
    ->middleware('automation.token')
    ->name('api.automation.news.')
    ->group(function (): void {
        Route::get('rules', [NewsAutomationController::class, 'rules'])->name('rules');
        Route::get('events', [NewsAutomationController::class, 'events'])->name('events');
        Route::get('milestones', [NewsAutomationController::class, 'milestones'])->name('milestones');
        Route::post('drafts', [NewsAutomationController::class, 'drafts'])->name('drafts');
        Route::post('rss-drafts', [NewsAutomationController::class, 'rssDrafts'])->name('rss-drafts');
        Route::post('logs', [NewsAutomationController::class, 'logs'])->name('logs');
        Route::post('notifications', [NewsAutomationController::class, 'notifications'])->name('notifications');
    });
