<?php

declare(strict_types=1);

namespace Tests\Doubles;

use DateTimeImmutable;
use Src\Promociones\Domain\EstadoPromocion;
use Src\Promociones\Domain\Promocion;
use Src\Promociones\Domain\PromocionRepository;
use Src\Promociones\Domain\TipoPromocion;

final class InMemoryPromocionRepository implements PromocionRepository
{
    /** @var array<string, Promocion> */
    private array $promociones = [];

    private int $guardadas = 0;

    public function __construct(Promocion ...$promociones)
    {
        foreach ($promociones as $promocion) {
            $this->promociones[$promocion->promocionId()] = $promocion;
        }
    }

    public function buscar(string $promocionId): ?Promocion
    {
        return $this->promociones[$promocionId] ?? null;
    }

    public function buscarPorCodigo(string $codigo): ?Promocion
    {
        $buscado = strtoupper(trim($codigo));

        foreach ($this->promociones as $promocion) {
            if ($promocion->codigo() === $buscado) {
                return $promocion;
            }
        }

        return null;
    }

    public function guardar(Promocion $promocion): void
    {
        ++$this->guardadas;
        $this->promociones[$promocion->promocionId()] = $promocion;
    }

    public function vigentesSinCupon(
        DateTimeImmutable $fecha,
        ?string $alojamientoId = null,
        ?string $anfitrionId = null,
    ): array {
        return array_values(array_filter(
            $this->promociones,
            static fn (Promocion $promocion): bool => ! $promocion->esCupon()
                && $promocion->estaActiva()
                && $promocion->estaVigenteEn($fecha)
                && $promocion->tieneUsosDisponibles()
                && $promocion->alcanzaAnfitrion($anfitrionId)
                && $promocion->alcanzaAlojamiento($alojamientoId),
        ));
    }

    public function vencibles(DateTimeImmutable $fecha): array
    {
        return array_values(array_filter(
            $this->promociones,
            static fn (Promocion $promocion): bool => ! $promocion->estado()->esTerminal()
                && $promocion->vigenteHasta() < $fecha,
        ));
    }

    public function listar(
        ?string $anfitrionId = null,
        ?EstadoPromocion $estado = null,
        ?TipoPromocion $tipo = null,
    ): array {
        return array_values(array_filter(
            $this->promociones,
            static fn (Promocion $promocion): bool => ($anfitrionId === null || $promocion->anfitrionId() === $anfitrionId)
                && ($estado === null || $promocion->estado() === $estado)
                && ($tipo === null || $promocion->tipo() === $tipo),
        ));
    }

    public function cantidad(): int
    {
        return count($this->promociones);
    }

    /** Cuantas veces se llamo a `guardar()`, para afirmar sobre las escrituras. */
    public function guardadas(): int
    {
        return $this->guardadas;
    }
}
