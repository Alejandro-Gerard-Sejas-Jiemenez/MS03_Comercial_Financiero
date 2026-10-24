<?php

declare(strict_types=1);

namespace Src\Liquidaciones\Domain;

use DateTimeImmutable;

interface ComisionRepository
{
    public function buscar(string $comisionId): ?Comision;

    /** Un pago capturado tiene una sola comision (`pago_id` UNIQUE). */
    public function buscarPorPago(string $pagoId): ?Comision;

    public function buscarPorReserva(string $reservaId): ?Comision;

    public function guardar(Comision $comision): void;

    /**
     * Comisiones RETENIDAS de una reserva o de un anfitrion, para CU-LIQ-03.
     *
     * @return list<Comision>
     */
    public function retenidasDeReserva(string $reservaId): array;

    /**
     * Comisiones LIQUIDABLES de un anfitrion cuyo plazo ya se cumplio.
     *
     * @return list<Comision>
     */
    public function liquidablesDe(string $anfitrionId, DateTimeImmutable $hasta): array;

    /** @return list<Comision> */
    public function deLiquidacion(string $liquidacionId): array;
}
