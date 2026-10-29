<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Promociones\Presentation\Http\PromocionController;

Route::prefix('api/v1')->middleware('sesion')->group(function (): void {
    Route::post('promociones/descuento', [PromocionController::class, 'descuento'])
        ->middleware('rol:CLIENTE,ANFITRION,ADMIN');

    Route::middleware('rol:ANFITRION,ADMIN')->group(function (): void {
        Route::get('promociones', [PromocionController::class, 'listar']);
        Route::post('promociones', [PromocionController::class, 'crear']);
        Route::get('promociones/{promocionId}', [PromocionController::class, 'mostrar']);
        Route::patch('promociones/{promocionId}', [PromocionController::class, 'actualizar']);
        Route::put('promociones/{promocionId}/alojamientos', [PromocionController::class, 'asignarAlojamientos']);
        Route::post('promociones/{promocionId}/activacion', [PromocionController::class, 'activar']);
        Route::post('promociones/{promocionId}/pausa', [PromocionController::class, 'pausar']);
        Route::post('promociones/{promocionId}/cancelacion', [PromocionController::class, 'cancelar']);
    });
});
