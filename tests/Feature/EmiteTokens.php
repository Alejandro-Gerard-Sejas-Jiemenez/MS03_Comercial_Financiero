<?php

declare(strict_types=1);

namespace Tests\Feature;

use Firebase\JWT\JWT;
use Src\Shared\Presentation\Auth\AutenticadorDeSolicitud;
use Src\Shared\Presentation\Auth\KeycloakJwtAutenticador;

/**
 * Hace de Keycloak en las pruebas: genera un par RSA y firma tokens con el,
 * y enlaza el autenticador real con el JWKS de esa clave. Sin servidor.
 */
trait EmiteTokens
{
    private const ISSUER = 'http://keycloak.test/realms/alojate';

    private const AUDIENCE = 'ms03-comercial-financiero';

    private string $clavePrivada = '';

    /** @var array<string, mixed> */
    private array $jwks;

    protected function prepararKeycloakFalso(): void
    {
        // En Windows PHP no trae openssl.cnf: se le da uno minimo para generar la clave.
        $cnf = sys_get_temp_dir().DIRECTORY_SEPARATOR.'ms03-openssl-test.cnf';
        file_put_contents($cnf, '[req]
distinguished_name=dn
[dn]
');
        $opciones = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA, 'config' => $cnf];

        $par = openssl_pkey_new($opciones);
        openssl_pkey_export($par, $this->clavePrivada, null, $opciones);
        $rsa = openssl_pkey_get_details($par)['rsa'];

        $b64 = static fn (string $bin): string => rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
        $this->jwks = ['keys' => [[
            'kty' => 'RSA', 'alg' => 'RS256', 'use' => 'sig', 'kid' => 'k1',
            'n' => $b64($rsa['n']), 'e' => $b64($rsa['e']),
        ]]];

        $jwks = $this->jwks;
        $this->app->singleton(
            AutenticadorDeSolicitud::class,
            static fn (): AutenticadorDeSolicitud => new KeycloakJwtAutenticador(
                self::ISSUER,
                self::AUDIENCE,
                static fn (): array => $jwks,
            ),
        );
    }

    /**
     * @param  array<string, mixed>  $cambios  claims que pisan los validos
     * @param  list<string>  $roles
     */
    protected function token(array $cambios = [], array $roles = ['CLIENTE']): string
    {
        $claims = array_merge([
            'iss' => self::ISSUER,
            'aud' => self::AUDIENCE,
            'sub' => '0d9e7f50-1111-4222-8333-444455556666',
            'exp' => time() + 300,
            'realm_access' => ['roles' => $roles],
        ], $cambios);

        return JWT::encode($claims, $this->clavePrivada, 'RS256', 'k1');
    }
}
