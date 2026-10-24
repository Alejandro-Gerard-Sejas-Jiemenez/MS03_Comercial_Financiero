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
    ],

    'outbox' => [
        // Cuantos eventos publica cada corrida de CU-SHA-01.
        'lote' => (int) env('COMERCIAL_OUTBOX_LOTE', 100),
        'canal_log' => env('COMERCIAL_OUTBOX_CANAL_LOG', 'stack'),
    ],

    'rabbitmq' => [
        // Pendiente: se usa cuando exista la capa Presentation/Messaging.
        'host' => env('RABBITMQ_HOST', '127.0.0.1'),
        'port' => (int) env('RABBITMQ_PORT', 5672),
        'usuario' => env('RABBITMQ_USER', 'guest'),
        'password' => env('RABBITMQ_PASSWORD', 'guest'),
        'exchange' => env('RABBITMQ_EXCHANGE', 'comercial.events'),
        'colas_consumidas' => ['usuarios.events', 'reservas.events'],
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
