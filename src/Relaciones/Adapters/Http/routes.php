<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Relaciones\Adapters\Http\AcuerdoController;
use Src\Relaciones\Adapters\Http\MetodoCobroController;

Route::prefix('api/v1')->middleware('sesion')->group(function (): void {
    Route::middleware('rol:ANFITRION,ADMIN')->group(function (): void {
        Route::get('acuerdos', [AcuerdoController::class, 'listar']);
        Route::get('acuerdos/{acuerdoId}', [AcuerdoController::class, 'mostrar']);

        Route::get('anfitriones/{anfitrionId}/metodos-cobro', [MetodoCobroController::class, 'listar']);
        Route::post('anfitriones/{anfitrionId}/metodos-cobro', [MetodoCobroController::class, 'registrar']);
        Route::get('metodos-cobro/{metodoId}', [MetodoCobroController::class, 'mostrar']);
        Route::put('metodos-cobro/{metodoId}', [MetodoCobroController::class, 'actualizar']);
    });

    Route::middleware('rol:ADMIN')->group(function (): void {
        Route::post('acuerdos', [AcuerdoController::class, 'crear']);
        Route::put('acuerdos/{acuerdoId}', [AcuerdoController::class, 'actualizar']);
        Route::post('metodos-cobro/{metodoId}/verificacion', [MetodoCobroController::class, 'verificar']);
    });
});
