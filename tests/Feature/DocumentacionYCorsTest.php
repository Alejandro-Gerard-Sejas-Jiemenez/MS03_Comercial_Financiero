<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

final class DocumentacionYCorsTest extends TestCase
{
    public function test_la_pagina_de_docs_y_el_contrato_se_sirven_sin_autenticacion(): void
    {
        $this->get('docs')->assertOk()->assertSee('swagger-ui', false)->assertSee('/docs/openapi.yaml', false);
        $this->get('docs/openapi.yaml')->assertOk()->assertSee('openapi: 3.0.3', false);
    }

    public function test_el_navegador_de_un_microfrontend_puede_llamar_a_la_api_pero_no_cualquier_origen(): void
    {
        $preflight = fn (string $origen) => $this->call('OPTIONS', 'api/v1/pagos', [], [], [], [
            'HTTP_ORIGIN' => $origen,
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'authorization,idempotency-key,content-type',
        ]);

        $permitido = $preflight('http://localhost:5173');
        self::assertSame('http://localhost:5173', $permitido->headers->get('Access-Control-Allow-Origin'));
        self::assertStringContainsStringIgnoringCase('idempotency-key', (string) $permitido->headers->get('Access-Control-Allow-Headers'));

        self::assertNull($preflight('http://sitio-malicioso.test')->headers->get('Access-Control-Allow-Origin'));
    }
}
