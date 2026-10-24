<?php

declare(strict_types=1);

namespace Src\Promociones\Domain;

use DateTimeImmutable;
use DomainException;
use Src\Monetizacion\Domain\FinanciamientoDescuento;
use Src\Promociones\Domain\Event\PromocionAplicada;
use Src\Shared\Domain\Money;
use Src\Shared\Domain\RegistraEventos;

/**
 * Promocion de F09: descuento con vigencia, noches minimas y tope de usos.
 * Es raiz de agregado: contiene sus alojamientos asignados
 * (`promocion_alojamiento`) y acumula sus eventos de dominio.
 *
 * Entidad pura: no conoce Eloquent ni Laravel. La fila de la base de datos se
 * traduce con `reconstituir()`.
 *
 * `financiada_por` se modela con `FinanciamientoDescuento` de Monetizacion,
 * porque es ese paquete el que decide la base comisionable con ese dato.
 */
final class Promocion
{
    use RegistraEventos;

    /** @param list<string> $alojamientos */
    private function __construct(
        private readonly string $promocionId,
        private ?string $codigo,
        private string $nombre,
        private readonly TipoPromocion $tipo,
        private readonly TipoDescuento $tipoDescuento,
        private string $valor,
        private readonly string $moneda,
        private readonly RolCreador $creadaPorRol,
        private readonly string $creadaPor,
        private readonly ?string $anfitrionId,
        private FinanciamientoDescuento $financiadaPor,
        private int $minNoches,
        private DateTimeImmutable $vigenteDesde,
        private DateTimeImmutable $vigenteHasta,
        private ?int $maxUsos,
        private int $usosActuales,
        private EstadoPromocion $estado,
        private array $alojamientos,
    ) {
    }

    /**
     * Nace en BORRADOR: se publica con `activar()`, para que una promocion a
     * medio configurar no descuente nada.
     *
     * @param list<string> $alojamientos alojamientos alcanzados; vacio = todos
     *                                   los del anfitrion (o de la plataforma)
     */
    public static function crear(
        string $promocionId,
        string $nombre,
        TipoPromocion $tipo,
        TipoDescuento $tipoDescuento,
        string $valor,
        DateTimeImmutable $vigenteDesde,
        DateTimeImmutable $vigenteHasta,
        RolCreador $creadaPorRol,
        string $creadaPor,
        ?string $codigo = null,
        ?string $anfitrionId = null,
        FinanciamientoDescuento $financiadaPor = FinanciamientoDescuento::ANFITRION,
        int $minNoches = 1,
        ?int $maxUsos = null,
        string $moneda = 'BOB',
        array $alojamientos = [],
    ): self {
        self::validarVigencia($vigenteDesde, $vigenteHasta);

        return new self(
            $promocionId,
            $codigo === null ? null : self::normalizarCodigo($codigo),
            self::validarNombre($nombre),
            $tipo,
            $tipoDescuento,
            self::validarValor($valor, $tipoDescuento),
            Money::cero($moneda)->moneda(),
            $creadaPorRol,
            $creadaPor,
            $anfitrionId,
            $financiadaPor,
            self::validarMinNoches($minNoches),
            $vigenteDesde,
            $vigenteHasta,
            self::validarMaxUsos($maxUsos, 0),
            0,
            EstadoPromocion::BORRADOR,
            self::normalizarAlojamientos($alojamientos),
        );
    }

    /**
     * Reconstruye la entidad desde la base de datos, sin revalidar reglas de
     * creacion.
     *
     * @param list<string> $alojamientos
     */
    public static function reconstituir(
        string $promocionId,
        ?string $codigo,
        string $nombre,
        TipoPromocion $tipo,
        TipoDescuento $tipoDescuento,
        string $valor,
        string $moneda,
        RolCreador $creadaPorRol,
        string $creadaPor,
        ?string $anfitrionId,
        FinanciamientoDescuento $financiadaPor,
        int $minNoches,
        DateTimeImmutable $vigenteDesde,
        DateTimeImmutable $vigenteHasta,
        ?int $maxUsos,
        int $usosActuales,
        EstadoPromocion $estado,
        array $alojamientos = [],
    ): self {
        return new self(
            $promocionId,
            $codigo,
            $nombre,
            $tipo,
            $tipoDescuento,
            $valor,
            $moneda,
            $creadaPorRol,
            $creadaPor,
            $anfitrionId,
            $financiadaPor,
            $minNoches,
            $vigenteDesde,
            $vigenteHasta,
            $maxUsos,
            $usosActuales,
            $estado,
            $alojamientos,
        );
    }

    /**
     * CU-PRO-02: descuento sobre el subtotal de la reserva.
     *
     * PORCENTAJE aplica `valor` % sobre el subtotal (medio centavo hacia
     * arriba, lo hace `Money::porPorcentaje()`). MONTO_FIJO devuelve el monto,
     * topado al subtotal: un descuento nunca puede dejar un total negativo.
     */
    public function calcularDescuento(Money $subtotal): Money
    {
        if ($subtotal->esNegativo()) {
            throw new DomainException('El subtotal no puede ser negativo');
        }

        if ($subtotal->moneda() !== $this->moneda) {
            throw new DomainException(
                "La promocion {$this->promocionId} esta en {$this->moneda} y el subtotal en {$subtotal->moneda()}",
            );
        }

        if ($this->tipoDescuento === TipoDescuento::PORCENTAJE) {
            return $subtotal->porPorcentaje($this->valor);
        }

        $monto = Money::deDecimal($this->valor, $this->moneda);

        return $monto->esMayorQue($subtotal) ? $subtotal : $monto;
    }

    /**
     * Si la promocion alcanza a esa reserva. Las promociones automaticas que no
     * aplican simplemente no se usan; las pedidas por codigo explican el motivo
     * con `asegurarAplicableA()`.
     */
    public function aplicaA(
        int $noches,
        DateTimeImmutable $fecha,
        ?string $alojamientoId = null,
        ?string $anfitrionId = null,
    ): bool {
        return $this->motivoDeNoAplicar($noches, $fecha, $alojamientoId, $anfitrionId) === null;
    }

    /** Igual que `aplicaA()`, pero falla con el motivo exacto. */
    public function asegurarAplicableA(
        int $noches,
        DateTimeImmutable $fecha,
        ?string $alojamientoId = null,
        ?string $anfitrionId = null,
    ): void {
        $motivo = $this->motivoDeNoAplicar($noches, $fecha, $alojamientoId, $anfitrionId);

        if ($motivo !== null) {
            throw new DomainException($motivo);
        }
    }

    /**
     * CU-PRO-03: la promocion se uso en un pago capturado.
     *
     * Falla antes de pasarse del tope (`chk_promocion_usos` en la base): el
     * caso de uso corre dentro de una transaccion, asi que nada se guarda.
     */
    public function registrarUso(
        DateTimeImmutable $aplicadaEn,
        Money $descuento,
        ?string $reservaId = null,
        ?string $pagoId = null,
        ?string $sagaId = null,
    ): void {
        if ($this->estado === EstadoPromocion::CANCELADA) {
            throw new DomainException("La promocion {$this->promocionId} esta CANCELADA, no se puede usar");
        }

        if (! $this->tieneUsosDisponibles()) {
            throw new DomainException(
                "La promocion {$this->promocionId} alcanzo su tope de usos ({$this->maxUsos})",
            );
        }

        ++$this->usosActuales;

        $this->registrar(new PromocionAplicada(
            promocionId: $this->promocionId,
            codigo: $this->codigo,
            anfitrionId: $this->anfitrionId,
            tipoDescuento: $this->tipoDescuento,
            descuento: $descuento,
            financiadaPor: $this->financiadaPor,
            usosActuales: $this->usosActuales,
            maxUsos: $this->maxUsos,
            aplicadaEn: $aplicadaEn,
            reservaId: $reservaId,
            pagoId: $pagoId,
            sagaId: $sagaId,
        ));
    }

    /** CU-PRO-04: solo vence la que ya paso su `vigente_hasta`. */
    public function vencer(DateTimeImmutable $fecha): void
    {
        if ($this->estado->esTerminal()) {
            throw new DomainException(
                "La promocion {$this->promocionId} ya esta {$this->estado->value}",
            );
        }

        if ($fecha <= $this->vigenteHasta) {
            throw new DomainException(
                "La promocion {$this->promocionId} sigue vigente hasta "
                . $this->vigenteHasta->format('Y-m-d H:i:s'),
            );
        }

        $this->estado = EstadoPromocion::VENCIDA;
    }

    public function activar(): void
    {
        if ($this->estado !== EstadoPromocion::BORRADOR && $this->estado !== EstadoPromocion::PAUSADA) {
            throw new DomainException(
                "La promocion {$this->promocionId} esta {$this->estado->value} y no se puede activar",
            );
        }

        $this->estado = EstadoPromocion::ACTIVA;
    }

    public function pausar(): void
    {
        if ($this->estado !== EstadoPromocion::ACTIVA) {
            throw new DomainException(
                "La promocion {$this->promocionId} esta {$this->estado->value} y no se puede pausar",
            );
        }

        $this->estado = EstadoPromocion::PAUSADA;
    }

    public function cancelar(): void
    {
        if ($this->estado->esTerminal()) {
            throw new DomainException(
                "La promocion {$this->promocionId} ya esta {$this->estado->value}",
            );
        }

        $this->estado = EstadoPromocion::CANCELADA;
    }

    public function renombrar(string $nombre): void
    {
        $this->asegurarEditable();
        $this->nombre = self::validarNombre($nombre);
    }

    public function cambiarValor(string $valor): void
    {
        $this->asegurarEditable();
        $this->valor = self::validarValor($valor, $this->tipoDescuento);
    }

    public function cambiarVigencia(DateTimeImmutable $vigenteDesde, DateTimeImmutable $vigenteHasta): void
    {
        $this->asegurarEditable();
        self::validarVigencia($vigenteDesde, $vigenteHasta);
        $this->vigenteDesde = $vigenteDesde;
        $this->vigenteHasta = $vigenteHasta;
    }

    public function cambiarMinNoches(int $minNoches): void
    {
        $this->asegurarEditable();
        $this->minNoches = self::validarMinNoches($minNoches);
    }

    public function cambiarMaxUsos(?int $maxUsos): void
    {
        $this->asegurarEditable();
        $this->maxUsos = self::validarMaxUsos($maxUsos, $this->usosActuales);
    }

    public function cambiarFinanciamiento(FinanciamientoDescuento $financiadaPor): void
    {
        $this->asegurarEditable();
        $this->financiadaPor = $financiadaPor;
    }

    public function cambiarCodigo(?string $codigo): void
    {
        $this->asegurarEditable();
        $this->codigo = $codigo === null ? null : self::normalizarCodigo($codigo);
    }

    /**
     * CU-PRO-01: reemplaza los alojamientos alcanzados. Una lista vacia
     * significa "todos los del anfitrion" (o de la plataforma si no tiene
     * anfitrion).
     *
     * @param list<string> $alojamientos
     */
    public function asignarAlojamientos(array $alojamientos): void
    {
        $this->asegurarEditable();
        $this->alojamientos = self::normalizarAlojamientos($alojamientos);
    }

    public function quitarAlojamiento(string $alojamientoId): void
    {
        $this->asegurarEditable();
        $this->alojamientos = array_values(array_filter(
            $this->alojamientos,
            static fn (string $id): bool => $id !== $alojamientoId,
        ));
    }

    public function tieneUsosDisponibles(): bool
    {
        return $this->maxUsos === null || $this->usosActuales < $this->maxUsos;
    }

    public function estaVigenteEn(DateTimeImmutable $fecha): bool
    {
        return $fecha >= $this->vigenteDesde && $fecha <= $this->vigenteHasta;
    }

    public function estaActiva(): bool
    {
        return $this->estado === EstadoPromocion::ACTIVA;
    }

    public function esCupon(): bool
    {
        return $this->codigo !== null;
    }

    /** Alcanza al alojamiento dado (lista vacia = todos los del alcance). */
    public function alcanzaAlojamiento(?string $alojamientoId): bool
    {
        if ($this->alojamientos === []) {
            return true;
        }

        return $alojamientoId !== null && in_array($alojamientoId, $this->alojamientos, true);
    }

    /** `anfitrion_id` NULL = promocion de la plataforma: alcanza a cualquiera. */
    public function alcanzaAnfitrion(?string $anfitrionId): bool
    {
        return $this->anfitrionId === null || $this->anfitrionId === $anfitrionId;
    }

    public static function normalizarCodigo(string $codigo): string
    {
        $limpio = strtoupper(trim($codigo));

        if ($limpio === '') {
            throw new DomainException('El codigo de cupon no puede estar vacio');
        }

        if (mb_strlen($limpio) > 30) {
            throw new DomainException("El codigo de cupon no puede pasar de 30 caracteres: {$codigo}");
        }

        return $limpio;
    }

    public function promocionId(): string
    {
        return $this->promocionId;
    }

    public function codigo(): ?string
    {
        return $this->codigo;
    }

    public function nombre(): string
    {
        return $this->nombre;
    }

    public function tipo(): TipoPromocion
    {
        return $this->tipo;
    }

    public function tipoDescuento(): TipoDescuento
    {
        return $this->tipoDescuento;
    }

    public function valor(): string
    {
        return $this->valor;
    }

    public function moneda(): string
    {
        return $this->moneda;
    }

    public function creadaPorRol(): RolCreador
    {
        return $this->creadaPorRol;
    }

    public function creadaPor(): string
    {
        return $this->creadaPor;
    }

    public function anfitrionId(): ?string
    {
        return $this->anfitrionId;
    }

    public function financiadaPor(): FinanciamientoDescuento
    {
        return $this->financiadaPor;
    }

    public function minNoches(): int
    {
        return $this->minNoches;
    }

    public function vigenteDesde(): DateTimeImmutable
    {
        return $this->vigenteDesde;
    }

    public function vigenteHasta(): DateTimeImmutable
    {
        return $this->vigenteHasta;
    }

    public function maxUsos(): ?int
    {
        return $this->maxUsos;
    }

    public function usosActuales(): int
    {
        return $this->usosActuales;
    }

    public function estado(): EstadoPromocion
    {
        return $this->estado;
    }

    /** @return list<string> */
    public function alojamientos(): array
    {
        return $this->alojamientos;
    }

    private function motivoDeNoAplicar(
        int $noches,
        DateTimeImmutable $fecha,
        ?string $alojamientoId,
        ?string $anfitrionId,
    ): ?string {
        if ($this->estado !== EstadoPromocion::ACTIVA) {
            return "La promocion {$this->promocionId} esta {$this->estado->value}, no se puede aplicar";
        }

        if (! $this->estaVigenteEn($fecha)) {
            return "La promocion {$this->promocionId} no esta vigente el " . $fecha->format('Y-m-d');
        }

        if (! $this->tieneUsosDisponibles()) {
            return "La promocion {$this->promocionId} alcanzo su tope de usos ({$this->maxUsos})";
        }

        if ($noches < $this->minNoches) {
            return "La promocion {$this->promocionId} exige un minimo de {$this->minNoches} noches";
        }

        if (! $this->alcanzaAnfitrion($anfitrionId)) {
            return "La promocion {$this->promocionId} no aplica a los alojamientos de ese anfitrion";
        }

        if (! $this->alcanzaAlojamiento($alojamientoId)) {
            return "La promocion {$this->promocionId} no aplica a ese alojamiento";
        }

        return null;
    }

    private function asegurarEditable(): void
    {
        if ($this->estado->esTerminal()) {
            throw new DomainException(
                "La promocion {$this->promocionId} esta {$this->estado->value} y ya no se edita",
            );
        }
    }

    private static function validarNombre(string $nombre): string
    {
        $limpio = trim($nombre);

        if ($limpio === '') {
            throw new DomainException('La promocion necesita un nombre');
        }

        return $limpio;
    }

    /** `valor` es NUMERIC(12,2) > 0; si es PORCENTAJE, nunca pasa de 100. */
    private static function validarValor(string $valor, TipoDescuento $tipoDescuento): string
    {
        $limpio = trim($valor);

        if (preg_match('/^\d+(\.\d{1,2})?$/', $limpio) !== 1) {
            throw new DomainException("Valor de promocion invalido: {$valor}");
        }

        if ((float) $limpio <= 0) {
            throw new DomainException('El valor de la promocion debe ser mayor que cero');
        }

        if ($tipoDescuento === TipoDescuento::PORCENTAJE && (float) $limpio > 100) {
            throw new DomainException("Un descuento porcentual no puede pasar de 100 %, recibido {$valor}");
        }

        return $limpio;
    }

    private static function validarMinNoches(int $minNoches): int
    {
        if ($minNoches < 1) {
            throw new DomainException("Las noches minimas deben ser al menos 1, recibido {$minNoches}");
        }

        return $minNoches;
    }

    private static function validarMaxUsos(?int $maxUsos, int $usosActuales): ?int
    {
        if ($maxUsos === null) {
            return null;
        }

        if ($maxUsos < 1) {
            throw new DomainException("El tope de usos debe ser al menos 1, recibido {$maxUsos}");
        }

        if ($maxUsos < $usosActuales) {
            throw new DomainException(
                "El tope de usos ({$maxUsos}) no puede ser menor que los {$usosActuales} usos ya registrados",
            );
        }

        return $maxUsos;
    }

    private static function validarVigencia(DateTimeImmutable $desde, DateTimeImmutable $hasta): void
    {
        if ($hasta <= $desde) {
            throw new DomainException('La vigencia de la promocion debe terminar despues de empezar');
        }
    }

    /**
     * @param list<string> $alojamientos
     * @return list<string>
     */
    private static function normalizarAlojamientos(array $alojamientos): array
    {
        $limpios = [];

        foreach ($alojamientos as $alojamientoId) {
            $id = trim($alojamientoId);

            if ($id === '') {
                throw new DomainException('El id de alojamiento no puede estar vacio');
            }

            if (! in_array($id, $limpios, true)) {
                $limpios[] = $id;
            }
        }

        return $limpios;
    }
}
