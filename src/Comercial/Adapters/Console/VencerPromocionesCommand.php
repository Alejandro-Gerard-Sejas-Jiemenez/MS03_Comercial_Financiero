<?php

declare(strict_types=1);

namespace Src\Comercial\Adapters\Console;

use Illuminate\Console\Command;
use Src\Comercial\Application\UseCase\GestionarPromociones\GestionarPromocionesUseCase;
use Src\Comercial\Application\UseCase\GestionarPromociones\VencerPromocionesRequest as Comando;

/** JOB: pasa a VENCIDA las promociones cuya vigencia ya termino. */
final class VencerPromocionesCommand extends Command
{
    protected $signature = 'promociones:vencer';

    protected $description = 'Vence las promociones cuya vigencia ya paso';

    public function handle(GestionarPromocionesUseCase $handler): int
    {
        $vencidas = $handler->vencerPorFecha(new Comando);
        $this->info(count($vencidas).' promociones vencidas');

        return self::SUCCESS;
    }
}
