<?php

declare(strict_types=1);

namespace Src\Acuerdos\Application\UseCase\GestionarAcuerdos;

use DomainException;
use Src\Acuerdos\Domain\AcuerdoComercial;
use Src\Acuerdos\Domain\AcuerdoComercialRepository;
use Src\Acuerdos\Domain\EstadoAcuerdo;
use Src\Shared\Application\Port\AuditLogger;
use Src\Shared\Application\Port\UuidGenerator;

/**
 * CU-ACU-01 GestionarAcuerdos (CRUD, disparador REST).
 *
 * El CRUD puro va junto en un servicio `Gestionar...`, como decide el documento
 * de arquitectura. Los cambios de estado NO estan aqui: mueven estado y emiten
 * eventos, asi que tienen su propio Command + Handler (CU-ACU-02).
 *
 * Autorizacion: escribe el Administrador. Esa comprobacion la hara el
 * middleware de la capa de Presentacion.
 */
final class GestionarAcuerdosService
{
    public function __construct(
        private readonly AcuerdoComercialRepository $acuerdos,
        private readonly UuidGenerator $uuid,
        private readonly AuditLogger $auditoria,
    ) {
    }

    /** Todo acuerdo nace en BORRADOR; activarlo es CU-ACU-02. */
    public function crear(CrearAcuerdoBorradorCommand $comando): AcuerdoComercial
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

        return $acuerdo;
    }

    public function actualizarCondiciones(ActualizarCondicionesAcuerdoCommand $comando): AcuerdoComercial
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

        return $acuerdo;
    }

    /** @return list<AcuerdoComercial> */
    public function listar(?EstadoAcuerdo $estado = null, ?string $anfitrionId = null): array
    {
        return $this->acuerdos->listar($estado, $anfitrionId);
    }

    /**
     * Historial del anfitrion, del mas reciente al mas antiguo. Los acuerdos no
     * se borran: el historico sostiene las liquidaciones ya hechas.
     *
     * @return list<AcuerdoComercial>
     */
    public function historialDe(string $anfitrionId): array
    {
        return $this->acuerdos->historialDe($anfitrionId);
    }

    public function consultar(string $acuerdoId): AcuerdoComercial
    {
        return $this->obligatorio($acuerdoId);
    }

    private function obligatorio(string $acuerdoId): AcuerdoComercial
    {
        return $this->acuerdos->buscar($acuerdoId)
            ?? throw new DomainException("El acuerdo {$acuerdoId} no existe");
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
}
