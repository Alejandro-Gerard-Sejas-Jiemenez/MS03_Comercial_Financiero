<?php

declare(strict_types=1);

namespace Src\Relaciones\Domain\Event;

use DateTimeImmutable;
use Src\Shared\Domain\DomainEvent;

/**
 * `acuerdo_comercial.activado`. MS-01 lo consume para habilitar las
 * publicaciones del anfitrion. Tambien lo leen Financiero para
 * conocer el plazo de liquidacion vigente.
 */
final class AcuerdoActivado implements DomainEvent
{
    public function __construct(
        private readonly string $acuerdoId,
        private readonly string $anfitrionId,
        private readonly ?string $reglaComisionId,
        private readonly int $diasLiquidacion,
        private readonly DateTimeImmutable $fechaInicio,
        private readonly ?DateTimeImmutable $fechaFin,
        private readonly string $aprobadoPor,
        private readonly ?string $sagaId = null,
    ) {}

    public function tipoEvento(): string
    {
        return 'acuerdo_comercial.activado';
    }

    public function agregadoTipo(): string
    {
        return 'acuerdo_comercial';
    }

    public function agregadoId(): string
    {
        return $this->acuerdoId;
    }

    public function payload(): array
    {
        return [
            'acuerdo_id' => $this->acuerdoId,
            'anfitrion_id' => $this->anfitrionId,
            'regla_comision_id' => $this->reglaComisionId,
            'dias_liquidacion' => $this->diasLiquidacion,
            'fecha_inicio' => $this->fechaInicio->format('Y-m-d'),
            'fecha_fin' => $this->fechaFin?->format('Y-m-d'),
            'aprobado_por' => $this->aprobadoPor,
        ];
    }

    public function sagaId(): ?string
    {
        return $this->sagaId;
    }
}
