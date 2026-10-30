<?php

declare(strict_types=1);

namespace Src\Financiero\Application\UseCase\ProcesarPago;

use InvalidArgumentException;
use Src\Financiero\Domain\Enum\TipoTransaccion;
use Src\Shared\Domain\Money;

final class RecibirNotificacionPasarelaRequest
{
    /** @param array<string, mixed> $payload cuerpo crudo del webhook, ya validado por la firma */
    public function __construct(
        public readonly string $pagoId,
        public readonly string $referenciaPasarela,
        public readonly bool $exitosa,
        public readonly ?string $pasarela = null,
        public readonly ?string $motivo = null,
        public readonly ?Money $montoNotificado = null,
        public readonly ?TipoTransaccion $tipo = null,
        public readonly array $payload = [],
    ) {
        if (trim($this->referenciaPasarela) === '') {
            throw new InvalidArgumentException('El webhook necesita la referencia de la pasarela');
        }
    }
}
