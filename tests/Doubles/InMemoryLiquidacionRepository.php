<?php

declare(strict_types=1);

namespace Tests\Doubles;

use Src\Liquidaciones\Domain\EstadoLiquidacion;
use Src\Liquidaciones\Domain\Liquidacion;
use Src\Liquidaciones\Domain\LiquidacionRepository;

final class InMemoryLiquidacionRepository implements LiquidacionRepository
{
    /** @var array<string, Liquidacion> */
    private array $liquidaciones = [];

    public function buscar(string $liquidacionId): ?Liquidacion
    {
        return $this->liquidaciones[$liquidacionId] ?? null;
    }

    public function guardar(Liquidacion $liquidacion): void
    {
        $this->liquidaciones[$liquidacion->liquidacionId()] = $liquidacion;
    }

    public function deAnfitrion(
        string $anfitrionId,
        ?EstadoLiquidacion $estado = null,
        int $limite = 50,
    ): array {
        return array_values(array_filter(
            $this->liquidaciones,
            static fn (Liquidacion $l): bool => $l->anfitrionId() === $anfitrionId
                && ($estado === null || $l->estado() === $estado),
        ));
    }

    public function pendientesDePago(string $anfitrionId): array
    {
        return array_values(array_filter(
            $this->liquidaciones,
            static fn (Liquidacion $l): bool => $l->anfitrionId() === $anfitrionId
                && in_array(
                    $l->estado(),
                    [EstadoLiquidacion::GENERADA, EstadoLiquidacion::APROBADA],
                    true,
                ),
        ));
    }

    /** @return list<Liquidacion> */
    public function todas(): array
    {
        return array_values($this->liquidaciones);
    }

    public function cantidad(): int
    {
        return count($this->liquidaciones);
    }
}
