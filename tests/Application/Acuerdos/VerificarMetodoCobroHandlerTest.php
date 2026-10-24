<?php

declare(strict_types=1);

namespace Tests\Application\Acuerdos;

use DomainException;
use PHPUnit\Framework\TestCase;
use Src\Acuerdos\Application\UseCase\VerificarMetodoCobro\VerificarMetodoCobroCommand;
use Src\Acuerdos\Application\UseCase\VerificarMetodoCobro\VerificarMetodoCobroHandler;
use Src\Acuerdos\Domain\MetodoCobro;
use Src\Acuerdos\Domain\TipoMetodoCobro;
use Tests\Doubles\InMemoryMetodoCobroRepository;
use Tests\Doubles\SpyAuditLogger;
use Tests\Doubles\SyncTransactionManager;

/**
 * CU-ACU-04 `VerificarMetodoCobro`: el Administrador marca el metodo como
 * verificado, y solo entonces sirve para liquidar.
 */
final class VerificarMetodoCobroHandlerTest extends TestCase
{
    private InMemoryMetodoCobroRepository $metodos;

    private SpyAuditLogger $auditoria;

    private SyncTransactionManager $transacciones;

    private VerificarMetodoCobroHandler $handler;

    protected function setUp(): void
    {
        $this->metodos = new InMemoryMetodoCobroRepository();
        $this->auditoria = new SpyAuditLogger();
        $this->transacciones = new SyncTransactionManager();

        $this->handler = new VerificarMetodoCobroHandler(
            $this->metodos,
            $this->transacciones,
            $this->auditoria,
        );
    }

    public function test_solo_el_metodo_verificado_sirve_para_liquidar(): void
    {
        $metodo = $this->principalSinVerificar();
        $this->metodos->guardar($metodo);

        self::assertFalse($metodo->sirveParaLiquidar(), 'sin verificar no se liquida');

        $verificado = $this->handler->manejar(new VerificarMetodoCobroCommand(
            metodoId: 'met-1',
            verificadoPor: 'admin-1',
        ));

        self::assertTrue($verificado->estaVerificado());
        self::assertTrue($verificado->sirveParaLiquidar());
        self::assertTrue($this->metodos->buscar('met-1')?->sirveParaLiquidar());
    }

    public function test_un_metodo_verificado_que_no_es_principal_no_sirve_para_liquidar(): void
    {
        $metodo = MetodoCobro::registrar(
            metodoId: 'met-2',
            anfitrionId: 'anf-1',
            tipo: TipoMetodoCobro::QR,
            titular: 'Ana Quispe',
            numeroCifrado: 'cif:ZZZZ',
            ultimosDigitos: '4321',
        );
        $this->metodos->guardar($metodo);

        $verificado = $this->handler->manejar(new VerificarMetodoCobroCommand(
            metodoId: 'met-2',
            verificadoPor: 'admin-1',
        ));

        self::assertTrue($verificado->estaVerificado());
        self::assertFalse($verificado->sirveParaLiquidar());
    }

    public function test_verificar_queda_registrado_en_auditoria(): void
    {
        $this->metodos->guardar($this->principalSinVerificar());

        $this->handler->manejar(new VerificarMetodoCobroCommand(
            metodoId: 'met-1',
            verificadoPor: 'admin-1',
        ));

        self::assertSame(
            [['accion' => 'VERIFICAR_METODO_COBRO', 'entidad' => 'metodo_cobro_anfitrion', 'entidadId' => 'met-1']],
            $this->auditoria->registros(),
        );
        self::assertSame(1, $this->transacciones->transacciones());
    }

    public function test_un_metodo_que_no_existe_se_rechaza(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('no existe');

        $this->handler->manejar(new VerificarMetodoCobroCommand(
            metodoId: 'met-fantasma',
            verificadoPor: 'admin-1',
        ));
    }

    private function principalSinVerificar(): MetodoCobro
    {
        $metodo = MetodoCobro::registrar(
            metodoId: 'met-1',
            anfitrionId: 'anf-1',
            tipo: TipoMetodoCobro::CUENTA_BANCARIA,
            titular: 'Ana Quispe',
            numeroCifrado: 'cif:XXXX',
            ultimosDigitos: '1234',
            banco: 'Banco Nacional',
            esPrincipal: true,
        );

        return $metodo;
    }
}
