<?php

declare(strict_types=1);

namespace Src\Shared\Domain\Exception;

use DomainException;

/**
 * Quien pide no puede hacer esto sobre este recurso (HTTP 403).
 *
 * Extiende DomainException para que lo que ya la atrapaba siga funcionando.
 */
class OperacionNoPermitida extends DomainException {}
