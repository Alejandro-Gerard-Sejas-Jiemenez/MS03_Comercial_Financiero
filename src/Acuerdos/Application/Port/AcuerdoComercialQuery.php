<?php

declare(strict_types=1);

namespace Src\Acuerdos\Application\Port;

/**
 * Puerto de lectura que consumen Liquidaciones (plazo de liquidacion) y Pagos
 * (tarifa negociada). Ningun paquete lee las tablas de Acuerdos: piden los
 * datos por esta interfaz de la capa de Aplicacion.
 */
interface AcuerdoComercialQuery
{
    /** Acuerdo ACTIVO del anfitrion, o null si no tiene ninguno. */
    public function acuerdoActivoDe(string $anfitrionId): ?AcuerdoVigente;
}
