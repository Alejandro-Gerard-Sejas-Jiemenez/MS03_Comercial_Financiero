<?php

declare(strict_types=1);

namespace Src\Pagos\Domain;

use DateTimeImmutable;
use Src\Shared\Domain\Money;

/**
 * Una linea del reporte de la pasarela. Estructura de datos simple que entra
 * desde el exterior por el puerto `PasarelaPago`; el dominio la compara, no la
 * obtiene.
 */
final class MovimientoPasarela
{
    public function __construct(
        public readonly string $referenciaPasarela,
        public readonly Money $monto,
        public readonly ?DateTimeImmutable $fecha = null,
        public readonly ?string $tipo = null,
    ) {
    }
}
