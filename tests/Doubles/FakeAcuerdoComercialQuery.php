<?php

declare(strict_types=1);

namespace Tests\Doubles;

use Src\Acuerdos\Application\Port\AcuerdoComercialQuery;
use Src\Acuerdos\Application\Port\AcuerdoVigente;

final class FakeAcuerdoComercialQuery implements AcuerdoComercialQuery
{
    /** @var array<string, AcuerdoVigente> */
    private array $acuerdos = [];

    public function acuerdoActivoDe(string $anfitrionId): ?AcuerdoVigente
    {
        return $this->acuerdos[$anfitrionId] ?? null;
    }

    public function con(
        string $anfitrionId,
        int $diasLiquidacion = 7,
        ?string $reglaComisionId = null,
        string $acuerdoId = 'acuerdo-1',
    ): self {
        $this->acuerdos[$anfitrionId] = new AcuerdoVigente(
            $acuerdoId,
            $anfitrionId,
            $diasLiquidacion,
            $reglaComisionId,
        );

        return $this;
    }
}
