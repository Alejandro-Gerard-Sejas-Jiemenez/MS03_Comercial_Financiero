<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Acuerdos\Presentation\Http\AcuerdoController;
use Src\Acuerdos\Presentation\Http\MetodoCobroController;

Route::prefix('api/v1')->middleware('sesion')->group(function (): void {
    Route::middleware('rol:ANFITRION,ADMIN')->group(function (): void {
        Route::get('acuerdos', [AcuerdoController::class, 'listar']);
        Route::get('acuerdos/{acuerdoId}', [AcuerdoController::class, 'mostrar']);
        Route::get('anfitriones/{anfitrionId}/acuerdos', [AcuerdoController::class, 'historial']);

        Route::get('anfitriones/{anfitrionId}/metodos-cobro', [MetodoCobroController::class, 'listar']);
        Route::post('anfitriones/{anfitrionId}/metodos-cobro', [MetodoCobroController::class, 'registrar']);
        Route::get('metodos-cobro/{metodoId}', [MetodoCobroController::class, 'mostrar']);
        Route::patch('metodos-cobro/{metodoId}', [MetodoCobroController::class, 'actualizar']);
        Route::post('metodos-cobro/{metodoId}/principal', [MetodoCobroController::class, 'definirPrincipal']);
    });

    Route::middleware('rol:ADMIN')->group(function (): void {
        Route::post('acuerdos', [AcuerdoController::class, 'crear']);
        Route::patch('acuerdos/{acuerdoId}', [AcuerdoController::class, 'actualizar']);
        Route::post('acuerdos/{acuerdoId}/estado', [AcuerdoController::class, 'cambiarEstado']);
        Route::post('metodos-cobro/{metodoId}/verificacion', [MetodoCobroController::class, 'verificar']);
    });
});
