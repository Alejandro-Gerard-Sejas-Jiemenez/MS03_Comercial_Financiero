<?php

declare(strict_types=1);

namespace Src\Relaciones\Domain\Entity\AcuerdoComercial;

use DateTimeImmutable;
use Src\Shared\Domain\Exception\ConflictoDeEstado;
use Src\Shared\Domain\Exception\ReglaDeNegocioViolada;

/**
 * Edicion de las condiciones del acuerdo y sus validaciones.
 *
 * Parte de `AcuerdoComercial`: usa sus campos privados.
 */
trait Edicion
{
    /**
     * Cambia las condiciones negociadas. Solo los valores no nulos se aplican.
     * Un acuerdo cerrado ya no se toca: su historico sostiene liquidaciones ya
     * hechas.
     *
     * @param  array<string, mixed>|null  $condiciones
     */
    public function actualizarCondiciones(
        ?int $diasLiquidacion = null,
        ?string $reglaComisionId = null,
        ?array $condiciones = null,
        ?DateTimeImmutable $fechaFin = null,
    ): void {
        if ($this->estado->esTerminal()) {
            throw new ConflictoDeEstado(
                "El acuerdo {$this->acuerdoId} esta {$this->estado->value} y ya no admite cambios",
            );
        }

        if ($diasLiquidacion !== null) {
            self::validarDiasLiquidacion($diasLiquidacion);
            $this->diasLiquidacion = $diasLiquidacion;
        }

        if ($reglaComisionId !== null) {
            $this->reglaComisionId = $reglaComisionId;
        }

        if ($condiciones !== null) {
            $this->condiciones = $condiciones;
        }

        if ($fechaFin !== null) {
            self::validarFechas($this->fechaInicio, $fechaFin);
            $this->fechaFin = self::soloFecha($fechaFin);
        }
    }

    private static function validarDiasLiquidacion(int $dias): void
    {
        if ($dias < self::DIAS_LIQUIDACION_MINIMO || $dias > self::DIAS_LIQUIDACION_MAXIMO) {
            throw new ReglaDeNegocioViolada(
                sprintf(
                    'Los dias de liquidacion deben estar entre %d y %d, recibido %d',
                    self::DIAS_LIQUIDACION_MINIMO,
                    self::DIAS_LIQUIDACION_MAXIMO,
                    $dias,
                ),
            );
        }
    }

    private static function validarFechas(DateTimeImmutable $inicio, ?DateTimeImmutable $fin): void
    {
        if ($fin !== null && self::soloFecha($fin) < self::soloFecha($inicio)) {
            throw new ReglaDeNegocioViolada('El acuerdo no puede terminar antes de empezar');
        }
    }

    private static function soloFecha(DateTimeImmutable $fecha): DateTimeImmutable
    {
        return new DateTimeImmutable($fecha->format('Y-m-d').' 00:00:00');
    }
}
