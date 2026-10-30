<?php

declare(strict_types=1);

namespace Src\Relaciones\Domain\Entity;

use DateTimeImmutable;
use Src\Relaciones\Domain\Entity\AcuerdoComercial\CicloDeVida;
use Src\Relaciones\Domain\Entity\AcuerdoComercial\Edicion;
use Src\Relaciones\Domain\Entity\AcuerdoComercial\Lectura;
use Src\Relaciones\Domain\Enum\EstadoAcuerdo;
use Src\Shared\Domain\RegistraEventos;

/**
 * Acuerdo comercial con un anfitrion (F17): la tarifa negociada y el plazo de
 * liquidacion que rigen sus cobros.
 *
 * Entidad pura y raiz de agregado: no conoce Eloquent ni Laravel, protege las
 * transiciones de estado de y acumula los eventos que MS-01 consume.
 * La fila de la base de datos se traduce con `reconstituir()`.
 *
 * La regla "un solo acuerdo ACTIVO por anfitrion" no se puede decidir aqui,
 * porque mira otros acuerdos: la comprueba el caso de uso con el repositorio
 * y la garantiza tambien el indice unico parcial `uq_acuerdo_activo_por_anfitrion`.
 */
final class AcuerdoComercial
{
    use CicloDeVida;
    use Edicion;
    use Lectura;
    use RegistraEventos;

    private const DIAS_LIQUIDACION_MINIMO = 0;

    private const DIAS_LIQUIDACION_MAXIMO = 60;

    /** @param array<string, mixed> $condiciones */
    private function __construct(
        private readonly string $acuerdoId,
        private readonly string $anfitrionId,
        private ?string $reglaComisionId,
        private EstadoAcuerdo $estado,
        private readonly DateTimeImmutable $fechaInicio,
        private ?DateTimeImmutable $fechaFin,
        private array $condiciones,
        private int $diasLiquidacion,
        private ?string $aprobadoPor,
        private ?DateTimeImmutable $aprobadoEn,
        private ?string $motivoEstado,
    ) {}

    /**
     * Todo acuerdo nace en BORRADOR: el Administrador lo negocia y recien
     * despues lo activa.
     *
     * @param  array<string, mixed>  $condiciones
     */
    public static function crearBorrador(
        string $acuerdoId,
        string $anfitrionId,
        DateTimeImmutable $fechaInicio,
        int $diasLiquidacion = 7,
        ?string $reglaComisionId = null,
        ?DateTimeImmutable $fechaFin = null,
        array $condiciones = [],
    ): self {
        self::validarDiasLiquidacion($diasLiquidacion);
        self::validarFechas($fechaInicio, $fechaFin);

        return new self(
            $acuerdoId,
            $anfitrionId,
            $reglaComisionId,
            EstadoAcuerdo::BORRADOR,
            self::soloFecha($fechaInicio),
            $fechaFin === null ? null : self::soloFecha($fechaFin),
            $condiciones,
            $diasLiquidacion,
            null,
            null,
            null,
        );
    }

    /**
     * Reconstruye la entidad desde la base de datos, sin revalidar reglas de
     * creacion y sin emitir eventos.
     *
     * @param  array<string, mixed>  $condiciones
     */
    public static function reconstituir(
        string $acuerdoId,
        string $anfitrionId,
        ?string $reglaComisionId,
        EstadoAcuerdo $estado,
        DateTimeImmutable $fechaInicio,
        ?DateTimeImmutable $fechaFin,
        array $condiciones,
        int $diasLiquidacion,
        ?string $aprobadoPor,
        ?DateTimeImmutable $aprobadoEn,
        ?string $motivoEstado,
    ): self {
        return new self(
            $acuerdoId,
            $anfitrionId,
            $reglaComisionId,
            $estado,
            $fechaInicio,
            $fechaFin,
            $condiciones,
            $diasLiquidacion,
            $aprobadoPor,
            $aprobadoEn,
            $motivoEstado,
        );
    }
}
