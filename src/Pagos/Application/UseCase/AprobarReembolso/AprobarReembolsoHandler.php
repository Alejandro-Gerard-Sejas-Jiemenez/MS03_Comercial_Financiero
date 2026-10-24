<?php

declare(strict_types=1);

namespace Src\Pagos\Application\UseCase\AprobarReembolso;

use DomainException;
use Src\Pagos\Domain\Reembolso;
use Src\Pagos\Domain\ReembolsoRepository;
use Src\Shared\Application\Port\AuditLogger;
use Src\Shared\Application\Port\TransactionManager;

/**
 * CU-PAG-08 AprobarReembolso (NEG, REST).
 *
 * Decision humana del Administrador: APROBADO o RECHAZADO. Es un caso de uso
 * aparte de CU-PAG-07 y CU-PAG-09 porque lo dispara una persona distinta y
 * queda auditado con su nombre.
 */
final class AprobarReembolsoHandler
{
    public function __construct(
        private readonly ReembolsoRepository $reembolsos,
        private readonly AuditLogger $auditoria,
        private readonly TransactionManager $tx,
    ) {
    }

    public function __invoke(AprobarReembolsoCommand $comando): Reembolso
    {
        $reembolso = $this->reembolsos->buscar($comando->reembolsoId)
            ?? throw new DomainException("El reembolso {$comando->reembolsoId} no existe");

        $estadoAnterior = $reembolso->estado()->value;

        return $this->tx->ejecutar(function () use ($reembolso, $comando, $estadoAnterior): Reembolso {
            if ($comando->aprobado) {
                $reembolso->aprobar($comando->detalle);
            } else {
                $reembolso->rechazar(
                    $comando->detalle ?? throw new DomainException('Un rechazo necesita explicacion'),
                );
            }

            $this->reembolsos->guardar($reembolso);

            $this->auditoria->registrar(
                $comando->aprobado ? 'APROBAR_REEMBOLSO' : 'RECHAZAR_REEMBOLSO',
                'reembolso',
                $reembolso->reembolsoId(),
                ['estado' => $estadoAnterior],
                ['estado' => $reembolso->estado()->value, 'detalle' => $reembolso->detalle()],
                $comando->revisadoPor,
            );

            return $reembolso;
        });
    }
}
