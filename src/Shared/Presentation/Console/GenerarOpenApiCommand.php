<?php

declare(strict_types=1);

namespace Src\Shared\Presentation\Console;

use Illuminate\Console\Command;
use Src\Shared\Presentation\OpenApi\GeneradorOpenApi;

/** Escribe `openapi.yaml` en la raiz; con --check solo verifica que este al dia (para CI). */
final class GenerarOpenApiCommand extends Command
{
    protected $signature = 'openapi:generar {--check : falla si openapi.yaml no coincide con las rutas}';

    protected $description = 'Genera openapi.yaml a partir de las rutas y sus reglas de validacion';

    public function handle(GeneradorOpenApi $generador): int
    {
        $archivo = base_path('openapi.yaml');
        $yaml = $generador->yaml();

        if ($this->option('check')) {
            if (! is_file($archivo) || file_get_contents($archivo) !== $yaml) {
                $this->error('openapi.yaml esta desactualizado: corre `php artisan openapi:generar`');

                return self::FAILURE;
            }
            $this->info('openapi.yaml al dia');

            return self::SUCCESS;
        }

        file_put_contents($archivo, $yaml);
        $this->info('openapi.yaml generado');

        return self::SUCCESS;
    }
}
