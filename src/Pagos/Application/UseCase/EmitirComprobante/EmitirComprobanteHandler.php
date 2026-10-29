<?php

declare(strict_types=1);

namespace Src\Pagos\Application\UseCase\EmitirComprobante;

use Src\Pagos\Application\Port\NumeradorComprobantes;
use Src\Pagos\Domain\Comprobante;
use Src\Pagos\Domain\ComprobanteRepository;
use Src\Pagos\Domain\PagoRepository;
use Src\Shared\Application\Port\TransactionManager;
use Src\Shared\Application\Port\UuidGenerator;
use Src\Shared\Domain\Exception\ConflictoDeEstado;
use Src\Shared\Domain\Exception\RecursoNoEncontrado;

/**
 * CU-PAG-10 EmitirComprobante (NEG, INT por el evento `PagoCapturado`).
 *
 * Recibo o factura con numero unico. Es idempotente: si el pago ya tiene
 * comprobante se devuelve ese, porque el evento `pago.capturado` puede llegar
 * dos veces (entrega at-least-once) y la columna `numero` es UNIQUE.
 */
final class EmitirComprobanteHandler
{
    public function __construct(
        private readonly PagoRepository $pagos,
        private readonly ComprobanteRepository $comprobantes,
        private readonly NumeradorComprobantes $numerador,
        private readonly UuidGenerator $uuid,
        private readonly TransactionManager $tx,
    ) {}

    public function __invoke(EmitirComprobanteCommand $comando): Comprobante
    {
        $yaEmitido = $this->comprobantes->delPago($comando->pagoId);

        if ($yaEmitido !== null) {
            return $yaEmitido;
        }

        $pago = $this->pagos->buscar($comando->pagoId)
            ?? throw new RecursoNoEncontrado("El pago {$comando->pagoId} no existe");

        // Solo se factura lo que se cobro.
        if (! $pago->estaCapturado()) {
            throw new ConflictoDeEstado(
                "No se emite comprobante de un pago en estado {$pago->estado()->value}",
            );
        }

        $comprobante = Comprobante::emitir(
            comprobanteId: $this->uuid->generar(),
            pagoId: $pago->pagoId(),
            tipo: $comando->tipo,
            numero: $this->numerador->siguiente($comando->tipo),
            monto: $pago->total(),
            nitCliente: $comando->nitCliente,
            razonSocial: $comando->razonSocial,
        );

        return $this->tx->ejecutar(function () use ($comprobante): Comprobante {
            $this->comprobantes->guardar($comprobante);

            return $comprobante;
        });
    }
}
