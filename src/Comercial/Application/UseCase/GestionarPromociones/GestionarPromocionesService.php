<?php

declare(strict_types=1);

namespace Src\Comercial\Application\UseCase\GestionarPromociones;

use Src\Comercial\Application\Port\PromocionRepository;
use Src\Comercial\Application\Response\PromocionResponse;
use Src\Comercial\Domain\Entity\Promocion;
use Src\Comercial\Domain\Enum\EstadoPromocion;
use Src\Comercial\Domain\Enum\TipoPromocion;
use Src\Comercial\Domain\ValueObject\CodigoCupon;
use Src\Shared\Application\Port\AuditLogger;
use Src\Shared\Application\Port\Clock;
use Src\Shared\Application\Port\TransactionManager;
use Src\Shared\Application\Port\UuidGenerator;
use Src\Shared\Domain\Exception\ConflictoDeEstado;
use Src\Shared\Domain\Exception\RecursoNoEncontrado;
use Src\Shared\Domain\Exception\ReglaDeNegocioViolada;

/** Caso de uso GestionarPromociones: implementa la interfaz de entrada GestionarPromocionesUseCase. */
final class GestionarPromocionesService implements GestionarPromocionesUseCase
{
    public function __construct(
        private readonly PromocionRepository $promociones,
        private readonly UuidGenerator $uuid,
        private readonly AuditLogger $auditoria,
        private readonly Clock $clock,
        private readonly TransactionManager $transacciones,
    ) {}

    public function crear(CrearPromocionRequest $comando): PromocionResponse
    {
        if ($comando->codigo !== null) {
            $codigo = CodigoCupon::desde($comando->codigo)->valor();

            if ($this->promociones->buscarPorCodigo($codigo) !== null) {
                throw new ConflictoDeEstado("Ya existe una promocion con el cupon {$codigo}");
            }
        }

        $promocion = Promocion::crear(
            promocionId: $this->uuid->generar(),
            nombre: $comando->nombre,
            tipo: $comando->tipo,
            tipoDescuento: $comando->tipoDescuento,
            valor: $comando->valor,
            vigenteDesde: $comando->vigenteDesde,
            vigenteHasta: $comando->vigenteHasta,
            creadaPorRol: $comando->creadaPorRol,
            creadaPor: $comando->creadaPor,
            codigo: $comando->codigo,
            anfitrionId: $comando->anfitrionId,
            financiadaPor: $comando->financiadaPor,
            minNoches: $comando->minNoches,
            maxUsos: $comando->maxUsos,
            moneda: $comando->moneda,
            alojamientos: $comando->alojamientos,
        );

        $this->promociones->guardar($promocion);
        $this->auditoria->registrar(
            'CREAR_PROMOCION',
            'promocion',
            $promocion->promocionId(),
            null,
            $this->retrato($promocion),
            $comando->creadaPor,
        );

        return PromocionResponse::desde($promocion);
    }

    public function actualizar(ActualizarPromocionRequest $comando): PromocionResponse
    {
        $promocion = $this->obligatoria($comando->promocionId);
        $antes = $this->retrato($promocion);

        if ($comando->nombre !== null) {
            $promocion->renombrar($comando->nombre);
        }

        if ($comando->valor !== null) {
            $promocion->cambiarValor($comando->valor);
        }

        if ($comando->vigenteDesde !== null || $comando->vigenteHasta !== null) {
            $actual = $promocion->retrato();

            $promocion->cambiarVigencia(
                $comando->vigenteDesde ?? $actual['vigente_desde'],
                $comando->vigenteHasta ?? $actual['vigente_hasta'],
            );
        }

        if ($comando->minNoches !== null) {
            $promocion->cambiarMinNoches($comando->minNoches);
        }

        if ($comando->maxUsos !== null) {
            $promocion->cambiarMaxUsos($comando->maxUsos);
        }

        if ($comando->financiadaPor !== null) {
            $promocion->cambiarFinanciamiento($comando->financiadaPor);
        }

        $this->promociones->guardar($promocion);
        $this->auditoria->registrar(
            'ACTUALIZAR_PROMOCION',
            'promocion',
            $promocion->promocionId(),
            $antes,
            $this->retrato($promocion),
            $comando->actualizadoPor,
        );

        return PromocionResponse::desde($promocion);
    }

    /** Reemplaza las filas de `promocion_alojamiento` de esa promocion. */
    public function asignarAlojamientos(AsignarAlojamientosRequest $comando): PromocionResponse
    {
        $promocion = $this->obligatoria($comando->promocionId);
        $antes = $this->retrato($promocion);

        $promocion->asignarAlojamientos($comando->alojamientos);

        $this->promociones->guardar($promocion);
        $this->auditoria->registrar(
            'ASIGNAR_ALOJAMIENTOS_PROMOCION',
            'promocion',
            $promocion->promocionId(),
            $antes,
            $this->retrato($promocion),
            $comando->asignadoPor,
        );

        return PromocionResponse::desde($promocion);
    }

    public function activar(string $promocionId, string $activadoPor): PromocionResponse
    {
        return PromocionResponse::desde($this->cambiarEstado($promocionId, $activadoPor, 'ACTIVAR_PROMOCION'));
    }

    public function pausar(string $promocionId, string $pausadoPor): PromocionResponse
    {
        return PromocionResponse::desde($this->cambiarEstado($promocionId, $pausadoPor, 'PAUSAR_PROMOCION'));
    }

    /**
     * Las promociones no se borran: se cancelan. El historico de descuentos ya
     * aplicados vive en los pagos y en los eventos `promocion.aplicada`.
     */
    public function cancelar(string $promocionId, string $canceladoPor): PromocionResponse
    {
        return PromocionResponse::desde($this->cambiarEstado($promocionId, $canceladoPor, 'CANCELAR_PROMOCION'));
    }

    /** @return list<Promocion> */
    public function listar(
        ?string $anfitrionId = null,
        ?EstadoPromocion $estado = null,
        ?TipoPromocion $tipo = null,
    ): array {
        return array_map(PromocionResponse::desde(...), $this->promociones->listar($anfitrionId, $estado, $tipo));
    }

    public function consultar(string $promocionId): PromocionResponse
    {
        return PromocionResponse::desde($this->obligatoria($promocionId));
    }

    private function cambiarEstado(string $promocionId, string $usuarioId, string $accion): Promocion
    {
        $promocion = $this->obligatoria($promocionId);
        $antes = $this->retrato($promocion);

        match ($accion) {
            'ACTIVAR_PROMOCION' => $promocion->activar(),
            'PAUSAR_PROMOCION' => $promocion->pausar(),
            'CANCELAR_PROMOCION' => $promocion->cancelar(),
            default => throw new ReglaDeNegocioViolada("Accion de estado desconocida: {$accion}"),
        };

        $this->promociones->guardar($promocion);
        $this->auditoria->registrar(
            $accion,
            'promocion',
            $promocionId,
            $antes,
            $this->retrato($promocion),
            $usuarioId,
        );

        return $promocion;
    }

    private function obligatoria(string $promocionId): Promocion
    {
        return $this->promociones->buscar($promocionId)
            ?? throw new RecursoNoEncontrado("La promocion {$promocionId} no existe");
    }

    /** @return array<string, mixed> */
    private function retrato(Promocion $promocion): array
    {
        $r = $promocion->retrato();

        return [
            'nombre' => $r['nombre'],
            'codigo' => $r['codigo'],
            'valor' => $r['valor'],
            'min_noches' => $r['min_noches'],
            'max_usos' => $r['max_usos'],
            'usos_actuales' => $r['usos_actuales'],
            'vigente_desde' => $r['vigente_desde']->format('Y-m-d H:i:s'),
            'vigente_hasta' => $r['vigente_hasta']->format('Y-m-d H:i:s'),
            'financiada_por' => $r['financiada_por'],
            'estado' => $r['estado'],
            'alojamientos' => $r['alojamientos'],
        ];
    }

    /**
     * Disparador: job programado.
     *
     * Pasa a VENCIDA solo las promociones cuya `vigente_hasta` ya paso y que
     * todavia no estan VENCIDAS ni CANCELADAS. La fecha viene del puerto Clock,
     * nunca de `now()`, para que el JOB se pueda probar sin esperar al calendario.
     */
    /**
     * @return list<string> ids de las promociones que pasaron a VENCIDA
     */
    public function vencerPorFecha(VencerPromocionesRequest $comando): array
    {
        $fecha = $comando->fecha ?? $this->clock->ahora();

        return $this->transacciones->ejecutar(function () use ($fecha): array {
            $vencidas = [];

            foreach ($this->promociones->vencibles($fecha) as $promocion) {
                $promocion->vencer($fecha);
                $this->promociones->guardar($promocion);
                $vencidas[] = $promocion->promocionId();
            }

            return $vencidas;
        });
    }
}
