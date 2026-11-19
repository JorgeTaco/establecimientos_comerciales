<?php

namespace Tests\Unit;

use App\Importacion\NormalizadorFilas;
use App\Importacion\ResolutorCategorias;
use PHPUnit\Framework\TestCase;

class NormalizadorFilasTest extends TestCase
{
    public function test_normaliza_columnas_del_excel_al_contrato_de_la_api(): void
    {
        $resultado = $this->normalizador()->normalizar([
            'nombre' => '  Tienda Ficticia Uno  ',
            'tipo' => 'MINIMERCADO',
            'parroquia' => 'Mulaló',
            'latitud' => '-0,775',
            'longitud' => '-78.5800001',
            'interes_encuesta' => 'Sí',
            'categorias' => 'Lácteos; Abarrotes',
            'perfil.direccion' => 'Calle Ficticia 123',
            'perfil.canton' => 'Latacunga',
            'perfil.productos_buscados' => 'Yogur; Leche',
            'perfil.tiene_refrigeracion' => 'SI',
            'perfil.contacto_telefono' => '(099) 123-4567',
            'perfil.contacto_email' => 'tienda@example.test',
            'perfil.canales_contacto' => 'WhatsApp; Correo',
            'perfil.modalidad_compra' => 'Crédito',
            'perfil.pedido_inicial' => '12',
            'perfil.decision_compra' => 'Oficina Central',
        ], 4);

        $this->assertSame([], $resultado['errores']);
        $this->assertSame(4, $resultado['fila']);
        $this->assertSame('Tienda Ficticia Uno', $resultado['datos']['nombre']);
        $this->assertSame('minimercado', $resultado['datos']['tipo']);
        $this->assertSame(-0.775, $resultado['datos']['latitud']);
        $this->assertSame(-78.58, $resultado['datos']['longitud']);
        $this->assertSame('por_confirmar', $resultado['datos']['recepcion_proveedores']);
        $this->assertTrue($resultado['datos']['interes_encuesta']);
        $this->assertSame([1, 2], $resultado['datos']['categorias']);
        $this->assertSame('0991234567', $resultado['datos']['perfil']['contacto_telefono']);
        $this->assertSame(['whatsapp', 'email'], $resultado['datos']['perfil']['canales_contacto']);
        $this->assertSame(['Yogur', 'Leche'], $resultado['datos']['perfil']['productos_buscados']);
        $this->assertTrue($resultado['datos']['perfil']['tiene_refrigeracion']);
        $this->assertSame('credito', $resultado['datos']['perfil']['modalidad_compra']);
        $this->assertSame(12, $resultado['datos']['perfil']['pedido_inicial']);
        $this->assertSame('oficina_central', $resultado['datos']['perfil']['decision_compra']);
        $this->assertFalse($resultado['datos']['perfil']['autorizar_contacto']);
    }

    public function test_acepta_fichas_sin_coordenadas(): void
    {
        $resultado = $this->normalizador()->normalizar([
            'nombre' => 'Tienda Sin Mapa',
            'tipo' => 'tienda',
            'parroquia' => 'Guaytacama',
        ], 0);

        $this->assertSame([], $resultado['errores']);
        $this->assertNull($resultado['datos']['latitud']);
        $this->assertNull($resultado['datos']['longitud']);
    }

    public function test_rechaza_coordenadas_incompletas_y_fuera_de_rango(): void
    {
        $normalizador = $this->normalizador();

        $incompleta = $normalizador->normalizar([
            'nombre' => 'Tienda Sin Longitud',
            'tipo' => 'tienda',
            'parroquia' => 'Centro',
            'latitud' => '-0.775',
        ], 0);
        $this->assertArrayHasKey('latitud', $incompleta['errores']);

        $fueraDeRango = $normalizador->normalizar([
            'nombre' => 'Tienda Fuera de Rango',
            'tipo' => 'tienda',
            'parroquia' => 'Centro',
            'latitud' => '95',
            'longitud' => '-10',
        ], 1);
        $this->assertArrayHasKey('latitud', $fueraDeRango['errores']);
    }

    public function test_interpreta_los_textos_de_ausencia_del_excel_como_nulos(): void
    {
        $resultado = $this->normalizador()->normalizar([
            'nombre' => 'Tienda Sin Contacto',
            'tipo' => 'tienda',
            'parroquia' => 'Mulaló',
            'perfil.contacto_telefono' => 'No tiene',
            'perfil.contacto_email' => 'No dispone de correo electrónico',
            'perfil.tiene_refrigeracion' => 'No',
        ], 0);

        $this->assertSame([], $resultado['errores']);
        $this->assertNull($resultado['datos']['perfil']['contacto_telefono']);
        $this->assertNull($resultado['datos']['perfil']['contacto_email']);
        $this->assertFalse($resultado['datos']['perfil']['tiene_refrigeracion']);
        $this->assertArrayNotHasKey('canales_contacto', $resultado['datos']['perfil']);
    }

    public function test_marca_campos_obligatorios_categoria_desconocida_y_canal_sin_datos(): void
    {
        $resultado = $this->normalizador()->normalizar([
            'nombre' => '',
            'parroquia' => 'Centro',
            'categorias' => 'Inexistente',
            'perfil.canales_contacto' => 'email',
        ], 2);

        $this->assertArrayHasKey('nombre', $resultado['errores']);
        $this->assertArrayHasKey('tipo', $resultado['errores']);
        $this->assertArrayHasKey('categorias', $resultado['errores']);
        $this->assertArrayHasKey('perfil.canales_contacto', $resultado['errores']);
    }

    private function normalizador(): NormalizadorFilas
    {
        $categorias = new class implements ResolutorCategorias
        {
            public function resolver(array $nombres): array
            {
                $ids = [];
                $desconocidas = [];
                foreach ($nombres as $nombre) {
                    if ($nombre === 'Lácteos') {
                        $ids[] = 1;
                    } elseif ($nombre === 'Abarrotes') {
                        $ids[] = 2;
                    } else {
                        $desconocidas[] = $nombre;
                    }
                }

                return ['ids' => array_values(array_unique($ids)), 'desconocidas' => $desconocidas];
            }
        };

        return new NormalizadorFilas($categorias);
    }
}
