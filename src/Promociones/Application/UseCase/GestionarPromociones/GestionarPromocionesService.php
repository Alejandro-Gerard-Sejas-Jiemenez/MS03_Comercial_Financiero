<?php

declare(strict_types=1);

namespace Src\Promociones\Application\UseCase\GestionarPromociones;

use Src\Promociones\Domain\EstadoPromocion;
use Src\Promociones\Domain\Promocion;
use Src\Promociones\Domain\PromocionRepository;
use Src\Promociones\Domain\TipoPromocion;
use Src\Shared\Application\Port\AuditLogger;
use Src\Shared\Application\Port\UuidGenerator;
use Src\Shared\Domain\Exception\ConflictoDeEstado;
use Src\Shared\Domain\Exception\RecursoNoEncontrado;
use Src\Shared\Domain\Exception\ReglaDeNegocioViolada;

/**
 * CU-PRO-01 GestionarPromociones (CRUD, disparador REST).
 *
 * El CRUD puro va junto en un servicio `Gestionar...`, como decide el documento
 * de arquitectura. Las reglas (vigencia, tope de usos, estados) siguen en la
 * entidad `Promocion`.
 *
 * Autorizacion: un ANFITRION solo gestiona sus promociones y el ADMIN las de
 * la plataforma. Esa comprobacion la hara el middleware de la capa de
 * Presentacion, que todavia no existe.
 */
final class GestionarPromocionesService
{
    public function __construct(
        private readonly PromocionRepository $promociones,
        private readonly UuidGenerator $uuid,
        private readonly AuditLogger $auditoria,
    ) {}

    public function crear(CrearPromocionCommand $comando): Promocion
    {
        if ($comando->codigo !== null) {
            $codigo = Promocion::normalizarCodigo($comando->codigo);

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

        return $promocion;
    }

    public function actualizar(ActualizarPromocionCommand $comando): Promocion
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
            $promocion->cambiarVigencia(
                $comando->vigenteDesde ?? $promocion->vigenteDesde(),
                $comando->vigenteHasta ?? $promocion->vigenteHasta(),
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

        return $promocion;
    }

    /** Reemplaza las filas de `promocion_alojamiento` de esa promocion. */
    public function asignarAlojamientos(AsignarAlojamientosCommand $comando): Promocion
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

        return $promocion;
    }

    public function activar(string $promocionId, string $activadoPor): Promocion
    {
        return $this->cambiarEstado($promocionId, $activadoPor, 'ACTIVAR_PROMOCION');
    }

    public function pausar(string $promocionId, string $pausadoPor): Promocion
    {
        return $this->cambiarEstado($promocionId, $pausadoPor, 'PAUSAR_PROMOCION');
    }

    /**
     * Las promociones no se borran: se cancelan. El historico de descuentos ya
     * aplicados vive en los pagos y en los eventos `promocion.aplicada`.
     */
    public function cancelar(string $promocionId, string $canceladoPor): Promocion
    {
        return $this->cambiarEstado($promocionId, $canceladoPor, 'CANCELAR_PROMOCION');
    }

    /** @return list<Promocion> */
    public function listar(
        ?string $anfitrionId = null,
        ?EstadoPromocion $estado = null,
        ?TipoPromocion $tipo = null,
    ): array {
        return $this->promociones->listar($anfitrionId, $estado, $tipo);
    }

    public function consultar(string $promocionId): Promocion
    {
        return $this->obligatoria($promocionId);
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
        return [
            'nombre' => $promocion->nombre(),
            'codigo' => $promocion->codigo(),
            'valor' => $promocion->valor(),
            'min_noches' => $promocion->minNoches(),
            'max_usos' => $promocion->maxUsos(),
            'usos_actuales' => $promocion->usosActuales(),
            'vigente_desde' => $promocion->vigenteDesde()->format('Y-m-d H:i:s'),
            'vigente_hasta' => $promocion->vigenteHasta()->format('Y-m-d H:i:s'),
            'financiada_por' => $promocion->financiadaPor()->value,
            'estado' => $promocion->estado()->value,
            'alojamientos' => $promocion->alojamientos(),
        ];
    }
}
