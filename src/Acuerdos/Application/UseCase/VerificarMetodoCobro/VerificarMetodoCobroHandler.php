<?php

declare(strict_types=1);

namespace Src\Acuerdos\Application\UseCase\VerificarMetodoCobro;

use Src\Acuerdos\Domain\MetodoCobro;
use Src\Acuerdos\Domain\MetodoCobroRepository;
use Src\Shared\Application\Port\AuditLogger;
use Src\Shared\Application\Port\TransactionManager;
use Src\Shared\Domain\Exception\RecursoNoEncontrado;

/**
 * CU-ACU-04 VerificarMetodoCobro (NEG, disparador REST).
 *
 * Tiene su propio Handler y no entra en el `Gestionar...` porque no es CRUD:
 * es la decision del Administrador que habilita pagarle al anfitrion. Solo el
 * metodo principal y verificado sirve para liquidar, y eso lo consulta
 * Liquidaciones por el puerto `MetodoCobroQuery`.
 *
 * No emite evento de dominio: el documento solo define eventos de
 * `acuerdo_comercial` hacia MS-01. Queda registrado en auditoria.
 */
final class VerificarMetodoCobroHandler
{
    public function __construct(
        private readonly MetodoCobroRepository $metodos,
        private readonly TransactionManager $transacciones,
        private readonly AuditLogger $auditoria,
    ) {}

    public function manejar(VerificarMetodoCobroCommand $comando): MetodoCobro
    {
        return $this->transacciones->ejecutar(function () use ($comando): MetodoCobro {
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
        });
    }
}
