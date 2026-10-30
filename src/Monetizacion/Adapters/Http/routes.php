<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Monetizacion\Adapters\Http\MonetizacionController;

Route::prefix('api/v1')->middleware('sesion')->group(function (): void {
    Route::post('desglose-comercial', [MonetizacionController::class, 'calcularDesglose'])
        ->middleware('rol:CLIENTE,ANFITRION,ADMIN');

    Route::middleware('rol:ADMIN')->group(function (): void {
        Route::get('reglas-monetizacion', [MonetizacionController::class, 'listar']);
        Route::get('reglas-monetizacion/vigentes', [MonetizacionController::class, 'vigentes']);
        Route::post('reglas-monetizacion', [MonetizacionController::class, 'crear']);
        Route::get('reglas-monetizacion/{reglaId}', [MonetizacionController::class, 'mostrar']);
        Route::put('reglas-monetizacion/{reglaId}', [MonetizacionController::class, 'actualizar']);
    });
});
