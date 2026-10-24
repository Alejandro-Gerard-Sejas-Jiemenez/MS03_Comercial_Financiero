<?php

declare(strict_types=1);

namespace Tests\Doubles;

use DateTimeImmutable;
use Src\Liquidaciones\Domain\Comision;
use Src\Liquidaciones\Domain\ComisionRepository;
use Src\Liquidaciones\Domain\EstadoComision;

final class InMemoryComisionRepository implements ComisionRepository
{
    /** @var array<string, Comision> */
    private array $comisiones = [];

    public function __construct(Comision ...$comisiones)
    {
        foreach ($comisiones as $comision) {
            $this->guardar($comision);
        }
    }

    public function buscar(string $comisionId): ?Comision
    {
        return $this->comisiones[$comisionId] ?? null;
    }

    public function buscarPorPago(string $pagoId): ?Comision
    {
        foreach ($this->comisiones as $comision) {
            if ($comision->pagoId() === $pagoId) {
                return $comision;
            }
        }

        return null;
    }

    public function buscarPorReserva(string $reservaId): ?Comision
    {
        foreach ($this->comisiones as $comision) {
            if ($comision->reservaId() === $reservaId) {
                return $comision;
            }
        }

        return null;
    }

    public function guardar(Comision $comision): void
    {
        $this->comisiones[$comision->comisionId()] = $comision;
    }

    public function retenidasDeReserva(string $reservaId): array
    {
        return array_values(array_filter(
            $this->comisiones,
            static fn (Comision $c): bool => $c->reservaId() === $reservaId
                && $c->estado() === EstadoComision::RETENIDA,
        ));
    }

    public function liquidablesDe(string $anfitrionId, DateTimeImmutable $hasta): array
    {
        return array_values(array_filter(
            $this->comisiones,
            static fn (Comision $c): bool => $c->anfitrionId() === $anfitrionId
                && $c->sePuedeLiquidarEn($hasta),
        ));
    }

    public function deLiquidacion(string $liquidacionId): array
    {
        return array_values(array_filter(
            $this->comisiones,
            static fn (Comision $c): bool => $c->liquidacionId() === $liquidacionId,
        ));
    }

    public function cantidad(): int
    {
        return count($this->comisiones);
    }
}
