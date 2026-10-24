<?php

declare(strict_types=1);

namespace Src\Pagos\Domain;

use DateTimeImmutable;
use Src\Shared\Domain\Money;

interface PagoRepository
{
    public function buscar(string $pagoId): ?Pago;

    /**
     * Busca por `idempotency_key`. Es lo que hace idempotente a CU-PAG-02:
     * un reintento de la saga con la misma clave devuelve el pago que ya existe
     * en vez de cobrar otra vez.
     */
    public function buscarPorIdempotencyKey(string $idempotencyKey): ?Pago;

    /** Pago en estado vivo (PENDIENTE, AUTORIZADO o CAPTURADO) de esa reserva. */
    public function buscarVivoPorReserva(string $reservaId): ?Pago;

    public function guardar(Pago $pago): void;

    /** Suma de los reembolsos PROCESADOS del pago. */
    public function totalReembolsado(string $pagoId): Money;

    /** @return list<Pago> */
    public function listar(CriterioDePagos $criterio): array;

    /**
     * Pagos PENDIENTES creados antes de la fecha, para expirarlos
     * (CU-PAG-04 disparado por JOB).
     *
     * @return list<Pago>
     */
    public function pendientesAnterioresA(DateTimeImmutable $limite, int $lote = 100): array;
}
