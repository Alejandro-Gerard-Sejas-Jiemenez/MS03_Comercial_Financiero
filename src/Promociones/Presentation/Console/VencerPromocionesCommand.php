<?php

declare(strict_types=1);

namespace Src\Promociones\Presentation\Console;

use Illuminate\Console\Command;
use Src\Promociones\Application\UseCase\VencerPromociones\VencerPromocionesCommand as Comando;
use Src\Promociones\Application\UseCase\VencerPromociones\VencerPromocionesHandler;

/** CU-PRO-04 (JOB): pasa a VENCIDA las promociones cuya vigencia ya termino. */
final class VencerPromocionesCommand extends Command
{
    protected $signature = 'promociones:vencer';

    protected $description = 'Vence las promociones cuya vigencia ya paso (CU-PRO-04)';

    public function handle(VencerPromocionesHandler $handler): int
    {
        $vencidas = $handler(new Comando);
        $this->info(count($vencidas).' promociones vencidas');

        return self::SUCCESS;
    }
}
