<?php

declare(strict_types=1);

/**
 * Prueba de humo de MS-03 contra el stack real (docker compose up -d).
 *
 * Recorre el flujo de punta a punta con tokens reales de Keycloak y eventos reales de RabbitMQ:
 * reglas, cotizacion, pago, cobro, comprobante, reembolso (solicitar, aprobar, procesar),
 * promociones, metodos de cobro, acuerdos, conciliacion y reportes.
 *
 *   php scripts/humo.php                       corre y muestra OK / FALLA por paso
 *   php scripts/humo.php --guardar=forma.json  ademas guarda la "forma" (claves) de cada respuesta
 *   php scripts/humo.php --comparar=forma.json compara la forma con una guardada antes:
 *                                              sirve para comprobar que un refactor no cambio la API
 *
 * Variables: HUMO_API (http://localhost:8000/api/v1), HUMO_KEYCLOAK (http://localhost:8080),
 * HUMO_RABBIT_HOST (127.0.0.1). Usuarios del realm `alojate`: admin1, anfi1, cli1 (clave123).
 */

require __DIR__.'/../vendor/autoload.php';

$api = getenv('HUMO_API') ?: 'http://localhost:8000/api/v1';
$keycloak = getenv('HUMO_KEYCLOAK') ?: 'http://localhost:8080';
$guardar = null;
$comparar = null;
foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--guardar=')) {
        $guardar = substr($arg, 10);
    }
    if (str_starts_with($arg, '--comparar=')) {
        $comparar = substr($arg, 11);
    }
}

// ---------- utilidades ----------
function http(string $metodo, string $url, ?array $cuerpo = null, array $cabeceras = []): array
{
    $ch = curl_init($url);
    $h = array_merge(['Accept: application/json', 'Content-Type: application/json'], $cabeceras);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => $metodo, CURLOPT_HTTPHEADER => $h, CURLOPT_TIMEOUT => 30]);
    if ($cuerpo !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($cuerpo));
    }
    $raw = (string) curl_exec($ch);

    return ['status' => curl_getinfo($ch, CURLINFO_HTTP_CODE), 'json' => json_decode($raw, true), 'raw' => $raw];
}

function token(string $keycloak, string $usuario): string
{
    $ch = curl_init("$keycloak/realms/alojate/protocol/openid-connect/token");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS => http_build_query(['client_id' => 'ms03-comercial-financiero', 'grant_type' => 'password', 'username' => $usuario, 'password' => 'clave123']),
    ]);
    $j = json_decode((string) curl_exec($ch), true);

    return $j['access_token'] ?? throw new RuntimeException("No se pudo obtener el token de {$usuario}: ".json_encode($j));
}

function sub(string $token): string
{
    return json_decode((string) base64_decode(strtr(explode('.', $token)[1], '-_', '+/')), true)['sub'];
}

function uuid(): string
{
    return sprintf('%08x-%04x-4%03x-8%03x-%012x', random_int(0, 0xFFFFFFFF), random_int(0, 0xFFFF), random_int(0, 0xFFF), random_int(0, 0xFFF), random_int(0, 0xFFFFFFFFFFFF));
}

/** Claves de un JSON (sin valores): la "forma" de la respuesta. */
function forma(mixed $v, string $p = ''): array
{
    if (! is_array($v)) {
        return [$p === '' ? '$' : $p];
    }
    if ($v === []) {
        return [$p.'(vacio)'];
    }
    if (array_is_list($v)) {
        return forma($v[0], $p.'[]');
    }
    $salida = [];
    foreach ($v as $k => $x) {
        $salida = array_merge($salida, forma($x, $p === '' ? (string) $k : "$p.$k"));
    }

    return $salida;
}

$fallos = 0;
$formas = [];
function paso(string $nombre, array $r, int $esperado, ?callable $extra = null): array
{
    global $fallos, $formas;
    $ok = $r['status'] === $esperado && ($extra === null || $extra($r));
    $fallos += $ok ? 0 : 1;
    $formas[$nombre] = array_values(array_unique(forma($r['json'] ?? [])));
    sort($formas[$nombre]);
    echo ($ok ? 'OK    ' : 'FALLA ')."[{$r['status']}] {$nombre}".($ok ? '' : '  -> '.substr($r['raw'], 0, 220))."\n";

    return $r;
}

// ---------- sesiones ----------
$tAdmin = token($keycloak, 'admin1');
$tAnfi = token($keycloak, 'anfi1');
$tCli = token($keycloak, 'cli1');
$A = ['Authorization: Bearer '.$tAdmin];
$F = ['Authorization: Bearer '.$tAnfi];
$C = ['Authorization: Bearer '.$tCli];
$O = ['Authorization: Bearer '.token($keycloak, 'ambos1')];   // otro usuario con rol ANFITRION: no es el dueno
$anfitrion = sub($tAnfi);
$cliente = sub($tCli);
$reserva = uuid();

// ---------- copias locales (MS-01 y MS-02) por RabbitMQ real ----------
$conn = new PhpAmqpLib\Connection\AMQPStreamConnection(getenv('HUMO_RABBIT_HOST') ?: '127.0.0.1', 5672, 'guest', 'guest');
$canal = $conn->channel();
foreach (['usuarios.events', 'reservas.events'] as $cola) {
    $canal->queue_declare($cola, false, true, false, false);
}
$enviar = static function (string $cola, string $tipo, array $data) use ($canal): void {
    $canal->basic_publish(new PhpAmqpLib\Message\AMQPMessage(json_encode(['id' => uuid(), 'tipo' => $tipo, 'origen' => 'humo', 'data' => $data]), ['delivery_mode' => 2]), '', $cola);
};
$enviar('usuarios.events', 'anfitrion.verificado', ['anfitrion_id' => $anfitrion, 'nombre_completo' => 'Ana Anfitriona', 'nit' => '1234567']);
$enviar('reservas.events', 'reserva.confirmada', [
    'reserva_id' => $reserva, 'cliente_id' => $cliente, 'anfitrion_id' => $anfitrion, 'alojamiento_id' => uuid(),
    'fecha_checkin' => date('Y-m-d', strtotime('+30 days')), 'fecha_checkout' => date('Y-m-d', strtotime('+34 days')),
    'estado' => 'CONFIRMADA', 'categoria_alojamiento' => 'CABANA',
]);
$canal->close();
$conn->close();
for ($i = 0; $i < 30; $i++) {   // espera a que el consumidor las procese
    sleep(1);
    if (http('GET', "$api/pagos?reserva_id=$reserva", null, $A)['status'] === 200 && ($sync = http('POST', "$api/pagos/cotizaciones", ['anfitrion_id' => $anfitrion, 'subtotal_centavos' => 1000, 'noches' => 1], $C))['status'] === 200) {
        break;
    }
}

echo "== Humo de MS-03 contra {$api}\n";
paso('salud (publica)', http('GET', "$api/salud"), 200);
paso('sin token -> 401', http('GET', "$api/pagos"), 401);
paso('cliente no crea reglas -> 403', http('POST', "$api/reglas-monetizacion", [], $C), 403);

// Monetizacion
$reglas = http('GET', "$api/reglas-monetizacion?concepto=COMISION_ANFITRION&estado=ACTIVA", null, $A);
if (($reglas['json']['data'] ?? []) === []) {
    paso('admin crea regla de comision', http('POST', "$api/reglas-monetizacion", ['nombre' => 'Base', 'concepto' => 'COMISION_ANFITRION', 'tipo_calculo' => 'PORCENTAJE', 'porcentaje' => 10, 'vigente_desde' => '2026-01-01'], $A), 201);
}
paso('reglas: listar', http('GET', "$api/reglas-monetizacion", null, $A), 200);
paso('reglas: vigentes', http('GET', "$api/reglas-monetizacion/vigentes?concepto=COMISION_ANFITRION", null, $A), 200);

// Pagos
$cot = paso('pagos: cotizar', http('POST', "$api/pagos/cotizaciones", ['anfitrion_id' => $anfitrion, 'subtotal_centavos' => 100000, 'noches' => 4], $C), 200);
$cuerpo = ['reserva_id' => $reserva, 'anfitrion_id' => $anfitrion, 'subtotal_centavos' => 100000, 'cargo_servicio_centavos' => 0, 'metodo' => 'QR'];
$clave = 'humo-'.bin2hex(random_bytes(4));
$pago = paso('pagos: crear', http('POST', "$api/pagos", $cuerpo, array_merge($C, ["Idempotency-Key: $clave"])), 201, fn ($r) => ($r['json']['data']['total']['centavos'] ?? 0) === 100000);
$id = $pago['json']['data']['pago_id'] ?? '';
paso('pagos: crear repetido (misma clave) -> mismo pago', http('POST', "$api/pagos", $cuerpo, array_merge($C, ["Idempotency-Key: $clave"])), 201, fn ($r) => ($r['json']['data']['pago_id'] ?? '') === $id);
paso('pagos: misma clave y otro cuerpo -> 422', http('POST', "$api/pagos", array_merge($cuerpo, ['metodo' => 'TARJETA']), array_merge($C, ["Idempotency-Key: $clave"])), 422);
paso('pagos: cobrar', http('POST', "$api/pagos/$id/cobro", [], array_merge($C, ['Idempotency-Key: c-'.$clave])), 200, fn ($r) => ($r['json']['data']['estado'] ?? '') === 'CAPTURADO');
paso('pagos: detalle (cliente)', http('GET', "$api/pagos/$id", null, $C), 200);
paso('pagos: detalle (anfitrion dueno)', http('GET', "$api/pagos/$id", null, $F), 200);
paso('pagos: listar', http('GET', "$api/pagos", null, $C), 200, fn ($r) => count($r['json']['data'] ?? []) >= 1);
paso('pagos: segundo pago vivo en la reserva -> 409', http('POST', "$api/pagos", $cuerpo, array_merge($C, ['Idempotency-Key: otro-'.$clave])), 409);
paso('pagos: emitir comprobante', http('POST', "$api/pagos/$id/comprobante", ['tipo' => 'RECIBO'], $C), 201);

// Reembolsos
$reem = paso('reembolsos: solicitar', http('POST', "$api/reembolsos", ['reserva_id' => $reserva, 'motivo' => 'CANCELACION_CLIENTE'], $C), 201);
$rid = $reem['json']['data']['reembolso_id'] ?? '';
paso('reembolsos: aprobar', http('POST', "$api/reembolsos/$rid/revision", ['aprobado' => true], $A), 200);
paso('reembolsos: procesar', http('POST', "$api/reembolsos/$rid/procesamiento", [], array_merge($A, ['Idempotency-Key: r-'.$clave])), 200);

// Conciliacion
paso('conciliacion: ejecutar', http('POST', "$api/conciliaciones", [], $A), 201);

// Promociones
$codigo = 'HUMO'.random_int(1000, 9999);
$promo = paso('promociones: crear', http('POST', "$api/promociones", ['nombre' => 'Humo', 'tipo' => 'CUPON', 'tipo_descuento' => 'PORCENTAJE', 'valor' => 10, 'codigo' => $codigo, 'vigente_desde' => '2026-01-01', 'vigente_hasta' => '2026-12-31'], $F), 201);
$pid = $promo['json']['data']['promocion_id'] ?? '';
paso('promociones: activar', http('PUT', "$api/promociones/$pid", ['estado' => 'ACTIVA'], $F), 200);
paso('promociones: descuento del cupon', http('POST', "$api/promociones/descuento", ['subtotal_centavos' => 100000, 'noches' => 2, 'codigo_cupon' => $codigo, 'anfitrion_id' => $anfitrion], $C), 200, fn ($r) => ($r['json']['data']['descuento_centavos'] ?? 0) === 10000);
paso('promociones: listar', http('GET', "$api/promociones", null, $F), 200);
paso('promociones: ver una', http('GET', "$api/promociones/$pid", null, $F), 200);
paso('promociones: el estado viaja solo -> 422', http('PUT', "$api/promociones/$pid", ['estado' => 'PAUSADA', 'nombre' => 'x'], $F), 422);
paso('promociones: pausar', http('PUT', "$api/promociones/$pid", ['estado' => 'PAUSADA'], $F), 200);
paso('promociones: otro anfitrion no la toca -> 403', http('PUT', "$api/promociones/$pid", ['estado' => 'CANCELADA'], $O), 403);
paso('promociones: cancelar', http('PUT', "$api/promociones/$pid", ['estado' => 'CANCELADA'], $F), 200);

// Metodos de cobro
$met = paso('metodos de cobro: registrar', http('POST', "$api/anfitriones/$anfitrion/metodos-cobro", ['tipo' => 'CUENTA_BANCARIA', 'titular' => 'Ana', 'numero' => '1234567890123', 'banco' => 'BNB'], $F), 201, fn ($r) => ! str_contains($r['raw'], '1234567890123'));
$mid = $met['json']['data']['metodo_id'] ?? '';
paso('metodos de cobro: listar', http('GET', "$api/anfitriones/$anfitrion/metodos-cobro", null, $F), 200);
paso('metodos de cobro: verificar (admin)', http('POST', "$api/metodos-cobro/$mid/verificacion", [], $A), 200);
paso('metodos de cobro: elegir principal (PUT)', http('PUT', "$api/metodos-cobro/$mid", ['es_principal' => true], $F), 200);
paso('metodos de cobro: la baja viaja sola -> 422', http('PUT', "$api/metodos-cobro/$mid", ['activo' => false, 'titular' => 'Otro'], $F), 422);
paso('metodos de cobro: baja logica (PUT)', http('PUT', "$api/metodos-cobro/$mid", ['activo' => false], $F), 200, fn ($r) => ($r['json']['data']['activo'] ?? true) === false && ($r['json']['data']['es_principal'] ?? true) === false);

// Acuerdos
$acu = paso('acuerdos: crear borrador', http('POST', "$api/acuerdos", ['anfitrion_id' => $anfitrion, 'fecha_inicio' => '2026-01-01'], $A), 201);
$aid = $acu['json']['data']['acuerdo_id'] ?? '';
paso('acuerdos: listar', http('GET', "$api/acuerdos", null, $A), 200);
paso('acuerdos: actualizar condiciones (PUT)', http('PUT', "$api/acuerdos/$aid", ['dias_liquidacion' => 10], $A), 200);
paso('acuerdos: el estado viaja solo -> 422', http('PUT', "$api/acuerdos/$aid", ['estado' => 'ACTIVO', 'dias_liquidacion' => 5], $A), 422);

// Liquidaciones y reportes
paso('liquidaciones: todas (admin)', http('GET', "$api/liquidaciones", null, $A), 200);
paso('liquidaciones: todas no es del anfitrion -> 403', http('GET', "$api/liquidaciones", null, $F), 403);
paso('liquidaciones: todas (admin)', http('GET', "$api/liquidaciones", null, $A), 200);
paso('liquidaciones: todas no es del anfitrion -> 403', http('GET', "$api/liquidaciones", null, $F), 403);
paso('liquidaciones: saldo del anfitrion', http('GET', "$api/anfitriones/$anfitrion/saldo", null, $F), 200);
paso('liquidaciones: historial del anfitrion', http('GET', "$api/anfitriones/$anfitrion/liquidaciones", null, $F), 200);
paso('liquidaciones: comisiones por liquidar', http('GET', "$api/anfitriones/$anfitrion/comisiones-por-liquidar", null, $F), 200);
paso('reportes: ingresos', http('GET', "$api/reportes/ingresos?desde=2026-01-01&hasta=2026-12-31", null, $A), 200);
paso('reportes: por categoria', http('GET', "$api/reportes/ingresos-por-categoria?desde=2026-01-01&hasta=2026-12-31", null, $A), 200);

echo $fallos === 0 ? "\nTODO OK\n" : "\n{$fallos} paso(s) fallaron\n";

// ---------- forma de las respuestas ----------
if ($guardar !== null) {
    file_put_contents($guardar, json_encode($formas, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo "Forma guardada en {$guardar}\n";
}
if ($comparar !== null) {
    $antes = json_decode((string) file_get_contents($comparar), true);
    $distintos = 0;
    foreach ($antes as $nombre => $claves) {
        $ahora = $formas[$nombre] ?? null;
        if ($ahora === null) {
            echo "FORMA: falta el paso '{$nombre}'\n";
            $distintos++;
        } elseif ($ahora !== $claves) {
            echo "FORMA: '{$nombre}' cambio. Faltan: ".implode(', ', array_diff($claves, $ahora) ?: ['-']).' · Sobran: '.implode(', ', array_diff($ahora, $claves) ?: ['-'])."\n";
            $distintos++;
        }
    }
    echo $distintos === 0 ? "La forma de las respuestas es identica a la guardada.\n" : "{$distintos} respuesta(s) cambiaron de forma.\n";
    $fallos += $distintos;
}

exit($fallos === 0 ? 0 : 1);
