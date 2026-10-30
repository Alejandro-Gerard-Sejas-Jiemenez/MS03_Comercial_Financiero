<?php

declare(strict_types=1);

namespace Src\Financiero\Domain\Entity;

use DateTimeImmutable;
use Src\Financiero\Domain\Entity\Comision\CicloDeVida;
use Src\Financiero\Domain\Entity\Comision\Lectura;
use Src\Financiero\Domain\Enum\EstadoComision;
use Src\Shared\Domain\Exception\ReglaDeNegocioViolada;
use Src\Shared\Domain\Money;

/**
 * Comision de un pago capturado. Un pago capturado produce exactamente una
 * comision (`pago_id` es UNIQUE en la tabla).
 *
 * Guarda el porcentaje aplicado como snapshot: si manana cambia la regla de
 * monetizacion, esta comision no se mueve. Es la razon por la que el historico
 * financiero es auditable.
 */
final class Comision
{
    use CicloDeVida;
    use Lectura;

    private function __construct(
        private readonly string $comisionId,
        private readonly string $pagoId,
        private readonly string $reservaId,
        private readonly string $anfitrionId,
        private readonly ?string $reglaId,
        private readonly ?string $acuerdoId,
        private ?string $liquidacionId,
        private readonly Money $baseComisionable,
        private readonly ?string $porcentajeAplicado,
        private readonly Money $montoComision,
        private readonly Money $montoNetoAnfitrion,
        private EstadoComision $estado,
        private ?DateTimeImmutable $liquidableDesde,
    ) {}

    public static function registrar(
        string $comisionId,
        string $pagoId,
        string $reservaId,
        string $anfitrionId,
        Money $baseComisionable,
        Money $montoComision,
        ?string $porcentajeAplicado = null,
        ?string $reglaId = null,
        ?string $acuerdoId = null,
    ): self {
        if ($baseComisionable->esNegativo()) {
            throw new ReglaDeNegocioViolada('La base comisionable no puede ser negativa');
        }

        if ($montoComision->esNegativo()) {
            throw new ReglaDeNegocioViolada('La comision no puede ser negativa');
        }

        if ($montoComision->esMayorQue($baseComisionable)) {
            throw new ReglaDeNegocioViolada('La comision no puede superar la base comisionable');
        }

        return new self(
            $comisionId,
            $pagoId,
            $reservaId,
            $anfitrionId,
            $reglaId,
            $acuerdoId,
            null,
            $baseComisionable,
            $porcentajeAplicado,
            $montoComision,
            // neto = base - comision
            $baseComisionable->menos($montoComision),
            EstadoComision::RETENIDA,
            null,
        );
    }

    public static function reconstituir(
        string $comisionId,
        string $pagoId,
        string $reservaId,
        string $anfitrionId,
        ?string $reglaId,
        ?string $acuerdoId,
        ?string $liquidacionId,
        Money $baseComisionable,
        ?string $porcentajeAplicado,
        Money $montoComision,
        Money $montoNetoAnfitrion,
        EstadoComision $estado,
        ?DateTimeImmutable $liquidableDesde,
    ): self {
        return new self(
            $comisionId,
            $pagoId,
            $reservaId,
            $anfitrionId,
            $reglaId,
            $acuerdoId,
            $liquidacionId,
            $baseComisionable,
            $porcentajeAplicado,
            $montoComision,
            $montoNetoAnfitrion,
            $estado,
            $liquidableDesde,
        );
    }
}
