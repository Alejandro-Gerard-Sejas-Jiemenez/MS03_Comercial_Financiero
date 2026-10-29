<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Src\Shared\Presentation\Auth\Sesion;
use Tests\TestCase;

final class AutenticacionYRolTest extends TestCase
{
    use EmiteTokens;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararKeycloakFalso();

        Route::middleware('sesion')->get('api/v1/_yo', static function (Request $r) {
            /** @var Sesion $s */
            $s = $r->attributes->get('sesion');

            return ['usuario' => $s->usuarioId, 'roles' => $s->roles];
        });
        Route::middleware(['sesion', 'rol:ADMIN'])->get('api/v1/_admin', static fn () => ['ok' => true]);
    }

    public function test_un_token_valido_deja_pasar_y_expone_usuario_y_roles(): void
    {
        $this->getJson('api/v1/_yo', ['Authorization' => 'Bearer '.$this->token()])
            ->assertOk()
            ->assertJson(['usuario' => '0d9e7f50-1111-4222-8333-444455556666', 'roles' => ['CLIENTE']]);
    }

    public function test_un_token_expirado_de_otro_emisor_otra_audiencia_o_firma_alterada_da_401(): void
    {
        $firmaAlterada = substr($this->token(), 0, -4).'AAAA';
        $malos = [
            'expirado' => $this->token(['exp' => time() - 60]),
            'emisor' => $this->token(['iss' => 'http://otro/realms/x']),
            'audiencia' => $this->token(['aud' => 'otro-servicio']),
            'firma' => $firmaAlterada,
        ];

        foreach ($malos as $caso => $token) {
            $this->getJson('api/v1/_yo', ['Authorization' => 'Bearer '.$token])
                ->assertStatus(401)
                ->assertJsonPath('codigo', 'no_autenticado');
            self::assertTrue(true, $caso);
        }
    }

    public function test_sin_token_da_401(): void
    {
        $this->getJson('api/v1/_yo')->assertStatus(401)->assertJsonPath('codigo', 'no_autenticado');
    }

    public function test_un_rol_insuficiente_da_403_y_el_correcto_pasa(): void
    {
        $h = fn (array $roles): array => ['Authorization' => 'Bearer '.$this->token([], $roles)];

        $this->getJson('api/v1/_admin', $h(['CLIENTE']))
            ->assertStatus(403)
            ->assertJsonPath('codigo', 'operacion_no_permitida');
        $this->getJson('api/v1/_admin', $h(['ADMIN']))->assertOk();
    }
}
