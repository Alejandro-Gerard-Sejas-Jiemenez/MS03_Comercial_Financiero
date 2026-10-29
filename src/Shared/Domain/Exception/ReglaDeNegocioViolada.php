<?php

declare(strict_types=1);

namespace Src\Shared\Domain\Exception;

use DomainException;

/**
 * El dato o la operacion rompe una regla de negocio o de validacion (HTTP 422).
 *
 * Extiende DomainException para que lo que ya la atrapaba siga funcionando.
 */
class ReglaDeNegocioViolada extends DomainException {}
