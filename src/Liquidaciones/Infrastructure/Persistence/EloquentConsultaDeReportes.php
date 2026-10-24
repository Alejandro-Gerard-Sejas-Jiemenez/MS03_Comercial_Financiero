<?php

declare(strict_types=1);

namespace Src\Liquidaciones\Infrastructure\Persistence;

use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Src\Liquidaciones\Application\Port\ConsultaDeReportes;
use Src\Liquidaciones\Application\Port\IngresoPorCategoria;
use Src\Liquidaciones\Application\Port\ResumenDeIngresos;
use Src\Shared\Domain\Money;

/**
 * Implementacion de los reportes de CU-LIQ-10 con agregaciones SQL.
 *
 * Solo lectura, y solo cuenta pagos CAPTURADOS o reembolsados: un pago fallido
 * o cancelado no es ingreso de nadie. Los reembolsos se cuentan por separado,
 * con los que ya estan PROCESADOS.
 */
final class EloquentConsultaDeReportes implements ConsultaDeReportes
{
    /** Estados en los que el dinero entro de verdad. */
    private const ESTADOS_COBRADOS = ['CAPTURADO', 'REEMBOLSADO_PARCIAL', 'REEMBOLSADO'];

    public function ingresosPorPeriodo(
        DateTimeImmutable $desde,
        DateTimeImmutable $hasta,
        string $moneda = 'BOB',
    ): ResumenDeIngresos {
        $pagos = DB::table('pago')
            ->selectRaw('
                COALESCE(SUM(monto_total), 0) AS total_cobrado,
                COALESCE(SUM(cargo_servicio), 0) AS total_cargos,
                COUNT(*) AS cantidad
            ')
            ->where('moneda', $moneda)
            ->whereIn('estado', self::ESTADOS_COBRADOS)
            ->whereBetween('created_at', [$desde, $hasta])
            ->first();

        $comisiones = DB::table('comision')
            ->selectRaw('
                COALESCE(SUM(monto_comision), 0) AS total_comisiones,
                COALESCE(SUM(monto_neto_anfitrion), 0) AS total_neto
            ')
            ->where('moneda', $moneda)
            ->where('estado', '<>', 'REVERTIDA')
            ->whereBetween('created_at', [$desde, $hasta])
            ->first();

        $reembolsos = DB::table('reembolso')
            ->join('pago', 'pago.pago_id', '=', 'reembolso.pago_id')
            ->where('pago.moneda', $moneda)
            ->where('reembolso.estado', 'PROCESADO')
            ->whereBetween('reembolso.created_at', [$desde, $hasta])
            ->sum('reembolso.monto');

        return new ResumenDeIngresos(
            totalCobrado: $this->aMoney($pagos->total_cobrado ?? 0, $moneda),
            totalComisiones: $this->aMoney($comisiones->total_comisiones ?? 0, $moneda),
            totalCargosServicio: $this->aMoney($pagos->total_cargos ?? 0, $moneda),
            totalReembolsos: $this->aMoney($reembolsos, $moneda),
            totalNetoAnfitriones: $this->aMoney($comisiones->total_neto ?? 0, $moneda),
            cantidadPagos: (int) ($pagos->cantidad ?? 0),
        );
    }

    public function ingresosPorCategoria(
        DateTimeImmutable $desde,
        DateTimeImmutable $hasta,
        string $moneda = 'BOB',
    ): array {
        // La categoria vive en la copia local `ref_reserva`, que es justamente
        // para lo que existe: agrupar sin preguntarle nada a MS-01 ni a MS-02.
        return DB::table('pago')
            ->join('ref_reserva', 'ref_reserva.reserva_id', '=', 'pago.reserva_id')
            ->leftJoin('comision', 'comision.pago_id', '=', 'pago.pago_id')
            ->selectRaw('
                ref_reserva.categoria_alojamiento AS categoria,
                COALESCE(SUM(pago.monto_total), 0) AS total_cobrado,
                COALESCE(SUM(comision.monto_comision), 0) AS total_comisiones,
                COUNT(DISTINCT pago.pago_id) AS cantidad
            ')
            ->where('pago.moneda', $moneda)
            ->whereIn('pago.estado', self::ESTADOS_COBRADOS)
            ->whereBetween('pago.created_at', [$desde, $hasta])
            ->groupBy('ref_reserva.categoria_alojamiento')
            ->orderByDesc('total_cobrado')
            ->get()
            ->map(fn (object $fila): IngresoPorCategoria => new IngresoPorCategoria(
                categoriaAlojamiento: $fila->categoria,
                totalCobrado: $this->aMoney($fila->total_cobrado, $moneda),
                totalComisiones: $this->aMoney($fila->total_comisiones, $moneda),
                cantidadPagos: (int) $fila->cantidad,
            ))
            ->values()
            ->all();
    }

    private function aMoney(mixed $valor, string $moneda): Money
    {
        return Money::deDecimal(number_format((float) $valor, 2, '.', ''), $moneda);
    }
}
