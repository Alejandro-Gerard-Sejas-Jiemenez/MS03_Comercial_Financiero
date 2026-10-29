<?php

declare(strict_types=1);

namespace Src\Shared\Presentation\Auth;

/**
 * Puerto de autenticacion de la capa HTTP. Cambiar de proveedor de identidad
 * es cambiar la implementacion enlazada en el provider, nada mas.
 */
interface AutenticadorDeSolicitud
{
    /**
     * @param  string|null  $token  valor del Bearer, sin el prefijo
     *
     * @throws NoAutenticado
     */
    public function autenticar(?string $token): Sesion;
}
