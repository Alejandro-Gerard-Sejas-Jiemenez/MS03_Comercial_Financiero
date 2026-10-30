<?php

declare(strict_types=1);

/**
 * Reglas de negocio que viven en el dominio y que antes estaban en los servicios de
 * Application: neto de una liquidacion, tope del reembolso, eleccion de la promocion
 * automatica y financiamiento por omision. Dominio puro: sin Laravel ni base de datos.
 *
 * Cada caso tiene el resultado esperado escrito a mano (sale de las reglas del negocio),
 * asi que el script falla si una regla cambia.   php scripts/dominio_financiero.php
 */
require __DIR__.'/../vendor/autoload.php';

use Src\Comercial\Domain\Entity\Promocion;
use Src\Comercial\Domain\Enum\RolCreador;
use Src\Comercial\Domain\Enum\TipoDescuento;
use Src\Comercial\Domain\Enum\TipoPromocion;
use Src\Comercial\Domain\Policy\SelectorDePromocion;
use Src\Financiero\Domain\Entity\Comision;
use Src\Financiero\Domain\Entity\Liquidacion;
use Src\Financiero\Domain\Entity\Pago;
use Src\Financiero\Domain\Enum\MetodoPago;
use Src\Financiero\Domain\Enum\MotivoReembolso;
use Src\Financiero\Domain\Policy\PoliticaDeReembolso;
use Src\Monetizacion\Domain\Enum\FinanciamientoDescuento;
use Src\Shared\Domain\Money;

$fallos = 0;
$total = 0;
$esperar = static function (string $nombre, mixed $obtenido, mixed $esperado) use (&$fallos, &$total): void {
    $total++;
    if ($obtenido === $esperado) {
        echo "OK    $nombre\n";

        return;
    }
    $fallos++;
    echo "FALLA $nombre\n  esperado: ".json_encode($esperado, JSON_UNESCAPED_UNICODE)."\n  obtenido: ".json_encode($obtenido, JSON_UNESCAPED_UNICODE)."\n";
};
/** Devuelve "Clase: mensaje" de la excepcion que lanza el bloque, o null si no lanza. */
$error = static function (callable $fn): ?string {
    try {
        $fn();

        return null;
    } catch (Throwable $e) {
        return (new ReflectionClass($e))->getShortName().': '.$e->getMessage();
    }
};
$bob = static fn (int $c): Money => Money::deCentavos($c, 'BOB');
$f = static fn (string $s): DateTimeImmutable => new DateTimeImmutable($s);

// ---------- Liquidacion::desdeComisiones ----------
$comision = static function (string $id, int $base, int $com, ?string $liquidableDesde) use ($bob, $f): Comision {
    $c = Comision::registrar($id, 'pago-'.$id, 'res-'.$id, 'a-1', $bob($base), $bob($com));
    if ($liquidableDesde !== null) {
        $c->marcarLiquidable($f($liquidableDesde));
    }

    return $c;
};
$cs = [$comision('c1', 100000, 10000, '2026-03-10'), $comision('c2', 50000, 5000, '2026-02-20')];
$liq = static fn (array $comisiones, int $ajuste, ?string $desde, string $hoy = '2026-06-15', ?string $metodo = 'm-1'): ?Liquidacion => Liquidacion::desdeComisiones('l-1', 'a-1', $comisiones, $bob($ajuste), $f('2026-04-01'), $desde === null ? null : $f($desde), $f($hoy), $metodo);

$l = $liq($cs, 2000, null);
$esperar('liquidacion: bruto', $l?->totalBruto()->centavos(), 150000);
$esperar('liquidacion: comisiones', $l?->totalComisiones()->centavos(), 15000);
$esperar('liquidacion: reembolsos', $l?->totalReembolsos()->centavos(), 2000);
$esperar('liquidacion: neto = bruto - comisiones - ajuste', $l?->totalNeto()->centavos(), 133000);
$esperar('liquidacion: el periodo arranca en la fecha liquidable mas antigua', $l?->periodoDesde()->format('Y-m-d'), '2026-02-20');
$esperar('liquidacion: el periodo termina en `hasta`', $l?->periodoHasta()->format('Y-m-d'), '2026-04-01');
$esperar('liquidacion: metodo de cobro', $l?->metodoId(), 'm-1');
$esperar('liquidacion: periodo explicito manda sobre las fechas', $liq($cs, 0, '2026-01-05')?->periodoDesde()->format('Y-m-d'), '2026-01-05');
$esperar('liquidacion: sin fechas liquidables usa hoy', $liq([$comision('c3', 1000, 100, null)], 0, null, '2026-03-30')?->periodoDesde()->format('Y-m-d'), '2026-03-30');
$esperar('liquidacion: sin comisiones no genera nada', $liq([], 0, null), null);
$esperar('liquidacion: neto en cero no genera nada', $liq($cs, 135000, null), null);
$esperar('liquidacion: ajuste mayor que el neto falla', $error(fn () => $liq($cs, 135001, null)), 'ConflictoDeEstado: El anfitrion a-1 tiene un ajuste por reembolsos (1350.01) mayor que su neto del periodo; revisar a mano');
$esperar('liquidacion: estado inicial', $l?->estado()->value, 'GENERADA');

// ---------- PoliticaDeReembolso::montoSolicitable ----------
$pago = static function (string $estado = 'capturado') use ($bob): Pago {
    $p = Pago::crear('pg-1', 'res-1', 'cli-1', 'a-1', $bob(100000), $bob(0), $bob(0), MetodoPago::QR, 'clave-1');
    if ($estado === 'capturado') {
        $p->capturar('ref-1', 'simulada');
    }

    return $p;
};
$politica = new PoliticaDeReembolso;
$solicitable = static fn (Pago $p, int $yaReembolsado, ?int $forzado, string $cancela, string $checkin, MotivoReembolso $motivo = MotivoReembolso::CANCELACION_CLIENTE): ?int => $politica->montoSolicitable($p, $bob($yaReembolsado), $forzado === null ? null : $bob($forzado), $f($cancela), $f($checkin), $motivo)?->centavos();

$p = $pago();
$esperar('reembolso: saldo reembolsable = total - ya reembolsado', $p->saldoReembolsable($bob(30000))->centavos(), 70000);
$esperar('reembolso: 7 dias o mas devuelve todo', $solicitable($p, 0, null, '2026-06-01', '2026-06-08'), 100000);
$esperar('reembolso: de 3 a 6 dias devuelve la mitad', $solicitable($p, 0, null, '2026-06-01', '2026-06-05'), 50000);
$esperar('reembolso: menos de 3 dias no devuelve nada', $solicitable($p, 0, null, '2026-06-01', '2026-06-02'), null);
$esperar('reembolso: cancela el anfitrion devuelve todo', $solicitable($p, 0, null, '2026-06-01', '2026-06-02', MotivoReembolso::CANCELACION_ANFITRION), 100000);
$esperar('reembolso: nunca pasa de lo que queda', $solicitable($p, 80000, null, '2026-06-01', '2026-06-20'), 20000);
$esperar('reembolso: monto forzado dentro del saldo', $solicitable($p, 0, 40000, '2026-06-01', '2026-06-02'), 40000);
$esperar('reembolso: monto forzado topado al saldo', $solicitable($p, 90000, 40000, '2026-06-01', '2026-06-02'), 10000);
$esperar('reembolso: sin saldo no hay reembolso', $solicitable($p, 100000, null, '2026-06-01', '2026-06-20'), null);
$esperar('reembolso: pago capturado admite reembolso', $error(fn () => $p->asegurarAdmiteReembolso()), null);
$esperar('reembolso: pago pendiente no admite reembolso', $error(fn () => $pago('pendiente')->asegurarAdmiteReembolso()), 'ConflictoDeEstado: El pago pg-1 esta en estado PENDIENTE y no admite reembolso');

// ---------- SelectorDePromocion ----------
$promo = static function (string $id, string $valor, TipoDescuento $tipo = TipoDescuento::PORCENTAJE, int $minNoches = 1, bool $activa = true) use ($f): Promocion {
    $p = Promocion::crear($id, 'Promo '.$id, TipoPromocion::TEMPORADA, $tipo, $valor, $f('2026-01-01 00:00:00+00:00'), $f('2026-12-31 00:00:00+00:00'), RolCreador::ADMIN, 'u-1', minNoches: $minNoches);
    if ($activa) {
        $p->activar();
    }

    return $p;
};
$selector = new SelectorDePromocion;
$mejor = static fn (array $candidatas, int $noches = 3, int $subtotal = 100000): ?string => $selector->mejorEntre($candidatas, $noches, $f('2026-06-01'), null, null, $bob($subtotal))?->promocionId();

$esperar('selector: gana la de mayor descuento', $mejor([$promo('p10', '10'), $promo('p20', '20'), $promo('p5', '5')]), 'p20');
$esperar('selector: monto fijo mayor gana a un porcentaje', $mejor([$promo('p10', '10'), $promo('fijo', '300', TipoDescuento::MONTO_FIJO)]), 'fijo');
$esperar('selector: ignora las que no aplican (noches minimas)', $mejor([$promo('larga', '50', minNoches: 7), $promo('p10', '10')]), 'p10');
$esperar('selector: ignora las que no estan activas', $mejor([$promo('borrador', '50', activa: false), $promo('p10', '10')]), 'p10');
$esperar('selector: ninguna aplica', $mejor([$promo('larga', '50', minNoches: 7)]), null);
$esperar('selector: sin candidatas', $mejor([]), null);
$esperar('selector: descuento cero no cuenta', $mejor([$promo('fijo', '5', TipoDescuento::MONTO_FIJO)], subtotal: 0), null);
$esperar('selector: empate se queda con la primera', $mejor([$promo('a', '10'), $promo('b', '10')]), 'a');

// ---------- Financiamiento por omision ----------
$esperar('financiamiento por omision es el anfitrion', FinanciamientoDescuento::porOmision(), FinanciamientoDescuento::ANFITRION);

echo "\n$total casos, ".($fallos === 0 ? 'todos bien' : "$fallos con falla")."\n";
exit($fallos === 0 ? 0 : 1);
