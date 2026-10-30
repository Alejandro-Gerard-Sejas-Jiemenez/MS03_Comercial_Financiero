<?php

declare(strict_types=1);

namespace Src\Relaciones\Application\Port;

/**
 * Puerto de lectura que consume Financiero: sin un metodo principal y
 * verificado no se le puede pagar al anfitrion.
 */
interface MetodoCobroQuery
{
    /** `metodo_id` del metodo de cobro principal y verificado del anfitrion, o null. */
    public function metodoPrincipalVerificado(string $anfitrionId): ?string;
}
