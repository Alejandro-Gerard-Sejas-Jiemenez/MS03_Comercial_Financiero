<?php

declare(strict_types=1);

namespace Src\Acuerdos\Application\UseCase\GestionarMetodosCobro;

use Src\Acuerdos\Domain\TipoMetodoCobro;

/**
 * `numero` llega en claro desde la Presentacion y NO se guarda asi: el caso de
 * uso lo cifra con el puerto `CifradorDeDatos` antes de construir la entidad.
 */
final class RegistrarMetodoCobroCommand
{
    public function __construct(
        public readonly string $anfitrionId,
        public readonly TipoMetodoCobro $tipo,
        public readonly string $titular,
        public readonly string $numero,
        public readonly string $registradoPor,
        public readonly ?string $banco = null,
        public readonly bool $esPrincipal = false,
    ) {
    }
}
