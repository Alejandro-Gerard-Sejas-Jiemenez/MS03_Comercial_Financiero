<?php

declare(strict_types=1);

namespace Src\Shared\Application\Auth;

use RuntimeException;

/** Token ausente, invalido o vencido (HTTP 401). */
final class NoAutenticado extends RuntimeException {}
