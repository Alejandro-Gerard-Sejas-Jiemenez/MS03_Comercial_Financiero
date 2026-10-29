<?php

declare(strict_types=1);

namespace Src\Shared\Domain\Exception;

use DomainException;

/**
 * La operacion choca con el estado actual o con algo ya existente (HTTP 409).
 *
 * Extiende DomainException para que lo que ya la atrapaba siga funcionando.
 */
class ConflictoDeEstado extends DomainException {}
