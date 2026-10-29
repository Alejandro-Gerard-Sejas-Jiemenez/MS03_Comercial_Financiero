<?php

declare(strict_types=1);

namespace Src\Shared\Presentation\Http;

use Illuminate\Http\JsonResponse;

final class SaludController
{
    public function __invoke(): JsonResponse
    {
        return new JsonResponse(['estado' => 'ok', 'servicio' => 'ms03-comercial-financiero']);
    }
}
