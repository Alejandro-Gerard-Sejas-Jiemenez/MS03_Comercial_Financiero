<?php

declare(strict_types=1);

namespace Src\Acuerdos\Domain;

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
     * CU-ACU-02 protege la regla de un solo acuerdo activo.
     */
    public function activoDe(string $anfitrionId): ?AcuerdoComercial;

    /**
     * Todos los acuerdos del anfitrion, del mas reciente al mas antiguo.
     *
     * @return list<AcuerdoComercial>
     */
    public function historialDe(string $anfitrionId): array;

    /** @return list<AcuerdoComercial> */
    public function listar(?EstadoAcuerdo $estado = null, ?string $anfitrionId = null): array;
}
