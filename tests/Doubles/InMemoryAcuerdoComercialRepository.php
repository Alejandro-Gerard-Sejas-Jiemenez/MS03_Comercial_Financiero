<?php

declare(strict_types=1);

namespace Tests\Doubles;

use Src\Acuerdos\Domain\AcuerdoComercial;
use Src\Acuerdos\Domain\AcuerdoComercialRepository;
use Src\Acuerdos\Domain\EstadoAcuerdo;

final class InMemoryAcuerdoComercialRepository implements AcuerdoComercialRepository
{
    /** @var array<string, AcuerdoComercial> */
    private array $acuerdos = [];

    public function __construct(AcuerdoComercial ...$acuerdos)
    {
        foreach ($acuerdos as $acuerdo) {
            $this->guardar($acuerdo);
        }
    }

    public function buscar(string $acuerdoId): ?AcuerdoComercial
    {
        return $this->acuerdos[$acuerdoId] ?? null;
    }

    public function guardar(AcuerdoComercial $acuerdo): void
    {
        $this->acuerdos[$acuerdo->acuerdoId()] = $acuerdo;
    }

    public function activoDe(string $anfitrionId): ?AcuerdoComercial
    {
        foreach ($this->acuerdos as $acuerdo) {
            if ($acuerdo->anfitrionId() === $anfitrionId && $acuerdo->estaActivo()) {
                return $acuerdo;
            }
        }

        return null;
    }

    public function historialDe(string $anfitrionId): array
    {
        $historial = array_values(array_filter(
            $this->acuerdos,
            static fn (AcuerdoComercial $acuerdo): bool => $acuerdo->anfitrionId() === $anfitrionId,
        ));

        // Del mas reciente al mas antiguo, como la consulta de historial.
        usort(
            $historial,
            static fn (AcuerdoComercial $a, AcuerdoComercial $b): int => $b->fechaInicio() <=> $a->fechaInicio(),
        );

        return $historial;
    }

    public function listar(?EstadoAcuerdo $estado = null, ?string $anfitrionId = null): array
    {
        return array_values(array_filter(
            $this->acuerdos,
            static fn (AcuerdoComercial $acuerdo): bool => ($estado === null || $acuerdo->estado() === $estado)
                && ($anfitrionId === null || $acuerdo->anfitrionId() === $anfitrionId),
        ));
    }

    public function cantidad(): int
    {
        return count($this->acuerdos);
    }
}
