<?php

declare(strict_types=1);

namespace Src\Liquidaciones\Domain;

use Src\Shared\Domain\Money;

interface LibroContableRepository
{
    /** Inserta las lineas del asiento; la tabla no admite UPDATE ni DELETE. */
    public function registrar(Asiento $asiento): void;

    /**
     * Saldo de la cuenta POR_PAGAR_ANFITRION: creditos menos debitos. Es lo que
     * la plataforma le debe hoy a ese anfitrion.
     */
    public function saldoPorPagarDe(string $anfitrionId, string $moneda = 'BOB'): Money;
}
