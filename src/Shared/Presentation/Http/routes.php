<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Shared\Presentation\Http\DocumentacionController;
use Src\Shared\Presentation\Http\SaludController;

Route::prefix('api/v1')->group(function (): void {
    Route::get('salud', SaludController::class);
});

// Documentacion de la API (Swagger UI). No es parte del contrato /api/v1.
Route::get('docs', [DocumentacionController::class, 'pagina']);
Route::get('docs/openapi.yaml', [DocumentacionController::class, 'contrato']);
