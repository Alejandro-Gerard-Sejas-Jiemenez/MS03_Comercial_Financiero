<?php

declare(strict_types=1);

namespace Tests\Doubles;

use DateTimeImmutable;
use Src\Monetizacion\Domain\Concepto;
use Src\Monetizacion\Domain\EstadoRegla;
use Src\Monetizacion\Domain\ReglaMonetizacion;
use Src\Monetizacion\Domain\ReglaMonetizacionRepository;

final class InMemoryReglaMonetizacionRepository implements ReglaMonetizacionRepository
{
    /** @var array<string, ReglaMonetizacion> */
    private array $reglas = [];

    public function __construct(ReglaMonetizacion ...$reglas)
    {
        foreach ($reglas as $regla) {
            $this->guardar($regla);
        }
    }

    public function buscar(string $reglaId): ?ReglaMonetizacion
    {
        return $this->reglas[$reglaId] ?? null;
    }

    public function guardar(ReglaMonetizacion $regla): void
    {
        $this->reglas[$regla->reglaId()] = $regla;
    }

    public function vigentes(
        Concepto $concepto,
        DateTimeImmutable $fecha,
        ?string $categoriaAlojamiento = null,
        ?string $nivelAnfitrion = null,
    ): array {
        $vigentes = array_values(array_filter(
            $this->reglas,
            static fn (ReglaMonetizacion $regla): bool => $regla->concepto() === $concepto
                && $regla->aplicaA($fecha, $categoriaAlojamiento, $nivelAnfitrion),
        ));

        usort(
            $vigentes,
            static fn (ReglaMonetizacion $a, ReglaMonetizacion $b): int => [
                $b->especificidad(), $b->vigenteDesde(),
            ] <=> [$a->especificidad(), $a->vigenteDesde()],
        );

        return $vigentes;
    }

    public function listar(?Concepto $concepto = null, ?EstadoRegla $estado = null): array
    {
        return array_values(array_filter(
            $this->reglas,
            static fn (ReglaMonetizacion $regla): bool => ($concepto === null || $regla->concepto() === $concepto)
                && ($estado === null || $regla->estado() === $estado),
        ));
    }

    public function cantidad(): int
    {
        return count($this->reglas);
    }
}
