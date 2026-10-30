<?php

declare(strict_types=1);

namespace Src\Relaciones\Adapters\Console;

use Illuminate\Console\Command;
use Src\Relaciones\Application\UseCase\GestionarAcuerdosComerciales\GestionarAcuerdosComercialesUseCase;

/** JOB: pasa a FINALIZADO los acuerdos comerciales cuya fecha de fin ya paso. */
final class FinalizarAcuerdosVencidosCommand extends Command
{
    protected $signature = 'acuerdos:finalizar-vencidos';

    protected $description = 'Finaliza los acuerdos comerciales ACTIVO cuya vigencia ya termino';

    public function handle(GestionarAcuerdosComercialesUseCase $acuerdos): int
    {
        $this->info(count($acuerdos->finalizarVencidos()).' acuerdos finalizados');

        return self::SUCCESS;
    }
}
