<?php

declare(strict_types=1);

namespace Src\Pagos\Application\UseCase\RevisarDiferenciaConciliacion;

use DomainException;
use Src\Pagos\Domain\Conciliacion;
use Src\Pagos\Domain\ConciliacionRepository;
use Src\Shared\Application\Port\AuditLogger;
use Src\Shared\Application\Port\TransactionManager;

/**
 * CU-PAG-12 RevisarDiferenciaConciliacion (NEG, REST).
 *
 * El Administrador explica un item que no cuadro y, si ya no queda nada
 * pendiente, cierra la conciliacion. Queda auditado con su nombre: una
 * diferencia de dinero explicada por nadie no sirve de nada.
 */
final class RevisarDiferenciaConciliacionHandler
{
    public function __construct(
        private readonly ConciliacionRepository $conciliaciones,
        private readonly AuditLogger $auditoria,
        private readonly TransactionManager $tx,
    ) {
    }

    public function __invoke(RevisarDiferenciaConciliacionCommand $comando): Conciliacion
    {
        $conciliacion = $this->conciliaciones->buscar($comando->conciliacionId)
            ?? throw new DomainException("La conciliacion {$comando->conciliacionId} no existe");

        $estadoAnterior = $conciliacion->estado()->value;

        return $this->tx->ejecutar(function () use ($conciliacion, $comando, $estadoAnterior): Conciliacion {
            $conciliacion->revisarItem(
                $comando->referenciaPasarela,
                $comando->observacion,
                $comando->transaccionId,
            );

            if ($comando->cerrar) {
                $conciliacion->cerrar();
            }

            $this->conciliaciones->guardar($conciliacion);

            $this->auditoria->registrar(
                'REVISAR_DIFERENCIA_CONCILIACION',
                'conciliacion',
                $conciliacion->conciliacionId(),
                ['estado' => $estadoAnterior],
                [
                    'estado' => $conciliacion->estado()->value,
                    'referencia_pasarela' => $comando->referenciaPasarela,
                    'observacion' => $comando->observacion,
                ],
                $comando->revisadoPor,
            );

            return $conciliacion;
        });
    }
}
