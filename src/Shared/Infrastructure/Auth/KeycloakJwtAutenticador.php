<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Auth;

use Closure;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Src\Shared\Application\Auth\AutenticadorDeSolicitud;
use Src\Shared\Application\Auth\NoAutenticado;
use Src\Shared\Application\Auth\Sesion;
use Throwable;

/**
 * Valida un JWT de Keycloak: firma con las claves publicas (JWKS), `exp`,
 * `iss` y `aud`. No guarda ningun secreto compartido.
 */
final class KeycloakJwtAutenticador implements AutenticadorDeSolicitud
{
    /**
     * @param  Closure():array<string, mixed>  $jwks  devuelve el JWKS como arreglo ({"keys": [...]})
     */
    public function __construct(
        private readonly string $issuer,
        private readonly string $audience,
        private readonly Closure $jwks,
    ) {}

    public function autenticar(?string $token): Sesion
    {
        if ($token === null || trim($token) === '') {
            throw new NoAutenticado('Falta el token de acceso');
        }

        try {
            $claims = (array) JWT::decode($token, JWK::parseKeySet(($this->jwks)(), 'RS256'));
        } catch (Throwable $e) {
            throw new NoAutenticado('Token invalido o vencido', 0, $e);
        }

        if (($claims['iss'] ?? null) !== $this->issuer) {
            throw new NoAutenticado('Token emitido por un emisor desconocido');
        }

        $aud = $claims['aud'] ?? [];
        if (! in_array($this->audience, is_array($aud) ? $aud : [$aud], true)) {
            throw new NoAutenticado('Token no destinado a este servicio');
        }

        $sub = $claims['sub'] ?? null;
        if (! is_string($sub) || $sub === '') {
            throw new NoAutenticado('Token sin sujeto');
        }

        $realm = (array) ($claims['realm_access'] ?? []);
        $roles = array_values(array_filter((array) ($realm['roles'] ?? []), 'is_string'));

        return new Sesion($sub, $roles);
    }
}
