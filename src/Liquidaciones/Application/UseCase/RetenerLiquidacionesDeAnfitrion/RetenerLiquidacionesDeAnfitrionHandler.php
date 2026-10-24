<?php

declare(strict_types=1);

namespace Src\Liquidaciones\Application\UseCase\RetenerLiquidacionesDeAnfitrion;

use Src\Liquidaciones\Domain\ComisionRepository;
use Src\Liquidaciones\Domain\LiquidacionRepository;
use Src\Shared\Application\Port\AuditLogger;
use Src\Shared\Application\Port\TransactionManager;

/**
 * CU-LIQ-08 RetenerLiquidacionesDeAnfitrion (EVT `anfitrion.suspendido`).
 *
 * Pausa el dinero de un anfitrion suspendido por MS-01.
 *
 * El esquema no tiene un estado "RETENIDA" para `liquidacion`, asi que "pausar"
 * se implementa como: anular las liquidaciones que todavia no se pagaron y
 * liberar sus comisiones, que vuelven a LIQUIDABLE. Es reversible por
 * construccion: cuando MS-01 reactive al anfitrion, `verificados()` lo vuelve a
 * incluir y la siguiente corrida de CU-LIQ-04 regenera su liquidacion con las
 * mismas comisiones. Una liquidacion ya PAGADA no se toca.
 *
 * El otro lado de la retencion lo da el paquete Referencias: `ref_anfitrion`
 * queda en SUSPENDIDO y CU-LIQ-04 solo recorre verificados, asi que no se
 * generan nuevas.
 */
final class RetenerLiquidacionesDeAnfitrionHandler
{
    public function __construct(
        private readonly LiquidacionRepository $liquidaciones,
        private readonly ComisionRepository $comisiones,
        private readonly AuditLogger $auditoria,
        private readonly TransactionManager $tx,
    ) {
    }

    /** @return list<string> ids de las liquidaciones retenidas */
    public function __invoke(RetenerLiquidacionesDeAnfitrionCommand $comando): array
    {
        $pendientes = $this->liquidaciones->pendientesDePago($comando->anfitrionId);

        if ($pendientes === []) {
            return [];
        }

        return $this->tx->ejecutar(function () use ($pendientes, $comando): array {
            $retenidas = [];

            foreach ($pendientes as $liquidacion) {
                $estadoAnterior = $liquidacion->estado()->value;

                $liquidacion->anular($comando->motivo);
                $this->liquidaciones->guardar($liquidacion);

                foreach ($this->comisiones->deLiquidacion($liquidacion->liquidacionId()) as $comision) {
                    $comision->liberar();
                    $this->comisiones->guardar($comision);
                }

                $this->auditoria->registrar(
                    'RETENER_LIQUIDACION',
                    'liquidacion',
                    $liquidacion->liquidacionId(),
                    ['estado' => $estadoAnterior],
                    ['estado' => $liquidacion->estado()->value, 'motivo' => $comando->motivo],
                    null,
                );

                $retenidas[] = $liquidacion->liquidacionId();
            }

            return $retenidas;
        });
    }
}
