<?php

declare(strict_types=1);

namespace Src\Pagos\Application\UseCase\CancelarPagoPendiente;

use DomainException;
use Src\Pagos\Domain\Pago;
use Src\Pagos\Domain\PagoRepository;
use Src\Shared\Application\Port\EventPublisher;
use Src\Shared\Application\Port\TransactionManager;

/**
 * CU-PAG-04 CancelarPagoPendiente (NEG, REST / JOB).
 *
 * Dos usos: compensacion de la saga cuando falla otro paso, y expiracion de un
 * pago que quedo PENDIENTE demasiado tiempo (el JOB recorre
 * `PagoRepository::pendientesAnterioresA()`).
 *
 * Libera la reserva para el indice `uq_pago_vivo_por_reserva`, asi que despues
 * se puede crear otro pago para la misma reserva.
 */
final class CancelarPagoPendienteHandler
{
    public function __construct(
        private readonly PagoRepository $pagos,
        private readonly EventPublisher $eventos,
        private readonly TransactionManager $tx,
    ) {
    }

    public function __invoke(CancelarPagoPendienteCommand $comando): Pago
    {
        $pago = $this->pagos->buscar($comando->pagoId)
            ?? throw new DomainException("El pago {$comando->pagoId} no existe");

        return $this->tx->ejecutar(function () use ($pago, $comando): Pago {
            $pago->cancelar($comando->motivo);
            $this->pagos->guardar($pago);
            $this->eventos->publicar($pago->sacarEventos());

            return $pago;
        });
    }
}
