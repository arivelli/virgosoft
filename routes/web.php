<?php

use Illuminate\Support\Facades\Route;

// Serve the SPA for all frontend routes
Route::get('/{any?}', function () {
    return view('welcome');
})->where('any', '.*');
