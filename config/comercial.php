<?php

declare(strict_types=1);

/**
 * Configuracion de MS-03 (Comercial y Financiero).
 *
 * Lo que aqui se elige son detalles de infraestructura: que proveedor de pagos,
 * que broker, cada cuanto corre el relay del Outbox. Ninguna regla de negocio
 * vive en este archivo.
 */
return [

    'pasarela' => [
        // Proveedor de pagos. 'simulada' aprueba todo y sirve para desarrollo;
        // el adaptador real implementa el mismo puerto PasarelaPago.
        'driver' => env('COMERCIAL_PASARELA_DRIVER', 'simulada'),
        'nombre' => env('COMERCIAL_PASARELA_NOMBRE', 'SIMULADA'),
        'url' => env('COMERCIAL_PASARELA_URL'),
        'llave' => env('COMERCIAL_PASARELA_LLAVE'),
        'timeout' => (int) env('COMERCIAL_PASARELA_TIMEOUT', 15),
        // Secreto compartido del webhook (CU-PAG-05). Vacio = se rechaza todo.
        'webhook_secreto' => env('COMERCIAL_WEBHOOK_SECRETO', ''),
    ],

    'outbox' => [
        // Cuantos eventos publica cada corrida de CU-SHA-01.
        'lote' => (int) env('COMERCIAL_OUTBOX_LOTE', 100),
        'canal_log' => env('COMERCIAL_OUTBOX_CANAL_LOG', 'stack'),
    ],

    'broker' => [
        // 'log' deja los eventos en el log (desarrollo); 'rabbitmq' los publica de verdad.
        'driver' => env('COMERCIAL_BROKER_DRIVER', 'log'),
    ],

    'rabbitmq' => [
        'host' => env('RABBITMQ_HOST', '127.0.0.1'),
        'port' => (int) env('RABBITMQ_PORT', 5672),
        'usuario' => env('RABBITMQ_USER', 'guest'),
        'password' => env('RABBITMQ_PASSWORD', 'guest'),
        'exchange' => env('RABBITMQ_EXCHANGE', 'comercial.events'),
        'colas_consumidas' => ['usuarios.events', 'reservas.events'],
    ],

    'keycloak' => [
        // Quien emite los tokens y para quien son. Solo claves publicas (JWKS):
        // MS-03 no guarda ningun secreto compartido.
        'issuer' => env('KEYCLOAK_ISSUER', 'http://localhost:8080/realms/alojate'),
        'audience' => env('KEYCLOAK_AUDIENCE', 'ms03-comercial-financiero'),
        'jwks_url' => env('KEYCLOAK_JWKS_URL', 'http://localhost:8080/realms/alojate/protocol/openid-connect/certs'),
        'jwks_ttl' => (int) env('KEYCLOAK_JWKS_TTL', 3600),
    ],

    'liquidaciones' => [
        // Plazo por omision cuando el anfitrion no tiene acuerdo ACTIVO;
        // coincide con el DEFAULT de `acuerdo_comercial.dias_liquidacion`.
        'dias_liquidacion_por_omision' => (int) env('COMERCIAL_DIAS_LIQUIDACION', 7),
        'moneda_por_omision' => env('COMERCIAL_MONEDA', 'BOB'),
    ],

    'pagos' => [
        // Horas que un pago puede quedarse PENDIENTE antes de que el JOB de
        // CU-PAG-04 lo cancele y libere la reserva.
        'horas_para_expirar_pendiente' => (int) env('COMERCIAL_HORAS_EXPIRA_PAGO', 24),
    ],

];
