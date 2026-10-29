<?php

declare(strict_types=1);

namespace Src\Shared\Domain\Exception;

use DomainException;

/**
 * El recurso pedido no existe (HTTP 404).
 *
 * Extiende DomainException para que lo que ya la atrapaba siga funcionando.
 */
class RecursoNoEncontrado extends DomainException {}
