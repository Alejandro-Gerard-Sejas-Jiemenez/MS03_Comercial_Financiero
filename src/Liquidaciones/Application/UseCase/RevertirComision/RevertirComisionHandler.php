<?php

declare(strict_types=1);

namespace Src\Liquidaciones\Application\UseCase\RevertirComision;

use Src\Liquidaciones\Domain\Asiento;
use Src\Liquidaciones\Domain\ComisionRepository;
use Src\Liquidaciones\Domain\EstadoComision;
use Src\Liquidaciones\Domain\LibroContableRepository;
use Src\Shared\Application\Port\TransactionManager;
use Src\Shared\Application\Port\UuidGenerator;

/**
 * CU-LIQ-02 RevertirComision (NEG, disparador INT por `reembolso.procesado`).
 *
 * Tres casos distintos, y ninguno reescribe el pasado:
 *
 *  1. la comision esta RETENIDA o LIQUIDABLE: se marca REVERTIDA y se escribe
 *     el asiento de reversa;
 *  2. la comision ya esta LIQUIDADA (al anfitrion ya se le pago): no se toca,
 *     se escribe el asiento de reversa y el monto queda para descontarse de la
 *     liquidacion siguiente;
 *  3. el reembolso fue parcial: la comision entera no corresponde revertirla,
 *     el ajuste va tambien en la liquidacion siguiente.
 *
 * En los casos 2 y 3 el resultado lo dice con
 * `requiereAjusteEnLaSiguienteLiquidacion`. Hoy ese descuento hay que pasarlo a
 * mano en `GenerarLiquidaciones`, porque el esquema tiene la columna
 * `total_reembolsos` pero no una tabla que diga que reembolso pertenece a que
 * liquidacion: es una decision pendiente, de la misma familia que la #4 del
 * documento de arquitectura.
 */
final class RevertirComisionHandler
{
    public function __construct(
        private readonly ComisionRepository $comisiones,
        private readonly LibroContableRepository $libro,
        private readonly UuidGenerator $uuid,
        private readonly TransactionManager $tx,
    ) {}

    public function __invoke(RevertirComisionCommand $comando): ResultadoReversion
    {
        $comision = $this->comisiones->buscarPorPago($comando->pagoId);

        if ($comision === null) {
            return ResultadoReversion::sinComision($comando->pagoId);
        }

        if ($comision->estaRevertida()) {
            return ResultadoReversion::revertida($comision);
        }

        $asiento = Asiento::porComisionRevertida(
            asientoId: $this->uuid->generar(),
            comisionId: $comision->comisionId(),
            anfitrionId: $comision->anfitrionId(),
            comision: $comision->montoComision(),
            netoAnfitrion: $comision->montoNetoAnfitrion(),
        );

        $yaLiquidada = $comision->estado() === EstadoComision::LIQUIDADA;
        $revierteEntera = $comando->esTotal && ! $yaLiquidada;

        return $this->tx->ejecutar(
            function () use ($comision, $asiento, $revierteEntera, $yaLiquidada): ResultadoReversion {
                if ($revierteEntera) {
                    $comision->revertir();
                    $this->comisiones->guardar($comision);
                }

                $this->libro->registrar($asiento);

                if ($revierteEntera) {
                    return ResultadoReversion::revertida($comision);
                }

                return $yaLiquidada
                    ? ResultadoReversion::yaLiquidada($comision)
                    : ResultadoReversion::parcial($comision);
            },
        );
    }
}
