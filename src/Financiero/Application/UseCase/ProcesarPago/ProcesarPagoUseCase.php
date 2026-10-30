<?php

declare(strict_types=1);

namespace Src\Financiero\Application\UseCase\ProcesarPago;

use Src\Financiero\Application\Response\ComprobanteResponse;
use Src\Financiero\Application\Response\CotizacionResponse;
use Src\Financiero\Application\Response\PagoConTotalResponse;

/**
 * Entrada del caso de uso ProcesarPago (Clean Architecture: Input Boundary).
 * Los adaptadores (controladores, consolas, suscriptores) dependen de esta interfaz, no del servicio.
 */
interface ProcesarPagoUseCase
{
    public function cotizar(CotizarReservaRequest $comando): CotizacionResponse;

    public function crear(CrearPagoRequest $comando): PagoConTotalResponse;

    public function cobrar(CobrarPagoRequest $comando): PagoConTotalResponse;

    public function cancelarPendiente(CancelarPagoPendienteRequest $comando): PagoConTotalResponse;

    public function recibirNotificacionPasarela(RecibirNotificacionPasarelaRequest $comando): ResultadoNotificacion;

    public function emitirComprobante(EmitirComprobanteRequest $comando): ComprobanteResponse;
}
