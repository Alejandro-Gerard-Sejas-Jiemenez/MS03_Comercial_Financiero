<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * La regla de Clean Architecture, como check que rompe el build:
 *
 *     Presentation -> Application -> Domain <- Infrastructure
 *
 * Una regla escrita en un documento es una sugerencia; una prueba que falla es
 * un hecho. Si alguien importa Eloquent en el dominio, esto se pone rojo.
 */
final class ReglaDeDependenciasTest extends TestCase
{
    private const PAQUETES = [
        'Shared',
        'Referencias',
        'Monetizacion',
        'Acuerdos',
        'Promociones',
        'Pagos',
        'Liquidaciones',
    ];

    /** Lo unico que un paquete puede tomar de otro. */
    private const PAQUETES_COMPARTIDOS = ['Shared'];

    public function test_el_dominio_no_conoce_laravel(): void
    {
        $infracciones = [];

        foreach ($this->archivosDe('Domain') as $ruta => $contenido) {
            foreach ($this->importacionesDe($contenido) as $importacion) {
                if (str_starts_with($importacion, 'Illuminate\\')) {
                    $infracciones[] = "{$ruta} importa {$importacion}";
                }
            }
        }

        self::assertSame(
            [],
            $infracciones,
            "El Dominio debe poder probarse sin Laravel:\n".implode("\n", $infracciones),
        );
    }

    public function test_la_aplicacion_no_conoce_laravel(): void
    {
        $infracciones = [];

        foreach ($this->archivosDe('Application') as $ruta => $contenido) {
            foreach ($this->importacionesDe($contenido) as $importacion) {
                if (str_starts_with($importacion, 'Illuminate\\')) {
                    $infracciones[] = "{$ruta} importa {$importacion}";
                }
            }
        }

        self::assertSame(
            [],
            $infracciones,
            "Los casos de uso hablan con puertos, no con el framework:\n".implode("\n", $infracciones),
        );
    }

    public function test_el_dominio_no_depende_de_la_aplicacion_ni_de_la_infraestructura(): void
    {
        $infracciones = [];

        foreach ($this->archivosDe('Domain') as $ruta => $contenido) {
            foreach ($this->importacionesDe($contenido) as $importacion) {
                if (! str_starts_with($importacion, 'Src\\')) {
                    continue;
                }

                if (str_contains($importacion, '\\Application\\') || str_contains($importacion, '\\Infrastructure\\')) {
                    $infracciones[] = "{$ruta} importa {$importacion}";
                }
            }
        }

        self::assertSame(
            [],
            $infracciones,
            "Las dependencias solo apuntan hacia adentro:\n".implode("\n", $infracciones),
        );
    }

    public function test_la_aplicacion_no_depende_de_la_infraestructura(): void
    {
        $infracciones = [];

        foreach ($this->archivosDe('Application') as $ruta => $contenido) {
            foreach ($this->importacionesDe($contenido) as $importacion) {
                if (str_starts_with($importacion, 'Src\\') && str_contains($importacion, '\\Infrastructure\\')) {
                    $infracciones[] = "{$ruta} importa {$importacion}";
                }
            }
        }

        self::assertSame(
            [],
            $infracciones,
            "La Aplicacion define puertos; la Infraestructura los implementa:\n"
            .implode("\n", $infracciones),
        );
    }

    public function test_ningun_paquete_entra_al_dominio_de_otro_salvo_shared(): void
    {
        $infracciones = [];

        foreach (self::PAQUETES as $paquete) {
            foreach ($this->archivosDelPaquete($paquete) as $ruta => $contenido) {
                foreach ($this->importacionesDe($contenido) as $importacion) {
                    $otro = $this->paqueteDe($importacion);

                    if ($otro === null || $otro === $paquete) {
                        continue;
                    }

                    if (in_array($otro, self::PAQUETES_COMPARTIDOS, true)) {
                        continue;
                    }

                    // Entre paquetes solo se habla por Domain (estructuras de
                    // datos y enums) o por los puertos de Application.
                    $esContratoPublico = str_contains($importacion, '\\Application\\Port\\')
                        || str_contains($importacion, '\\Application\\UseCase\\')
                        || str_contains($importacion, '\\Domain\\');

                    if (! $esContratoPublico) {
                        $infracciones[] = "{$ruta} importa {$importacion}";
                    }
                }
            }
        }

        self::assertSame(
            [],
            $infracciones,
            "Un paquete no toca la infraestructura de otro:\n".implode("\n", $infracciones),
        );
    }

    /**
     * Archivos de una capa en todos los paquetes.
     *
     * @return array<string, string> ruta relativa => contenido
     */
    private function archivosDe(string $capa): array
    {
        $archivos = [];

        foreach (self::PAQUETES as $paquete) {
            $archivos += $this->archivosEn($this->raiz()."/src/{$paquete}/{$capa}");
        }

        self::assertNotSame([], $archivos, "No se encontro ningun archivo de la capa {$capa}");

        return $archivos;
    }

    /** @return array<string, string> */
    private function archivosDelPaquete(string $paquete): array
    {
        return $this->archivosEn($this->raiz()."/src/{$paquete}");
    }

    /** @return array<string, string> */
    private function archivosEn(string $directorio): array
    {
        if (! is_dir($directorio)) {
            return [];
        }

        $archivos = [];
        $iterador = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directorio));

        /** @var SplFileInfo $archivo */
        foreach ($iterador as $archivo) {
            if ($archivo->isFile() && $archivo->getExtension() === 'php') {
                $ruta = str_replace('\\', '/', $archivo->getPathname());
                $relativa = str_replace(str_replace('\\', '/', $this->raiz()).'/', '', $ruta);
                $archivos[$relativa] = (string) file_get_contents($archivo->getPathname());
            }
        }

        return $archivos;
    }

    /** @return list<string> */
    private function importacionesDe(string $contenido): array
    {
        preg_match_all('/^use\s+([^\s;(]+)/m', $contenido, $coincidencias);

        return $coincidencias[1];
    }

    private function paqueteDe(string $importacion): ?string
    {
        if (! str_starts_with($importacion, 'Src\\')) {
            return null;
        }

        $partes = explode('\\', $importacion);

        return $partes[1] ?? null;
    }

    private function raiz(): string
    {
        return dirname(__DIR__, 2);
    }
}
