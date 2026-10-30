<?php

declare(strict_types=1);

namespace Src\Relaciones\Application\UseCase\GestionarMetodosCobro;

use Src\Relaciones\Domain\Enum\TipoMetodoCobro;

/**
 * `numero` llega en claro desde el controlador y NO se guarda asi: el caso de
 * uso lo cifra con el puerto `CifradorDeDatos` antes de construir la entidad.
 */
final class RegistrarMetodoCobroRequest
{
    public function __construct(
        public readonly string $anfitrionId,
        public readonly TipoMetodoCobro $tipo,
        public readonly string $titular,
        public readonly string $numero,
        public readonly string $registradoPor,
        public readonly ?string $banco = null,
        public readonly bool $esPrincipal = false,
    ) {}
}
