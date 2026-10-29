<?php

declare(strict_types=1);

namespace Src\Acuerdos\Application\UseCase\GestionarMetodosCobro;

use Src\Acuerdos\Application\Port\CifradorDeDatos;
use Src\Acuerdos\Domain\MetodoCobro;
use Src\Acuerdos\Domain\MetodoCobroRepository;
use Src\Shared\Application\Port\AuditLogger;
use Src\Shared\Application\Port\TransactionManager;
use Src\Shared\Application\Port\UuidGenerator;
use Src\Shared\Domain\Exception\OperacionNoPermitida;
use Src\Shared\Domain\Exception\RecursoNoEncontrado;
use Src\Shared\Domain\Exception\ReglaDeNegocioViolada;

/**
 * CU-ACU-03 GestionarMetodosCobro (CRUD, disparador REST).
 *
 * Dos cosas pasan aqui y no en el Dominio:
 *
 * 1. Cifrar. El numero llega en claro, se guarda cifrado en `numero_cifrado` y
 *    en texto claro solo quedan los cuatro ultimos digitos. El cifrado es un
 *    detalle de infraestructura detras del puerto `CifradorDeDatos`: el
 *    dominio nunca ve `Crypt`.
 * 2. Un solo metodo principal por anfitrion. La entidad no puede decidirlo
 *    sola (mira los otros metodos del anfitrion), asi que al marcar uno se
 *    degrada el anterior en la misma transaccion. La base lo respalda con el
 *    indice unico parcial `uq_metodo_principal`.
 */
final class GestionarMetodosCobroService
{
    private const LARGO_MINIMO_NUMERO = 6;

    public function __construct(
        private readonly MetodoCobroRepository $metodos,
        private readonly CifradorDeDatos $cifrador,
        private readonly UuidGenerator $uuid,
        private readonly TransactionManager $transacciones,
        private readonly AuditLogger $auditoria,
    ) {}

    public function registrar(RegistrarMetodoCobroCommand $comando): MetodoCobro
    {
        $numero = self::validarNumero($comando->numero);

        return $this->transacciones->ejecutar(function () use ($comando, $numero): MetodoCobro {
            // Si el anfitrion todavia no tiene ninguno, el primero es el principal:
            // sin metodo principal no hay a donde pagarle.
            $anterior = $this->metodos->principalDe($comando->anfitrionId);
            $principal = $comando->esPrincipal || $anterior === null;

            $metodo = MetodoCobro::registrar(
                metodoId: $this->uuid->generar(),
                anfitrionId: $comando->anfitrionId,
                tipo: $comando->tipo,
                titular: $comando->titular,
                numeroCifrado: $this->cifrador->cifrar($numero),
                ultimosDigitos: self::ultimosDigitos($numero),
                banco: $comando->banco,
                esPrincipal: $principal,
            );

            if ($principal) {
                $this->degradarAnterior($anterior, $metodo->metodoId());
            }

            $this->metodos->guardar($metodo);
            $this->auditoria->registrar(
                'REGISTRAR_METODO_COBRO',
                'metodo_cobro_anfitrion',
                $metodo->metodoId(),
                null,
                $this->retrato($metodo),
                $comando->registradoPor,
            );

            return $metodo;
        });
    }

    public function actualizar(ActualizarMetodoCobroCommand $comando): MetodoCobro
    {
        $numero = $comando->numero === null ? null : self::validarNumero($comando->numero);

        return $this->transacciones->ejecutar(function () use ($comando, $numero): MetodoCobro {
            $metodo = $this->obligatorio($comando->metodoId);
            $antes = $this->retrato($metodo);

            $metodo->actualizarDatos($comando->titular, $comando->banco);

            if ($numero !== null) {
                $metodo->reemplazarNumero($this->cifrador->cifrar($numero), self::ultimosDigitos($numero));
            }

            $this->metodos->guardar($metodo);
            $this->auditoria->registrar(
                'ACTUALIZAR_METODO_COBRO',
                'metodo_cobro_anfitrion',
                $metodo->metodoId(),
                $antes,
                $this->retrato($metodo),
                $comando->actualizadoPor,
            );

            return $metodo;
        });
    }

    /** El anfitrion elige cual de sus metodos es el principal. */
    public function definirPrincipal(string $metodoId, string $anfitrionId): MetodoCobro
    {
        return $this->transacciones->ejecutar(function () use ($metodoId, $anfitrionId): MetodoCobro {
            $metodo = $this->obligatorio($metodoId);

            if (! $metodo->esDe($anfitrionId)) {
                throw new OperacionNoPermitida("El metodo {$metodoId} no es del anfitrion {$anfitrionId}");
            }

            $this->degradarAnterior($this->metodos->principalDe($anfitrionId), $metodoId);

            $metodo->marcarPrincipal();
            $this->metodos->guardar($metodo);

            $this->auditoria->registrar(
                'DEFINIR_METODO_COBRO_PRINCIPAL',
                'metodo_cobro_anfitrion',
                $metodoId,
                null,
                ['es_principal' => true],
                $anfitrionId,
            );

            return $metodo;
        });
    }

    /** @return list<MetodoCobro> */
    public function listarDe(string $anfitrionId): array
    {
        return $this->metodos->deAnfitrion($anfitrionId);
    }

    public function consultar(string $metodoId): MetodoCobro
    {
        return $this->obligatorio($metodoId);
    }

    private function degradarAnterior(?MetodoCobro $anterior, string $nuevoId): void
    {
        if ($anterior === null || $anterior->metodoId() === $nuevoId) {
            return;
        }

        $anterior->dejarDeSerPrincipal();
        $this->metodos->guardar($anterior);
    }

    private function obligatorio(string $metodoId): MetodoCobro
    {
        return $this->metodos->buscar($metodoId)
            ?? throw new RecursoNoEncontrado("El metodo de cobro {$metodoId} no existe");
    }

    /**
     * Retrato para auditoria: a proposito NO incluye `numero_cifrado` ni el
     * numero en claro, solo los cuatro ultimos digitos.
     *
     * @return array<string, mixed>
     */
    private function retrato(MetodoCobro $metodo): array
    {
        return [
            'anfitrion_id' => $metodo->anfitrionId(),
            'tipo' => $metodo->tipo()->value,
            'banco' => $metodo->banco(),
            'titular' => $metodo->titular(),
            'ultimos_digitos' => $metodo->ultimosDigitos(),
            'es_principal' => $metodo->esPrincipal(),
            'verificado' => $metodo->estaVerificado(),
        ];
    }

    private static function validarNumero(string $numero): string
    {
        $limpio = preg_replace('/\s+/', '', trim($numero)) ?? '';

        if (strlen($limpio) < self::LARGO_MINIMO_NUMERO) {
            throw new ReglaDeNegocioViolada(
                'El numero de la cuenta es demasiado corto: necesita al menos '
                .self::LARGO_MINIMO_NUMERO.' caracteres',
            );
        }

        return $limpio;
    }

    private static function ultimosDigitos(string $numero): ?string
    {
        $soloDigitos = preg_replace('/\D+/', '', $numero) ?? '';

        return strlen($soloDigitos) >= 4 ? substr($soloDigitos, -4) : null;
    }
}
