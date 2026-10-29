<?php

declare(strict_types=1);

namespace Src\Acuerdos\Domain;

use DateTimeImmutable;
use Src\Acuerdos\Domain\Event\AcuerdoActivado;
use Src\Acuerdos\Domain\Event\AcuerdoFinalizado;
use Src\Acuerdos\Domain\Event\AcuerdoSuspendido;
use Src\Shared\Domain\Exception\ConflictoDeEstado;
use Src\Shared\Domain\Exception\ReglaDeNegocioViolada;
use Src\Shared\Domain\RegistraEventos;

/**
 * Acuerdo comercial con un anfitrion (F17): la tarifa negociada y el plazo de
 * liquidacion que rigen sus cobros.
 *
 * Entidad pura y raiz de agregado: no conoce Eloquent ni Laravel, protege las
 * transiciones de estado de CU-ACU-02 y acumula los eventos que MS-01 consume.
 * La fila de la base de datos se traduce con `reconstituir()`.
 *
 * La regla "un solo acuerdo ACTIVO por anfitrion" no se puede decidir aqui,
 * porque mira otros acuerdos: la comprueba el caso de uso con el repositorio
 * y la garantiza tambien el indice unico parcial `uq_acuerdo_activo_por_anfitrion`.
 */
final class AcuerdoComercial
{
    use RegistraEventos;

    private const DIAS_LIQUIDACION_MINIMO = 0;

    private const DIAS_LIQUIDACION_MAXIMO = 60;

    /** @param array<string, mixed> $condiciones */
    private function __construct(
        private readonly string $acuerdoId,
        private readonly string $anfitrionId,
        private ?string $reglaComisionId,
        private EstadoAcuerdo $estado,
        private readonly DateTimeImmutable $fechaInicio,
        private ?DateTimeImmutable $fechaFin,
        private array $condiciones,
        private int $diasLiquidacion,
        private ?string $aprobadoPor,
        private ?DateTimeImmutable $aprobadoEn,
        private ?string $motivoEstado,
    ) {}

    /**
     * Todo acuerdo nace en BORRADOR: el Administrador lo negocia y recien
     * despues lo activa (CU-ACU-02).
     *
     * @param  array<string, mixed>  $condiciones
     */
    public static function crearBorrador(
        string $acuerdoId,
        string $anfitrionId,
        DateTimeImmutable $fechaInicio,
        int $diasLiquidacion = 7,
        ?string $reglaComisionId = null,
        ?DateTimeImmutable $fechaFin = null,
        array $condiciones = [],
    ): self {
        self::validarDiasLiquidacion($diasLiquidacion);
        self::validarFechas($fechaInicio, $fechaFin);

        return new self(
            $acuerdoId,
            $anfitrionId,
            $reglaComisionId,
            EstadoAcuerdo::BORRADOR,
            self::soloFecha($fechaInicio),
            $fechaFin === null ? null : self::soloFecha($fechaFin),
            $condiciones,
            $diasLiquidacion,
            null,
            null,
            null,
        );
    }

    /**
     * Reconstruye la entidad desde la base de datos, sin revalidar reglas de
     * creacion y sin emitir eventos.
     *
     * @param  array<string, mixed>  $condiciones
     */
    public static function reconstituir(
        string $acuerdoId,
        string $anfitrionId,
        ?string $reglaComisionId,
        EstadoAcuerdo $estado,
        DateTimeImmutable $fechaInicio,
        ?DateTimeImmutable $fechaFin,
        array $condiciones,
        int $diasLiquidacion,
        ?string $aprobadoPor,
        ?DateTimeImmutable $aprobadoEn,
        ?string $motivoEstado,
    ): self {
        return new self(
            $acuerdoId,
            $anfitrionId,
            $reglaComisionId,
            $estado,
            $fechaInicio,
            $fechaFin,
            $condiciones,
            $diasLiquidacion,
            $aprobadoPor,
            $aprobadoEn,
            $motivoEstado,
        );
    }

    /**
     * Cambia las condiciones negociadas. Solo los valores no nulos se aplican.
     * Un acuerdo cerrado ya no se toca: su historico sostiene liquidaciones ya
     * hechas.
     *
     * @param  array<string, mixed>|null  $condiciones
     */
    public function actualizarCondiciones(
        ?int $diasLiquidacion = null,
        ?string $reglaComisionId = null,
        ?array $condiciones = null,
        ?DateTimeImmutable $fechaFin = null,
    ): void {
        if ($this->estado->esTerminal()) {
            throw new ConflictoDeEstado(
                "El acuerdo {$this->acuerdoId} esta {$this->estado->value} y ya no admite cambios",
            );
        }

        if ($diasLiquidacion !== null) {
            self::validarDiasLiquidacion($diasLiquidacion);
            $this->diasLiquidacion = $diasLiquidacion;
        }

        if ($reglaComisionId !== null) {
            $this->reglaComisionId = $reglaComisionId;
        }

        if ($condiciones !== null) {
            $this->condiciones = $condiciones;
        }

        if ($fechaFin !== null) {
            self::validarFechas($this->fechaInicio, $fechaFin);
            $this->fechaFin = self::soloFecha($fechaFin);
        }
    }

    /**
     * Activa el acuerdo (o lo reactiva desde SUSPENDIDO) y emite
     * `acuerdo_comercial.activado`.
     */
    public function activar(string $aprobadoPor, DateTimeImmutable $aprobadoEn, ?string $sagaId = null): void
    {
        $this->asegurarTransicion(EstadoAcuerdo::ACTIVO);

        $this->estado = EstadoAcuerdo::ACTIVO;
        $this->aprobadoPor = $aprobadoPor;
        $this->aprobadoEn = $aprobadoEn;
        $this->motivoEstado = null;

        $this->registrar(new AcuerdoActivado(
            $this->acuerdoId,
            $this->anfitrionId,
            $this->reglaComisionId,
            $this->diasLiquidacion,
            $this->fechaInicio,
            $this->fechaFin,
            $aprobadoPor,
            $sagaId,
        ));
    }

    /** Suspende el acuerdo y emite `acuerdo_comercial.suspendido`. */
    public function suspender(string $motivo, ?string $sagaId = null): void
    {
        $this->asegurarTransicion(EstadoAcuerdo::SUSPENDIDO);

        $limpio = self::validarMotivo($motivo);
        $this->estado = EstadoAcuerdo::SUSPENDIDO;
        $this->motivoEstado = $limpio;

        $this->registrar(new AcuerdoSuspendido($this->acuerdoId, $this->anfitrionId, $limpio, $sagaId));
    }

    /** Cierre normal del acuerdo: emite `acuerdo_comercial.finalizado`. */
    public function finalizar(?string $motivo = null, ?string $sagaId = null): void
    {
        $this->cerrar(EstadoAcuerdo::FINALIZADO, $motivo, $sagaId);
    }

    /**
     * Rescision: el acuerdo se corta antes de tiempo. Emite el mismo
     * `acuerdo_comercial.finalizado` con `estado_final = RESCINDIDO`, porque
     * para MS-01 el efecto es identico: el acuerdo dejo de regir.
     */
    public function rescindir(string $motivo, ?string $sagaId = null): void
    {
        $this->cerrar(EstadoAcuerdo::RESCINDIDO, self::validarMotivo($motivo), $sagaId);
    }

    public function estaActivo(): bool
    {
        return $this->estado === EstadoAcuerdo::ACTIVO;
    }

    public function acuerdoId(): string
    {
        return $this->acuerdoId;
    }

    public function anfitrionId(): string
    {
        return $this->anfitrionId;
    }

    public function reglaComisionId(): ?string
    {
        return $this->reglaComisionId;
    }

    public function estado(): EstadoAcuerdo
    {
        return $this->estado;
    }

    public function fechaInicio(): DateTimeImmutable
    {
        return $this->fechaInicio;
    }

    public function fechaFin(): ?DateTimeImmutable
    {
        return $this->fechaFin;
    }

    /** @return array<string, mixed> */
    public function condiciones(): array
    {
        return $this->condiciones;
    }

    public function diasLiquidacion(): int
    {
        return $this->diasLiquidacion;
    }

    public function aprobadoPor(): ?string
    {
        return $this->aprobadoPor;
    }

    public function aprobadoEn(): ?DateTimeImmutable
    {
        return $this->aprobadoEn;
    }

    public function motivoEstado(): ?string
    {
        return $this->motivoEstado;
    }

    private function cerrar(EstadoAcuerdo $destino, ?string $motivo, ?string $sagaId): void
    {
        $this->asegurarTransicion($destino);

        $this->estado = $destino;
        $this->motivoEstado = $motivo;

        $this->registrar(new AcuerdoFinalizado(
            $this->acuerdoId,
            $this->anfitrionId,
            $destino,
            $motivo,
            $this->fechaFin,
            $sagaId,
        ));
    }

    private function asegurarTransicion(EstadoAcuerdo $destino): void
    {
        if (! $this->estado->puedeTransicionarA($destino)) {
            throw new ConflictoDeEstado(
                "El acuerdo {$this->acuerdoId} esta {$this->estado->value} y no puede pasar a {$destino->value}",
            );
        }
    }

    private static function validarMotivo(string $motivo): string
    {
        $limpio = trim($motivo);

        if ($limpio === '') {
            throw new ReglaDeNegocioViolada('El cambio de estado necesita un motivo');
        }

        return $limpio;
    }

    private static function validarDiasLiquidacion(int $dias): void
    {
        if ($dias < self::DIAS_LIQUIDACION_MINIMO || $dias > self::DIAS_LIQUIDACION_MAXIMO) {
            throw new ReglaDeNegocioViolada(
                sprintf(
                    'Los dias de liquidacion deben estar entre %d y %d, recibido %d',
                    self::DIAS_LIQUIDACION_MINIMO,
                    self::DIAS_LIQUIDACION_MAXIMO,
                    $dias,
                ),
            );
        }
    }

    private static function validarFechas(DateTimeImmutable $inicio, ?DateTimeImmutable $fin): void
    {
        if ($fin !== null && self::soloFecha($fin) < self::soloFecha($inicio)) {
            throw new ReglaDeNegocioViolada('El acuerdo no puede terminar antes de empezar');
        }
    }

    private static function soloFecha(DateTimeImmutable $fecha): DateTimeImmutable
    {
        return new DateTimeImmutable($fecha->format('Y-m-d').' 00:00:00');
    }
}
