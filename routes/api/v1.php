<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\BlogController;
use Illuminate\Support\Facades\Route;

Route::apiResource('blogs', BlogController::class)->only([
    'index',
    'store',
    'show',
    'update',
    'destroy',
]);

Route::get('blogs/{blog}/logs', [BlogController::class, 'logs'])->name('blogs.logs');
