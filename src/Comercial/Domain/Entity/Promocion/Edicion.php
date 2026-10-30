<?php

declare(strict_types=1);

namespace Src\Comercial\Domain\Entity\Promocion;

use DateTimeImmutable;
use Src\Comercial\Domain\ValueObject\ValorDeDescuento;
use Src\Comercial\Domain\ValueObject\Vigencia;
use Src\Monetizacion\Domain\Enum\FinanciamientoDescuento;
use Src\Shared\Domain\Exception\ConflictoDeEstado;
use Src\Shared\Domain\Exception\ReglaDeNegocioViolada;

/**
 * Edicion de los datos mientras la promocion no sea terminal.
 *
 * Parte de `Promocion`: usa sus campos privados.
 */
trait Edicion
{
    public function renombrar(string $nombre): void
    {
        $this->asegurarEditable();
        $this->nombre = self::validarNombre($nombre);
    }

    public function cambiarValor(string $valor): void
    {
        $this->asegurarEditable();
        $this->valor = ValorDeDescuento::crear($valor, $this->valor->tipo());
    }

    public function cambiarVigencia(DateTimeImmutable $vigenteDesde, DateTimeImmutable $vigenteHasta): void
    {
        $this->asegurarEditable();
        $this->vigencia = Vigencia::crear($vigenteDesde, $vigenteHasta);
    }

    public function cambiarMinNoches(int $minNoches): void
    {
        $this->asegurarEditable();
        $this->minNoches = self::validarMinNoches($minNoches);
    }

    public function cambiarMaxUsos(?int $maxUsos): void
    {
        $this->asegurarEditable();
        $this->tope = $this->tope->conMaximo($maxUsos);
    }

    public function cambiarFinanciamiento(FinanciamientoDescuento $financiadaPor): void
    {
        $this->asegurarEditable();
        $this->financiadaPor = $financiadaPor;
    }

    /**
     * Reemplaza los alojamientos alcanzados. Una lista vacia significa "todos
     * los del anfitrion" (o de la plataforma si no tiene anfitrion).
     *
     * @param  list<string>  $alojamientos
     */
    public function asignarAlojamientos(array $alojamientos): void
    {
        $this->asegurarEditable();
        $this->alcance = $this->alcance->conAlojamientos($alojamientos);
    }

    private function asegurarEditable(): void
    {
        if ($this->estado->esTerminal()) {
            throw new ConflictoDeEstado(
                "La promocion {$this->promocionId} esta {$this->estado->value} y ya no se edita",
            );
        }
    }

    private static function validarNombre(string $nombre): string
    {
        $limpio = trim($nombre);

        if ($limpio === '') {
            throw new ReglaDeNegocioViolada('La promocion necesita un nombre');
        }

        return $limpio;
    }

    private static function validarMinNoches(int $minNoches): int
    {
        if ($minNoches < 1) {
            throw new ReglaDeNegocioViolada("Las noches minimas deben ser al menos 1, recibido {$minNoches}");
        }

        return $minNoches;
    }
}
