<?php

declare(strict_types=1);

namespace Src\Comercial\Domain\Enum;

/**
 * Quien creo la promocion (columna `creada_por_rol`, CHECK `chk_promocion_rol`).
 * Un ANFITRION solo promociona sus alojamientos; el ADMIN crea promociones de
 * plataforma, con `anfitrion_id` NULL.
 */
enum RolCreador: string
{
    case ANFITRION = 'ANFITRION';
    case ADMIN = 'ADMIN';
}
