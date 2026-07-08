<?php

use Illuminate\Support\Facades\Route;

Route::view('/live', 'welcome')->name('live.index');
Route::view('/live/{username}', 'welcome')
    ->where('username', '[A-Za-z0-9_-]+')
    ->name('live.channel');
Route::view('/dashboard/live', 'welcome')->name('dashboard.live');
