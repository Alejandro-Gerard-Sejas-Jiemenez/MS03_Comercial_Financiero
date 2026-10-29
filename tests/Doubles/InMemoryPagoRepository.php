<?php

declare(strict_types=1);

namespace Tests\Doubles;

use DateTimeImmutable;
use Src\Pagos\Domain\CriterioDePagos;
use Src\Pagos\Domain\EstadoPago;
use Src\Pagos\Domain\Pago;
use Src\Pagos\Domain\PagoRepository;
use Src\Shared\Domain\Money;

final class InMemoryPagoRepository implements PagoRepository
{
    /** @var array<string, Pago> */
    private array $pagos = [];

    /** @var array<string, Money> */
    private array $reembolsado = [];

    private int $guardados = 0;

    public function __construct(Pago ...$pagos)
    {
        foreach ($pagos as $pago) {
            $this->pagos[$pago->pagoId()] = $pago;
        }
    }

    public function buscar(string $pagoId): ?Pago
    {
        return $this->pagos[$pagoId] ?? null;
    }

    public function buscarPorIdempotencyKey(string $idempotencyKey): ?Pago
    {
        foreach ($this->pagos as $pago) {
            if ($pago->idempotencyKey() === $idempotencyKey) {
                return $pago;
            }
        }

        return null;
    }

    public function buscarVivoPorReserva(string $reservaId): ?Pago
    {
        foreach ($this->pagos as $pago) {
            if ($pago->reservaId() === $reservaId && $pago->estado()->estaVivo()) {
                return $pago;
            }
        }

        return null;
    }

    public function guardar(Pago $pago): void
    {
        $this->pagos[$pago->pagoId()] = $pago;
        $this->guardados++;
    }

    public function totalReembolsado(string $pagoId): Money
    {
        return $this->reembolsado[$pagoId] ?? Money::cero();
    }

    public function listar(CriterioDePagos $criterio): array
    {
        return array_values(array_filter(
            $this->pagos,
            static fn (Pago $pago): bool => ($criterio->clienteId === null
                    || $pago->clienteId() === $criterio->clienteId)
                && ($criterio->anfitrionId === null || $pago->anfitrionId() === $criterio->anfitrionId)
                && ($criterio->reservaId === null || $pago->reservaId() === $criterio->reservaId)
                && ($criterio->estado === null || $pago->estado() === $criterio->estado),
        ));
    }

    public function pendientesAnterioresA(DateTimeImmutable $limite, int $lote = 100): array
    {
        return array_values(array_filter(
            $this->pagos,
            static fn (Pago $pago): bool => $pago->estado() === EstadoPago::PENDIENTE,
        ));
    }

    /** Fija el acumulado de reembolsos de un pago, como lo haria la suma en SQL. */
    public function conReembolsadoDe(string $pagoId, Money $monto): self
    {
        $this->reembolsado[$pagoId] = $monto;

        return $this;
    }

    public function guardados(): int
    {
        return $this->guardados;
    }

    public function cantidad(): int
    {
        return count($this->pagos);
    }
}
