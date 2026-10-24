<?php

declare(strict_types=1);

namespace Src\Liquidaciones\Application\UseCase\MarcarComisionesLiquidables;

use DomainException;
use Src\Acuerdos\Application\Port\AcuerdoComercialQuery;
use Src\Liquidaciones\Domain\ComisionRepository;
use Src\Referencias\Domain\ReservaReferenceRepository;
use Src\Shared\Application\Port\TransactionManager;

/**
 * CU-LIQ-03 MarcarComisionesLiquidables (EVT `estadia.completada` / JOB).
 *
 * La estadia termino, asi que la comision deja de estar RETENIDA y pasa a
 * LIQUIDABLE con fecha `check-out + dias_liquidacion` del acuerdo del
 * anfitrion. Ese plazo es la ventana para disputas y contracargos: antes de
 * que venza, el dinero no se le transfiere a nadie.
 *
 * Las fechas salen de la copia local `ref_reserva`, no de una llamada a MS-02.
 * Si el anfitrion no tiene acuerdo ACTIVO se usa el plazo por omision de la
 * columna `acuerdo_comercial.dias_liquidacion`.
 */
final class MarcarComisionesLiquidablesHandler
{
    /** Igual que el DEFAULT de `acuerdo_comercial.dias_liquidacion`. */
    private const DIAS_POR_OMISION = 7;

    public function __construct(
        private readonly ComisionRepository $comisiones,
        private readonly ReservaReferenceRepository $reservas,
        private readonly AcuerdoComercialQuery $acuerdos,
        private readonly TransactionManager $tx,
    ) {
    }

    /** @return list<string> ids de las comisiones que pasaron a LIQUIDABLE */
    public function __invoke(MarcarComisionesLiquidablesCommand $comando): array
    {
        $reserva = $this->reservas->buscar($comando->reservaId)
            ?? throw new DomainException(
                "No hay copia local de la reserva {$comando->reservaId}; falta el evento de MS-02",
            );

        $retenidas = $this->comisiones->retenidasDeReserva($comando->reservaId);

        if ($retenidas === []) {
            return [];
        }

        $dias = $comando->diasLiquidacion
            ?? $this->acuerdos->acuerdoActivoDe($reserva->anfitrionId)?->diasLiquidacion
            ?? self::DIAS_POR_OMISION;

        $liquidableDesde = $reserva->liquidableDesde($dias);

        return $this->tx->ejecutar(function () use ($retenidas, $liquidableDesde): array {
            $marcadas = [];

            foreach ($retenidas as $comision) {
                $comision->marcarLiquidable($liquidableDesde);
                $this->comisiones->guardar($comision);
                $marcadas[] = $comision->comisionId();
            }

            return $marcadas;
        });
    }
}
