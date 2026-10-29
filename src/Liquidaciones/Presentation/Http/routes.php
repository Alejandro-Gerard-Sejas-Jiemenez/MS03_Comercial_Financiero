<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Liquidaciones\Presentation\Http\LiquidacionController;

Route::prefix('api/v1')->middleware('sesion')->group(function (): void {
    Route::middleware('rol:ANFITRION,ADMIN')->group(function (): void {
        Route::get('anfitriones/{anfitrionId}/liquidaciones', [LiquidacionController::class, 'historial']);
        Route::get('anfitriones/{anfitrionId}/saldo', [LiquidacionController::class, 'saldo']);
        Route::get('anfitriones/{anfitrionId}/comisiones-por-liquidar', [LiquidacionController::class, 'porLiquidar']);
        Route::get('liquidaciones/{liquidacionId}', [LiquidacionController::class, 'detalle']);
    });

    Route::middleware('rol:ADMIN')->group(function (): void {
        Route::post('liquidaciones/{liquidacionId}/aprobacion', [LiquidacionController::class, 'aprobar']);
        Route::post('liquidaciones/{liquidacionId}/rechazo', [LiquidacionController::class, 'rechazar']);
        Route::post('liquidaciones/{liquidacionId}/pago', [LiquidacionController::class, 'pagar']);
        Route::get('reportes/ingresos', [LiquidacionController::class, 'reporteIngresos']);
        Route::get('reportes/ingresos-por-categoria', [LiquidacionController::class, 'reportePorCategoria']);
    });
});
