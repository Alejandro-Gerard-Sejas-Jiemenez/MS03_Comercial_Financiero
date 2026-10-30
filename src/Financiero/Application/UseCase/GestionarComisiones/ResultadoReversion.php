<?php

declare(strict_types=1);

namespace Src\Financiero\Application\UseCase\GestionarComisiones;

use Src\Financiero\Domain\Entity\Comision;

final class ResultadoReversion
{
    private function __construct(
        public readonly ?Comision $comision,
        public readonly bool $revertida,
        public readonly bool $requiereAjusteEnLaSiguienteLiquidacion,
        public readonly string $detalle,
    ) {}

    public static function revertida(Comision $comision): self
    {
        return new self($comision, true, false, 'comision revertida y asiento de reversa registrado');
    }

    /** Ya se le liquido al anfitrion: hay que descontarlo de la liquidacion siguiente. */
    public static function yaLiquidada(Comision $comision): self
    {
        return new self(
            $comision,
            false,
            true,
            'la comision ya estaba LIQUIDADA: el asiento de reversa queda registrado y el '
            .'monto debe descontarse de la liquidacion siguiente',
        );
    }

    public static function sinComision(string $pagoId): self
    {
        return new self(null, false, false, "el pago {$pagoId} no tiene comision registrada");
    }

    public static function parcial(Comision $comision): self
    {
        return new self(
            $comision,
            false,
            true,
            'reembolso parcial: la comision no se revierte entera, el ajuste va en la '
            .'liquidacion siguiente',
        );
    }
}
