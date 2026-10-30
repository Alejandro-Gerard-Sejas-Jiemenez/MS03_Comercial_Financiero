<?php

declare(strict_types=1);

namespace Src\Comercial\Domain\ValueObject;

use Src\Comercial\Domain\Enum\TipoDescuento;
use Src\Shared\Domain\Exception\ReglaDeNegocioViolada;
use Src\Shared\Domain\Money;

/**
 * Valor de un descuento (`promocion.valor`, NUMERIC(12,2) > 0) leido segun su
 * `TipoDescuento`: PORCENTAJE (nunca mas de 100) o MONTO_FIJO.
 */
final class ValorDeDescuento
{
    private function __construct(
        private readonly string $valor,
        private readonly TipoDescuento $tipo,
    ) {}

    public static function crear(string $valor, TipoDescuento $tipo): self
    {
        $limpio = trim($valor);

        if (preg_match('/^\d+(\.\d{1,2})?$/', $limpio) !== 1) {
            throw new ReglaDeNegocioViolada("Valor de promocion invalido: {$valor}");
        }

        if ((float) $limpio <= 0) {
            throw new ReglaDeNegocioViolada('El valor de la promocion debe ser mayor que cero');
        }

        if ($tipo === TipoDescuento::PORCENTAJE && (float) $limpio > 100) {
            throw new ReglaDeNegocioViolada("Un descuento porcentual no puede pasar de 100 %, recibido {$valor}");
        }

        return new self($limpio, $tipo);
    }

    /** Lo que ya esta guardado: no se revalida. */
    public static function reconstituir(string $valor, TipoDescuento $tipo): self
    {
        return new self($valor, $tipo);
    }

    /**
     * Descuento sobre el subtotal. PORCENTAJE aplica `valor` % (medio centavo
     * hacia arriba, lo hace `Money::porPorcentaje()`); MONTO_FIJO devuelve el
     * monto, topado al subtotal: un descuento nunca deja un total negativo.
     */
    public function sobre(Money $subtotal): Money
    {
        if ($this->tipo === TipoDescuento::PORCENTAJE) {
            return $subtotal->porPorcentaje($this->valor);
        }

        $monto = Money::deDecimal($this->valor, $subtotal->moneda());

        return $monto->esMayorQue($subtotal) ? $subtotal : $monto;
    }

    public function valor(): string
    {
        return $this->valor;
    }

    public function tipo(): TipoDescuento
    {
        return $this->tipo;
    }
}
