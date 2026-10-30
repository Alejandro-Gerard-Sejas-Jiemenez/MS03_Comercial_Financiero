<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Financiero\Adapters\Http\ConciliacionController;
use Src\Financiero\Adapters\Http\LiquidacionController;
use Src\Financiero\Adapters\Http\NotificacionPasarelaController;
use Src\Financiero\Adapters\Http\PagoController;
use Src\Financiero\Adapters\Http\ReembolsoController;

Route::prefix('api/v1')->group(function (): void {
    // Webhook de la pasarela: sin JWT, con secreto compartido.
    Route::post('pagos/notificaciones-pasarela', NotificacionPasarelaController::class);

    Route::middleware('sesion')->group(function (): void {
        Route::post('pagos/cotizaciones', [PagoController::class, 'cotizar'])->middleware('rol:CLIENTE,ADMIN');
        Route::get('pagos', [PagoController::class, 'listar'])->middleware('rol:CLIENTE,ANFITRION,ADMIN');
        Route::post('pagos', [PagoController::class, 'crear'])->middleware(['rol:CLIENTE,ADMIN', 'idempotente:exigir']);

        Route::middleware('rol:CLIENTE,ANFITRION,ADMIN')->group(function (): void {
            Route::get('pagos/{pagoId}', [PagoController::class, 'mostrar']);

        });
        Route::middleware('rol:CLIENTE,ADMIN')->group(function (): void {
            Route::post('pagos/{pagoId}/cobro', [PagoController::class, 'cobrar'])->middleware('idempotente:exigir');
            Route::post('pagos/{pagoId}/cancelacion', [PagoController::class, 'cancelar']);
            Route::post('pagos/{pagoId}/comprobante', [PagoController::class, 'emitirComprobante']);
            Route::post('reembolsos', [ReembolsoController::class, 'solicitar']);
        });

        Route::middleware('rol:ANFITRION,ADMIN')->group(function (): void {
            Route::get('anfitriones/{anfitrionId}/liquidaciones', [LiquidacionController::class, 'historial']);
            Route::get('anfitriones/{anfitrionId}/saldo', [LiquidacionController::class, 'saldo']);
            Route::get('anfitriones/{anfitrionId}/comisiones-por-liquidar', [LiquidacionController::class, 'porLiquidar']);
            Route::get('liquidaciones/{liquidacionId}', [LiquidacionController::class, 'detalle']);
        });

        Route::middleware('rol:ADMIN')->group(function (): void {
            Route::get('liquidaciones', [LiquidacionController::class, 'listar']);
            Route::post('liquidaciones/{liquidacionId}/aprobacion', [LiquidacionController::class, 'aprobar']);
            Route::post('liquidaciones/{liquidacionId}/rechazo', [LiquidacionController::class, 'rechazar']);
            Route::post('liquidaciones/{liquidacionId}/pago', [LiquidacionController::class, 'pagar']);
            Route::get('reportes/ingresos', [LiquidacionController::class, 'reporteIngresos']);
            Route::get('reportes/ingresos-por-categoria', [LiquidacionController::class, 'reportePorCategoria']);
            Route::post('reembolsos/{reembolsoId}/revision', [ReembolsoController::class, 'revisar']);
            Route::post('reembolsos/{reembolsoId}/procesamiento', [ReembolsoController::class, 'procesar'])
                ->middleware('idempotente:exigir');
            Route::post('conciliaciones', [ConciliacionController::class, 'ejecutar']);
            Route::post('conciliaciones/{conciliacionId}/revisiones', [ConciliacionController::class, 'revisar']);
        });
    });
});
