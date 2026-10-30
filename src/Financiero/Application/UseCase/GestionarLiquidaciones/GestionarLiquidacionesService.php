<?php

declare(strict_types=1);

namespace Src\Financiero\Application\UseCase\GestionarLiquidaciones;

use DateTimeImmutable;
use Src\Financiero\Application\Port\ComisionRepository;
use Src\Financiero\Application\Port\LibroContableRepository;
use Src\Financiero\Application\Port\LiquidacionRepository;
use Src\Financiero\Application\Response\LiquidacionResponse;
use Src\Financiero\Domain\Entity\Asiento;
use Src\Financiero\Domain\Entity\Comision;
use Src\Financiero\Domain\Entity\Liquidacion;
use Src\Relaciones\Application\Port\AcuerdoComercialQuery;
use Src\Relaciones\Application\Port\MetodoCobroQuery;
use Src\Shared\Application\Port\AnfitrionReferenceRepository;
use Src\Shared\Application\Port\AuditLogger;
use Src\Shared\Application\Port\Clock;
use Src\Shared\Application\Port\EventPublisher;
use Src\Shared\Application\Port\ReservaReferenceRepository;
use Src\Shared\Application\Port\TransactionManager;
use Src\Shared\Application\Port\UuidGenerator;
use Src\Shared\Domain\Exception\OperacionNoPermitida;
use Src\Shared\Domain\Exception\RecursoNoEncontrado;
use Src\Shared\Domain\Exception\ReglaDeNegocioViolada;
use Src\Shared\Domain\Money;
use Src\Shared\Domain\Referencia\AnfitrionReference;

/** Caso de uso GestionarLiquidaciones: implementa la interfaz de entrada GestionarLiquidacionesUseCase. */
final class GestionarLiquidacionesService implements GestionarLiquidacionesUseCase
{
    public function __construct(
        private readonly ComisionRepository $comisiones,
        private readonly ReservaReferenceRepository $reservas,
        private readonly AcuerdoComercialQuery $acuerdos,
        private readonly TransactionManager $tx,
        private readonly LiquidacionRepository $liquidaciones,
        private readonly AnfitrionReferenceRepository $anfitriones,
        private readonly MetodoCobroQuery $metodosDeCobro,
        private readonly UuidGenerator $uuid,
        private readonly Clock $clock,
        private readonly AuditLogger $auditoria,
        private readonly LibroContableRepository $libro,
        private readonly EventPublisher $eventos,
    ) {}

    /**
     * Disparador: `estadia.completada` / JOB.
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
    /** Igual que el DEFAULT de `acuerdo_comercial.dias_liquidacion`. */
    private const DIAS_POR_OMISION = 7;

    /** @return list<string> ids de las comisiones que pasaron a LIQUIDABLE */
    public function marcarLiquidables(MarcarComisionesLiquidablesRequest $comando): array
    {
        $reserva = $this->reservas->buscar($comando->reservaId)
            ?? throw new RecursoNoEncontrado(
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

    /**
     * Disparador: periodico.
     *
     * Una liquidacion GENERADA por anfitrion, juntando sus comisiones LIQUIDABLES
     * cuyo plazo ya se cumplio.
     *
     * Recorre `ref_anfitrion` (la copia local), no MS-01: si MS-01 esta caido esa
     * noche la liquidacion igual corre, y un anfitrion suspendido simplemente no
     * aparece en la lista de verificados. Eso es exactamente para lo que existen
     * las referencias locales (`Shared`).
     *
     * Un anfitrion sin comisiones liquidables, o cuyo neto quedaria en cero, no
     * genera liquidacion: no se crean filas vacias.
     */
    /** @return list<Liquidacion> */
    public function generar(GenerarLiquidacionesRequest $comando): array
    {
        $hasta = $comando->hasta ?? $this->clock->hoy();
        $generadas = [];

        foreach ($this->anfitriones->verificados() as $anfitrion) {
            $liquidacion = $this->paraAnfitrion($anfitrion, $hasta, $comando);

            if ($liquidacion !== null) {
                $generadas[] = $liquidacion;
            }
        }

        return array_map(LiquidacionResponse::desde(...), $generadas);
    }

    private function paraAnfitrion(
        AnfitrionReference $anfitrion,
        DateTimeImmutable $hasta,
        GenerarLiquidacionesRequest $comando,
    ): ?Liquidacion {
        $liquidables = $this->comisiones->liquidablesDe($anfitrion->anfitrionId, $hasta);

        if ($liquidables === []) {
            return null;
        }

        // Las cuentas (neto, ajuste, periodo) las hace la entidad: aqui solo se orquesta.
        $liquidacion = Liquidacion::desdeComisiones(
            liquidacionId: $this->uuid->generar(),
            anfitrionId: $anfitrion->anfitrionId,
            comisiones: $liquidables,
            ajustePorReembolsos: Money::deCentavos($comando->ajustesPorReembolso[$anfitrion->anfitrionId] ?? 0, $comando->moneda),
            periodoHasta: $hasta,
            periodoDesde: $comando->periodoDesde,
            hoy: $this->clock->hoy(),
            metodoId: $this->metodosDeCobro->metodoPrincipalVerificado($anfitrion->anfitrionId),
        );

        if ($liquidacion === null) {
            return null;
        }

        return $this->tx->ejecutar(function () use ($liquidacion, $liquidables): Liquidacion {
            $this->liquidaciones->guardar($liquidacion);

            foreach ($liquidables as $comision) {
                $comision->asignarALiquidacion($liquidacion->liquidacionId());
                $this->comisiones->guardar($comision);
            }

            return $liquidacion;
        });
    }

    /**
     * Disparador: REST.
     *
     * Decision del Administrador antes de que salga el dinero. Comprueba dos cosas
     * que pueden haber cambiado desde que el JOB genero la liquidacion: que el
     * anfitrion siga habilitado y que su metodo de cobro siga verificado.
     */
    public function aprobar(AprobarLiquidacionRequest $comando): LiquidacionResponse
    {
        $liquidacion = $this->liquidaciones->buscar($comando->liquidacionId)
            ?? throw new RecursoNoEncontrado("La liquidacion {$comando->liquidacionId} no existe");

        $anfitrion = $this->anfitriones->buscar($liquidacion->anfitrionId());

        if ($anfitrion === null || ! $anfitrion->puedeRecibirLiquidaciones()) {
            throw new OperacionNoPermitida(
                "El anfitrion {$liquidacion->anfitrionId()} no esta habilitado para recibir pagos",
            );
        }

        $metodo = $this->metodosDeCobro->metodoPrincipalVerificado($liquidacion->anfitrionId());

        if ($metodo === null) {
            throw new ReglaDeNegocioViolada(
                "El anfitrion {$liquidacion->anfitrionId()} no tiene metodo de cobro verificado",
            );
        }

        return LiquidacionResponse::desde($this->tx->ejecutar(function () use ($liquidacion, $comando, $metodo): Liquidacion {
            if ($liquidacion->metodoId() !== $metodo) {
                $liquidacion->asignarMetodoDeCobro($metodo);
            }

            $liquidacion->aprobar($comando->aprobadoPor);
            $this->liquidaciones->guardar($liquidacion);

            $this->auditoria->registrar(
                'APROBAR_LIQUIDACION',
                'liquidacion',
                $liquidacion->liquidacionId(),
                ['estado' => 'GENERADA'],
                ['estado' => $liquidacion->estado()->value, 'metodo_id' => $metodo],
                $comando->aprobadoPor,
            );

            return $liquidacion;
        }));
    }

    /**
     * Disparador: REST.
     *
     * Rechaza o anula, y libera las comisiones: vuelven a LIQUIDABLE y entran en la
     * corrida siguiente. Si no se liberaran, ese dinero quedaria atrapado en una
     * liquidacion muerta y el anfitrion nunca lo cobraria.
     *
     * Una liquidacion PAGADA no se rechaza ni se anula: el dinero ya salio y eso se
     * corrige con un asiento de reversa.
     */
    public function rechazar(RechazarLiquidacionRequest $comando): LiquidacionResponse
    {
        $liquidacion = $this->liquidaciones->buscar($comando->liquidacionId)
            ?? throw new RecursoNoEncontrado("La liquidacion {$comando->liquidacionId} no existe");

        $estadoAnterior = $liquidacion->estado()->value;

        return LiquidacionResponse::desde($this->tx->ejecutar(function () use ($liquidacion, $comando, $estadoAnterior): Liquidacion {
            if ($comando->anular) {
                $liquidacion->anular($comando->motivo);
            } else {
                $liquidacion->rechazar($comando->revisadoPor, $comando->motivo);
            }

            $this->liquidaciones->guardar($liquidacion);

            foreach ($this->comisiones->deLiquidacion($liquidacion->liquidacionId()) as $comision) {
                $comision->liberar();
                $this->comisiones->guardar($comision);
            }

            $this->auditoria->registrar(
                $comando->anular ? 'ANULAR_LIQUIDACION' : 'RECHAZAR_LIQUIDACION',
                'liquidacion',
                $liquidacion->liquidacionId(),
                ['estado' => $estadoAnterior],
                ['estado' => $liquidacion->estado()->value, 'motivo' => $comando->motivo],
                $comando->revisadoPor,
            );

            return $liquidacion;
        }));
    }

    /**
     * Disparador: REST.
     *
     * Registra la transferencia al anfitrion, deja la liquidacion PAGADA, escribe
     * el asiento que cancela la deuda y emite `liquidacion.pagada`.
     *
     * Todo en una sola transaccion, con el evento por Outbox: no puede quedar una
     * liquidacion PAGADA sin asiento, ni un evento publicado de una transferencia
     * que no se guardo.
     */
    public function pagar(PagarLiquidacionRequest $comando): LiquidacionResponse
    {
        $liquidacion = $this->liquidaciones->buscar($comando->liquidacionId)
            ?? throw new RecursoNoEncontrado("La liquidacion {$comando->liquidacionId} no existe");

        if ($liquidacion->estaPagada()) {
            return LiquidacionResponse::desde($liquidacion);
        }

        $asiento = Asiento::porLiquidacionPagada(
            asientoId: $this->uuid->generar(),
            liquidacionId: $liquidacion->liquidacionId(),
            anfitrionId: $liquidacion->anfitrionId(),
            totalNeto: $liquidacion->totalNeto(),
        );

        return LiquidacionResponse::desde($this->tx->ejecutar(function () use ($liquidacion, $asiento, $comando): Liquidacion {
            $liquidacion->pagar($comando->referenciaTransferencia);

            $this->liquidaciones->guardar($liquidacion);
            $this->libro->registrar($asiento);
            $this->eventos->publicar($liquidacion->sacarEventos());

            $this->auditoria->registrar(
                'PAGAR_LIQUIDACION',
                'liquidacion',
                $liquidacion->liquidacionId(),
                ['estado' => 'APROBADA'],
                [
                    'estado' => $liquidacion->estado()->value,
                    'total_neto' => $liquidacion->totalNeto()->aDecimal(),
                    'referencia_transferencia' => $comando->referenciaTransferencia,
                ],
                $comando->pagadoPor,
            );

            return $liquidacion;
        }));
    }

    /**
     * Disparador: `anfitrion.suspendido`.
     *
     * Pausa el dinero de un anfitrion suspendido por MS-01.
     *
     * El esquema no tiene un estado "RETENIDA" para `liquidacion`, asi que "pausar"
     * se implementa como: anular las liquidaciones que todavia no se pagaron y
     * liberar sus comisiones, que vuelven a LIQUIDABLE. Es reversible por
     * construccion: cuando MS-01 reactive al anfitrion, `verificados()` lo vuelve a
     * incluir y la siguiente corrida de regenera su liquidacion con las
     * mismas comisiones. Una liquidacion ya PAGADA no se toca.
     *
     * El otro lado de la retencion lo dan las referencias locales (`Shared`): `ref_anfitrion`
     * queda en SUSPENDIDO y solo recorre verificados, asi que no se
     * generan nuevas.
     */
    /** @return list<string> ids de las liquidaciones retenidas */
    public function retener(RetenerLiquidacionesDeAnfitrionRequest $comando): array
    {
        $pendientes = $this->liquidaciones->pendientesDePago($comando->anfitrionId);

        if ($pendientes === []) {
            return [];
        }

        return $this->tx->ejecutar(function () use ($pendientes, $comando): array {
            $retenidas = [];

            foreach ($pendientes as $liquidacion) {
                $estadoAnterior = $liquidacion->estado()->value;

                $liquidacion->anular($comando->motivo);
                $this->liquidaciones->guardar($liquidacion);

                foreach ($this->comisiones->deLiquidacion($liquidacion->liquidacionId()) as $comision) {
                    $comision->liberar();
                    $this->comisiones->guardar($comision);
                }

                $this->auditoria->registrar(
                    'RETENER_LIQUIDACION',
                    'liquidacion',
                    $liquidacion->liquidacionId(),
                    ['estado' => $estadoAnterior],
                    ['estado' => $liquidacion->estado()->value, 'motivo' => $comando->motivo],
                    null,
                );

                $retenidas[] = $liquidacion->liquidacionId();
            }

            return $retenidas;
        });
    }
}
