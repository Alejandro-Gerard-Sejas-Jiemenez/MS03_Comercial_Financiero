<?php

declare(strict_types=1);

namespace Src\Financiero\Application\Response;

use DateTimeImmutable;
use Src\Financiero\Domain\Entity\Comision;
use Src\Shared\Application\Response\MontoResponse;

/** Modelo de respuesta (Clean Architecture: Response Model) de Comision: solo datos primitivos, sin reglas. */
final class ComisionResponse
{
    public function __construct(
        public readonly string $comisionId,
        public readonly string $pagoId,
        public readonly string $reservaId,
        public readonly string $anfitrionId,
        public readonly ?string $reglaId,
        public readonly ?string $acuerdoId,
        public readonly ?string $liquidacionId,
        public readonly MontoResponse $baseComisionable,
        public readonly ?string $porcentajeAplicado,
        public readonly MontoResponse $montoComision,
        public readonly MontoResponse $montoNetoAnfitrion,
        public readonly string $estado,
        public readonly ?DateTimeImmutable $liquidableDesde,
    ) {}

    public static function desde(Comision $e): self
    {
        return new self(
            comisionId: $e->comisionId(),
            pagoId: $e->pagoId(),
            reservaId: $e->reservaId(),
            anfitrionId: $e->anfitrionId(),
            reglaId: $e->reglaId(),
            acuerdoId: $e->acuerdoId(),
            liquidacionId: $e->liquidacionId(),
            baseComisionable: MontoResponse::desde($e->baseComisionable()),
            porcentajeAplicado: $e->porcentajeAplicado(),
            montoComision: MontoResponse::desde($e->montoComision()),
            montoNetoAnfitrion: MontoResponse::desde($e->montoNetoAnfitrion()),
            estado: $e->estado()->value,
            liquidableDesde: $e->liquidableDesde(),
        );
    }

    public static function desdeOpcional(?Comision $e): ?self
    {
        return $e === null ? null : self::desde($e);
    }
}
