<?php

declare(strict_types=1);

namespace Src\Shared\Presentation\Auth;

use RuntimeException;

/** Token ausente, invalido o vencido (HTTP 401). */
final class NoAutenticado extends RuntimeException {}
