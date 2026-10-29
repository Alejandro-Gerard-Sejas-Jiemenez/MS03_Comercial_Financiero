<?php

declare(strict_types=1);

namespace Src\Promociones\Application\UseCase\VencerPromociones;

use Src\Promociones\Domain\PromocionRepository;
use Src\Shared\Application\Port\Clock;
use Src\Shared\Application\Port\TransactionManager;

/**
 * CU-PRO-04 VencerPromociones (JOB).
 *
 * Pasa a VENCIDA solo las promociones cuya `vigente_hasta` ya paso y que
 * todavia no estan VENCIDAS ni CANCELADAS. La fecha viene del puerto Clock,
 * nunca de `now()`, para que el JOB se pueda probar sin esperar al calendario.
 */
final class VencerPromocionesHandler
{
    public function __construct(
        private readonly PromocionRepository $promociones,
        private readonly Clock $clock,
        private readonly TransactionManager $transacciones,
    ) {}

    /**
     * @return list<string> ids de las promociones que pasaron a VENCIDA
     */
    public function __invoke(VencerPromocionesCommand $comando): array
    {
        $fecha = $comando->fecha ?? $this->clock->ahora();

        return $this->transacciones->ejecutar(function () use ($fecha): array {
            $vencidas = [];

            foreach ($this->promociones->vencibles($fecha) as $promocion) {
                $promocion->vencer($fecha);
                $this->promociones->guardar($promocion);
                $vencidas[] = $promocion->promocionId();
            }

            return $vencidas;
        });
    }
}
