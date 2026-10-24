<?php

declare(strict_types=1);

namespace Tests\Application\Acuerdos;

use DomainException;
use PHPUnit\Framework\TestCase;
use Src\Acuerdos\Application\UseCase\GestionarMetodosCobro\ActualizarMetodoCobroCommand;
use Src\Acuerdos\Application\UseCase\GestionarMetodosCobro\GestionarMetodosCobroService;
use Src\Acuerdos\Application\UseCase\GestionarMetodosCobro\RegistrarMetodoCobroCommand;
use Src\Acuerdos\Domain\MetodoCobro;
use Src\Acuerdos\Domain\TipoMetodoCobro;
use Tests\Application\Acuerdos\Doubles\AuditoriaDetallada;
use Tests\Application\Acuerdos\Doubles\CifradorFalso;
use Tests\Doubles\InMemoryMetodoCobroRepository;
use Tests\Doubles\SequentialUuidGenerator;
use Tests\Doubles\SyncTransactionManager;

/**
 * CU-ACU-03 `GestionarMetodosCobro`: el numero va cifrado y en claro solo
 * quedan los ultimos cuatro digitos; un solo metodo principal por anfitrion.
 */
final class GestionarMetodosCobroServiceTest extends TestCase
{
    private const NUMERO = '4111111111111234';

    private InMemoryMetodoCobroRepository $metodos;

    private CifradorFalso $cifrador;

    private AuditoriaDetallada $auditoria;

    private SyncTransactionManager $transacciones;

    private GestionarMetodosCobroService $servicio;

    protected function setUp(): void
    {
        $this->metodos = new InMemoryMetodoCobroRepository();
        $this->cifrador = new CifradorFalso();
        $this->auditoria = new AuditoriaDetallada();
        $this->transacciones = new SyncTransactionManager();

        $this->servicio = new GestionarMetodosCobroService(
            $this->metodos,
            $this->cifrador,
            new SequentialUuidGenerator(),
            $this->transacciones,
            $this->auditoria,
        );
    }

    public function test_el_numero_de_cobro_nunca_se_guarda_en_claro(): void
    {
        $metodo = $this->servicio->registrar($this->comandoDe('anf-1'));

        self::assertNotSame(self::NUMERO, $metodo->numeroCifrado());
        self::assertStringNotContainsString(self::NUMERO, $metodo->numeroCifrado());
        self::assertSame(self::NUMERO, $this->cifrador->descifrar($metodo->numeroCifrado()));
        // En texto claro solo quedan los ultimos cuatro digitos.
        self::assertSame('1234', $metodo->ultimosDigitos());
        self::assertStringNotContainsString(self::NUMERO, print_r($this->metodos->todos(), true));
    }

    public function test_la_auditoria_tampoco_registra_el_numero(): void
    {
        $this->servicio->registrar($this->comandoDe('anf-1'));

        self::assertStringNotContainsString(self::NUMERO, $this->auditoria->volcado());
        self::assertSame(['REGISTRAR_METODO_COBRO'], $this->auditoria->acciones());
    }

    public function test_un_anfitrion_solo_puede_tener_un_metodo_principal(): void
    {
        $primero = $this->servicio->registrar($this->comandoDe('anf-1', esPrincipal: true));
        $segundo = $this->servicio->registrar($this->comandoDe('anf-1', esPrincipal: true));

        self::assertFalse($this->metodos->buscar($primero->metodoId())?->esPrincipal());
        self::assertTrue($this->metodos->buscar($segundo->metodoId())?->esPrincipal());
        self::assertSame($segundo->metodoId(), $this->metodos->principalDe('anf-1')?->metodoId());
        self::assertCount(2, $this->metodos->deAnfitrion('anf-1'));
    }

    public function test_definir_principal_degrada_al_anterior(): void
    {
        $primero = $this->servicio->registrar($this->comandoDe('anf-1', esPrincipal: true));
        $segundo = $this->servicio->registrar($this->comandoDe('anf-1'));

        $this->servicio->definirPrincipal($segundo->metodoId(), 'anf-1');

        self::assertFalse($this->metodos->buscar($primero->metodoId())?->esPrincipal());
        self::assertTrue($this->metodos->buscar($segundo->metodoId())?->esPrincipal());
    }

    public function test_el_primer_metodo_del_anfitrion_queda_principal_por_defecto(): void
    {
        $metodo = $this->servicio->registrar($this->comandoDe('anf-1'));

        self::assertTrue($metodo->esPrincipal(), 'si no tiene otro, el primero es el principal');
    }

    public function test_el_metodo_de_otro_anfitrion_no_se_toca(): void
    {
        $ajeno = $this->servicio->registrar($this->comandoDe('anf-2', esPrincipal: true));
        $this->servicio->registrar($this->comandoDe('anf-1', esPrincipal: true));

        self::assertTrue($this->metodos->buscar($ajeno->metodoId())?->esPrincipal());
    }

    public function test_cambiar_el_numero_lo_vuelve_a_cifrar_y_quita_la_verificacion(): void
    {
        $metodo = $this->servicio->registrar($this->comandoDe('anf-1'));
        $metodo->verificar();
        $this->metodos->guardar($metodo);

        $actualizado = $this->servicio->actualizar(new ActualizarMetodoCobroCommand(
            metodoId: $metodo->metodoId(),
            actualizadoPor: 'anf-1',
            titular: 'Ana Quispe Vargas',
            numero: '5555444433339876',
        ));

        self::assertSame('9876', $actualizado->ultimosDigitos());
        self::assertSame('5555444433339876', $this->cifrador->descifrar($actualizado->numeroCifrado()));
        self::assertFalse($actualizado->estaVerificado());
        self::assertSame('Ana Quispe Vargas', $actualizado->titular());
    }

    public function test_rechaza_un_numero_demasiado_corto(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('numero');

        $this->servicio->registrar(new RegistrarMetodoCobroCommand(
            anfitrionId: 'anf-1',
            tipo: TipoMetodoCobro::CUENTA_BANCARIA,
            titular: 'Ana Quispe',
            numero: '123',
            registradoPor: 'anf-1',
        ));
    }

    public function test_listar_devuelve_los_metodos_del_anfitrion(): void
    {
        $this->servicio->registrar($this->comandoDe('anf-1'));
        $this->servicio->registrar($this->comandoDe('anf-1'));
        $this->servicio->registrar($this->comandoDe('anf-2'));

        self::assertCount(2, $this->servicio->listarDe('anf-1'));
        self::assertCount(1, $this->servicio->listarDe('anf-2'));
    }

    public function test_definir_principal_exige_que_el_metodo_sea_del_anfitrion(): void
    {
        $ajeno = $this->servicio->registrar($this->comandoDe('anf-2'));

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('no es del anfitrion');

        $this->servicio->definirPrincipal($ajeno->metodoId(), 'anf-1');
    }

    public function test_registrar_y_degradar_ocurren_en_una_sola_transaccion(): void
    {
        $this->servicio->registrar($this->comandoDe('anf-1', esPrincipal: true));
        $this->servicio->registrar($this->comandoDe('anf-1', esPrincipal: true));

        self::assertSame(2, $this->transacciones->transacciones());
        self::assertSame(0, $this->transacciones->revertidas());
    }

    public function test_un_metodo_que_no_existe_se_rechaza(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('no existe');

        $this->servicio->consultar('met-fantasma');
    }

    public function test_guarda_el_tipo_y_el_banco_tal_cual(): void
    {
        $metodo = $this->servicio->registrar(new RegistrarMetodoCobroCommand(
            anfitrionId: 'anf-1',
            tipo: TipoMetodoCobro::BILLETERA,
            titular: 'Ana Quispe',
            numero: self::NUMERO,
            registradoPor: 'anf-1',
            banco: 'Billetera Tigo Money',
        ));

        self::assertSame(TipoMetodoCobro::BILLETERA, $metodo->tipo());
        self::assertSame('Billetera Tigo Money', $metodo->banco());
        self::assertInstanceOf(MetodoCobro::class, $metodo);
    }

    private function comandoDe(string $anfitrionId, bool $esPrincipal = false): RegistrarMetodoCobroCommand
    {
        return new RegistrarMetodoCobroCommand(
            anfitrionId: $anfitrionId,
            tipo: TipoMetodoCobro::CUENTA_BANCARIA,
            titular: 'Ana Quispe',
            numero: self::NUMERO,
            registradoPor: $anfitrionId,
            banco: 'Banco Nacional',
            esPrincipal: $esPrincipal,
        );
    }
}
