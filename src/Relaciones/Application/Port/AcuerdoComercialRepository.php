<?php

declare(strict_types=1);

namespace Src\Relaciones\Application\Port;

use Src\Relaciones\Domain\Entity\AcuerdoComercial;
use Src\Relaciones\Domain\Enum\EstadoAcuerdo;

/**
 * Interfaz definida por el Dominio e implementada por Infraestructura
 * (Principio de Inversion de Dependencias).
 */
interface AcuerdoComercialRepository
{
    public function buscar(string $acuerdoId): ?AcuerdoComercial;

    public function guardar(AcuerdoComercial $acuerdo): void;

    /**
     * El unico acuerdo ACTIVO del anfitrion, o null. Es la consulta con la que
     * protege la regla de un solo acuerdo activo.
     */
    public function activoDe(string $anfitrionId): ?AcuerdoComercial;

    /** @return list<AcuerdoComercial> */
    public function listar(?EstadoAcuerdo $estado = null, ?string $anfitrionId = null): array;
}
