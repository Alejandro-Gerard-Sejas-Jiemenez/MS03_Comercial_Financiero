<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Pagos\Presentation\Http\ConciliacionController;
use Src\Pagos\Presentation\Http\NotificacionPasarelaController;
use Src\Pagos\Presentation\Http\PagoController;
use Src\Pagos\Presentation\Http\ReembolsoController;

Route::prefix('api/v1')->group(function (): void {
    // Webhook de la pasarela: sin JWT, con secreto compartido.
    Route::post('pagos/notificaciones-pasarela', NotificacionPasarelaController::class);

    Route::middleware('sesion')->group(function (): void {
        Route::post('pagos/cotizaciones', [PagoController::class, 'cotizar'])->middleware('rol:CLIENTE,ADMIN');
        Route::get('pagos', [PagoController::class, 'listar'])->middleware('rol:CLIENTE,ANFITRION,ADMIN');
        Route::post('pagos', [PagoController::class, 'crear'])->middleware(['rol:CLIENTE,ADMIN', 'idempotente:exigir']);

        Route::middleware('rol:CLIENTE,ANFITRION,ADMIN')->group(function (): void {
            Route::get('pagos/{pagoId}', [PagoController::class, 'mostrar']);
            Route::get('pagos/{pagoId}/transacciones', [PagoController::class, 'transacciones']);
            Route::get('pagos/{pagoId}/reembolsos', [PagoController::class, 'reembolsos']);
            Route::get('pagos/{pagoId}/comprobante', [PagoController::class, 'comprobante']);
        });
        Route::middleware('rol:CLIENTE,ADMIN')->group(function (): void {
            Route::post('pagos/{pagoId}/cobro', [PagoController::class, 'cobrar'])->middleware('idempotente:exigir');
            Route::post('pagos/{pagoId}/cancelacion', [PagoController::class, 'cancelar']);
            Route::post('pagos/{pagoId}/comprobante', [PagoController::class, 'emitirComprobante']);
            Route::post('reembolsos', [ReembolsoController::class, 'solicitar']);
        });

        Route::middleware('rol:ADMIN')->group(function (): void {
            Route::post('reembolsos/{reembolsoId}/revision', [ReembolsoController::class, 'revisar']);
            Route::post('reembolsos/{reembolsoId}/procesamiento', [ReembolsoController::class, 'procesar'])
                ->middleware('idempotente:exigir');
            Route::post('conciliaciones', [ConciliacionController::class, 'ejecutar']);
            Route::post('conciliaciones/{conciliacionId}/revisiones', [ConciliacionController::class, 'revisar']);
        });
    });
});
