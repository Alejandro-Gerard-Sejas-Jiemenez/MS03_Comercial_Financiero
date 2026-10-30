<?php

declare(strict_types=1);

namespace Src\Relaciones\Application\Response;

use DateTimeImmutable;
use Src\Relaciones\Domain\Entity\AcuerdoComercial;

/** Modelo de respuesta (Clean Architecture: Response Model) de AcuerdoComercial: solo datos primitivos, sin reglas. */
final class AcuerdoComercialResponse
{
    public function __construct(
        public readonly string $acuerdoId,
        public readonly string $anfitrionId,
        public readonly ?string $reglaComisionId,
        public readonly string $estado,
        public readonly DateTimeImmutable $fechaInicio,
        public readonly ?DateTimeImmutable $fechaFin,
        public readonly array $condiciones,
        public readonly int $diasLiquidacion,
        public readonly ?string $aprobadoPor,
        public readonly ?DateTimeImmutable $aprobadoEn,
        public readonly ?string $motivoEstado,
    ) {}

    public static function desde(AcuerdoComercial $e): self
    {
        return new self(
            acuerdoId: $e->acuerdoId(),
            anfitrionId: $e->anfitrionId(),
            reglaComisionId: $e->reglaComisionId(),
            estado: $e->estado()->value,
            fechaInicio: $e->fechaInicio(),
            fechaFin: $e->fechaFin(),
            condiciones: $e->condiciones(),
            diasLiquidacion: $e->diasLiquidacion(),
            aprobadoPor: $e->aprobadoPor(),
            aprobadoEn: $e->aprobadoEn(),
            motivoEstado: $e->motivoEstado(),
        );
    }

    public static function desdeOpcional(?AcuerdoComercial $e): ?self
    {
        return $e === null ? null : self::desde($e);
    }
}
