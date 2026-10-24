<?php

declare(strict_types=1);

namespace Tests\Unit\Acuerdos;

use DomainException;
use PHPUnit\Framework\TestCase;
use Src\Acuerdos\Domain\MetodoCobro;
use Src\Acuerdos\Domain\TipoMetodoCobro;

/**
 * CU-ACU-03 y CU-ACU-04: el metodo de cobro del anfitrion. La entidad solo
 * conoce el numero ya cifrado; cifrar es tarea de Infraestructura.
 */
final class MetodoCobroTest extends TestCase
{
    public function test_un_metodo_nace_sin_verificar_y_sin_ser_principal(): void
    {
        $metodo = $this->metodo();

        self::assertFalse($metodo->esPrincipal());
        self::assertFalse($metodo->estaVerificado());
        self::assertFalse($metodo->sirveParaLiquidar());
    }

    public function test_la_entidad_solo_conoce_el_numero_cifrado_y_los_ultimos_digitos(): void
    {
        $metodo = $this->metodo();

        self::assertSame('cif:XXXX', $metodo->numeroCifrado());
        self::assertSame('1234', $metodo->ultimosDigitos());
        // No existe ningun accesor al numero en claro: el dominio nunca lo tiene.
        self::assertFalse(method_exists($metodo, 'numero'));
        self::assertFalse(method_exists($metodo, 'numeroEnClaro'));
    }

    public function test_solo_un_metodo_principal_y_verificado_sirve_para_liquidar(): void
    {
        $metodo = $this->metodo();

        $metodo->marcarPrincipal();
        self::assertFalse($metodo->sirveParaLiquidar(), 'principal pero sin verificar');

        $metodo->verificar();
        self::assertTrue($metodo->sirveParaLiquidar());

        $metodo->dejarDeSerPrincipal();
        self::assertFalse($metodo->sirveParaLiquidar(), 'verificado pero ya no es principal');
    }

    public function test_reemplazar_el_numero_obliga_a_verificar_de_nuevo(): void
    {
        $metodo = $this->metodo();
        $metodo->verificar();

        $metodo->reemplazarNumero('cif:YYYY', '9876');

        self::assertSame('cif:YYYY', $metodo->numeroCifrado());
        self::assertSame('9876', $metodo->ultimosDigitos());
        self::assertFalse($metodo->estaVerificado());
    }

    public function test_actualizar_datos_cambia_titular_y_banco(): void
    {
        $metodo = $this->metodo();

        $metodo->actualizarDatos(titular: 'Ana Quispe Vargas', banco: 'Banco Union');

        self::assertSame('Ana Quispe Vargas', $metodo->titular());
        self::assertSame('Banco Union', $metodo->banco());
    }

    public function test_rechaza_un_titular_vacio(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('titular');

        MetodoCobro::registrar(
            metodoId: 'met-1',
            anfitrionId: 'anf-1',
            tipo: TipoMetodoCobro::CUENTA_BANCARIA,
            titular: '   ',
            numeroCifrado: 'cif:XXXX',
            ultimosDigitos: '1234',
        );
    }

    public function test_rechaza_un_numero_cifrado_vacio(): void
    {
        $this->expectException(DomainException::class);

        MetodoCobro::registrar(
            metodoId: 'met-1',
            anfitrionId: 'anf-1',
            tipo: TipoMetodoCobro::CUENTA_BANCARIA,
            titular: 'Ana Quispe',
            numeroCifrado: '',
            ultimosDigitos: '1234',
        );
    }

    public function test_rechaza_ultimos_digitos_que_no_sean_cuatro_digitos(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('ultimos digitos');

        MetodoCobro::registrar(
            metodoId: 'met-1',
            anfitrionId: 'anf-1',
            tipo: TipoMetodoCobro::CUENTA_BANCARIA,
            titular: 'Ana Quispe',
            numeroCifrado: 'cif:XXXX',
            ultimosDigitos: '12',
        );
    }

    public function test_verificar_dos_veces_es_idempotente(): void
    {
        $metodo = $this->metodo();

        $metodo->verificar();
        $metodo->verificar();

        self::assertTrue($metodo->estaVerificado());
    }

    private function metodo(): MetodoCobro
    {
        return MetodoCobro::registrar(
            metodoId: 'met-1',
            anfitrionId: 'anf-1',
            tipo: TipoMetodoCobro::CUENTA_BANCARIA,
            titular: 'Ana Quispe',
            numeroCifrado: 'cif:XXXX',
            ultimosDigitos: '1234',
            banco: 'Banco Nacional',
        );
    }
}
