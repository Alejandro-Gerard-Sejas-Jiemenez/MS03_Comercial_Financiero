<?php

declare(strict_types=1);

namespace Src\Financiero\Adapters\Presenter;

use Src\Financiero\Application\Response\ComisionResponse;
use Src\Financiero\Application\Response\ComprobanteResponse;
use Src\Financiero\Application\Response\ConciliacionItemResponse;
use Src\Financiero\Application\Response\ConciliacionResponse;
use Src\Financiero\Application\Response\CotizacionResponse;
use Src\Financiero\Application\Response\DetalleDeLiquidacionResponse;
use Src\Financiero\Application\Response\DetalleDePagoResponse;
use Src\Financiero\Application\Response\IngresoPorCategoriaResponse;
use Src\Financiero\Application\Response\LiquidacionResponse;
use Src\Financiero\Application\Response\PagoConTotalResponse;
use Src\Financiero\Application\Response\PagoResponse;
use Src\Financiero\Application\Response\ReembolsoResponse;
use Src\Financiero\Application\Response\ResultadoReversionResponse;
use Src\Financiero\Application\Response\ResumenDeIngresosResponse;
use Src\Financiero\Application\Response\TransaccionPagoResponse;
use Src\Financiero\Application\UseCase\ProcesarPago\ResultadoNotificacion;
use Src\Monetizacion\Adapters\Presenter\MonetizacionPresentador;
use Src\Shared\Adapters\Presenter\PresentadorBase;

/** Presentador de Financiero: convierte los Response del caso de uso al arreglo que sale por HTTP. */
final class FinancieroPresentador extends PresentadorBase
{
    private readonly MonetizacionPresentador $monetizacion;

    public function __construct()
    {
        $this->monetizacion = new MonetizacionPresentador;
    }

    protected function objeto(object $r): mixed
    {
        return match (true) {
            $r instanceof PagoResponse => $this->pago($r),
            $r instanceof ReembolsoResponse => $this->reembolso($r),
            $r instanceof ComprobanteResponse => $this->comprobante($r),
            $r instanceof TransaccionPagoResponse => $this->transaccionPago($r),
            $r instanceof ConciliacionResponse => $this->conciliacion($r),
            $r instanceof ConciliacionItemResponse => $this->conciliacionItem($r),
            $r instanceof ComisionResponse => $this->comision($r),
            $r instanceof LiquidacionResponse => $this->liquidacion($r),
            $r instanceof CotizacionResponse => $this->cotizacion($r),
            $r instanceof DetalleDePagoResponse => $this->detalleDePago($r),
            $r instanceof DetalleDeLiquidacionResponse => $this->detalleDeLiquidacion($r),
            $r instanceof ResultadoReversionResponse => $this->resultadoReversion($r),
            $r instanceof ResumenDeIngresosResponse => $this->resumenDeIngresos($r),
            $r instanceof IngresoPorCategoriaResponse => $this->ingresoPorCategoria($r),
            $r instanceof ResultadoNotificacion => $this->resultadoNotificacion($r),
            $r instanceof PagoConTotalResponse => $this->pagoConTotal($r),
            default => $this->monetizacion->presentar($r),
        };
    }

    /** @return array<string, mixed> */
    private function pago(PagoResponse $r): array
    {
        return [
            'pago_id' => $r->pagoId,
            'reserva_id' => $r->reservaId,
            'cliente_id' => $r->clienteId,
            'anfitrion_id' => $r->anfitrionId,
            'promocion_id' => $r->promocionId,
            'subtotal' => $this->monto($r->subtotal),
            'descuento' => $this->monto($r->descuento),
            'cargo_servicio' => $this->monto($r->cargoServicio),
            'metodo' => $r->metodo,
            'estado' => $r->estado,
            'idempotency_key' => $r->idempotencyKey,
            'saga_id' => $r->sagaId,
            'pasarela' => $r->pasarela,
            'referencia_pasarela' => $r->referenciaPasarela,
        ];
    }

    /** @return array<string, mixed> */
    private function reembolso(ReembolsoResponse $r): array
    {
        return [
            'reembolso_id' => $r->reembolsoId,
            'pago_id' => $r->pagoId,
            'monto' => $this->monto($r->monto),
            'motivo' => $r->motivo,
            'estado' => $r->estado,
            'detalle' => $r->detalle,
            'solicitado_por' => $r->solicitadoPor,
            'transaccion_id' => $r->transaccionId,
        ];
    }

    /** @return array<string, mixed> */
    private function comprobante(ComprobanteResponse $r): array
    {
        return [
            'comprobante_id' => $r->comprobanteId,
            'pago_id' => $r->pagoId,
            'tipo' => $r->tipo,
            'numero' => $r->numero,
            'monto' => $this->monto($r->monto),
            'nit_cliente' => $r->nitCliente,
            'razon_social' => $r->razonSocial,
            'archivo_url' => $r->archivoUrl,
            'hash_documento' => $r->hashDocumento,
        ];
    }

    /** @return array<string, mixed> */
    private function transaccionPago(TransaccionPagoResponse $r): array
    {
        return [
            'transaccion_id' => $r->transaccionId,
            'pago_id' => $r->pagoId,
            'tipo' => $r->tipo,
            'monto' => $this->monto($r->monto),
            'estado' => $r->estado,
            'referencia_pasarela' => $r->referenciaPasarela,
            'respuesta_pasarela' => $r->respuestaPasarela,
        ];
    }

    /** @return array<string, mixed> */
    private function conciliacion(ConciliacionResponse $r): array
    {
        return [
            'conciliacion_id' => $r->conciliacionId,
            'pasarela' => $r->pasarela,
            'fecha_corte' => $this->fecha($r->fechaCorte),
            'total_sistema' => $this->monto($r->totalSistema),
            'total_pasarela' => $this->monto($r->totalPasarela),
            'estado' => $r->estado,
            'ejecutada_por' => $r->ejecutadaPor,
            'items' => $this->presentar($r->items),
        ];
    }

    /** @return array<string, mixed> */
    private function conciliacionItem(ConciliacionItemResponse $r): array
    {
        return [
            'referencia_pasarela' => $r->referenciaPasarela,
            'monto_pasarela' => $this->monto($r->montoPasarela),
            'resultado' => $r->resultado,
            'transaccion_id' => $r->transaccionId,
            'observacion' => $r->observacion,
            'item_id' => $r->itemId,
        ];
    }

    /** @return array<string, mixed> */
    private function comision(ComisionResponse $r): array
    {
        return [
            'comision_id' => $r->comisionId,
            'pago_id' => $r->pagoId,
            'reserva_id' => $r->reservaId,
            'anfitrion_id' => $r->anfitrionId,
            'regla_id' => $r->reglaId,
            'acuerdo_id' => $r->acuerdoId,
            'liquidacion_id' => $r->liquidacionId,
            'base_comisionable' => $this->monto($r->baseComisionable),
            'porcentaje_aplicado' => $r->porcentajeAplicado,
            'monto_comision' => $this->monto($r->montoComision),
            'monto_neto_anfitrion' => $this->monto($r->montoNetoAnfitrion),
            'estado' => $r->estado,
            'liquidable_desde' => $this->fecha($r->liquidableDesde),
        ];
    }

    /** @return array<string, mixed> */
    private function liquidacion(LiquidacionResponse $r): array
    {
        return [
            'liquidacion_id' => $r->liquidacionId,
            'anfitrion_id' => $r->anfitrionId,
            'metodo_id' => $r->metodoId,
            'periodo_desde' => $this->fecha($r->periodoDesde),
            'periodo_hasta' => $this->fecha($r->periodoHasta),
            'total_bruto' => $this->monto($r->totalBruto),
            'total_comisiones' => $this->monto($r->totalComisiones),
            'total_reembolsos' => $this->monto($r->totalReembolsos),
            'estado' => $r->estado,
            'aprobado_por' => $r->aprobadoPor,
            'referencia_transferencia' => $r->referenciaTransferencia,
        ];
    }

    /** @return array<string, mixed> */
    private function cotizacion(CotizacionResponse $r): array
    {
        return [
            'desglose' => $this->presentar($r->desglose),
            'promocion_id' => $r->promocionId,
            'acuerdo_id' => $r->acuerdoId,
        ];
    }

    /** @return array<string, mixed> */
    private function detalleDePago(DetalleDePagoResponse $r): array
    {
        return [
            'pago' => $this->presentar($r->pago),
            'transacciones' => $this->presentar($r->transacciones),
            'reembolsos' => $this->presentar($r->reembolsos),
            'comprobante' => $this->presentar($r->comprobante),
            'total_reembolsado' => $this->monto($r->totalReembolsado),
        ];
    }

    /** @return array<string, mixed> */
    private function detalleDeLiquidacion(DetalleDeLiquidacionResponse $r): array
    {
        return [
            'liquidacion' => $this->presentar($r->liquidacion),
            'comisiones' => $this->presentar($r->comisiones),
        ];
    }

    /** @return array<string, mixed> */
    private function resultadoReversion(ResultadoReversionResponse $r): array
    {
        return [
            'comision' => $this->presentar($r->comision),
            'revertida' => $r->revertida,
            'requiere_ajuste_en_la_siguiente_liquidacion' => $r->requiereAjusteEnLaSiguienteLiquidacion,
            'detalle' => $r->detalle,
        ];
    }

    /** @return array<string, mixed> */
    private function resumenDeIngresos(ResumenDeIngresosResponse $r): array
    {
        return [
            'total_cobrado' => $this->monto($r->totalCobrado),
            'total_comisiones' => $this->monto($r->totalComisiones),
            'total_cargos_servicio' => $this->monto($r->totalCargosServicio),
            'total_reembolsos' => $this->monto($r->totalReembolsos),
            'total_neto_anfitriones' => $this->monto($r->totalNetoAnfitriones),
            'cantidad_pagos' => $r->cantidadPagos,
        ];
    }

    /** @return array<string, mixed> */
    private function ingresoPorCategoria(IngresoPorCategoriaResponse $r): array
    {
        return [
            'categoria_alojamiento' => $r->categoriaAlojamiento,
            'total_cobrado' => $this->monto($r->totalCobrado),
            'total_comisiones' => $this->monto($r->totalComisiones),
            'cantidad_pagos' => $r->cantidadPagos,
        ];
    }

    /** @return array<string, mixed> */
    private function resultadoNotificacion(ResultadoNotificacion $r): array
    {
        return [
            'referencia_pasarela' => $r->referenciaPasarela,
            'aplicada' => $r->aplicada,
            'cambio_el_estado' => $r->cambioElEstado,
        ];
    }

    /** @return array<string, mixed> */
    private function pagoConTotal(PagoConTotalResponse $r): array
    {
        return $this->pago($r->pago) + [
            'total' => $this->monto($r->total),
            'monto_anfitrion' => $this->monto($r->montoAnfitrion),
        ];
    }
}
