<?php

use Cloudcake\Components\Http\Controllers\IconController;
use Cloudcake\Components\Http\Controllers\IconSvgController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web'])->group(function () {
    Route::get('/selecticons/icons', IconController::class)
        ->name('cloudcake.icons');

    Route::get('/selecticons/icon/{icon}', IconSvgController::class)
        ->name('cloudcake.icon')
        ->where('icon', '.*');
});
