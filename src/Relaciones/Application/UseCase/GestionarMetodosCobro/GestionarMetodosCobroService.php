<?php

declare(strict_types=1);

namespace Src\Relaciones\Application\UseCase\GestionarMetodosCobro;

use Src\Relaciones\Application\Port\CifradorDeDatos;
use Src\Relaciones\Application\Port\MetodoCobroRepository;
use Src\Relaciones\Application\Response\MetodoCobroResponse;
use Src\Relaciones\Domain\Entity\MetodoCobro;
use Src\Shared\Application\Port\AuditLogger;
use Src\Shared\Application\Port\TransactionManager;
use Src\Shared\Application\Port\UuidGenerator;
use Src\Shared\Domain\Exception\RecursoNoEncontrado;
use Src\Shared\Domain\Exception\ReglaDeNegocioViolada;

/** Caso de uso GestionarMetodosCobro: implementa la interfaz de entrada GestionarMetodosCobroUseCase. */
final class GestionarMetodosCobroService implements GestionarMetodosCobroUseCase
{
    public function __construct(
        private readonly MetodoCobroRepository $metodos,
        private readonly CifradorDeDatos $cifrador,
        private readonly UuidGenerator $uuid,
        private readonly TransactionManager $transacciones,
        private readonly AuditLogger $auditoria,
    ) {}

    private const LARGO_MINIMO_NUMERO = 6;

    public function registrar(RegistrarMetodoCobroRequest $comando): MetodoCobroResponse
    {
        $numero = self::validarNumero($comando->numero);

        return MetodoCobroResponse::desde($this->transacciones->ejecutar(function () use ($comando, $numero): MetodoCobro {
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
        }));
    }

    public function actualizar(ActualizarMetodoCobroRequest $comando): MetodoCobroResponse
    {
        $numero = $comando->numero === null ? null : self::validarNumero($comando->numero);

        return MetodoCobroResponse::desde($this->transacciones->ejecutar(function () use ($comando, $numero): MetodoCobro {
            $metodo = $this->obligatorio($comando->metodoId);
            $antes = $this->retrato($metodo);

            $metodo->actualizarDatos($comando->titular, $comando->banco);

            if ($numero !== null) {
                $metodo->reemplazarNumero($this->cifrador->cifrar($numero), self::ultimosDigitos($numero));
            }

            if ($comando->activo === false) {
                $metodo->desactivar();
            } elseif ($comando->activo === true) {
                $metodo->reactivar();
            }

            if ($comando->esPrincipal === true) {
                $this->degradarAnterior($this->metodos->principalDe($metodo->anfitrionId()), $metodo->metodoId());
                $metodo->marcarPrincipal();
            } elseif ($comando->esPrincipal === false) {
                $metodo->dejarDeSerPrincipal();
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
        }));
    }

    /** @return list<MetodoCobroResponse> */
    public function listarDe(string $anfitrionId): array
    {
        return array_map(MetodoCobroResponse::desde(...), $this->metodos->deAnfitrion($anfitrionId));
    }

    public function consultar(string $metodoId): MetodoCobroResponse
    {
        return MetodoCobroResponse::desde($this->obligatorio($metodoId));
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
            'activo' => $metodo->estaActivo(),
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

    /**
     * Disparador: REST.
     *
     * No es CRUD: es la decision del Administrador que habilita pagarle al anfitrion. Solo el
     * metodo principal y verificado sirve para liquidar, y eso lo consulta
     * Financiero por el puerto `MetodoCobroQuery`.
     *
     * No emite evento de dominio: el documento solo define eventos de
     * `acuerdo_comercial` hacia MS-01. Queda registrado en auditoria.
     */
    public function verificar(VerificarMetodoCobroRequest $comando): MetodoCobroResponse
    {
        return MetodoCobroResponse::desde($this->transacciones->ejecutar(function () use ($comando): MetodoCobro {
            $metodo = $this->metodos->buscar($comando->metodoId)
                ?? throw new RecursoNoEncontrado("El metodo de cobro {$comando->metodoId} no existe");

            $estabaVerificado = $metodo->estaVerificado();

            $metodo->verificar();
            $this->metodos->guardar($metodo);

            $this->auditoria->registrar(
                'VERIFICAR_METODO_COBRO',
                'metodo_cobro_anfitrion',
                $metodo->metodoId(),
                ['verificado' => $estabaVerificado],
                ['verificado' => true, 'ultimos_digitos' => $metodo->ultimosDigitos()],
                $comando->verificadoPor,
            );

            return $metodo;
        }));
    }
}
