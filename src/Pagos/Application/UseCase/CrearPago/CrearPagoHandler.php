<?php

declare(strict_types=1);

namespace Src\Pagos\Application\UseCase\CrearPago;

use DomainException;
use Src\Pagos\Domain\Pago;
use Src\Pagos\Domain\PagoRepository;
use Src\Shared\Application\Port\TransactionManager;
use Src\Shared\Application\Port\UuidGenerator;
use Src\Shared\Domain\Money;

/**
 * CU-PAG-02 CrearPago (NEG, REST con cabecera `Idempotency-Key`).
 *
 * Crea el pago en estado PENDIENTE. Es el paso de la saga que MS-02 puede
 * reintentar, asi que es idempotente por la `Idempotency-Key`: si la clave ya
 * existe devuelve el pago que ya se creo, en lugar de crear otro. La columna
 * `idempotency_key` es UNIQUE, asi que la base es la ultima linea de defensa
 * si dos reintentos llegan a la vez.
 */
final class CrearPagoHandler
{
    public function __construct(
        private readonly PagoRepository $pagos,
        private readonly UuidGenerator $uuid,
        private readonly TransactionManager $tx,
    ) {
    }

    public function __invoke(CrearPagoCommand $comando): Pago
    {
        $yaExiste = $this->pagos->buscarPorIdempotencyKey($comando->idempotencyKey);

        if ($yaExiste !== null) {
            return $yaExiste;
        }

        // Una reserva no puede tener dos pagos vivos a la vez; lo garantiza
        // tambien el indice unico parcial `uq_pago_vivo_por_reserva`.
        $vivo = $this->pagos->buscarVivoPorReserva($comando->reservaId);

        if ($vivo !== null) {
            throw new DomainException(
                "La reserva {$comando->reservaId} ya tiene un pago vivo ({$vivo->estado()->value})",
            );
        }

        $pago = Pago::crear(
            pagoId: $this->uuid->generar(),
            reservaId: $comando->reservaId,
            clienteId: $comando->clienteId,
            anfitrionId: $comando->anfitrionId,
            subtotal: Money::deCentavos($comando->subtotalCentavos, $comando->moneda),
            descuento: Money::deCentavos($comando->descuentoCentavos, $comando->moneda),
            cargoServicio: Money::deCentavos($comando->cargoServicioCentavos, $comando->moneda),
            metodo: $comando->metodo,
            idempotencyKey: $comando->idempotencyKey,
            sagaId: $comando->sagaId,
            promocionId: $comando->promocionId,
            pasarela: $comando->pasarela,
        );

        return $this->tx->ejecutar(function () use ($pago): Pago {
            $this->pagos->guardar($pago);

            return $pago;
        });
    }
}
