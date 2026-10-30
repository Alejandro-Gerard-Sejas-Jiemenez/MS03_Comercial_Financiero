<?php

declare(strict_types=1);

namespace Src\Comercial\Domain\ValueObject;

use Src\Shared\Domain\Exception\ReglaDeNegocioViolada;

/**
 * A quien alcanza una promocion: a los alojamientos de un anfitrion
 * (`anfitrion_id`, NULL = promocion de la plataforma, alcanza a cualquiera) y,
 * dentro de eso, a los alojamientos asignados (lista vacia = todos).
 */
final class AlcanceDePromocion
{
    /** @param list<string> $alojamientos */
    private function __construct(
        private readonly ?string $anfitrionId,
        private readonly array $alojamientos,
    ) {}

    /** @param list<string> $alojamientos */
    public static function crear(?string $anfitrionId, array $alojamientos = []): self
    {
        return new self($anfitrionId, self::normalizar($alojamientos));
    }

    /**
     * Lo que ya esta guardado: no se revalida.
     *
     * @param  list<string>  $alojamientos
     */
    public static function reconstituir(?string $anfitrionId, array $alojamientos = []): self
    {
        return new self($anfitrionId, $alojamientos);
    }

    /**
     * Reemplaza los alojamientos asignados; una lista vacia significa "todos".
     *
     * @param  list<string>  $alojamientos
     */
    public function conAlojamientos(array $alojamientos): self
    {
        return new self($this->anfitrionId, self::normalizar($alojamientos));
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

    public function anfitrionId(): ?string
    {
        return $this->anfitrionId;
    }

    /** @return list<string> */
    public function alojamientos(): array
    {
        return $this->alojamientos;
    }

    /**
     * @param  list<string>  $alojamientos
     * @return list<string>
     */
    private static function normalizar(array $alojamientos): array
    {
        $limpios = [];

        foreach ($alojamientos as $alojamientoId) {
            $id = trim($alojamientoId);

            if ($id === '') {
                throw new ReglaDeNegocioViolada('El id de alojamiento no puede estar vacio');
            }

            if (! in_array($id, $limpios, true)) {
                $limpios[] = $id;
            }
        }

        return $limpios;
    }
}
