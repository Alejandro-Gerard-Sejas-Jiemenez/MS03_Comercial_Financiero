<?php

declare(strict_types=1);

namespace Src\Relaciones\Application\Port;

/**
 * Puerto de lectura que consume Financiero (plazo de liquidacion y tarifa
 * negociada). Ningun otro modulo lee las tablas de acuerdos: pide los datos
 * por esta interfaz.
 */
interface AcuerdoComercialQuery
{
    /** Acuerdo ACTIVO del anfitrion, o null si no tiene ninguno. */
    public function acuerdoActivoDe(string $anfitrionId): ?AcuerdoVigente;
}
