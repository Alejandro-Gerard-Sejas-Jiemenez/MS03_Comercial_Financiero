<?php

declare(strict_types=1);

namespace Src\Financiero\Domain\Entity;

use DateTimeImmutable;
use Src\Financiero\Domain\Entity\Conciliacion\Lectura;
use Src\Financiero\Domain\Entity\Conciliacion\Revision;
use Src\Financiero\Domain\Enum\EstadoConciliacion;
use Src\Financiero\Domain\ValueObject\MovimientoPasarela;
use Src\Shared\Domain\Exception\ReglaDeNegocioViolada;
use Src\Shared\Domain\Money;

/**
 * Cuadre entre lo que registro MS-03 y lo que reporta la pasarela en una fecha
 * de corte.
 *
 * La comparacion es regla de negocio, no consulta SQL: se hace en el dominio
 * para poder probarla con listas en memoria.
 *
 * El enum de la base de datos tiene tres resultados (COINCIDE,
 * DIFERENCIA_MONTO, NO_REGISTRADA) mirados desde el reporte de la pasarela. Una
 * transaccion nuestra que la pasarela no reporta se guarda como
 * DIFERENCIA_MONTO con `monto_pasarela = 0.00` y su observacion, porque no hay
 * un cuarto valor en el esquema.
 */
final class Conciliacion
{
    use Lectura;
    use Revision;

    /** @param list<ConciliacionItem> $items */
    private function __construct(
        private readonly string $conciliacionId,
        private readonly string $pasarela,
        private readonly DateTimeImmutable $fechaCorte,
        private readonly Money $totalSistema,
        private readonly Money $totalPasarela,
        private EstadoConciliacion $estado,
        private readonly ?string $ejecutadaPor,
        private array $items,
    ) {}

    /**
     * @param  list<TransaccionPago>  $transaccionesDelSistema  transacciones EXITOSAS del corte
     * @param  list<MovimientoPasarela>  $reporteDeLaPasarela  lineas del reporte externo
     */
    public static function ejecutar(
        string $conciliacionId,
        string $pasarela,
        DateTimeImmutable $fechaCorte,
        array $transaccionesDelSistema,
        array $reporteDeLaPasarela,
        ?string $ejecutadaPor = null,
        string $moneda = 'BOB',
    ): self {
        $porReferencia = [];

        foreach ($transaccionesDelSistema as $transaccion) {
            if ($transaccion->referenciaPasarela === null) {
                throw new ReglaDeNegocioViolada(
                    "La transaccion {$transaccion->transaccionId} no tiene referencia de pasarela y no se puede conciliar",
                );
            }

            $porReferencia[$transaccion->referenciaPasarela] = $transaccion;
        }

        $items = [];
        $reportadas = [];

        foreach ($reporteDeLaPasarela as $movimiento) {
            $reportadas[$movimiento->referenciaPasarela] = true;
            $nuestra = $porReferencia[$movimiento->referenciaPasarela] ?? null;

            if ($nuestra === null) {
                $items[] = ConciliacionItem::noRegistrada($movimiento);

                continue;
            }

            $items[] = $nuestra->monto->igualA($movimiento->monto)
                ? ConciliacionItem::coincide($movimiento, $nuestra->transaccionId)
                : ConciliacionItem::diferenciaDeMonto($movimiento, $nuestra->transaccionId);
        }

        foreach ($porReferencia as $referencia => $transaccion) {
            if (isset($reportadas[$referencia])) {
                continue;
            }

            $items[] = ConciliacionItem::sinReportar($transaccion, $moneda);
        }

        $totalSistema = Money::sumaDe(
            array_map(static fn (TransaccionPago $t): Money => $t->monto, $transaccionesDelSistema),
            $moneda,
        );
        $totalPasarela = Money::sumaDe(
            array_map(static fn (MovimientoPasarela $m): Money => $m->monto, $reporteDeLaPasarela),
            $moneda,
        );

        $conciliacion = new self(
            $conciliacionId,
            $pasarela,
            $fechaCorte,
            $totalSistema,
            $totalPasarela,
            EstadoConciliacion::ABIERTA,
            $ejecutadaPor,
            $items,
        );

        $conciliacion->estado = $conciliacion->todoCuadra()
            ? EstadoConciliacion::CONCILIADA
            : EstadoConciliacion::CON_DIFERENCIAS;

        return $conciliacion;
    }

    /** @param list<ConciliacionItem> $items */
    public static function reconstituir(
        string $conciliacionId,
        string $pasarela,
        DateTimeImmutable $fechaCorte,
        Money $totalSistema,
        Money $totalPasarela,
        EstadoConciliacion $estado,
        ?string $ejecutadaPor,
        array $items,
    ): self {
        return new self(
            $conciliacionId,
            $pasarela,
            $fechaCorte,
            $totalSistema,
            $totalPasarela,
            $estado,
            $ejecutadaPor,
            $items,
        );
    }
}
