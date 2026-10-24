<?php

declare(strict_types=1);

namespace Src\Promociones\Application\UseCase\GestionarPromociones;

/**
 * Reemplaza la lista de `promocion_alojamiento`. Una lista vacia significa
 * "todos los alojamientos del anfitrion" (o de la plataforma).
 */
final class AsignarAlojamientosCommand
{
    /** @param list<string> $alojamientos */
    public function __construct(
        public readonly string $promocionId,
        public readonly array $alojamientos,
        public readonly string $asignadoPor,
    ) {
    }
}
