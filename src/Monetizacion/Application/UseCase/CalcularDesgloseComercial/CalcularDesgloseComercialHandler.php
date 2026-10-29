<?php

declare(strict_types=1);

namespace Src\Monetizacion\Application\UseCase\CalcularDesgloseComercial;

use Src\Monetizacion\Domain\CalculadoraComision;
use Src\Monetizacion\Domain\Concepto;
use Src\Monetizacion\Domain\DesgloseComercial;
use Src\Monetizacion\Domain\ReglaMonetizacion;
use Src\Monetizacion\Domain\ReglaMonetizacionRepository;
use Src\Shared\Application\Port\Clock;
use Src\Shared\Domain\Exception\RecursoNoEncontrado;
use Src\Shared\Domain\Money;

/**
 * CU-MON-02 CalcularDesgloseComercial (NEG, disparador INT).
 *
 * Resuelve que reglas aplican y delega la aritmetica al servicio de dominio.
 * Lo usan CU-PAG-01 CotizarReserva y CU-LIQ-01 RegistrarComision, para que el
 * cliente vea el mismo total que despues se cobra y se comisiona.
 */
final class CalcularDesgloseComercialHandler
{
    public function __construct(
        private readonly ReglaMonetizacionRepository $reglas,
        private readonly CalculadoraComision $calculadora,
        private readonly Clock $clock,
    ) {}

    public function __invoke(CalcularDesgloseComercialCommand $comando): DesgloseComercial
    {
        $fecha = $comando->fecha ?? $this->clock->hoy();

        return $this->calculadora->calcular(
            Money::deCentavos($comando->subtotalCentavos, $comando->moneda),
            Money::deCentavos($comando->descuentoCentavos, $comando->moneda),
            $comando->financiamiento,
            $this->reglaComision($comando, $fecha),
            $this->primeraVigente(Concepto::CARGO_SERVICIO_CLIENTE, $comando, $fecha),
        );
    }

    private function reglaComision(
        CalcularDesgloseComercialCommand $comando,
        \DateTimeImmutable $fecha,
    ): ?ReglaMonetizacion {
        if ($comando->reglaComisionNegociadaId === null) {
            return $this->primeraVigente(Concepto::COMISION_ANFITRION, $comando, $fecha);
        }

        $negociada = $this->reglas->buscar($comando->reglaComisionNegociadaId);

        if ($negociada === null) {
            throw new RecursoNoEncontrado(
                "La regla negociada {$comando->reglaComisionNegociadaId} no existe",
            );
        }

        return $negociada;
    }

    /** La primera de la lista es la de alcance mas especifico. */
    private function primeraVigente(
        Concepto $concepto,
        CalcularDesgloseComercialCommand $comando,
        \DateTimeImmutable $fecha,
    ): ?ReglaMonetizacion {
        return $this->reglas->vigentes(
            $concepto,
            $fecha,
            $comando->categoriaAlojamiento,
            $comando->nivelAnfitrion,
        )[0] ?? null;
    }
}
