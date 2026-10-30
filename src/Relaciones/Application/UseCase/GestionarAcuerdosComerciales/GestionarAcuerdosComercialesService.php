<?php

declare(strict_types=1);

namespace Src\Relaciones\Application\UseCase\GestionarAcuerdosComerciales;

use Src\Relaciones\Application\Port\AcuerdoComercialRepository;
use Src\Relaciones\Application\Response\AcuerdoComercialResponse;
use Src\Relaciones\Domain\Entity\AcuerdoComercial;
use Src\Relaciones\Domain\Enum\EstadoAcuerdo;
use Src\Shared\Application\Port\AuditLogger;
use Src\Shared\Application\Port\Clock;
use Src\Shared\Application\Port\EventPublisher;
use Src\Shared\Application\Port\TransactionManager;
use Src\Shared\Application\Port\UuidGenerator;
use Src\Shared\Domain\Exception\ConflictoDeEstado;
use Src\Shared\Domain\Exception\RecursoNoEncontrado;
use Src\Shared\Domain\Exception\ReglaDeNegocioViolada;

/** Caso de uso GestionarAcuerdosComerciales: implementa la interfaz de entrada GestionarAcuerdosComercialesUseCase. */
final class GestionarAcuerdosComercialesService implements GestionarAcuerdosComercialesUseCase
{
    public function __construct(
        private readonly AcuerdoComercialRepository $acuerdos,
        private readonly UuidGenerator $uuid,
        private readonly AuditLogger $auditoria,
        private readonly EventPublisher $eventos,
        private readonly TransactionManager $transacciones,
        private readonly Clock $clock,
    ) {}

    /** Todo acuerdo nace en BORRADOR; activarlo es . */
    public function crear(CrearAcuerdoBorradorRequest $comando): AcuerdoComercialResponse
    {
        $acuerdo = AcuerdoComercial::crearBorrador(
            acuerdoId: $this->uuid->generar(),
            anfitrionId: $comando->anfitrionId,
            fechaInicio: $comando->fechaInicio,
            diasLiquidacion: $comando->diasLiquidacion,
            reglaComisionId: $comando->reglaComisionId,
            fechaFin: $comando->fechaFin,
            condiciones: $comando->condiciones,
        );

        $this->acuerdos->guardar($acuerdo);
        $this->auditoria->registrar(
            'CREAR_ACUERDO_COMERCIAL',
            'acuerdo_comercial',
            $acuerdo->acuerdoId(),
            null,
            $this->retrato($acuerdo),
            $comando->creadoPor,
        );

        return AcuerdoComercialResponse::desde($acuerdo);
    }

    public function actualizarCondiciones(ActualizarCondicionesAcuerdoRequest $comando): AcuerdoComercialResponse
    {
        $acuerdo = $this->obligatorio($comando->acuerdoId);
        $antes = $this->retrato($acuerdo);

        $acuerdo->actualizarCondiciones(
            diasLiquidacion: $comando->diasLiquidacion,
            reglaComisionId: $comando->reglaComisionId,
            condiciones: $comando->condiciones,
            fechaFin: $comando->fechaFin,
        );

        $this->acuerdos->guardar($acuerdo);
        $this->auditoria->registrar(
            'ACTUALIZAR_ACUERDO_COMERCIAL',
            'acuerdo_comercial',
            $acuerdo->acuerdoId(),
            $antes,
            $this->retrato($acuerdo),
            $comando->actualizadoPor,
        );

        return AcuerdoComercialResponse::desde($acuerdo);
    }

    /** @return list<AcuerdoComercial> */
    public function listar(?EstadoAcuerdo $estado = null, ?string $anfitrionId = null): array
    {
        return array_map(AcuerdoComercialResponse::desde(...), $this->acuerdos->listar($estado, $anfitrionId));
    }

    public function consultar(string $acuerdoId): AcuerdoComercialResponse
    {
        return AcuerdoComercialResponse::desde($this->obligatorio($acuerdoId));
    }

    private function obligatorio(string $acuerdoId): AcuerdoComercial
    {
        return $this->acuerdos->buscar($acuerdoId)
            ?? throw new RecursoNoEncontrado("El acuerdo {$acuerdoId} no existe");
    }

    /** @return array<string, mixed> */
    private function retrato(AcuerdoComercial $acuerdo): array
    {
        return [
            'anfitrion_id' => $acuerdo->anfitrionId(),
            'estado' => $acuerdo->estado()->value,
            'regla_comision_id' => $acuerdo->reglaComisionId(),
            'dias_liquidacion' => $acuerdo->diasLiquidacion(),
            'fecha_inicio' => $acuerdo->fechaInicio()->format('Y-m-d'),
            'fecha_fin' => $acuerdo->fechaFin()?->format('Y-m-d'),
            'condiciones' => $acuerdo->condiciones(),
        ];
    }

    /**
     * Disparador: REST / EVT / JOB.
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
    public function cambiarEstado(CambiarEstadoAcuerdoRequest $comando): AcuerdoComercialResponse
    {
        return AcuerdoComercialResponse::desde($this->transacciones->ejecutar(function () use ($comando): AcuerdoComercial {
            $acuerdo = $this->acuerdos->buscar($comando->acuerdoId)
                ?? throw new RecursoNoEncontrado("El acuerdo {$comando->acuerdoId} no existe");

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
                EstadoAcuerdo::BORRADOR => throw new ConflictoDeEstado(
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
        }));
    }

    private function activar(AcuerdoComercial $acuerdo, CambiarEstadoAcuerdoRequest $comando): void
    {
        $vigente = $this->acuerdos->activoDe($acuerdo->anfitrionId());

        // Reactivar el mismo acuerdo suspendido no choca consigo mismo.
        if ($vigente !== null && $vigente->acuerdoId() !== $acuerdo->acuerdoId()) {
            throw new ReglaDeNegocioViolada(
                sprintf(
                    'El anfitrion %s ya tiene el acuerdo %s ACTIVO: finalicelo o suspendalo antes',
                    $acuerdo->anfitrionId(),
                    $vigente->acuerdoId(),
                ),
            );
        }

        $acuerdo->activar($comando->solicitadoPor, $this->clock->ahora(), $comando->sagaId);
    }

    private function motivoObligatorio(CambiarEstadoAcuerdoRequest $comando): string
    {
        return $comando->motivo
            ?? throw new ReglaDeNegocioViolada(
                "Pasar a {$comando->estadoDestino->value} necesita un motivo",
            );
    }

    /**
     * JOB: pasa a FINALIZADO los acuerdos ACTIVO cuya fecha de fin ya paso.
     *
     * @return list<string> ids de los acuerdos finalizados
     */
    public function finalizarVencidos(): array
    {
        $hoy = $this->clock->ahora()->format('Y-m-d');
        $finalizados = [];

        foreach ($this->acuerdos->listar(EstadoAcuerdo::ACTIVO) as $acuerdo) {
            $fin = $acuerdo->fechaFin();
            if ($fin === null || $fin->format('Y-m-d') >= $hoy) {
                continue;
            }

            $this->transacciones->ejecutar(function () use ($acuerdo, &$finalizados): void {
                $acuerdo->finalizar('Vigencia vencida');
                $this->acuerdos->guardar($acuerdo);
                $this->eventos->publicar($acuerdo->sacarEventos());
                $this->auditoria->registrar(
                    'FINALIZAR_ACUERDO_VENCIDO',
                    'acuerdo_comercial',
                    $acuerdo->acuerdoId(),
                    ['estado' => EstadoAcuerdo::ACTIVO->value],
                    ['estado' => $acuerdo->estado()->value, 'motivo' => $acuerdo->motivoEstado()],
                    null,
                );
            });
            $finalizados[] = $acuerdo->acuerdoId();
        }

        return $finalizados;
    }
}
