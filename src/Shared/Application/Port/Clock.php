<?php

declare(strict_types=1);

namespace Src\Shared\Application\Port;

use DateTimeImmutable;

/**
 * El tiempo como dependencia explicita: las reglas con fechas (vigencia de una
 * promocion, `liquidable_desde`) se prueban sin esperar al calendario.
 */
interface Clock
{
    public function ahora(): DateTimeImmutable;

    /** Fecha de hoy a las 00:00, para comparar con columnas DATE. */
    public function hoy(): DateTimeImmutable;
}
