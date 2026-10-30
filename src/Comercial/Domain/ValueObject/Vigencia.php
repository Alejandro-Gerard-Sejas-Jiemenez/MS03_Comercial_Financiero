<?php

declare(strict_types=1);

namespace Src\Comercial\Domain\ValueObject;

use DateTimeImmutable;
use Src\Shared\Domain\Exception\ReglaDeNegocioViolada;

/**
 * Periodo en que una promocion puede usarse (`vigente_desde` .. `vigente_hasta`,
 * CHECK `chk_promocion_vigencia`: el fin es estrictamente posterior al inicio).
 */
final class Vigencia
{
    private function __construct(
        private readonly DateTimeImmutable $desde,
        private readonly DateTimeImmutable $hasta,
    ) {}

    public static function crear(DateTimeImmutable $desde, DateTimeImmutable $hasta): self
    {
        if ($hasta <= $desde) {
            throw new ReglaDeNegocioViolada('La vigencia de la promocion debe terminar despues de empezar');
        }

        return new self($desde, $hasta);
    }

    /** Lo que ya esta guardado: no se revalida. */
    public static function reconstituir(DateTimeImmutable $desde, DateTimeImmutable $hasta): self
    {
        return new self($desde, $hasta);
    }

    /** Los dos extremos cuentan como vigentes. */
    public function incluye(DateTimeImmutable $fecha): bool
    {
        return $fecha >= $this->desde && $fecha <= $this->hasta;
    }

    public function terminoAntesDe(DateTimeImmutable $fecha): bool
    {
        return $fecha > $this->hasta;
    }

    public function desde(): DateTimeImmutable
    {
        return $this->desde;
    }

    public function hasta(): DateTimeImmutable
    {
        return $this->hasta;
    }
}
