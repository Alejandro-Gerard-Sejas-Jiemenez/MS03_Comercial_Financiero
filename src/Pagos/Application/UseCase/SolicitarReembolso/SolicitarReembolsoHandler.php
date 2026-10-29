<?php

declare(strict_types=1);

namespace Src\Pagos\Application\UseCase\SolicitarReembolso;

use Src\Pagos\Domain\PagoRepository;
use Src\Pagos\Domain\PoliticaDeReembolso;
use Src\Pagos\Domain\Reembolso;
use Src\Pagos\Domain\ReembolsoRepository;
use Src\Referencias\Domain\ReservaReferenceRepository;
use Src\Shared\Application\Port\Clock;
use Src\Shared\Application\Port\TransactionManager;
use Src\Shared\Application\Port\UuidGenerator;
use Src\Shared\Domain\Exception\ConflictoDeEstado;
use Src\Shared\Domain\Exception\RecursoNoEncontrado;

/**
 * CU-PAG-07 SolicitarReembolso (NEG, EVT `reserva.cancelada`).
 *
 * Lo dispara MS-02 al cancelar una reserva. Crea el reembolso en SOLICITADO con
 * el monto que manda la politica de cancelacion; no mueve dinero todavia.
 *
 * Si la politica dice que no corresponde devolver nada, no se crea ningun
 * reembolso: queda registrado en el resultado y nadie tiene que aprobar un
 * reembolso de cero.
 *
 * Las fechas vienen de la copia local `ref_reserva`, no de una llamada a MS-02:
 * asi esto funciona aunque MS-02 este caido.
 */
final class SolicitarReembolsoHandler
{
    public function __construct(
        private readonly PagoRepository $pagos,
        private readonly ReembolsoRepository $reembolsos,
        private readonly ReservaReferenceRepository $reservas,
        private readonly PoliticaDeReembolso $politica,
        private readonly UuidGenerator $uuid,
        private readonly Clock $clock,
        private readonly TransactionManager $tx,
    ) {}

    public function __invoke(SolicitarReembolsoCommand $comando): ?Reembolso
    {
        $pago = $this->pagos->buscarVivoPorReserva($comando->reservaId)
            ?? throw new RecursoNoEncontrado(
                "La reserva {$comando->reservaId} no tiene un pago vivo que reembolsar",
            );

        if (! $pago->admiteReembolso()) {
            throw new ConflictoDeEstado(
                "El pago {$pago->pagoId()} esta en estado {$pago->estado()->value} y no admite reembolso",
            );
        }

        $reserva = $this->reservas->buscar($comando->reservaId)
            ?? throw new RecursoNoEncontrado(
                "No hay copia local de la reserva {$comando->reservaId}; falta el evento de MS-02",
            );

        $yaReembolsado = $this->pagos->totalReembolsado($pago->pagoId());
        $saldo = $pago->total()->menos($yaReembolsado);

        $monto = $comando->montoForzado ?? $this->politica->montoADevolver(
            $pago->total(),
            $comando->fechaCancelacion ?? $this->clock->hoy(),
            $reserva->fechaCheckin,
            $comando->motivo,
        );

        // Nunca se devuelve mas de lo que queda por devolver.
        if ($monto->esMayorQue($saldo)) {
            $monto = $saldo;
        }

        if (! $monto->esPositivo()) {
            return null;
        }

        $reembolso = Reembolso::solicitar(
            reembolsoId: $this->uuid->generar(),
            pagoId: $pago->pagoId(),
            monto: $monto,
            motivo: $comando->motivo,
            detalle: $comando->detalle,
            solicitadoPor: $comando->solicitadoPor,
        );

        return $this->tx->ejecutar(function () use ($reembolso): Reembolso {
            $this->reembolsos->guardar($reembolso);

            return $reembolso;
        });
    }
}
