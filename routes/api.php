<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('v1.')->middleware('throttle:60,1')->group(base_path('routes/api/v1.php'));
