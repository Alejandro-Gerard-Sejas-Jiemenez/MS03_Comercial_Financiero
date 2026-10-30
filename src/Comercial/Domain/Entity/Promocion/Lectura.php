<?php

declare(strict_types=1);

namespace Src\Comercial\Domain\Entity\Promocion;

use DateTimeImmutable;
use Src\Comercial\Domain\Enum\EstadoPromocion;
use Src\Monetizacion\Domain\Enum\FinanciamientoDescuento;

/**
 * Lectura de los datos de la promocion.
 *
 * Parte de `Promocion`: usa sus campos privados.
 */
trait Lectura
{
    /**
     * Foto de solo lectura con los datos primitivos de la promocion, con las
     * claves de las columnas. La leen el repositorio, la respuesta y la
     * auditoria, en lugar de un getter por campo.
     *
     * @return array{
     *     promocion_id: string, codigo: ?string, nombre: string, tipo: string,
     *     tipo_descuento: string, valor: string, moneda: string, creada_por_rol: string,
     *     creada_por: string, anfitrion_id: ?string, financiada_por: string,
     *     min_noches: int, vigente_desde: DateTimeImmutable, vigente_hasta: DateTimeImmutable,
     *     max_usos: ?int, usos_actuales: int, estado: string, alojamientos: list<string>
     * }
     */
    public function retrato(): array
    {
        return [
            'promocion_id' => $this->promocionId,
            'codigo' => $this->codigo?->valor(),
            'nombre' => $this->nombre,
            'tipo' => $this->tipo->value,
            'tipo_descuento' => $this->valor->tipo()->value,
            'valor' => $this->valor->valor(),
            'moneda' => $this->moneda,
            'creada_por_rol' => $this->creadaPorRol->value,
            'creada_por' => $this->creadaPor,
            'anfitrion_id' => $this->alcance->anfitrionId(),
            'financiada_por' => $this->financiadaPor->value,
            'min_noches' => $this->minNoches,
            'vigente_desde' => $this->vigencia->desde(),
            'vigente_hasta' => $this->vigencia->hasta(),
            'max_usos' => $this->tope->maximo(),
            'usos_actuales' => $this->tope->actuales(),
            'estado' => $this->estado->value,
            'alojamientos' => $this->alcance->alojamientos(),
        ];
    }

    public function promocionId(): string
    {
        return $this->promocionId;
    }

    public function financiadaPor(): FinanciamientoDescuento
    {
        return $this->financiadaPor;
    }

    public function minNoches(): int
    {
        return $this->minNoches;
    }

    public function estado(): EstadoPromocion
    {
        return $this->estado;
    }
}
