<?php

declare(strict_types=1);

namespace Src\Monetizacion\Application\UseCase\CalcularDesgloseComercial;

use Src\Monetizacion\Application\Port\CalculadorDeDesglose;
use Src\Monetizacion\Application\Port\ReglaMonetizacionRepository;
use Src\Monetizacion\Application\Response\DesgloseComercialResponse;
use Src\Monetizacion\Domain\Entity\ReglaMonetizacion;
use Src\Monetizacion\Domain\Enum\Concepto;
use Src\Monetizacion\Domain\Policy\CalculadoraComision;
use Src\Monetizacion\Domain\ValueObject\DesgloseComercial;
use Src\Shared\Application\Port\Clock;
use Src\Shared\Domain\Exception\RecursoNoEncontrado;
use Src\Shared\Domain\Money;

/** Caso de uso CalcularDesgloseComercial: implementa la interfaz de entrada CalcularDesgloseComercialUseCase. */
final class CalcularDesgloseComercialService implements CalculadorDeDesglose, CalcularDesgloseComercialUseCase
{
    public function __construct(
        private readonly ReglaMonetizacionRepository $reglas,
        private readonly CalculadoraComision $calculadora,
        private readonly Clock $clock,
    ) {}

    /**
     * Disparador: INT.
     *
     * Resuelve que reglas aplican y delega la aritmetica al servicio de dominio.
     * Lo usan CotizarReserva y RegistrarComision, para que el
     * cliente vea el mismo total que despues se cobra y se comisiona.
     */
    public function calcular(CalcularDesgloseComercialRequest $comando): DesgloseComercialResponse
    {
        return DesgloseComercialResponse::desde($this->desglosar($comando));
    }

    public function desglosar(CalcularDesgloseComercialRequest $comando): DesgloseComercial
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
        CalcularDesgloseComercialRequest $comando,
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
        CalcularDesgloseComercialRequest $comando,
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
