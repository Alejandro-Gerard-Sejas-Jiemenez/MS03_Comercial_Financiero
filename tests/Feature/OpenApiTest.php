<?php

declare(strict_types=1);

namespace Tests\Feature;

use Src\Shared\Presentation\OpenApi\GeneradorOpenApi;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

final class OpenApiTest extends TestCase
{
    public function test_openapi_yaml_esta_al_dia_y_describe_todas_las_rutas(): void
    {
        $generado = $this->app->make(GeneradorOpenApi::class)->yaml();

        self::assertSame(
            $generado,
            file_get_contents(base_path('openapi.yaml')),
            'openapi.yaml esta desactualizado: corre `php artisan openapi:generar`',
        );

        $doc = Yaml::parse($generado);
        $operaciones = array_sum(array_map('count', $doc['paths']));
        self::assertSame('3.0.3', $doc['openapi']);
        self::assertGreaterThanOrEqual(54, $operaciones);
        self::assertArrayHasKey('/pagos', $doc['paths']);
        self::assertSame(['Idempotency-Key'], array_column($doc['paths']['/pagos']['post']['parameters'], 'name'));
    }
}
