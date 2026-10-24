<?php

declare(strict_types=1);

namespace Src\Liquidaciones\Application\UseCase\RechazarLiquidacion;

use DomainException;
use Src\Liquidaciones\Domain\ComisionRepository;
use Src\Liquidaciones\Domain\Liquidacion;
use Src\Liquidaciones\Domain\LiquidacionRepository;
use Src\Shared\Application\Port\AuditLogger;
use Src\Shared\Application\Port\TransactionManager;

/**
 * CU-LIQ-06 RechazarLiquidacion (NEG, REST).
 *
 * Rechaza o anula, y libera las comisiones: vuelven a LIQUIDABLE y entran en la
 * corrida siguiente. Si no se liberaran, ese dinero quedaria atrapado en una
 * liquidacion muerta y el anfitrion nunca lo cobraria.
 *
 * Una liquidacion PAGADA no se rechaza ni se anula: el dinero ya salio y eso se
 * corrige con un asiento de reversa.
 */
final class RechazarLiquidacionHandler
{
    public function __construct(
        private readonly LiquidacionRepository $liquidaciones,
        private readonly ComisionRepository $comisiones,
        private readonly AuditLogger $auditoria,
        private readonly TransactionManager $tx,
    ) {
    }

    public function __invoke(RechazarLiquidacionCommand $comando): Liquidacion
    {
        $liquidacion = $this->liquidaciones->buscar($comando->liquidacionId)
            ?? throw new DomainException("La liquidacion {$comando->liquidacionId} no existe");

        $estadoAnterior = $liquidacion->estado()->value;

        return $this->tx->ejecutar(function () use ($liquidacion, $comando, $estadoAnterior): Liquidacion {
            if ($comando->anular) {
                $liquidacion->anular($comando->motivo);
            } else {
                $liquidacion->rechazar($comando->revisadoPor, $comando->motivo);
            }

            $this->liquidaciones->guardar($liquidacion);

            foreach ($this->comisiones->deLiquidacion($liquidacion->liquidacionId()) as $comision) {
                $comision->liberar();
                $this->comisiones->guardar($comision);
            }

            $this->auditoria->registrar(
                $comando->anular ? 'ANULAR_LIQUIDACION' : 'RECHAZAR_LIQUIDACION',
                'liquidacion',
                $liquidacion->liquidacionId(),
                ['estado' => $estadoAnterior],
                ['estado' => $liquidacion->estado()->value, 'motivo' => $comando->motivo],
                $comando->revisadoPor,
            );

            return $liquidacion;
        });
    }
}
