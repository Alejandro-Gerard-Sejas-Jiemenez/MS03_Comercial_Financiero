<?php

declare(strict_types=1);

namespace Src\Comercial\Application\Port;

use DateTimeImmutable;
use Src\Comercial\Domain\Entity\Promocion;
use Src\Comercial\Domain\Enum\EstadoPromocion;
use Src\Comercial\Domain\Enum\TipoPromocion;

/**
 * Interfaz definida por el Dominio e implementada por Infraestructura
 * (Principio de Inversion de Dependencias).
 */
interface PromocionRepository
{
    public function buscar(string $promocionId): ?Promocion;

    /** El codigo de cupon es unico en la tabla; se busca normalizado en mayusculas. */
    public function buscarPorCodigo(string $codigo): ?Promocion;

    /** Guarda la promocion y sus alojamientos asignados (`promocion_alojamiento`). */
    public function guardar(Promocion $promocion): void;

    /**
     * Promociones ACTIVAS sin codigo de cupon vigentes en la fecha, cuyo alcance
     * alcanza al alojamiento y al anfitrion dados. Son las que se aplican solas,
     * sin que el cliente escriba nada (temporada, ultimo minuto, estadia larga).
     *
     * @return list<Promocion>
     */
    public function vigentesSinCupon(
        DateTimeImmutable $fecha,
        ?string $alojamientoId = null,
        ?string $anfitrionId = null,
    ): array;

    /**
     * Promociones que ya pasaron su `vigente_hasta` y todavia no estan VENCIDAS
     * ni CANCELADAS. Es la consulta del JOB .
     *
     * @return list<Promocion>
     */
    public function vencibles(DateTimeImmutable $fecha): array;

    /** @return list<Promocion> */
    public function listar(
        ?string $anfitrionId = null,
        ?EstadoPromocion $estado = null,
        ?TipoPromocion $tipo = null,
    ): array;
}
