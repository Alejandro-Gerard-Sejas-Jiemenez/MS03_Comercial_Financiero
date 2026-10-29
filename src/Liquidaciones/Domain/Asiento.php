<?php

declare(strict_types=1);

namespace Src\Liquidaciones\Domain;

use Src\Shared\Domain\Exception\ConflictoDeEstado;
use Src\Shared\Domain\Exception\RecursoNoEncontrado;
use Src\Shared\Domain\Exception\ReglaDeNegocioViolada;
use Src\Shared\Domain\Money;

/**
 * Asiento contable: el grupo de movimientos de una misma operacion.
 *
 * La regla de partida doble -- SUM(DEBITO) = SUM(CREDITO) por `asiento_id` --
 * la valida este objeto, porque la base de datos no puede expresarla en un
 * CHECK. Un asiento desbalanceado no se puede construir.
 */
final class Asiento
{
    /** @param list<MovimientoContable> $movimientos */
    private function __construct(
        private readonly string $asientoId,
        private readonly array $movimientos,
    ) {}

    /** @param list<MovimientoContable> $movimientos */
    public static function de(string $asientoId, array $movimientos): self
    {
        if ($movimientos === []) {
            throw new RecursoNoEncontrado('Un asiento sin movimientos no existe');
        }

        $moneda = $movimientos[0]->monto->moneda();
        $debitos = Money::cero($moneda);
        $creditos = Money::cero($moneda);

        foreach ($movimientos as $movimiento) {
            if ($movimiento->asientoId !== $asientoId) {
                throw new ConflictoDeEstado(
                    "El movimiento de la cuenta {$movimiento->cuenta->value} pertenece a otro asiento",
                );
            }

            if ($movimiento->tipo === TipoMovimiento::DEBITO) {
                $debitos = $debitos->mas($movimiento->monto);
            } else {
                $creditos = $creditos->mas($movimiento->monto);
            }
        }

        if (! $debitos->igualA($creditos)) {
            throw new ReglaDeNegocioViolada(
                "Asiento desbalanceado: debitos {$debitos->aDecimal()} contra creditos "
                ."{$creditos->aDecimal()}",
            );
        }

        return new self($asientoId, $movimientos);
    }

    /**
     * Entro dinero por un pago capturado: la pasarela nos debe el total, y ese
     * total se reparte entre el ingreso por comision, el ingreso por cargo de
     * servicio y lo que queda por pagarle al anfitrion.
     */
    public static function porPagoCapturado(
        string $asientoId,
        string $pagoId,
        string $anfitrionId,
        Money $total,
        Money $comision,
        Money $cargoServicio,
        Money $netoAnfitrion,
    ): self {
        $movimientos = [
            new MovimientoContable(
                $asientoId,
                CuentaContable::PASARELA,
                TipoMovimiento::DEBITO,
                $total,
                OrigenMovimiento::PAGO,
                $pagoId,
                $anfitrionId,
            ),
        ];

        if ($comision->esPositivo()) {
            $movimientos[] = new MovimientoContable(
                $asientoId,
                CuentaContable::INGRESO_COMISION,
                TipoMovimiento::CREDITO,
                $comision,
                OrigenMovimiento::PAGO,
                $pagoId,
                $anfitrionId,
            );
        }

        if ($cargoServicio->esPositivo()) {
            $movimientos[] = new MovimientoContable(
                $asientoId,
                CuentaContable::INGRESO_CARGO_SERVICIO,
                TipoMovimiento::CREDITO,
                $cargoServicio,
                OrigenMovimiento::PAGO,
                $pagoId,
                $anfitrionId,
            );
        }

        if ($netoAnfitrion->esPositivo()) {
            $movimientos[] = new MovimientoContable(
                $asientoId,
                CuentaContable::POR_PAGAR_ANFITRION,
                TipoMovimiento::CREDITO,
                $netoAnfitrion,
                OrigenMovimiento::PAGO,
                $pagoId,
                $anfitrionId,
            );
        }

        return self::de($asientoId, $movimientos);
    }

    /**
     * Se revirtio una comision por reembolso: se deshace el ingreso por
     * comision y lo que se le debia al anfitrion, y la contrapartida va a la
     * cuenta de reembolsos.
     */
    public static function porComisionRevertida(
        string $asientoId,
        string $comisionId,
        string $anfitrionId,
        Money $comision,
        Money $netoAnfitrion,
    ): self {
        $movimientos = [];

        if ($comision->esPositivo()) {
            $movimientos[] = new MovimientoContable(
                $asientoId,
                CuentaContable::INGRESO_COMISION,
                TipoMovimiento::DEBITO,
                $comision,
                OrigenMovimiento::COMISION,
                $comisionId,
                $anfitrionId,
            );
        }

        if ($netoAnfitrion->esPositivo()) {
            $movimientos[] = new MovimientoContable(
                $asientoId,
                CuentaContable::POR_PAGAR_ANFITRION,
                TipoMovimiento::DEBITO,
                $netoAnfitrion,
                OrigenMovimiento::COMISION,
                $comisionId,
                $anfitrionId,
            );
        }

        $movimientos[] = new MovimientoContable(
            $asientoId,
            CuentaContable::REEMBOLSOS,
            TipoMovimiento::CREDITO,
            $comision->mas($netoAnfitrion),
            OrigenMovimiento::COMISION,
            $comisionId,
            $anfitrionId,
        );

        return self::de($asientoId, $movimientos);
    }

    /** Se le transfirio el dinero al anfitrion: deja de debersele y sale de la pasarela. */
    public static function porLiquidacionPagada(
        string $asientoId,
        string $liquidacionId,
        string $anfitrionId,
        Money $totalNeto,
    ): self {
        return self::de($asientoId, [
            new MovimientoContable(
                $asientoId,
                CuentaContable::POR_PAGAR_ANFITRION,
                TipoMovimiento::DEBITO,
                $totalNeto,
                OrigenMovimiento::LIQUIDACION,
                $liquidacionId,
                $anfitrionId,
            ),
            new MovimientoContable(
                $asientoId,
                CuentaContable::PASARELA,
                TipoMovimiento::CREDITO,
                $totalNeto,
                OrigenMovimiento::LIQUIDACION,
                $liquidacionId,
                $anfitrionId,
            ),
        ]);
    }

    public function asientoId(): string
    {
        return $this->asientoId;
    }

    /** @return list<MovimientoContable> */
    public function movimientos(): array
    {
        return $this->movimientos;
    }
}
