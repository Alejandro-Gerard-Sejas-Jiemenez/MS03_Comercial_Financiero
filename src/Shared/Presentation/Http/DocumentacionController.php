<?php

declare(strict_types=1);

namespace Src\Shared\Presentation\Http;

use Illuminate\Http\Response;

/**
 * Documentacion interactiva de la API (Swagger UI) a partir de `openapi.yaml`.
 * Sirve para ver y probar los endpoints desde el navegador, pegando un token
 * de Keycloak en "Authorize".
 */
final class DocumentacionController
{
    public function pagina(): Response
    {
        $html = <<<'HTML'
            <!doctype html>
            <html lang="es">
            <head>
              <meta charset="utf-8">
              <title>MS-03 Comercial y Financiero — API</title>
              <meta name="viewport" content="width=device-width, initial-scale=1">
              <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5.17.14/swagger-ui.css">
            </head>
            <body>
              <div id="swagger"></div>
              <script src="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5.17.14/swagger-ui-bundle.js"></script>
              <script>
                window.ui = SwaggerUIBundle({
                  url: '/docs/openapi.yaml',
                  dom_id: '#swagger',
                  persistAuthorization: true,
                  tryItOutEnabled: true,
                });
              </script>
            </body>
            </html>
            HTML;

        return new Response($html, 200, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    public function contrato(): Response
    {
        $archivo = base_path('openapi.yaml');
        abort_unless(is_file($archivo), 404, 'openapi.yaml no existe: corre `php artisan openapi:generar`');

        return new Response((string) file_get_contents($archivo), 200, ['Content-Type' => 'application/yaml; charset=utf-8']);
    }
}
