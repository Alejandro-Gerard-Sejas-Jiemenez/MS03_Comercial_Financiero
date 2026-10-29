<?php

declare(strict_types=1);

namespace Src\Promociones\Application\UseCase\RegistrarUsoDePromocion;

use Src\Promociones\Domain\Promocion;
use Src\Promociones\Domain\PromocionRepository;
use Src\Shared\Application\Port\Clock;
use Src\Shared\Application\Port\EventPublisher;
use Src\Shared\Application\Port\TransactionManager;
use Src\Shared\Domain\Exception\RecursoNoEncontrado;
use Src\Shared\Domain\Money;

/**
 * CU-PRO-03 RegistrarUsoDePromocion (NEG, disparador INT: `PagoCapturado`).
 *
 * Incrementa `usos_actuales` y emite `promocion.aplicada`, que consume MS-04
 * para el historico de F11. El incremento y la publicacion van en la misma
 * transaccion (patron Outbox): si el evento no se puede guardar, el uso no
 * queda contado.
 *
 * Si la promocion ya alcanzo su tope, la entidad falla con una excepcion de
 * dominio y la transaccion se revierte, en vez de pasarse del `max_usos`.
 */
final class RegistrarUsoDePromocionHandler
{
    public function __construct(
        private readonly PromocionRepository $promociones,
        private readonly EventPublisher $eventos,
        private readonly TransactionManager $transacciones,
        private readonly Clock $clock,
    ) {}

    public function __invoke(RegistrarUsoDePromocionCommand $comando): Promocion
    {
        return $this->transacciones->ejecutar(function () use ($comando): Promocion {
            $promocion = $this->promociones->buscar($comando->promocionId)
                ?? throw new RecursoNoEncontrado("La promocion {$comando->promocionId} no existe");

            $promocion->registrarUso(
                $comando->aplicadaEn ?? $this->clock->ahora(),
                Money::deCentavos($comando->descuentoCentavos, $comando->moneda),
                $comando->reservaId,
                $comando->pagoId,
                $comando->sagaId,
            );

            $this->promociones->guardar($promocion);
            $this->eventos->publicar($promocion->sacarEventos());

            return $promocion;
        });
    }
}
