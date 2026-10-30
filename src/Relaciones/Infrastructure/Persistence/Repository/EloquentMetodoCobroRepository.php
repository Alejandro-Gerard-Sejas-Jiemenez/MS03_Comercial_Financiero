<?php

declare(strict_types=1);

namespace Src\Relaciones\Infrastructure\Persistence\Repository;

use Src\Relaciones\Application\Port\MetodoCobroRepository;
use Src\Relaciones\Domain\Entity\MetodoCobro;
use Src\Relaciones\Domain\Enum\TipoMetodoCobro;
use Src\Relaciones\Infrastructure\Persistence\Model\MetodoCobroModel;

final class EloquentMetodoCobroRepository implements MetodoCobroRepository
{
    public function buscar(string $metodoId): ?MetodoCobro
    {
        $fila = MetodoCobroModel::query()->find($metodoId);

        return $fila === null ? null : $this->aEntidad($fila);
    }

    public function guardar(MetodoCobro $metodo): void
    {
        MetodoCobroModel::query()->updateOrCreate(
            ['metodo_id' => $metodo->metodoId()],
            [
                'anfitrion_id' => $metodo->anfitrionId(),
                'tipo' => $metodo->tipo()->value,
                'banco' => $metodo->banco(),
                'titular' => $metodo->titular(),
                // Ya viene cifrado desde el caso de uso: aqui no se cifra ni se descifra.
                'numero_cifrado' => $metodo->numeroCifrado(),
                'ultimos_digitos' => $metodo->ultimosDigitos(),
                'es_principal' => $metodo->esPrincipal(),
                'verificado' => $metodo->estaVerificado(),
                'activo' => $metodo->estaActivo(),
            ],
        );
    }

    public function deAnfitrion(string $anfitrionId): array
    {
        return MetodoCobroModel::query()
            ->where('anfitrion_id', $anfitrionId)
            ->orderByDesc('es_principal')
            ->orderBy('created_at')
            ->get()
            ->map(fn (MetodoCobroModel $fila): MetodoCobro => $this->aEntidad($fila))
            ->values()
            ->all();
    }

    public function principalDe(string $anfitrionId): ?MetodoCobro
    {
        $fila = MetodoCobroModel::query()
            ->where('anfitrion_id', $anfitrionId)
            ->where('es_principal', true)
            // El indice unico parcial `uq_metodo_principal` garantiza que haya una.
            ->first();

        return $fila === null ? null : $this->aEntidad($fila);
    }

    private function aEntidad(MetodoCobroModel $fila): MetodoCobro
    {
        return MetodoCobro::reconstituir(
            metodoId: $fila->metodo_id,
            anfitrionId: $fila->anfitrion_id,
            tipo: TipoMetodoCobro::from($fila->tipo),
            banco: $fila->banco,
            titular: $fila->titular,
            numeroCifrado: $fila->numero_cifrado,
            ultimosDigitos: $fila->ultimos_digitos,
            esPrincipal: (bool) $fila->es_principal,
            verificado: (bool) $fila->verificado,
            activo: (bool) $fila->activo,
        );
    }
}
