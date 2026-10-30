<?php

declare(strict_types=1);

namespace Src\Shared\Adapters\Console;

use Illuminate\Console\Command;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Verifica la regla de dependencia de Clean Architecture: las dependencias solo
 * apuntan hacia adentro.
 *
 *   Domain  <-  Application  <-  Adapters  <-  Infrastructure
 *
 * - Domain: no importa nada de Application, Adapters, Infrastructure ni de Laravel.
 * - Application: solo Domain y Application (de cualquier modulo); sin Laravel.
 * - Adapters: Application y Domain; nunca Infrastructure.
 * - Infrastructure: puede usar todo (es el circulo exterior).
 */
final class VerificarArquitecturaCommand extends Command
{
    protected $signature = 'arquitectura:verificar';

    protected $description = 'Verifica que las capas de Clean Architecture solo dependan hacia adentro';

    /** capa => capas de src/ que NO puede importar */
    private const PROHIBIDO = [
        'Domain' => ['Application', 'Adapters', 'Infrastructure'],
        'Application' => ['Adapters', 'Infrastructure'],
        'Adapters' => ['Infrastructure'],
        'Infrastructure' => [],
    ];

    /** capas que no pueden depender del framework */
    private const SIN_FRAMEWORK = ['Domain', 'Application'];

    public function handle(): int
    {
        $raiz = base_path('src');
        $violaciones = [];
        $archivos = 0;

        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($raiz, RecursiveDirectoryIterator::SKIP_DOTS));
        foreach ($it as $archivo) {
            if (! $archivo->isFile() || $archivo->getExtension() !== 'php') {
                continue;
            }
            $relativa = str_replace('\\', '/', substr($archivo->getPathname(), strlen($raiz) + 1));
            $partes = explode('/', $relativa);
            if (count($partes) < 3 || ! isset(self::PROHIBIDO[$partes[1]])) {
                continue;
            }
            $archivos++;
            $capa = $partes[1];

            preg_match_all('/^use\s+([^;\s]+)(?:\s+as\s+\w+)?;/m', (string) file_get_contents($archivo->getPathname()), $m);
            foreach ($m[1] as $importada) {
                if (preg_match('/^Src\\\\\w+\\\\(\w+)\\\\/', $importada, $dest) === 1 && in_array($dest[1], self::PROHIBIDO[$capa], true)) {
                    $violaciones[] = "{$relativa}: {$capa} no puede depender de {$dest[1]} ({$importada})";
                }
                if (in_array($capa, self::SIN_FRAMEWORK, true) && str_starts_with($importada, 'Illuminate\\')) {
                    $violaciones[] = "{$relativa}: {$capa} no puede depender del framework ({$importada})";
                }
            }
        }

        if ($violaciones !== []) {
            foreach ($violaciones as $v) {
                $this->error($v);
            }
            $this->error(count($violaciones).' violaciones de la regla de dependencia');

            return self::FAILURE;
        }

        $this->info("Arquitectura correcta: {$archivos} archivos revisados, las dependencias solo apuntan hacia adentro.");

        return self::SUCCESS;
    }
}
