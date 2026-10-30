<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Shared\Adapters\Http\SaludController;

Route::prefix('api/v1')->group(function (): void {
    Route::get('salud', SaludController::class);
});
