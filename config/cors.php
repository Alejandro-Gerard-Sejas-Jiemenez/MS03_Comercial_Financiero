<?php

/*
 * CORS para los microfrontends (React, Angular y el shell single-spa), que se
 * sirven desde otro origen que la API. Se autentica con `Authorization: Bearer`,
 * no con cookies, asi que no hace falta `supports_credentials`.
 *
 * COMERCIAL_CORS_ORIGINS: origenes permitidos separados por coma. Por omision
 * los puertos de desarrollo de Vite (5173), Angular (4200), single-spa (9000)
 * y 3000. En produccion se fija a los dominios reales del frontend.
 */
return [

    'paths' => ['api/*', 'docs/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env(
            'COMERCIAL_CORS_ORIGINS',
            'http://localhost:5173,http://localhost:4200,http://localhost:9000,http://localhost:3000',
        )),
    ))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    // Sin esto el navegador no deja leer este header de la respuesta repetida.
    'exposed_headers' => ['Idempotency-Replayed'],

    'max_age' => 3600,

    'supports_credentials' => false,

];
