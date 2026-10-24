<?php

declare(strict_types=1);

namespace Src\Acuerdos\Application\UseCase\CambiarEstadoAcuerdo;

use DomainException;
use Src\Acuerdos\Domain\AcuerdoComercial;
use Src\Acuerdos\Domain\AcuerdoComercialRepository;
use Src\Acuerdos\Domain\EstadoAcuerdo;
use Src\Shared\Application\Port\AuditLogger;
use Src\Shared\Application\Port\Clock;
use Src\Shared\Application\Port\EventPublisher;
use Src\Shared\Application\Port\TransactionManager;

/**
 * CU-ACU-02 CambiarEstadoAcuerdo (NEG; disparadores REST / EVT / JOB).
 *
 * Regla dura: un anfitrion tiene un solo acuerdo ACTIVO. La entidad no puede
 * verla sola (mira otros acuerdos), asi que se comprueba aqui contra el
 * repositorio; la base la respalda con el indice unico parcial
 * `uq_acuerdo_activo_por_anfitrion`, que ataja la carrera entre dos peticiones
 * simultaneas.
 *
 * Guardar y publicar van en la misma transaccion (patron Outbox): si falla el
 * guardado, el evento no sale; si sale el evento, el estado ya esta guardado.
 */
final class CambiarEstadoAcuerdoHandler
{
    public function __construct(
        private readonly AcuerdoComercialRepository $acuerdos,
        private readonly EventPublisher $eventos,
        private readonly TransactionManager $transacciones,
        private readonly Clock $clock,
        private readonly AuditLogger $auditoria,
    ) {
    }

    public function manejar(CambiarEstadoAcuerdoCommand $comando): AcuerdoComercial
    {
        return $this->transacciones->ejecutar(function () use ($comando): AcuerdoComercial {
            $acuerdo = $this->acuerdos->buscar($comando->acuerdoId)
                ?? throw new DomainException("El acuerdo {$comando->acuerdoId} no existe");

            $estadoAnterior = $acuerdo->estado();

            match ($comando->estadoDestino) {
                EstadoAcuerdo::ACTIVO => $this->activar($acuerdo, $comando),
                EstadoAcuerdo::SUSPENDIDO => $acuerdo->suspender(
                    $this->motivoObligatorio($comando),
                    $comando->sagaId,
                ),
                EstadoAcuerdo::FINALIZADO => $acuerdo->finalizar($comando->motivo, $comando->sagaId),
                EstadoAcuerdo::RESCINDIDO => $acuerdo->rescindir(
                    $this->motivoObligatorio($comando),
                    $comando->sagaId,
                ),
                EstadoAcuerdo::BORRADOR => throw new DomainException(
                    'Un acuerdo no vuelve a BORRADOR',
                ),
            };

            $this->acuerdos->guardar($acuerdo);
            $this->eventos->publicar($acuerdo->sacarEventos());

            $this->auditoria->registrar(
                'CAMBIAR_ESTADO_ACUERDO',
                'acuerdo_comercial',
                $acuerdo->acuerdoId(),
                ['estado' => $estadoAnterior->value],
                ['estado' => $acuerdo->estado()->value, 'motivo' => $acuerdo->motivoEstado()],
                $comando->solicitadoPor,
            );

            return $acuerdo;
        });
    }

    private function activar(AcuerdoComercial $acuerdo, CambiarEstadoAcuerdoCommand $comando): void
    {
        $vigente = $this->acuerdos->activoDe($acuerdo->anfitrionId());

        // Reactivar el mismo acuerdo suspendido no choca consigo mismo.
        if ($vigente !== null && $vigente->acuerdoId() !== $acuerdo->acuerdoId()) {
            throw new DomainException(
                sprintf(
                    'El anfitrion %s ya tiene el acuerdo %s ACTIVO: finalicelo o suspendalo antes',
                    $acuerdo->anfitrionId(),
                    $vigente->acuerdoId(),
                ),
            );
        }

        $acuerdo->activar($comando->solicitadoPor, $this->clock->ahora(), $comando->sagaId);
    }

    private function motivoObligatorio(CambiarEstadoAcuerdoCommand $comando): string
    {
        return $comando->motivo
            ?? throw new DomainException(
                "Pasar a {$comando->estadoDestino->value} necesita un motivo",
            );
    }
}
