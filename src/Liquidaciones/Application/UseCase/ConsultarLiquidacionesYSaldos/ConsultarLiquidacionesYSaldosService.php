<?php

declare(strict_types=1);

namespace Src\Liquidaciones\Application\UseCase\ConsultarLiquidacionesYSaldos;

use DateTimeImmutable;
use DomainException;
use Src\Liquidaciones\Domain\Comision;
use Src\Liquidaciones\Domain\ComisionRepository;
use Src\Liquidaciones\Domain\EstadoLiquidacion;
use Src\Liquidaciones\Domain\LibroContableRepository;
use Src\Liquidaciones\Domain\Liquidacion;
use Src\Liquidaciones\Domain\LiquidacionRepository;
use Src\Shared\Application\Port\Clock;
use Src\Shared\Domain\Money;

/**
 * CU-LIQ-09 ConsultarLiquidacionesYSaldos (QRY, REST).
 *
 * Historial del anfitrion, detalle de las comisiones de una liquidacion y su
 * saldo. El saldo sale del libro contable (creditos menos debitos de la cuenta
 * POR_PAGAR_ANFITRION), no de sumar liquidaciones: el libro es la fuente de
 * verdad y ya incluye las reversas.
 */
final class ConsultarLiquidacionesYSaldosService
{
    public function __construct(
        private readonly LiquidacionRepository $liquidaciones,
        private readonly ComisionRepository $comisiones,
        private readonly LibroContableRepository $libro,
        private readonly Clock $clock,
    ) {
    }

    /** @return list<Liquidacion> */
    public function historialDe(
        string $anfitrionId,
        ?EstadoLiquidacion $estado = null,
        int $limite = 50,
    ): array {
        return $this->liquidaciones->deAnfitrion($anfitrionId, $estado, $limite);
    }

    public function detalle(string $liquidacionId): DetalleDeLiquidacion
    {
        $liquidacion = $this->liquidaciones->buscar($liquidacionId)
            ?? throw new DomainException("La liquidacion {$liquidacionId} no existe");

        return new DetalleDeLiquidacion(
            liquidacion: $liquidacion,
            comisiones: $this->comisiones->deLiquidacion($liquidacionId),
        );
    }

    /** Lo que la plataforma le debe hoy al anfitrion. */
    public function saldoDe(string $anfitrionId, string $moneda = 'BOB'): Money
    {
        return $this->libro->saldoPorPagarDe($anfitrionId, $moneda);
    }

    /**
     * Comisiones ya liquidables del anfitrion: lo que entraria en la proxima
     * corrida de CU-LIQ-04.
     *
     * @return list<Comision>
     */
    public function porLiquidarDe(string $anfitrionId, ?DateTimeImmutable $hasta = null): array
    {
        return $this->comisiones->liquidablesDe($anfitrionId, $hasta ?? $this->clock->hoy());
    }
}
