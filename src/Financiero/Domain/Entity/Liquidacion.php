<?php

declare(strict_types=1);

namespace Src\Financiero\Domain\Entity;

use DateTimeImmutable;
use Src\Financiero\Domain\Entity\Liquidacion\CicloDeVida;
use Src\Financiero\Domain\Entity\Liquidacion\Lectura;
use Src\Financiero\Domain\Enum\EstadoLiquidacion;
use Src\Shared\Domain\Exception\ConflictoDeEstado;
use Src\Shared\Domain\Exception\ReglaDeNegocioViolada;
use Src\Shared\Domain\Money;
use Src\Shared\Domain\RegistraEventos;

/**
 * Lo que la plataforma le paga a un anfitrion por un periodo.
 *
 *   total_neto = total_bruto - total_comisiones - total_reembolsos
 *
 * La base garantiza esa ecuacion con un CHECK; aqui se garantiza ademas que no
 * se genere una liquidacion de monto negativo y que los estados se muevan en
 * orden: GENERADA -> APROBADA -> PAGADA, con RECHAZADA y ANULADA como salidas.
 */
final class Liquidacion
{
    use CicloDeVida;
    use Lectura;
    use RegistraEventos;

    private function __construct(
        private readonly string $liquidacionId,
        private readonly string $anfitrionId,
        private ?string $metodoId,
        private readonly DateTimeImmutable $periodoDesde,
        private readonly DateTimeImmutable $periodoHasta,
        private readonly Money $totalBruto,
        private readonly Money $totalComisiones,
        private readonly Money $totalReembolsos,
        private EstadoLiquidacion $estado,
        private ?string $aprobadoPor,
        private ?string $referenciaTransferencia,
    ) {}

    public static function generar(
        string $liquidacionId,
        string $anfitrionId,
        DateTimeImmutable $periodoDesde,
        DateTimeImmutable $periodoHasta,
        Money $totalBruto,
        Money $totalComisiones,
        Money $totalReembolsos,
        ?string $metodoId = null,
    ): self {
        if ($periodoHasta < $periodoDesde) {
            throw new ReglaDeNegocioViolada('El periodo no puede terminar antes de empezar');
        }

        $neto = $totalBruto->menos($totalComisiones)->menos($totalReembolsos);

        if ($neto->esNegativo()) {
            throw new ReglaDeNegocioViolada(
                "La liquidacion quedaria en {$neto->aDecimal()}: "
                .'los reembolsos superan lo que se le debe al anfitrion',
            );
        }

        if ($neto->esCero()) {
            throw new ReglaDeNegocioViolada('No se genera una liquidacion de monto cero');
        }

        return new self(
            $liquidacionId,
            $anfitrionId,
            $metodoId,
            self::soloFecha($periodoDesde),
            self::soloFecha($periodoHasta),
            $totalBruto,
            $totalComisiones,
            $totalReembolsos,
            EstadoLiquidacion::GENERADA,
            null,
            null,
        );
    }

    /**
     * La liquidacion de un anfitrion a partir de sus comisiones liquidables.
     *
     *   neto = suma(base comisionable) - suma(comisiones) - ajuste por reembolsos
     *
     * Devuelve null si no hay nada que transferir (sin comisiones o neto en cero):
     * no se crean filas vacias y las comisiones quedan LIQUIDABLES para la corrida
     * siguiente. Falla si el ajuste por reembolsos supera el neto del periodo.
     *
     * @param  list<Comision>  $comisiones
     */
    public static function desdeComisiones(
        string $liquidacionId,
        string $anfitrionId,
        array $comisiones,
        Money $ajustePorReembolsos,
        DateTimeImmutable $periodoHasta,
        ?DateTimeImmutable $periodoDesde,
        DateTimeImmutable $hoy,
        ?string $metodoId = null,
    ): ?self {
        if ($comisiones === []) {
            return null;
        }

        $moneda = $ajustePorReembolsos->moneda();
        $bruto = Money::sumaDe(array_map(static fn (Comision $c): Money => $c->baseComisionable(), $comisiones), $moneda);
        $totalComisiones = Money::sumaDe(array_map(static fn (Comision $c): Money => $c->montoComision(), $comisiones), $moneda);
        $neto = $bruto->menos($totalComisiones)->menos($ajustePorReembolsos);

        if ($neto->esNegativo()) {
            throw new ConflictoDeEstado(
                "El anfitrion {$anfitrionId} tiene un ajuste por reembolsos "
                ."({$ajustePorReembolsos->aDecimal()}) mayor que su neto del periodo; revisar a mano",
            );
        }

        if ($neto->esCero()) {
            return null;
        }

        return self::generar(
            liquidacionId: $liquidacionId,
            anfitrionId: $anfitrionId,
            periodoDesde: $periodoDesde ?? self::inicioDelPeriodo($comisiones, $hoy),
            periodoHasta: $periodoHasta,
            totalBruto: $bruto,
            totalComisiones: $totalComisiones,
            totalReembolsos: $ajustePorReembolsos,
            metodoId: $metodoId,
        );
    }

    /**
     * El periodo arranca en la fecha liquidable mas antigua; sin fechas, hoy.
     *
     * @param  list<Comision>  $comisiones
     */
    private static function inicioDelPeriodo(array $comisiones, DateTimeImmutable $hoy): DateTimeImmutable
    {
        $fechas = array_filter(array_map(
            static fn (Comision $c): ?DateTimeImmutable => $c->liquidableDesde(),
            $comisiones,
        ));

        return $fechas === [] ? $hoy : min($fechas);
    }

    public static function reconstituir(
        string $liquidacionId,
        string $anfitrionId,
        ?string $metodoId,
        DateTimeImmutable $periodoDesde,
        DateTimeImmutable $periodoHasta,
        Money $totalBruto,
        Money $totalComisiones,
        Money $totalReembolsos,
        EstadoLiquidacion $estado,
        ?string $aprobadoPor,
        ?string $referenciaTransferencia,
    ): self {
        return new self(
            $liquidacionId,
            $anfitrionId,
            $metodoId,
            $periodoDesde,
            $periodoHasta,
            $totalBruto,
            $totalComisiones,
            $totalReembolsos,
            $estado,
            $aprobadoPor,
            $referenciaTransferencia,
        );
    }

    private static function soloFecha(DateTimeImmutable $fecha): DateTimeImmutable
    {
        return new DateTimeImmutable($fecha->format('Y-m-d').' 00:00:00');
    }
}
