<?php

namespace Tests\Feature;

use App\Models\Categoria;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ImportacionCsvTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_comando_prepara_lotes_sin_escribir_en_la_base(): void
    {
        $categoria = Categoria::query()->create(['nombre' => 'Lácteos']);
        [$archivo, $destino] = $this->archivosTemporales();

        try {
            $this->artisan('establecimientos:preparar-lotes', ['archivo' => $archivo, '--destino' => $destino])
                ->assertSuccessful();

            $lote = json_decode(file_get_contents($destino.'/lote-1.json'), true);
            $this->assertCount(1, $lote['filas']);
            $this->assertSame('Tienda Ficticia Uno', $lote['filas'][0]['nombre']);
            $this->assertSame(-0.775, $lote['filas'][0]['latitud']);
            $this->assertSame('por_confirmar', $lote['filas'][0]['recepcion_proveedores']);
            $this->assertTrue($lote['filas'][0]['interes_encuesta']);
            $this->assertSame([$categoria->id], $lote['filas'][0]['categorias']);
            $this->assertSame('0991234567', $lote['filas'][0]['perfil']['contacto_telefono']);
            $this->assertFalse($lote['filas'][0]['perfil']['autorizar_contacto']);

            $informe = json_decode(file_get_contents($destino.'/informe.json'), true);
            $this->assertSame(3, $informe['totales']['filas']);
            $this->assertSame(1, $informe['totales']['validas']);
            $this->assertSame(1, $informe['totales']['invalidas']);
            $this->assertSame(1, $informe['totales']['duplicados']);
            $this->assertSame('fila:0', $informe['duplicados'][0]['candidatos'][0]);
            $this->assertDatabaseCount('establecimientos', 0);
        } finally {
            $this->limpiar($archivo, $destino);
        }
    }

    public function test_mapea_las_columnas_reales_del_excel(): void
    {
        Categoria::query()->create(['nombre' => 'Lácteos']);
        $archivo = base_path('datos/ejemplos/establecimientos-importar.csv');
        $destino = sys_get_temp_dir().DIRECTORY_SEPARATOR.uniqid('ejemplo-', true);

        try {
            $this->artisan('establecimientos:preparar-lotes', ['archivo' => $archivo, '--destino' => $destino])
                ->assertSuccessful();

            $informe = json_decode(file_get_contents($destino.'/informe.json'), true);
            $this->assertSame(3, $informe['totales']['filas']);
            $this->assertSame(3, $informe['totales']['validas']);
            $this->assertSame(0, $informe['totales']['invalidas']);
            $this->assertContains('Productos lacteos que comercializa', $informe['sin_mapear']);

            $lote = json_decode(file_get_contents($destino.'/lote-1.json'), true);
            $primera = $lote['filas'][0];
            $this->assertSame('Tienda Ficticia Amanecer', $primera['nombre']);
            $this->assertSame('tienda', $primera['tipo']);
            $this->assertSame('Mulaló', $primera['parroquia']);
            $this->assertSame(-0.781192, $primera['latitud']);
            $this->assertSame('Mulaló, Calle Ficticia 123', $primera['perfil']['direccion']);
            $this->assertSame('Ana Ficticia', $primera['perfil']['contacto_nombre']);
            $this->assertSame('0991234567', $primera['perfil']['contacto_telefono']);
            $this->assertSame('08:00 a 20:00', $primera['perfil']['horario_proveedores']);
            $this->assertTrue($primera['perfil']['tiene_refrigeracion']);
            $this->assertSame(['Leche'], $primera['perfil']['productos_buscados']);
            $this->assertTrue($primera['interes_encuesta']);

            $segunda = $lote['filas'][1];
            $this->assertSame('minimercado', $segunda['tipo']);
            $this->assertFalse($segunda['interes_encuesta']);

            $tercera = $lote['filas'][2];
            $this->assertNull($tercera['latitud']);
            $this->assertNull($tercera['longitud']);
            $this->assertArrayNotHasKey('interes_encuesta', $tercera);
        } finally {
            $this->limpiar('', $destino);
        }
    }

    public function test_el_comando_puede_enviar_los_lotes_a_la_api(): void
    {
        Categoria::query()->create(['nombre' => 'Lácteos']);
        [$archivo, $destino] = $this->archivosTemporales();

        Http::fake([
            '*/api/sesion' => Http::response(['csrf_token' => 'token-de-prueba'], 200),
            '*/api/admin/establecimientos/importar' => Http::response([
                'resultados' => [['fila' => 0, 'estado' => 'creado', 'establecimiento_id' => '9']],
            ], 200),
        ]);

        try {
            $this->artisan('establecimientos:preparar-lotes', [
                'archivo' => $archivo,
                '--destino' => $destino,
                '--enviar' => true,
                '--url' => 'http://localhost',
                '--correo' => 'admin@example.test',
                '--clave' => 'clave-de-prueba-123',
            ])->assertSuccessful();

            Http::assertSent(fn ($peticion) => $peticion->url() === 'http://localhost/api/sesion'
                && $peticion['email'] === 'admin@example.test');
            Http::assertSent(fn ($peticion) => $peticion->url() === 'http://localhost/api/admin/establecimientos/importar'
                && $peticion->hasHeader('X-CSRF-TOKEN', 'token-de-prueba')
                && count($peticion['filas']) === 1);
            $this->assertFileExists($destino.'/respuestas.json');
        } finally {
            $this->limpiar($archivo, $destino);
        }
    }

    private function archivosTemporales(): array
    {
        $prefijo = uniqid('importacion-', true);
        $archivo = sys_get_temp_dir().DIRECTORY_SEPARATOR.$prefijo.'.csv';
        $destino = sys_get_temp_dir().DIRECTORY_SEPARATOR.$prefijo.'-lotes';
        $csv = implode("\n", [
            'nombre,tipo,parroquia,latitud,longitud,direccion,canton,productos_buscados,tiene_refrigeracion,telefono,correo,canales_contacto,interes_encuesta,categorias',
            'Tienda Ficticia Uno,tienda,Mulaló,-0.775,-78.58,Calle Ficticia 1,Latacunga,Yogur;Leche,Sí,(099) 123-4567,uno@example.test,whatsapp,Sí,Lácteos',
            'Tienda Ficticia Uno,tienda,Mulaló,-0.775,-78.58,Calle Ficticia 1,Latacunga,Yogur;Leche,Sí,(099) 123-4567,uno@example.test,whatsapp,Sí,Lácteos',
            'Tienda Invalida,tienda,Centro,95,-10,,,,,,,,,',
        ])."\n";
        file_put_contents($archivo, $csv);

        return [$archivo, $destino];
    }

    private function limpiar(string $archivo, string $destino): void
    {
        if ($archivo !== '') {
            @unlink($archivo);
        }
        foreach (glob($destino.DIRECTORY_SEPARATOR.'*.json') ?: [] as $archivoJson) {
            @unlink($archivoJson);
        }
        @rmdir($destino);
    }
}
