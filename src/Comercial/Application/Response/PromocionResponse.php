<?php

declare(strict_types=1);

namespace Src\Comercial\Application\Response;

use DateTimeImmutable;
use Src\Comercial\Domain\Entity\Promocion;

/** Modelo de respuesta (Clean Architecture: Response Model) de Promocion: solo datos primitivos, sin reglas. */
final class PromocionResponse
{
    public function __construct(
        public readonly string $promocionId,
        public readonly ?string $codigo,
        public readonly string $nombre,
        public readonly string $tipo,
        public readonly string $tipoDescuento,
        public readonly string $valor,
        public readonly string $moneda,
        public readonly string $creadaPorRol,
        public readonly string $creadaPor,
        public readonly ?string $anfitrionId,
        public readonly string $financiadaPor,
        public readonly int $minNoches,
        public readonly DateTimeImmutable $vigenteDesde,
        public readonly DateTimeImmutable $vigenteHasta,
        public readonly ?int $maxUsos,
        public readonly int $usosActuales,
        public readonly string $estado,
        public readonly array $alojamientos,
    ) {}

    public static function desde(Promocion $e): self
    {
        $r = $e->retrato();

        return new self(
            promocionId: $r['promocion_id'],
            codigo: $r['codigo'],
            nombre: $r['nombre'],
            tipo: $r['tipo'],
            tipoDescuento: $r['tipo_descuento'],
            valor: $r['valor'],
            moneda: $r['moneda'],
            creadaPorRol: $r['creada_por_rol'],
            creadaPor: $r['creada_por'],
            anfitrionId: $r['anfitrion_id'],
            financiadaPor: $r['financiada_por'],
            minNoches: $r['min_noches'],
            vigenteDesde: $r['vigente_desde'],
            vigenteHasta: $r['vigente_hasta'],
            maxUsos: $r['max_usos'],
            usosActuales: $r['usos_actuales'],
            estado: $r['estado'],
            alojamientos: $r['alojamientos'],
        );
    }

    public static function desdeOpcional(?Promocion $e): ?self
    {
        return $e === null ? null : self::desde($e);
    }
}
