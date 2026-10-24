<?php

declare(strict_types=1);

namespace Src\Acuerdos\Domain;

/**
 * Estados del acuerdo comercial. Son los mismos del CHECK
 * `chk_acuerdo_estado` de la tabla `acuerdo_comercial`.
 */
enum EstadoAcuerdo: string
{
    case BORRADOR = 'BORRADOR';
    case ACTIVO = 'ACTIVO';
    case SUSPENDIDO = 'SUSPENDIDO';
    case FINALIZADO = 'FINALIZADO';
    case RESCINDIDO = 'RESCINDIDO';

    /**
     * Mapa de transiciones permitidas (CU-ACU-02). Lo que no esta aqui se
     * rechaza: un BORRADOR no se suspende, y FINALIZADO y RESCINDIDO son
     * terminales.
     *
     * @return list<self>
     */
    public function transicionesPermitidas(): array
    {
        return match ($this) {
            self::BORRADOR => [self::ACTIVO, self::RESCINDIDO],
            self::ACTIVO => [self::SUSPENDIDO, self::FINALIZADO, self::RESCINDIDO],
            self::SUSPENDIDO => [self::ACTIVO, self::FINALIZADO, self::RESCINDIDO],
            self::FINALIZADO, self::RESCINDIDO => [],
        };
    }

    public function puedeTransicionarA(self $destino): bool
    {
        return in_array($destino, $this->transicionesPermitidas(), true);
    }

    /** Un acuerdo cerrado ya no admite cambios de condiciones ni de estado. */
    public function esTerminal(): bool
    {
        return $this === self::FINALIZADO || $this === self::RESCINDIDO;
    }
}
