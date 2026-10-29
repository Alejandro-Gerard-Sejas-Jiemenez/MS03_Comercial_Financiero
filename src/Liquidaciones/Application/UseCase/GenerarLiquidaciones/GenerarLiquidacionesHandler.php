<?php

declare(strict_types=1);

namespace Src\Liquidaciones\Application\UseCase\GenerarLiquidaciones;

use DateTimeImmutable;
use Src\Acuerdos\Application\Port\MetodoCobroQuery;
use Src\Liquidaciones\Domain\Comision;
use Src\Liquidaciones\Domain\ComisionRepository;
use Src\Liquidaciones\Domain\Liquidacion;
use Src\Liquidaciones\Domain\LiquidacionRepository;
use Src\Referencias\Domain\AnfitrionReference;
use Src\Referencias\Domain\AnfitrionReferenceRepository;
use Src\Shared\Application\Port\Clock;
use Src\Shared\Application\Port\TransactionManager;
use Src\Shared\Application\Port\UuidGenerator;
use Src\Shared\Domain\Exception\ConflictoDeEstado;
use Src\Shared\Domain\Money;

/**
 * CU-LIQ-04 GenerarLiquidaciones (JOB periodico).
 *
 * Una liquidacion GENERADA por anfitrion, juntando sus comisiones LIQUIDABLES
 * cuyo plazo ya se cumplio.
 *
 * Recorre `ref_anfitrion` (la copia local), no MS-01: si MS-01 esta caido esa
 * noche la liquidacion igual corre, y un anfitrion suspendido simplemente no
 * aparece en la lista de verificados. Eso es exactamente para lo que existe el
 * paquete Referencias.
 *
 * Un anfitrion sin comisiones liquidables, o cuyo neto quedaria en cero, no
 * genera liquidacion: no se crean filas vacias.
 */
final class GenerarLiquidacionesHandler
{
    public function __construct(
        private readonly ComisionRepository $comisiones,
        private readonly LiquidacionRepository $liquidaciones,
        private readonly AnfitrionReferenceRepository $anfitriones,
        private readonly MetodoCobroQuery $metodosDeCobro,
        private readonly UuidGenerator $uuid,
        private readonly Clock $clock,
        private readonly TransactionManager $tx,
    ) {}

    /** @return list<Liquidacion> */
    public function __invoke(GenerarLiquidacionesCommand $comando): array
    {
        $hasta = $comando->hasta ?? $this->clock->hoy();
        $generadas = [];

        foreach ($this->anfitriones->verificados() as $anfitrion) {
            $liquidacion = $this->paraAnfitrion($anfitrion, $hasta, $comando);

            if ($liquidacion !== null) {
                $generadas[] = $liquidacion;
            }
        }

        return $generadas;
    }

    private function paraAnfitrion(
        AnfitrionReference $anfitrion,
        DateTimeImmutable $hasta,
        GenerarLiquidacionesCommand $comando,
    ): ?Liquidacion {
        $liquidables = $this->comisiones->liquidablesDe($anfitrion->anfitrionId, $hasta);

        if ($liquidables === []) {
            return null;
        }

        $moneda = $comando->moneda;
        $bruto = Money::sumaDe(
            array_map(static fn (Comision $c): Money => $c->baseComisionable(), $liquidables),
            $moneda,
        );
        $comisiones = Money::sumaDe(
            array_map(static fn (Comision $c): Money => $c->montoComision(), $liquidables),
            $moneda,
        );
        $reembolsos = Money::deCentavos(
            $comando->ajustesPorReembolso[$anfitrion->anfitrionId] ?? 0,
            $moneda,
        );

        $neto = $bruto->menos($comisiones)->menos($reembolsos);

        if ($neto->esNegativo()) {
            throw new ConflictoDeEstado(
                "El anfitrion {$anfitrion->anfitrionId} tiene un ajuste por reembolsos "
                ."({$reembolsos->aDecimal()}) mayor que su neto del periodo; revisar a mano",
            );
        }

        // Nada que transferir: no se crea una fila vacia y las comisiones
        // quedan LIQUIDABLES para la corrida siguiente.
        if ($neto->esCero()) {
            return null;
        }

        $liquidacion = Liquidacion::generar(
            liquidacionId: $this->uuid->generar(),
            anfitrionId: $anfitrion->anfitrionId,
            periodoDesde: $comando->periodoDesde ?? $this->periodoDesdeDe($liquidables),
            periodoHasta: $hasta,
            totalBruto: $bruto,
            totalComisiones: $comisiones,
            totalReembolsos: $reembolsos,
            metodoId: $this->metodosDeCobro->metodoPrincipalVerificado($anfitrion->anfitrionId),
        );

        return $this->tx->ejecutar(function () use ($liquidacion, $liquidables): Liquidacion {
            $this->liquidaciones->guardar($liquidacion);

            foreach ($liquidables as $comision) {
                $comision->asignarALiquidacion($liquidacion->liquidacionId());
                $this->comisiones->guardar($comision);
            }

            return $liquidacion;
        });
    }

    /** @param list<Comision> $liquidables */
    private function periodoDesdeDe(array $liquidables): DateTimeImmutable
    {
        $fechas = array_filter(array_map(
            static fn (Comision $c): ?DateTimeImmutable => $c->liquidableDesde(),
            $liquidables,
        ));

        return $fechas === [] ? $this->clock->hoy() : min($fechas);
    }
}
