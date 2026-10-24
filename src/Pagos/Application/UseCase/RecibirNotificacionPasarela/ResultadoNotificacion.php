<?php

declare(strict_types=1);

namespace Src\Pagos\Application\UseCase\RecibirNotificacionPasarela;

final class ResultadoNotificacion
{
    private function __construct(
        public readonly string $referenciaPasarela,
        public readonly bool $aplicada,
        public readonly bool $cambioElEstado,
    ) {
    }

    public static function aplicada(string $referencia, bool $cambioElEstado): self
    {
        return new self($referencia, true, $cambioElEstado);
    }

    /** Webhook repetido: ya habia una transaccion con esa referencia. */
    public static function yaProcesada(string $referencia): self
    {
        return new self($referencia, false, false);
    }
}
