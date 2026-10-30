<?php

declare(strict_types=1);

/**
 * Red de seguridad de la entidad Promocion (dominio puro, sin Laravel ni base de datos).
 *
 * Ejecuta las reglas de la entidad y arma una "foto" JSON con los resultados (valores,
 * clases de excepcion y mensajes). La foto sirve para comparar antes y despues de un
 * refactor: si cambia una regla, un mensaje o un dato, la comparacion lo muestra.
 *
 *   php scripts/dominio_promocion.php --guardar=foto.json     guarda la foto
 *   php scripts/dominio_promocion.php --comparar=foto.json    compara con la foto guardada
 */
require __DIR__.'/../vendor/autoload.php';

use Src\Comercial\Domain\Entity\Promocion;
use Src\Comercial\Domain\Enum\EstadoPromocion;
use Src\Comercial\Domain\Enum\RolCreador;
use Src\Comercial\Domain\Enum\TipoDescuento;
use Src\Comercial\Domain\Enum\TipoPromocion;
use Src\Comercial\Domain\ValueObject\CodigoCupon;
use Src\Monetizacion\Domain\Enum\FinanciamientoDescuento;
use Src\Shared\Domain\Money;

$opciones = getopt('', ['guardar::', 'comparar::']);
$f = static fn (string $s): DateTimeImmutable => new DateTimeImmutable($s);

$foto = [];

/** Ejecuta y registra el resultado o la excepcion (clase corta + mensaje). */
$caso = static function (string $nombre, callable $fn) use (&$foto): mixed {
    try {
        $r = $fn();
        $foto[$nombre] = ['ok' => $r];

        return $r;
    } catch (Throwable $e) {
        $foto[$nombre] = ['error' => (new ReflectionClass($e))->getShortName().': '.$e->getMessage()];

        return null;
    }
};

$base = static fn (array $o = []): Promocion => Promocion::crear(...array_merge([
    'promocionId' => 'p-1',
    'nombre' => '  Verano  ',
    'tipo' => TipoPromocion::TEMPORADA,
    'tipoDescuento' => TipoDescuento::PORCENTAJE,
    'valor' => '10',
    'vigenteDesde' => new DateTimeImmutable('2026-01-01 00:00:00+00:00'),
    'vigenteHasta' => new DateTimeImmutable('2026-12-31 00:00:00+00:00'),
    'creadaPorRol' => RolCreador::ANFITRION,
    'creadaPor' => 'u-1',
    'codigo' => ' verano10 ',
    'anfitrionId' => 'a-1',
    'financiadaPor' => FinanciamientoDescuento::ANFITRION,
    'minNoches' => 2,
    'maxUsos' => 2,
    'alojamientos' => [' al-1 ', 'al-2', 'al-1'],
], $o));

$resumen = static function (Promocion $p): array {
    $r = $p->retrato();

    return [
        'id' => $r['promocion_id'], 'codigo' => $r['codigo'], 'nombre' => $r['nombre'],
        'tipo' => $r['tipo'], 'tipoDescuento' => $r['tipo_descuento'], 'valor' => $r['valor'],
        'moneda' => $r['moneda'], 'rol' => $r['creada_por_rol'], 'creadaPor' => $r['creada_por'],
        'anfitrion' => $r['anfitrion_id'], 'financiadaPor' => $p->financiadaPor()->value,
        'minNoches' => $p->minNoches(), 'desde' => $r['vigente_desde']->format(DATE_ATOM),
        'hasta' => $r['vigente_hasta']->format(DATE_ATOM), 'maxUsos' => $r['max_usos'],
        'usos' => $r['usos_actuales'], 'estado' => $p->estado()->value, 'alojamientos' => $r['alojamientos'],
    ];
};

// ---- creacion y validaciones
$caso('crear: valida y normaliza', static fn () => $resumen($base()));
$caso('crear: cupon sin codigo ni tope ni alojamientos', static fn () => $resumen($base(['codigo' => null, 'maxUsos' => null, 'alojamientos' => [], 'anfitrionId' => null, 'creadaPorRol' => RolCreador::ADMIN])));
$caso('crear: nombre vacio', static fn () => $base(['nombre' => '   ']));
$caso('crear: valor cero', static fn () => $base(['valor' => '0']));
$caso('crear: valor con formato invalido', static fn () => $base(['valor' => '10,5']));
$caso('crear: valor con tres decimales', static fn () => $base(['valor' => '10.123']));
$caso('crear: porcentaje de 101', static fn () => $base(['valor' => '101']));
$caso('crear: monto fijo de 500', static fn () => $resumen($base(['tipoDescuento' => TipoDescuento::MONTO_FIJO, 'valor' => '500'])));
$caso('crear: vigencia invertida', static fn () => $base(['vigenteHasta' => $f('2025-01-01')]));
$caso('crear: vigencia igual', static fn () => $base(['vigenteHasta' => $f('2026-01-01 00:00:00+00:00')]));
$caso('crear: min noches 0', static fn () => $base(['minNoches' => 0]));
$caso('crear: max usos 0', static fn () => $base(['maxUsos' => 0]));
$caso('crear: codigo vacio', static fn () => $base(['codigo' => '  ']));
$caso('crear: codigo de 31 caracteres', static fn () => $base(['codigo' => str_repeat('a', 31)]));
$caso('crear: alojamiento vacio', static fn () => $base(['alojamientos' => ['al-1', '  ']]));
$caso('crear: moneda invalida', static fn () => $base(['moneda' => 'BOLIVIANOS']));
$caso('normalizarCodigo', static fn () => [CodigoCupon::desde(' abc12 ')->valor()]);

// ---- calculo del descuento
$caso('descuento: 10 % de 200.00', static fn () => $base()->calcularDescuento(Money::deCentavos(20000, 'BOB'))->centavos());
$caso('descuento: 12.5 % redondeo', static fn () => $base(['valor' => '12.5'])->calcularDescuento(Money::deCentavos(999, 'BOB'))->centavos());
$caso('descuento: monto fijo menor', static fn () => $base(['tipoDescuento' => TipoDescuento::MONTO_FIJO, 'valor' => '50'])->calcularDescuento(Money::deCentavos(20000, 'BOB'))->centavos());
$caso('descuento: monto fijo topado', static fn () => $base(['tipoDescuento' => TipoDescuento::MONTO_FIJO, 'valor' => '500'])->calcularDescuento(Money::deCentavos(20000, 'BOB'))->centavos());
$caso('descuento: otra moneda', static fn () => $base()->calcularDescuento(Money::deCentavos(20000, 'USD')));
$caso('descuento: subtotal negativo', static fn () => $base()->calcularDescuento(Money::deCentavos(-1, 'BOB')));

// ---- ciclo de vida
$estado = static function (Promocion $p) {
    return $p->estado()->value;
};
$caso('estado: BORRADOR -> ACTIVA', static function () use ($base, $estado) {
    $p = $base();
    $p->activar();

    return $estado($p);
});
$caso('estado: ACTIVA -> activar de nuevo', static function () use ($base) {
    $p = $base();
    $p->activar();
    $p->activar();
});
$caso('estado: pausar en BORRADOR', static fn () => $base()->pausar());
$caso('estado: ACTIVA -> PAUSADA -> ACTIVA', static function () use ($base, $estado) {
    $p = $base();
    $p->activar();
    $p->pausar();
    $a = $estado($p);
    $p->activar();

    return [$a, $estado($p)];
});
$caso('estado: cancelar y reactivar', static function () use ($base) {
    $p = $base();
    $p->activar();
    $p->cancelar();
    $p->activar();
});
$caso('estado: cancelar dos veces', static function () use ($base) {
    $p = $base();
    $p->cancelar();
    $p->cancelar();
});
$caso('vencer: aun vigente', static function () use ($base, $f) {
    $p = $base();
    $p->activar();
    $p->vencer($f('2026-06-01'));
});
$caso('vencer: pasada la fecha', static function () use ($base, $f, $estado) {
    $p = $base();
    $p->activar();
    $p->vencer($f('2027-01-01'));

    return $estado($p);
});
$caso('vencer: ya vencida', static function () use ($base, $f) {
    $p = $base();
    $p->vencer($f('2027-01-01'));
    $p->vencer($f('2027-01-02'));
});

// ---- aplicabilidad (motivos, en el orden en que se evaluan)
$activa = static function (array $o = []) use ($base) {
    $p = $base($o);
    $p->activar();

    return $p;
};
$caso('aplica: ok', static fn () => [$activa()->aplicaA(3, new DateTimeImmutable('2026-06-01'), 'al-1', 'a-1')]);
$caso('aplica: borrador', static fn () => $base()->asegurarAplicableA(3, new DateTimeImmutable('2026-06-01'), 'al-1', 'a-1'));
$caso('aplica: fuera de vigencia', static fn () => $activa()->asegurarAplicableA(3, new DateTimeImmutable('2027-06-01'), 'al-1', 'a-1'));
$caso('aplica: pocas noches', static fn () => $activa()->asegurarAplicableA(1, new DateTimeImmutable('2026-06-01'), 'al-1', 'a-1'));
$caso('aplica: otro anfitrion', static fn () => $activa()->asegurarAplicableA(3, new DateTimeImmutable('2026-06-01'), 'al-1', 'a-9'));
$caso('aplica: otro alojamiento', static fn () => $activa()->asegurarAplicableA(3, new DateTimeImmutable('2026-06-01'), 'al-9', 'a-1'));
$caso('aplica: alojamiento nulo con lista', static fn () => $activa()->asegurarAplicableA(3, new DateTimeImmutable('2026-06-01'), null, 'a-1'));
$caso('aplica: promocion de plataforma, cualquier anfitrion', static fn () => [$activa(['anfitrionId' => null, 'alojamientos' => []])->aplicaA(3, new DateTimeImmutable('2026-06-01'), 'x', 'otro')]);
$caso('aplica: limites de vigencia', static fn () => [
    $activa()->aplicaA(3, new DateTimeImmutable('2026-01-01 00:00:00+00:00'), 'al-1', 'a-1'),
    $activa()->aplicaA(3, new DateTimeImmutable('2026-12-31 00:00:00+00:00'), 'al-1', 'a-1'),
    $activa()->aplicaA(3, new DateTimeImmutable('2026-12-31 00:00:01+00:00'), 'al-1', 'a-1'),
]);
$caso('aplica: sin usos disponibles', static function () use ($activa) {
    $p = $activa(['maxUsos' => 1]);
    $p->registrarUso(new DateTimeImmutable('2026-06-01'), Money::deCentavos(100, 'BOB'));

    return $p->asegurarAplicableA(3, new DateTimeImmutable('2026-06-01'), 'al-1', 'a-1');
});
$caso('predicados', static function () use ($activa) {
    $p = $activa();

    $dia = new DateTimeImmutable('2026-06-01');

    return [
        $p->aplicaA(3, $dia, 'al-1', 'a-1'),
        $p->aplicaA(3, new DateTimeImmutable('2025-06-01'), 'al-1', 'a-1'),
        $p->aplicaA(3, $dia, 'zz', 'a-1'),
        $p->aplicaA(3, $dia, null, 'a-1'),
        $p->aplicaA(3, $dia, 'al-1', 'zz'),
    ];
});

// ---- registrarUso y eventos
$caso('uso: incrementa y emite el evento', static function () use ($activa) {
    $p = $activa();
    $p->registrarUso(new DateTimeImmutable('2026-06-01 10:00:00+00:00'), Money::deCentavos(2500, 'BOB'), 'r-1', 'pg-1', 's-1');
    $eventos = $p->sacarEventos();
    $de = array_map(static fn ($e) => ['tipo' => $e->tipoEvento(), 'agregado' => [$e->agregadoTipo(), $e->agregadoId()], 'saga' => $e->sagaId(), 'payload' => $e->payload()], $eventos);

    return ['usos' => $p->retrato()['usos_actuales'], 'eventos' => $de, 'despues' => count($p->sacarEventos())];
});
$caso('uso: tope alcanzado', static function () use ($activa) {
    $p = $activa(['maxUsos' => 1]);
    $p->registrarUso(new DateTimeImmutable('2026-06-01'), Money::deCentavos(100, 'BOB'));
    $p->registrarUso(new DateTimeImmutable('2026-06-01'), Money::deCentavos(100, 'BOB'));
});
$caso('uso: cancelada', static function () use ($base) {
    $p = $base();
    $p->cancelar();
    $p->registrarUso(new DateTimeImmutable('2026-06-01'), Money::deCentavos(100, 'BOB'));
});
$caso('uso: sin tope, tres usos', static function () use ($activa) {
    $p = $activa(['maxUsos' => null]);
    for ($i = 0; $i < 3; $i++) {
        $p->registrarUso(new DateTimeImmutable('2026-06-01'), Money::deCentavos(100, 'BOB'));
    }

    return [$p->retrato()['usos_actuales'], $p->aplicaA(3, new DateTimeImmutable('2026-06-01'), 'al-1', 'a-1')];
});

// ---- edicion
$caso('editar: renombrar', static function () use ($base) {
    $p = $base();
    $p->renombrar('  Nuevo  ');

    return $p->retrato()['nombre'];
});
$caso('editar: renombrar vacio', static fn () => $base()->renombrar(' '));
$caso('editar: cambiar valor', static function () use ($base) {
    $p = $base();
    $p->cambiarValor('15.5');

    return $p->retrato()['valor'];
});
$caso('editar: valor invalido para porcentaje', static fn () => $base()->cambiarValor('150'));
$caso('editar: cambiar vigencia', static function () use ($base, $f) {
    $p = $base();
    $p->cambiarVigencia($f('2026-02-01'), $f('2026-03-01'));

    return [$p->retrato()['vigente_desde']->format('Y-m-d'), $p->retrato()['vigente_hasta']->format('Y-m-d')];
});
$caso('editar: vigencia invertida', static fn () => $base()->cambiarVigencia(new DateTimeImmutable('2026-03-01'), new DateTimeImmutable('2026-02-01')));
$caso('editar: min noches', static function () use ($base) {
    $p = $base();
    $p->cambiarMinNoches(5);

    return $p->minNoches();
});
$caso('editar: min noches 0', static fn () => $base()->cambiarMinNoches(0));
$caso('editar: tope menor a los usos', static function () use ($activa) {
    $p = $activa(['maxUsos' => 5]);
    $p->registrarUso(new DateTimeImmutable('2026-06-01'), Money::deCentavos(100, 'BOB'));
    $p->registrarUso(new DateTimeImmutable('2026-06-01'), Money::deCentavos(100, 'BOB'));
    $p->cambiarMaxUsos(1);
});
$caso('editar: quitar el tope', static function () use ($base) {
    $p = $base();
    $p->cambiarMaxUsos(null);

    return $p->retrato()['max_usos'];
});
$caso('editar: financiamiento', static function () use ($base) {
    $p = $base();
    $p->cambiarFinanciamiento(FinanciamientoDescuento::PLATAFORMA);

    return $p->financiadaPor()->value;
});
$caso('editar: asignar alojamientos', static function () use ($base) {
    $p = $base();
    $p->asignarAlojamientos(['x', ' y ', 'x']);

    return $p->retrato()['alojamientos'];
});
$caso('editar: asignar lista con vacio', static fn () => $base()->asignarAlojamientos(['x', '']));
$caso('editar: cancelada no se edita', static function () use ($base) {
    $p = $base();
    $p->cancelar();
    $p->renombrar('x');
});
$caso('editar: vencida no se edita', static function () use ($base) {
    $p = $base();
    $p->vencer(new DateTimeImmutable('2027-01-01'));
    $p->cambiarValor('5');
});

// ---- reconstituir
$caso('reconstituir: no revalida', static function () use ($resumen) {
    $p = Promocion::reconstituir('p-9', 'CUP', 'Vieja', TipoPromocion::CUPON, TipoDescuento::MONTO_FIJO, '25.00', 'BOB', RolCreador::ADMIN, 'u-9', null, FinanciamientoDescuento::PLATAFORMA, 1, new DateTimeImmutable('2026-01-01 00:00:00+00:00'), new DateTimeImmutable('2026-02-01 00:00:00+00:00'), 10, 3, EstadoPromocion::PAUSADA, ['z']);

    return $resumen($p);
});

$json = json_encode($foto, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

if (isset($opciones['guardar']) && is_string($opciones['guardar'])) {
    file_put_contents($opciones['guardar'], $json);
    echo count($foto)." casos guardados en {$opciones['guardar']}\n";
    exit(0);
}
if (isset($opciones['comparar']) && is_string($opciones['comparar'])) {
    $antes = json_decode((string) file_get_contents($opciones['comparar']), true, 512, JSON_THROW_ON_ERROR);
    $distintos = 0;
    foreach ($antes as $nombre => $esperado) {
        if (($foto[$nombre] ?? null) !== $esperado) {
            $distintos++;
            echo "DISTINTO: $nombre\n  antes: ".json_encode($esperado, JSON_UNESCAPED_UNICODE)."\n  ahora: ".json_encode($foto[$nombre] ?? null, JSON_UNESCAPED_UNICODE)."\n";
        }
    }
    foreach (array_diff_key($foto, $antes) as $nombre => $_) {
        echo "NUEVO: $nombre\n";
        $distintos++;
    }
    echo $distintos === 0 ? count($foto)." casos: la entidad se comporta igual que en la foto guardada.\n" : "$distintos caso(s) cambiaron.\n";
    exit($distintos === 0 ? 0 : 1);
}
echo $json."\n";
